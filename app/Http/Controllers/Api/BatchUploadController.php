<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessBatchUpload;
use App\Models\BatchUpload;
use App\Services\MediaFileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class BatchUploadController extends Controller
{
    protected MediaFileService $mediaFileService;

    public function __construct(MediaFileService $mediaFileService)
    {
        $this->mediaFileService = $mediaFileService;
    }

    /**
     * Initiate a batch upload operation.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function upload(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'files' => 'required|array|min:1|max:100',
            'files.*' => 'required|file|max:524288', // 500MB max per file
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $files = $request->file('files');
            $totalFiles = count($files);

            // Create batch upload record
            $batch = BatchUpload::create([
                'total_files' => $totalFiles,
                'pending_files' => $totalFiles,
                'metadata' => [
                    'user_ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'uploaded_at' => now()->toIso8601String(),
                ]
            ]);

            Log::info('Batch upload initiated', [
                'batch_id' => $batch->batch_id,
                'total_files' => $totalFiles,
            ]);

            // Store files and dispatch processing jobs
            $fileResults = [];
            foreach ($files as $index => $file) {
                try {
                    // Validate file type
                    $mediaType = $this->mediaFileService->detectMediaType($file);
                    $this->mediaFileService->validateMediaFile($file, $mediaType);

                    // Store file
                    $fileData = $this->mediaFileService->storeUploadedMedia($file, $mediaType);

                    // Create media file record
                    $mediaFile = $this->mediaFileService->createMediaFileRecord($fileData, $file);
                    $mediaFile->update(['batch_id' => $batch->batch_id]);

                    // Dispatch processing job
                    ProcessBatchUpload::dispatch($mediaFile->id, $batch->batch_id)
                        ->onQueue('batch-processing');

                    $fileResults[] = [
                        'index' => $index,
                        'filename' => $file->getClientOriginalName(),
                        'media_id' => $mediaFile->id,
                        'status' => 'queued',
                    ];

                } catch (\Exception $e) {
                    Log::error('Failed to queue file in batch', [
                        'batch_id' => $batch->batch_id,
                        'filename' => $file->getClientOriginalName(),
                        'error' => $e->getMessage()
                    ]);

                    // Increment failed count
                    $batch->incrementFailed();

                    $fileResults[] = [
                        'index' => $index,
                        'filename' => $file->getClientOriginalName(),
                        'status' => 'failed',
                        'error' => $e->getMessage(),
                    ];
                }
            }

            // Mark batch as processing
            $batch->markProcessing();

            return response()->json([
                'success' => true,
                'message' => 'Batch upload initiated',
                'data' => [
                    'batch_id' => $batch->batch_id,
                    'total_files' => $totalFiles,
                    'queued_files' => collect($fileResults)->where('status', 'queued')->count(),
                    'failed_files' => collect($fileResults)->where('status', 'failed')->count(),
                    'files' => $fileResults,
                ],
            ], 201);

        } catch (\Exception $e) {
            Log::error('Batch upload failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Batch upload failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get batch upload status.
     *
     * @param string $batchId
     * @return JsonResponse
     */
    public function status(string $batchId): JsonResponse
    {
        $batch = BatchUpload::where('batch_id', $batchId)->first();

        if (!$batch) {
            return response()->json([
                'success' => false,
                'message' => 'Batch not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $batch->getStatusSummary(),
        ]);
    }

    /**
     * Get recent batch uploads.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function recent(Request $request): JsonResponse
    {
        $days = $request->input('days', 7);
        $limit = $request->input('limit', 20);

        $batches = BatchUpload::recent($days)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(fn($batch) => $batch->getStatusSummary());

        return response()->json([
            'success' => true,
            'data' => $batches,
        ]);
    }

    /**
     * Cancel a batch upload.
     *
     * @param string $batchId
     * @return JsonResponse
     */
    public function cancel(string $batchId): JsonResponse
    {
        $batch = BatchUpload::where('batch_id', $batchId)->first();

        if (!$batch) {
            return response()->json([
                'success' => false,
                'message' => 'Batch not found'
            ], 404);
        }

        if (in_array($batch->status, ['completed', 'completed_with_errors', 'failed'])) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot cancel completed or failed batch'
            ], 400);
        }

        // Mark batch as failed (cancelled)
        $batch->markFailed('Cancelled by user');

        Log::info('Batch upload cancelled', [
            'batch_id' => $batchId,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Batch upload cancelled',
            'data' => $batch->getStatusSummary(),
        ]);
    }
}
