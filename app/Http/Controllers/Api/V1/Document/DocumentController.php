<?php

namespace App\Http\Controllers\Api\V1\Document;

use App\Http\Controllers\Controller;
use App\Http\Resources\Document\DocumentResource;
use App\Models\Tenant\Document;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends Controller
{
    /**
     * GET /api/v1/documents?mbl_id=&container_id=&booking_id=
     * Lists stored documents for the tenant, optionally scoped to a shipment.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Document::query()->where('organization_id', tenancy()->tenant?->id);

        foreach (['mbl_id', 'container_id', 'booking_id', 'document_type'] as $filter) {
            if ($request->filled($filter)) {
                $query->where($filter, $request->input($filter));
            }
        }

        $this->applySorting(
            $query,
            $request->input('order_by', 'created_at'),
            (int) $request->input('direction', -1)
        );

        return $this->paginateResource($query, $request, DocumentResource::class);
    }

    /**
     * POST /api/v1/documents   (multipart/form-data)
     * Stores an uploaded file and optionally attaches it to an MBL/container/booking.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'file'          => ['required', 'file', 'max:20480'], // 20 MB
            'mbl_id'        => ['nullable', 'string', 'max:64'],
            'container_id'  => ['nullable', 'string', 'max:64'],
            'booking_id'    => ['nullable', 'string', 'max:64'],
            'document_type' => ['nullable', 'string', 'max:50'],
        ]);

        $org  = tenancy()->tenant?->id;
        $file = $request->file('file');
        $id   = (string) Str::uuid();

        // Tenant-scoped path; sanitize the original filename for the stored name.
        $safeName = preg_replace('/[^A-Za-z0-9._-]/', '_', $file->getClientOriginalName());
        $path     = $file->storeAs("documents/{$org}", "{$id}-{$safeName}", 'local');

        $document = Document::create([
            'id'              => $id,
            'organization_id' => $org,
            'mbl_id'          => $request->input('mbl_id'),
            'container_id'    => $request->input('container_id'),
            'booking_id'      => $request->input('booking_id'),
            'original_name'   => $file->getClientOriginalName(),
            'path'            => $path,
            'disk'            => 'local',
            'mime_type'       => $file->getClientMimeType(),
            'size'            => $file->getSize(),
            'document_type'   => $request->input('document_type'),
            'uploaded_by'     => $request->user()?->id,
        ]);

        return $this->created(new DocumentResource($document), 'Document uploaded');
    }

    /**
     * GET /api/v1/documents/{uuid}
     */
    public function show(string $uuid): JsonResponse
    {
        $document = Document::where('organization_id', tenancy()->tenant?->id)->find($uuid);

        if (! $document) {
            return $this->notFound('Document not found');
        }

        return $this->success(new DocumentResource($document));
    }

    /**
     * GET /api/v1/documents/{uuid}/download
     */
    public function download(string $uuid): StreamedResponse|JsonResponse
    {
        $document = Document::where('organization_id', tenancy()->tenant?->id)->find($uuid);

        if (! $document) {
            return $this->notFound('Document not found');
        }

        if (! Storage::disk($document->disk)->exists($document->path)) {
            return $this->notFound('File is missing from storage');
        }

        return Storage::disk($document->disk)->download($document->path, $document->original_name);
    }

    /**
     * DELETE /api/v1/documents/{uuid}
     */
    public function destroy(string $uuid): JsonResponse
    {
        $document = Document::where('organization_id', tenancy()->tenant?->id)->find($uuid);

        if (! $document) {
            return $this->notFound('Document not found');
        }

        try {
            Storage::disk($document->disk)->delete($document->path);
        } catch (\Throwable $e) {
            // ignore storage delete failures — still remove the DB record
        }

        $document->delete();

        return $this->noContent();
    }
}
