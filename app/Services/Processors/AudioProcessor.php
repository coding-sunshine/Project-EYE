<?php

namespace App\Services\Processors;

use App\Models\AudioFile;
use App\Services\AiService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Audio Processor
 *
 * Handles audio-specific processing:
 * - Metadata extraction (duration, bitrate, codec, sample rate)
 * - Waveform visualization generation
 * - Audio transcription (Whisper integration placeholder)
 * - Format detection and analysis
 */
class AudioProcessor
{
    /**
     * Process an audio file and extract metadata.
     *
     * @param AudioFile $audio
     * @return AudioFile
     */
    public function process(AudioFile $audio): AudioFile
    {
        try {
            $fullPath = Storage::path($audio->file_path);

            // Extract audio metadata
            $metadata = $this->extractMetadata($fullPath);

            // Update audio record with metadata
            $audio->update([
                'duration_seconds' => $metadata['duration'] ?? null,
                'audio_codec' => $metadata['audio_codec'] ?? null,
                'bitrate' => $metadata['bitrate'] ?? null,
                'processing_status' => 'completed',
            ]);

            // Generate waveform visualization
            $this->generateWaveform($audio);

            Log::info('Audio processed successfully', [
                'audio_id' => $audio->id,
                'duration' => $metadata['duration'] ?? null,
                'codec' => $metadata['audio_codec'] ?? null,
            ]);

            return $audio;

        } catch (\Exception $e) {
            Log::error('Audio processing failed', [
                'audio_id' => $audio->id,
                'error' => $e->getMessage()
            ]);

            $audio->update([
                'processing_status' => 'failed',
                'processing_error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Extract metadata from audio file using FFmpeg.
     *
     * @param string $filePath
     * @return array
     */
    protected function extractMetadata(string $filePath): array
    {
        if (!$this->isFfmpegAvailable()) {
            Log::warning('FFmpeg not available, skipping audio metadata extraction');
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
            Log::error('Failed to extract audio metadata', [
                'file' => $filePath,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Parse FFprobe JSON output for audio.
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

        // Extract audio stream information
        if (isset($data['streams']) && is_array($data['streams'])) {
            foreach ($data['streams'] as $stream) {
                if ($stream['codec_type'] === 'audio') {
                    $metadata['audio_codec'] = $stream['codec_name'] ?? null;
                    $metadata['sample_rate'] = $stream['sample_rate'] ?? null;
                    $metadata['channels'] = $stream['channels'] ?? null;
                    break;
                }
            }
        }

        return $metadata;
    }

    /**
     * Generate waveform visualization for audio file.
     *
     * @param AudioFile $audio
     * @return bool
     */
    public function generateWaveform(AudioFile $audio): bool
    {
        if (!$this->isFfmpegAvailable()) {
            Log::warning('FFmpeg not available, skipping waveform generation');
            return false;
        }

        try {
            $sourcePath = Storage::path($audio->file_path);
            $filename = pathinfo($audio->file_path, PATHINFO_FILENAME) . '.png';
            $waveformPath = 'public/waveforms/' . $filename;
            $fullWaveformPath = Storage::path($waveformPath);

            // Ensure waveforms directory exists
            $waveformDir = dirname($fullWaveformPath);
            if (!is_dir($waveformDir)) {
                mkdir($waveformDir, 0755, true);
            }

            // Generate waveform using FFmpeg showwavespic filter
            $command = sprintf(
                'ffmpeg -i %s -filter_complex "showwavespic=s=1200x200:colors=0x3b82f6" -frames:v 1 %s 2>&1',
                escapeshellarg($sourcePath),
                escapeshellarg($fullWaveformPath)
            );

            exec($command, $output, $returnCode);

            if ($returnCode === 0 && file_exists($fullWaveformPath)) {
                Log::info('Audio waveform generated', [
                    'audio_id' => $audio->id,
                    'waveform_path' => $waveformPath,
                ]);
                return true;
            }

            return false;

        } catch (\Exception $e) {
            Log::error('Failed to generate audio waveform', [
                'audio_id' => $audio->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Transcribe audio to text using Whisper via Python AI service.
     *
     * @param AudioFile $audio
     * @param AiService $aiService
     * @param string|null $language Language code (e.g., 'en', 'es') or null for auto-detect
     * @return array Transcription data including text and detected language
     */
    public function transcribeAudio(AudioFile $audio, AiService $aiService, ?string $language = null): array
    {
        try {
            $fullPath = Storage::path($audio->file_path);

            Log::info('Starting audio transcription', [
                'audio_id' => $audio->id,
                'language' => $language ?? 'auto-detect',
            ]);

            // Call AI service for transcription
            $transcriptionData = $aiService->transcribeAudio($fullPath, $language);

            Log::info('Audio transcription completed', [
                'audio_id' => $audio->id,
                'text_length' => strlen($transcriptionData['text'] ?? ''),
                'detected_language' => $transcriptionData['language'] ?? 'unknown',
            ]);

            return $transcriptionData;

        } catch (\Exception $e) {
            Log::error('Audio transcription failed', [
                'audio_id' => $audio->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Normalize audio levels.
     *
     * @param AudioFile $audio
     * @param string $targetFormat
     * @return string|null Path to normalized audio file
     */
    public function normalizeAudio(AudioFile $audio, string $targetFormat = 'mp3'): ?string
    {
        if (!$this->isFfmpegAvailable()) {
            return null;
        }

        try {
            $sourcePath = Storage::path($audio->file_path);
            $filename = pathinfo($audio->file_path, PATHINFO_FILENAME) . '_normalized.' . $targetFormat;
            $normalizedPath = 'public/audio/' . $filename;
            $fullNormalizedPath = Storage::path($normalizedPath);

            // Normalize audio using loudnorm filter
            $command = sprintf(
                'ffmpeg -i %s -af loudnorm -acodec libmp3lame -q:a 2 %s 2>&1',
                escapeshellarg($sourcePath),
                escapeshellarg($fullNormalizedPath)
            );

            exec($command, $output, $returnCode);

            if ($returnCode === 0 && file_exists($fullNormalizedPath)) {
                Log::info('Audio normalized', [
                    'audio_id' => $audio->id,
                    'normalized_path' => $normalizedPath,
                ]);
                return $normalizedPath;
            }

            return null;

        } catch (\Exception $e) {
            Log::error('Failed to normalize audio', [
                'audio_id' => $audio->id,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Convert audio to different format.
     *
     * @param AudioFile $audio
     * @param string $targetFormat
     * @param int $quality Quality 0-9 (lower is better)
     * @return string|null Path to converted audio file
     */
    public function convertFormat(AudioFile $audio, string $targetFormat, int $quality = 2): ?string
    {
        if (!$this->isFfmpegAvailable()) {
            return null;
        }

        try {
            $sourcePath = Storage::path($audio->file_path);
            $filename = pathinfo($audio->file_path, PATHINFO_FILENAME) . '.' . $targetFormat;
            $convertedPath = 'public/audio/' . $filename;
            $fullConvertedPath = Storage::path($convertedPath);

            $codec = match($targetFormat) {
                'mp3' => 'libmp3lame',
                'ogg' => 'libvorbis',
                'flac' => 'flac',
                'wav' => 'pcm_s16le',
                default => 'libmp3lame',
            };

            $command = sprintf(
                'ffmpeg -i %s -acodec %s -q:a %d %s 2>&1',
                escapeshellarg($sourcePath),
                escapeshellarg($codec),
                $quality,
                escapeshellarg($fullConvertedPath)
            );

            exec($command, $output, $returnCode);

            if ($returnCode === 0 && file_exists($fullConvertedPath)) {
                Log::info('Audio format converted', [
                    'audio_id' => $audio->id,
                    'target_format' => $targetFormat,
                    'converted_path' => $convertedPath,
                ]);
                return $convertedPath;
            }

            return null;

        } catch (\Exception $e) {
            Log::error('Failed to convert audio format', [
                'audio_id' => $audio->id,
                'error' => $e->getMessage()
            ]);
            return null;
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
     * Get audio information summary.
     *
     * @param AudioFile $audio
     * @return array
     */
    public function getAudioInfo(AudioFile $audio): array
    {
        return [
            'duration' => $audio->duration_formatted,
            'codec' => $audio->audio_codec,
            'quality' => $audio->quality_label,
            'bitrate' => $audio->bitrate,
            'file_size' => $audio->file_size,
            'has_transcription' => $audio->hasTranscription(),
        ];
    }
}
