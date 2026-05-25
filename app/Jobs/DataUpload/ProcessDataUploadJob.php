<?php

namespace App\Jobs\DataUpload;

use App\Services\DataUpload\DataUploadService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\UploadedFile;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ProcessDataUploadJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 1;
    public int $timeout = 600;

    public function __construct(
        public readonly string $type,
        public readonly string $storagePath,
        public readonly string $originalName,
        public readonly ?string $uploadedBy = null,
    ) {
        $this->onQueue('uploads');
    }

    public function handle(DataUploadService $uploadService): void
    {
        Log::info('ProcessDataUploadJob: starting', [
            'type'          => $this->type,
            'storage_path'  => $this->storagePath,
            'original_name' => $this->originalName,
        ]);

        $fullPath = Storage::path($this->storagePath);

        if (!file_exists($fullPath)) {
            Log::error('ProcessDataUploadJob: file not found', ['path' => $fullPath]);
            return;
        }

        $file = new UploadedFile(
            $fullPath,
            $this->originalName,
            mime_content_type($fullPath) ?: 'text/csv',
            null,
            true
        );

        $results = $uploadService->processUpload($this->type, $file);

        Log::info('ProcessDataUploadJob: completed', [
            'type'    => $this->type,
            'created' => $results['created'],
            'updated' => $results['updated'],
            'errors'  => count($results['errors']),
        ]);

        // Clean up the temporary file
        Storage::delete($this->storagePath);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('ProcessDataUploadJob: failed', [
            'type'  => $this->type,
            'error' => $exception->getMessage(),
        ]);

        Storage::delete($this->storagePath);
    }
}
