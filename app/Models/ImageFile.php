<?php

namespace App\Models;

class ImageFile extends MediaFile
{
    /**
     * Get the media type for images.
     *
     * @return string
     */
    protected function getMediaType(): string
    {
        return 'image';
    }

    /**
     * Get the full URL for the image.
     *
     * @return string
     */
    public function getImageUrlAttribute(): string
    {
        return $this->file_url;
    }

    /**
     * Get the thumbnail URL for the image.
     *
     * @return string
     */
    public function getThumbnailUrlAttribute(): string
    {
        // Assuming thumbnails are stored in a thumbnails subdirectory
        $filename = basename($this->file_path);
        return asset('storage/thumbnails/' . $filename);
    }

    /**
     * Get EXIF summary for display.
     *
     * @return array
     */
    public function getExifSummaryAttribute(): array
    {
        if (!$this->exif_data) {
            return [];
        }

        $summary = [];

        // Camera info
        if ($this->camera_make && $this->camera_model) {
            $summary['camera'] = trim($this->camera_make . ' ' . $this->camera_model);
        }

        // Lens info
        if ($this->lens_model) {
            $summary['lens'] = $this->lens_model;
        }

        // Camera settings
        $settings = [];
        if ($this->f_number) {
            $settings[] = 'f/' . $this->f_number;
        }
        if ($this->exposure_time) {
            $settings[] = $this->exposure_time;
        }
        if ($this->iso) {
            $settings[] = 'ISO ' . $this->iso;
        }
        if ($this->focal_length) {
            $settings[] = $this->focal_length . 'mm';
        }

        if (!empty($settings)) {
            $summary['settings'] = implode(' • ', $settings);
        }

        // Date taken
        if ($this->date_taken) {
            $summary['date'] = $this->date_taken->format('M d, Y g:i A');
        }

        // Location
        if ($this->gps_location_name) {
            $summary['location'] = $this->gps_location_name;
        } elseif ($this->gps_latitude && $this->gps_longitude) {
            $summary['location'] = number_format($this->gps_latitude, 4) . ', ' .
                                   number_format($this->gps_longitude, 4);
        }

        // Dimensions
        if ($this->width && $this->height) {
            $summary['dimensions'] = $this->width . ' × ' . $this->height;
            $megapixels = ($this->width * $this->height) / 1000000;
            $summary['megapixels'] = number_format($megapixels, 1) . ' MP';
        }

        return $summary;
    }

    /**
     * Get the aspect ratio.
     *
     * @return string|null
     */
    public function getAspectRatioAttribute(): ?string
    {
        if (!$this->width || !$this->height) {
            return null;
        }

        $gcd = $this->gcd($this->width, $this->height);
        $ratioWidth = $this->width / $gcd;
        $ratioHeight = $this->height / $gcd;

        // Common aspect ratios
        $commonRatios = [
            '1:1' => 1.0,
            '4:3' => 1.333,
            '3:2' => 1.5,
            '16:9' => 1.778,
            '21:9' => 2.333,
        ];

        $currentRatio = $this->width / $this->height;

        foreach ($commonRatios as $name => $value) {
            if (abs($currentRatio - $value) < 0.01) {
                return $name;
            }
        }

        return $ratioWidth . ':' . $ratioHeight;
    }

    /**
     * Calculate greatest common divisor.
     *
     * @param int $a
     * @param int $b
     * @return int
     */
    private function gcd(int $a, int $b): int
    {
        return $b ? $this->gcd($b, $a % $b) : $a;
    }
}
