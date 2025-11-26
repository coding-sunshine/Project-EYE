<?php

namespace App\Models;

class AudioFile extends MediaFile
{
    /**
     * Get the media type for audio.
     *
     * @return string
     */
    protected function getMediaType(): string
    {
        return 'audio';
    }

    /**
     * Get the audio stream URL.
     *
     * @return string
     */
    public function getStreamUrlAttribute(): string
    {
        return route('media.stream', $this->id);
    }

    /**
     * Get the thumbnail URL (audio waveform or default icon).
     *
     * @return string
     */
    public function getThumbnailUrlAttribute(): string
    {
        // Audio waveform visualization if generated
        $filename = pathinfo($this->file_path, PATHINFO_FILENAME) . '.png';
        $waveformPath = public_path('storage/waveforms/' . $filename);

        if (file_exists($waveformPath)) {
            return asset('storage/waveforms/' . $filename);
        }

        // Default audio icon (SVG data URI)
        return 'data:image/svg+xml;base64,' . base64_encode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="#fbbc04"><path d="M12 3v9.28c-.47-.17-.97-.28-1.5-.28C8.01 12 6 14.01 6 16.5S8.01 21 10.5 21c2.31 0 4.2-1.75 4.45-4H15V6h4V3h-7z"/></svg>');
    }

    /**
     * Get formatted duration (MM:SS or HH:MM:SS).
     *
     * @return string|null
     */
    public function getDurationFormattedAttribute(): ?string
    {
        if (!$this->duration_seconds) {
            return null;
        }

        $seconds = $this->duration_seconds;
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $seconds = $seconds % 60;

        if ($hours > 0) {
            return sprintf("%d:%02d:%02d", $hours, $minutes, $seconds);
        }

        return sprintf("%d:%02d", $minutes, $seconds);
    }

    /**
     * Get audio technical info.
     *
     * @return array
     */
    public function getTechnicalInfoAttribute(): array
    {
        $info = [];

        if ($this->audio_codec) {
            $info['codec'] = strtoupper($this->audio_codec);
        }

        if ($this->bitrate) {
            $bitrate = $this->bitrate / 1000; // Convert to kbps
            $info['bitrate'] = number_format($bitrate) . ' kbps';
        }

        if ($this->duration_seconds) {
            $info['duration'] = $this->duration_formatted;
        }

        return $info;
    }

    /**
     * Get audio type/format based on mime type.
     *
     * @return string
     */
    public function getAudioTypeAttribute(): string
    {
        return match($this->mime_type) {
            'audio/mpeg' => 'MP3',
            'audio/wav', 'audio/wave' => 'WAV',
            'audio/ogg' => 'OGG',
            'audio/webm' => 'WebM Audio',
            'audio/flac' => 'FLAC',
            'audio/aac' => 'AAC',
            'audio/m4a' => 'M4A',
            default => 'Audio',
        };
    }

    /**
     * Get audio quality label based on bitrate.
     *
     * @return string|null
     */
    public function getQualityLabelAttribute(): ?string
    {
        if (!$this->bitrate) {
            return null;
        }

        $kbps = $this->bitrate / 1000;

        if ($kbps >= 320) {
            return 'High Quality';
        } elseif ($kbps >= 192) {
            return 'Good Quality';
        } elseif ($kbps >= 128) {
            return 'Standard Quality';
        }

        return 'Low Quality';
    }

    /**
     * Search within transcribed audio text.
     *
     * @param string $query
     * @return array
     */
    public function searchInTranscription(string $query): array
    {
        if (!$this->extracted_text) {
            return [];
        }

        $text = $this->extracted_text;
        $query = strtolower($query);
        $textLower = strtolower($text);

        $matches = [];
        $offset = 0;

        while (($pos = strpos($textLower, $query, $offset)) !== false) {
            // Extract context around the match
            $contextStart = max(0, $pos - 50);
            $contextEnd = min(strlen($text), $pos + strlen($query) + 50);
            $context = substr($text, $contextStart, $contextEnd - $contextStart);

            $matches[] = [
                'position' => $pos,
                'context' => '...' . $context . '...',
            ];

            $offset = $pos + 1;

            // Limit to 10 matches
            if (count($matches) >= 10) {
                break;
            }
        }

        return $matches;
    }

    /**
     * Check if audio has been transcribed.
     *
     * @return bool
     */
    public function hasTranscription(): bool
    {
        return !empty($this->extracted_text);
    }
}
