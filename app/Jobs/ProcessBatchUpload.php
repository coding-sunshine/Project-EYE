<?php

namespace App\Jobs;

use App\Models\BatchUpload;
use App\Models\MediaFile;
use App\Services\AiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessBatchUpload implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $mediaFileId;
    public string $batchId;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The maximum number of seconds the job can run.
     *
     * @var int
     */
    public $timeout = 300;

    /**
     * Create a new job instance.
     *
     * @param int $mediaFileId
     * @param string $batchId
     */
    public function __construct(int $mediaFileId, string $batchId)
    {
        $this->mediaFileId = $mediaFileId;
        $this->batchId = $batchId;
    }

    /**
     * Execute the job.
     *
     * @param AiService $aiService
     * @return void
     */
    public function handle(AiService $aiService): void
    {
        $batch = BatchUpload::where('batch_id', $this->batchId)->first();
        $mediaFile = MediaFile::find($this->mediaFileId);

        if (!$batch || !$mediaFile) {
            Log::error('Batch or media file not found', [
                'batch_id' => $this->batchId,
                'media_file_id' => $this->mediaFileId,
            ]);
            return;
        }

        try {
            Log::info('Processing batch upload file', [
                'batch_id' => $this->batchId,
                'media_file_id' => $this->mediaFileId,
                'media_type' => $mediaFile->media_type,
            ]);

            // Mark processing started
            $mediaFile->markProcessingStarted();

            // Process based on media type
            switch ($mediaFile->media_type) {
                case 'image':
                    $this->processImage($mediaFile, $aiService);
                    break;

                case 'video':
                    $this->processVideo($mediaFile, $aiService);
                    break;

                case 'document':
                    $this->processDocument($mediaFile, $aiService);
                    break;

                case 'audio':
                    $this->processAudio($mediaFile, $aiService);
                    break;

                default:
                    // For archives and other types, just mark as completed
                    $mediaFile->markProcessingCompleted();
            }

            // Update batch success count
            $batch->incrementSuccessful();

            Log::info('Batch upload file processed successfully', [
                'batch_id' => $this->batchId,
                'media_file_id' => $this->mediaFileId,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to process batch upload file', [
                'batch_id' => $this->batchId,
                'media_file_id' => $this->mediaFileId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Mark media file as failed
            $mediaFile->markProcessingFailed($e->getMessage());

            // Update batch failed count
            $batch->incrementFailed();

            // Re-throw if not last attempt
            if ($this->attempts() < $this->tries) {
                throw $e;
            }
        }
    }

    /**
     * Process image file.
     *
     * @param MediaFile $mediaFile
     * @param AiService $aiService
     * @return void
     */
    protected function processImage(MediaFile $mediaFile, AiService $aiService): void
    {
        $result = $aiService->analyzeImage(
            $mediaFile->file_path,
            captioningModel: 'blip',
            embeddingModel: 'clip',
            faceDetectionEnabled: true,
            ollamaEnabled: false
        );

        $mediaFile->update([
            'description' => $result['description'] ?? null,
            'detailed_description' => $result['detailed_description'] ?? null,
            'meta_tags' => $result['tags'] ?? [],
            'embedding' => $result['embedding'] ?? null,
            'face_count' => $result['face_count'] ?? 0,
            'face_encodings' => $result['face_encodings'] ?? [],
        ]);

        $mediaFile->markProcessingCompleted();
    }

    /**
     * Process video file.
     *
     * @param MediaFile $mediaFile
     * @param AiService $aiService
     * @return void
     */
    protected function processVideo(MediaFile $mediaFile, AiService $aiService): void
    {
        $result = $aiService->analyzeVideo(
            $mediaFile->file_path,
            frameInterval: 2.0,
            maxFrames: 10
        );

        $mediaFile->update([
            'description' => $result['summary'] ?? null,
            'detailed_description' => $result['detailed_description'] ?? null,
            'meta_tags' => $result['tags'] ?? [],
            'duration_seconds' => $result['duration'] ?? null,
        ]);

        $mediaFile->markProcessingCompleted();
    }

    /**
     * Process document file.
     *
     * @param MediaFile $mediaFile
     * @param AiService $aiService
     * @return void
     */
    protected function processDocument(MediaFile $mediaFile, AiService $aiService): void
    {
        $result = $aiService->analyzeDocument(
            $mediaFile->file_path,
            ollamaEnabled: false
        );

        $mediaFile->update([
            'extracted_text' => $result['text'] ?? null,
            'description' => $result['summary'] ?? null,
            'document_type' => $result['document_type'] ?? null,
            'page_count' => $result['page_count'] ?? null,
        ]);

        $mediaFile->markProcessingCompleted();
    }

    /**
     * Process audio file.
     *
     * @param MediaFile $mediaFile
     * @param AiService $aiService
     * @return void
     */
    protected function processAudio(MediaFile $mediaFile, AiService $aiService): void
    {
        $result = $aiService->transcribeAudio($mediaFile->file_path);

        $mediaFile->update([
            'extracted_text' => $result['transcript'] ?? null,
            'description' => $result['summary'] ?? null,
            'duration_seconds' => $result['duration'] ?? null,
        ]);

        $mediaFile->markProcessingCompleted();
    }

    /**
     * Handle a job failure.
     *
     * @return void
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Batch upload job failed permanently', [
            'batch_id' => $this->batchId,
            'media_file_id' => $this->mediaFileId,
            'error' => $exception->getMessage(),
        ]);

        // Try to update batch failed count even if job failed
        try {
            $batch = BatchUpload::where('batch_id', $this->batchId)->first();
            $mediaFile = MediaFile::find($this->mediaFileId);

            if ($mediaFile) {
                $mediaFile->markProcessingFailed($exception->getMessage());
            }

            if ($batch) {
                $batch->incrementFailed();
            }
        } catch (\Exception $e) {
            Log::error('Failed to handle batch job failure', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
