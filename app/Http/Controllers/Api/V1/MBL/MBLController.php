<?php

namespace App\Http\Controllers\Api\V1\MBL;

use App\Http\Controllers\Controller;
use App\Http\Resources\Container\ContainerResource;
use App\Http\Resources\MBL\MBLResource;
use App\Models\Tenant\Mbl;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MBLController extends Controller
{
    /**
     * GET /api/v1/mbls
     */
    public function index(Request $request): JsonResponse
    {
        $query = Mbl::query();

        if ($request->has('filters')) {
            $this->applyFilters($query, $request->input('filters', []));
        }

        $this->applySorting(
            $query,
            $request->input('order_by', 'created_at'),
            (int) $request->input('direction', -1)
        );

        return $this->paginateResource($query, $request, MBLResource::class);
    }

    /**
     * GET /api/v1/mbls/{uuid}
     */
    public function show(string $uuid): JsonResponse
    {
        $mbl = Mbl::where('uuid', $uuid)->first();

        if (! $mbl) {
            return $this->notFound('MBL not found');
        }

        return $this->success(new MBLResource($mbl));
    }

    /**
     * GET /api/v1/mbls/number/{mbl_number}
     */
    public function byNumber(string $mblNumber): JsonResponse
    {
        $mbl = Mbl::where('mbl_number', $mblNumber)->first();

        if (! $mbl) {
            return $this->notFound('MBL not found');
        }

        return $this->success(new MBLResource($mbl));
    }

    /**
     * PATCH /api/v1/mbls/{uuid}/not-tracking
     */
    public function updateNotTracking(Request $request, string $uuid): JsonResponse
    {
        $mbl = Mbl::where('uuid', $uuid)->first();

        if (! $mbl) {
            return $this->notFound('MBL not found');
        }

        $request->validate([
            'is_not_tracking' => 'required|boolean',
            'reason'          => 'nullable|string|max:500',
        ]);

        $mbl->update([
            'is_not_tracking'      => $request->boolean('is_not_tracking'),
            'not_tracking_reason'  => $request->input('reason'),
        ]);

        return $this->success(new MBLResource($mbl->fresh()), 'MBL tracking status updated');
    }

    /**
     * GET /api/v1/mbls/{uuid}/containers
     */
    public function containers(string $uuid, Request $request): JsonResponse
    {
        $mbl = Mbl::where('uuid', $uuid)->first();

        if (! $mbl) {
            return $this->notFound('MBL not found');
        }

        $query = $mbl->containers()->getQuery();

        $this->applySorting(
            $query,
            $request->input('order_by', 'container_number'),
            (int) $request->input('direction', 1)
        );

        return $this->paginateResource($query, $request, ContainerResource::class);
    }
}
