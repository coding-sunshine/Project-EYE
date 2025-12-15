<?php

namespace App\Services;

use App\Models\MediaFile;
use App\Models\ImageFile;
use App\Models\VideoFile;
use App\Models\DocumentFile;
use App\Models\AudioFile;
use App\Models\ArchiveFile;
use App\Services\Processors\VideoProcessor;
use App\Services\Processors\DocumentProcessor;
use App\Services\Processors\AudioProcessor;
use App\Services\Processors\ArchiveProcessor;
use Illuminate\Support\Facades\Log;

/**
 * Media Processor Service
 *
 * Orchestrates processing of different media types by routing
 * to appropriate specialized processors.
 */
class MediaProcessorService
{
    protected VideoProcessor $videoProcessor;
    protected DocumentProcessor $documentProcessor;
    protected AudioProcessor $audioProcessor;
    protected ArchiveProcessor $archiveProcessor;

    public function __construct(
        VideoProcessor $videoProcessor,
        DocumentProcessor $documentProcessor,
        AudioProcessor $audioProcessor,
        ArchiveProcessor $archiveProcessor
    ) {
        $this->videoProcessor = $videoProcessor;
        $this->documentProcessor = $documentProcessor;
        $this->audioProcessor = $audioProcessor;
        $this->archiveProcessor = $archiveProcessor;
    }

