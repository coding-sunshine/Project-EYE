<?php

namespace App\Services\Processors;

use App\Models\VideoFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Video Processor
 *
 * Handles video-specific processing:
 * - Metadata extraction (duration, resolution, fps, codecs)
 * - Thumbnail generation from video frames
 * - Video format detection and analysis
 * - Audio track detection
 */
class VideoProcessor
{
    /**
     * Process a video file and extract metadata.
     *
     * @param VideoFile $video
     * @return VideoFile
     */
    public function process(VideoFile $video): VideoFile
    {
        try {
            $fullPath = Storage::path($video->file_path);

            // Extract video metadata
            $metadata = $this->extractMetadata($fullPath);

            // Update video record with metadata
            $video->update([
                'duration_seconds' => $metadata['duration'] ?? null,
                'resolution' => $metadata['resolution'] ?? null,
                'fps' => $metadata['fps'] ?? null,
                'video_codec' => $metadata['video_codec'] ?? null,
                'audio_codec' => $metadata['audio_codec'] ?? null,
                'bitrate' => $metadata['bitrate'] ?? null,
                'processing_status' => 'completed',
            ]);

            // Generate thumbnail
            $this->generateThumbnail($video);

            Log::info('Video processed successfully', [
                'video_id' => $video->id,
                'duration' => $metadata['duration'] ?? null,
                'resolution' => $metadata['resolution'] ?? null,
            ]);

            return $video;

        } catch (\Exception $e) {
            Log::error('Video processing failed', [
                'video_id' => $video->id,
                'error' => $e->getMessage()
            ]);

            $video->update([
                'processing_status' => 'failed',
                'processing_error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Extract metadata from video file using FFmpeg.
     *
     * @param string $filePath
     * @return array
     */
    protected function extractMetadata(string $filePath): array
    {
        if (!$this->isFfmpegAvailable()) {
            Log::warning('FFmpeg not available, skipping video metadata extraction');
            return [];
        }

        try {
            // Use ffprobe to extract metadata in JSON format
            $command = sprintf(
                'ffprobe -v quiet -print_format json -show_format -show_streams %s 2>&1',
                escapeshellarg($filePath)
            );

            exec($command, $output, $returnCode);

            if ($returnCode !== 0) {
                throw new \Exception('FFprobe command failed');
            }

            $data = json_decode(implode('', $output), true);

            if (!$data) {
                throw new \Exception('Failed to parse FFprobe output');
            }

            return $this->parseFFprobeOutput($data);

        } catch (\Exception $e) {
            Log::error('Failed to extract video metadata', [
                'file' => $filePath,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Parse FFprobe JSON output.
     *
     * @param array $data
     * @return array
     */
    protected function parseFFprobeOutput(array $data): array
    {
        $metadata = [];

        // Extract format information
        if (isset($data['format'])) {
            $metadata['duration'] = isset($data['format']['duration'])
                ? (int) round($data['format']['duration'])
                : null;
            $metadata['bitrate'] = $data['format']['bit_rate'] ?? null;
        }

        // Extract stream information
        if (isset($data['streams']) && is_array($data['streams'])) {
            foreach ($data['streams'] as $stream) {
                // Video stream
                if ($stream['codec_type'] === 'video') {
                    $metadata['video_codec'] = $stream['codec_name'] ?? null;
                    $metadata['resolution'] = isset($stream['width'], $stream['height'])
                        ? $stream['width'] . 'x' . $stream['height']
                        : null;

                    // FPS calculation
                    if (isset($stream['r_frame_rate'])) {
                        $fps = $stream['r_frame_rate'];
                        if (strpos($fps, '/') !== false) {
                            [$num, $den] = explode('/', $fps);
                            $metadata['fps'] = $den > 0 ? round($num / $den, 2) : null;
                        } else {
                            $metadata['fps'] = (float) $fps;
                        }
                    }
                }

                // Audio stream
                if ($stream['codec_type'] === 'audio') {
                    $metadata['audio_codec'] = $stream['codec_name'] ?? null;
                }
            }
        }

        return $metadata;
    }

    /**
     * Generate thumbnail from video.
     *
     * @param VideoFile $video
     * @return bool
     */
    public function generateThumbnail(VideoFile $video): bool
    {
        if (!$this->isFfmpegAvailable()) {
            Log::warning('FFmpeg not available, skipping thumbnail generation');
            return false;
        }

        try {
            $sourcePath = Storage::path($video->file_path);
            $filename = pathinfo($video->file_path, PATHINFO_FILENAME) . '.jpg';
            $thumbnailPath = 'public/thumbnails/' . $filename;
            $fullThumbnailPath = Storage::path($thumbnailPath);

            // Ensure thumbnails directory exists
            $thumbnailDir = dirname($fullThumbnailPath);
            if (!is_dir($thumbnailDir)) {
                mkdir($thumbnailDir, 0755, true);
            }

            // Extract frame at 1 second (or 10% into video)
            $seekTime = $video->duration_seconds ? min(1, $video->duration_seconds * 0.1) : 1;

            $command = sprintf(
                'ffmpeg -ss %s -i %s -vframes 1 -q:v 2 %s 2>&1',
                escapeshellarg($seekTime),
                escapeshellarg($sourcePath),
                escapeshellarg($fullThumbnailPath)
            );

            exec($command, $output, $returnCode);

            if ($returnCode === 0 && file_exists($fullThumbnailPath)) {
                $video->update(['thumbnail_path' => $thumbnailPath]);
                Log::info('Video thumbnail generated', [
                    'video_id' => $video->id,
                    'thumbnail_path' => $thumbnailPath,
                ]);
                return true;
            }

            return false;

        } catch (\Exception $e) {
            Log::error('Failed to generate video thumbnail', [
                'video_id' => $video->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Check if FFmpeg is available on the system.
     *
     * @return bool
     */
    public function isFfmpegAvailable(): bool
    {
        exec('which ffmpeg', $output, $returnCode);
        return $returnCode === 0;
    }

    /**
     * Extract audio track from video.
     *
     * @param VideoFile $video
     * @param string $outputFormat
     * @return string|null Path to extracted audio file
     */
    public function extractAudio(VideoFile $video, string $outputFormat = 'mp3'): ?string
    {
        if (!$this->isFfmpegAvailable() || !$video->hasAudio()) {
            return null;
        }

        try {
            $sourcePath = Storage::path($video->file_path);
            $filename = pathinfo($video->file_path, PATHINFO_FILENAME) . '.' . $outputFormat;
            $audioPath = 'public/audio/' . $filename;
            $fullAudioPath = Storage::path($audioPath);

            // Ensure audio directory exists
            $audioDir = dirname($fullAudioPath);
            if (!is_dir($audioDir)) {
                mkdir($audioDir, 0755, true);
            }

            $command = sprintf(
                'ffmpeg -i %s -vn -acodec libmp3lame -q:a 2 %s 2>&1',
                escapeshellarg($sourcePath),
                escapeshellarg($fullAudioPath)
            );

            exec($command, $output, $returnCode);

            if ($returnCode === 0 && file_exists($fullAudioPath)) {
                Log::info('Audio extracted from video', [
                    'video_id' => $video->id,
                    'audio_path' => $audioPath,
                ]);
                return $audioPath;
            }

            return null;

        } catch (\Exception $e) {
            Log::error('Failed to extract audio from video', [
                'video_id' => $video->id,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Get video information summary.
     *
     * @param VideoFile $video
     * @return array
     */
    public function getVideoInfo(VideoFile $video): array
    {
        return [
            'duration' => $video->duration_formatted,
            'resolution' => $video->resolution,
            'quality' => $video->quality_label,
            'fps' => $video->fps,
            'has_audio' => $video->hasAudio(),
            'codecs' => [
                'video' => $video->video_codec,
                'audio' => $video->audio_codec,
            ],
            'file_size' => $video->file_size,
            'bitrate' => $video->bitrate,
        ];
    }
}
