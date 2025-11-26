<?php

namespace App\Models;

class VideoFile extends MediaFile
{
    /**
     * Get the media type for videos.
     *
     * @return string
     */
    protected function getMediaType(): string
    {
        return 'video';
    }

    /**
     * Get the video stream URL.
     *
     * @return string
     */
    public function getStreamUrlAttribute(): string
    {
        return route('media.stream', $this->id);
    }

    /**
     * Get the thumbnail URL for the video.
     *
     * @return string
     */
    public function getThumbnailUrlAttribute(): string
    {
        // Video thumbnails extracted at processing time
        $filename = pathinfo($this->file_path, PATHINFO_FILENAME) . '.jpg';
        return asset('storage/thumbnails/' . $filename);
    }

    /**
     * Get formatted duration (HH:MM:SS or MM:SS).
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
     * Get video technical info.
     *
     * @return array
     */
    public function getTechnicalInfoAttribute(): array
    {
        $info = [];

        if ($this->resolution) {
            $info['resolution'] = $this->resolution;
        }

        if ($this->fps) {
            $info['fps'] = number_format($this->fps, 2) . ' fps';
        }

        if ($this->video_codec) {
            $info['video_codec'] = strtoupper($this->video_codec);
        }

        if ($this->audio_codec) {
            $info['audio_codec'] = strtoupper($this->audio_codec);
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
     * Check if video has audio track.
     *
     * @return bool
     */
    public function hasAudio(): bool
    {
        return !empty($this->audio_codec);
    }

    /**
     * Get video quality label based on resolution.
     *
     * @return string|null
     */
    public function getQualityLabelAttribute(): ?string
    {
        if (!$this->resolution) {
            return null;
        }

        // Parse resolution (e.g., "1920x1080")
        $parts = explode('x', $this->resolution);
        if (count($parts) !== 2) {
            return null;
        }

        $height = (int) $parts[1];

        if ($height >= 2160) {
            return '4K';
        } elseif ($height >= 1440) {
            return '2K';
        } elseif ($height >= 1080) {
            return 'Full HD';
        } elseif ($height >= 720) {
            return 'HD';
        } elseif ($height >= 480) {
            return 'SD';
        }

        return 'Low Quality';
    }
}