    /**
     * Process a media file based on its type.
     *
     * @param MediaFile $mediaFile
     * @return MediaFile
     */
    public function process(MediaFile $mediaFile): MediaFile
    {
        try {
            Log::info('Starting media processing', [
                'media_id' => $mediaFile->id,
                'media_type' => $mediaFile->media_type,
                'file_name' => $mediaFile->file_name,
            ]);

            $processedMedia = match($mediaFile->media_type) {
                'image' => $this->processImage($mediaFile),
                'video' => $this->processVideo($mediaFile),
                'document' => $this->processDocument($mediaFile),
                'audio' => $this->processAudio($mediaFile),
                'archive' => $this->processArchive($mediaFile),
                default => throw new \InvalidArgumentException("Unknown media type: {$mediaFile->media_type}"),
            };

            Log::info('Media processing completed', [
                'media_id' => $processedMedia->id,
                'media_type' => $processedMedia->media_type,
            ]);

            return $processedMedia;

        } catch (\Exception $e) {
            Log::error('Media processing failed', [
                'media_id' => $mediaFile->id,
                'media_type' => $mediaFile->media_type,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $mediaFile->update([
                'processing_status' => 'failed',
                'processing_error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Process an image file.
     *
     * @param MediaFile $mediaFile
     * @return ImageFile
     */
    protected function processImage(MediaFile $mediaFile): ImageFile
    {
        /** @var ImageFile $image */
        $image = ImageFile::find($mediaFile->id);

        // Image processing logic will be handled by existing ImageService
        // For now, just mark as completed
        $image->update(['processing_status' => 'completed']);

        Log::info('Image processing handled by ImageService', [
            'image_id' => $image->id,
        ]);

        return $image;
    }

    /**
     * Process a video file.
     *
     * @param MediaFile $mediaFile
     * @return VideoFile
     */
    protected function processVideo(MediaFile $mediaFile): VideoFile
    {
        /** @var VideoFile $video */
        $video = VideoFile::find($mediaFile->id);

        return $this->videoProcessor->process($video);
    }

    /**
     * Process a document file.
     *
     * @param MediaFile $mediaFile
     * @return DocumentFile
     */
    protected function processDocument(MediaFile $mediaFile): DocumentFile
    {
        /** @var DocumentFile $document */
        $document = DocumentFile::find($mediaFile->id);

        return $this->documentProcessor->process($document);
    }

    /**
     * Process an audio file.
     *
     * @param MediaFile $mediaFile
     * @return AudioFile
     */
    protected function processAudio(MediaFile $mediaFile): AudioFile
    {
        /** @var AudioFile $audio */
        $audio = AudioFile::find($mediaFile->id);

        return $this->audioProcessor->process($audio);
    }

    /**
     * Process an archive file.
     *
     * @param MediaFile $mediaFile
     * @return ArchiveFile
     */
    protected function processArchive(MediaFile $mediaFile): ArchiveFile
    {
        /** @var ArchiveFile $archive */
        $archive = ArchiveFile::find($mediaFile->id);

        return $this->archiveProcessor->process($archive);
    }

    /**
     * Batch process multiple media files.
     *
     * @param array $mediaFiles
     * @return array Results with success/failure counts
     */
    public function batchProcess(array $mediaFiles): array
    {
        $results = [
            'total' => count($mediaFiles),
            'successful' => 0,
            'failed' => 0,
            'errors' => [],
        ];

        foreach ($mediaFiles as $mediaFile) {
            try {
                $this->process($mediaFile);
                $results['successful']++;
            } catch (\Exception $e) {
                $results['failed']++;
                $results['errors'][] = [
                    'media_id' => $mediaFile->id,
                    'error' => $e->getMessage(),
                ];
            }
        }

        Log::info('Batch media processing completed', $results);

        return $results;
    }

    /**
     * Reprocess a media file (useful after processing failures).
     *
     * @param MediaFile $mediaFile
     * @return MediaFile
     */
    public function reprocess(MediaFile $mediaFile): MediaFile
    {
        Log::info('Reprocessing media file', [
            'media_id' => $mediaFile->id,
            'media_type' => $mediaFile->media_type,
        ]);

        // Reset processing status
        $mediaFile->update([
            'processing_status' => 'pending',
            'processing_error' => null,
        ]);

        return $this->process($mediaFile);
    }

    /**
     * Get processing statistics for all media types.
     *
     * @return array
     */
    public function getProcessingStats(): array
    {
        return [
            'image' => ImageFile::selectRaw('processing_status, COUNT(*) as count')
                ->groupBy('processing_status')
                ->pluck('count', 'processing_status')
                ->toArray(),
            'video' => VideoFile::selectRaw('processing_status, COUNT(*) as count')
                ->groupBy('processing_status')
                ->pluck('count', 'processing_status')
                ->toArray(),
            'document' => DocumentFile::selectRaw('processing_status, COUNT(*) as count')
                ->groupBy('processing_status')
                ->pluck('count', 'processing_status')
                ->toArray(),
            'audio' => AudioFile::selectRaw('processing_status, COUNT(*) as count')
                ->groupBy('processing_status')
                ->pluck('count', 'processing_status')
                ->toArray(),
            'archive' => ArchiveFile::selectRaw('processing_status, COUNT(*) as count')
                ->groupBy('processing_status')
                ->pluck('count', 'processing_status')
                ->toArray(),
        ];
    }

    /**
     * Check if processing is available for a media type.
     *
     * @param string $mediaType
     * @return array Status and requirements
     */
    public function checkProcessingAvailability(string $mediaType): array
    {
        return match($mediaType) {
            'image' => [
                'available' => true,
                'requirements' => ['ImageMagick or GD'],
                'features' => ['EXIF extraction', 'thumbnail generation', 'AI analysis'],
            ],
            'video' => [
                'available' => $this->videoProcessor->isFfmpegAvailable(),
                'requirements' => ['FFmpeg'],
                'features' => ['metadata extraction', 'thumbnail generation', 'audio extraction'],
            ],
            'document' => [
                'available' => true,
                'requirements' => ['pdftotext (optional)', 'ImageMagick (optional)'],
                'features' => ['text extraction', 'PDF thumbnails', 'page counting'],
            ],
            'audio' => [
                'available' => $this->audioProcessor->isFfmpegAvailable(),
                'requirements' => ['FFmpeg'],
                'features' => ['metadata extraction', 'waveform generation', 'format conversion'],
            ],
            'archive' => [
                'available' => true,
                'requirements' => ['ZipArchive (native)', 'unrar (optional)', 'tar (optional)', '7z (optional)'],
                'features' => ['metadata extraction', 'file listing', 'compression analysis'],
            ],
            default => [
                'available' => false,
                'requirements' => [],
                'features' => [],
            ],
        };
    }
}
