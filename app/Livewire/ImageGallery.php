<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\MediaFile;
use Illuminate\Support\Facades\Storage;

class ImageGallery extends Component
{
    public $files = [];
    public $selectedImage = null;
    public $filterTag = '';

    public function mount()
    {
        $this->loadImages();
    }

    public function loadImages()
    {
        $query = MediaFile::orderBy('created_at', 'desc');
        
        if ($this->filterTag) {
            $query->whereJsonContains('meta_tags', $this->filterTag);
        }

        $this->files = $query->get()->map(function ($image) {
            return [
                'id' => $image->id,
                'media_type' => $image->media_type,
                'url' => $this->getDisplayUrl($image),
                'description' => $image->description,
                'detailed_description' => $image->detailed_description ?? $image->description,
                'meta_tags' => $image->meta_tags ?? [],
                'face_count' => $image->face_count ?? 0,
                'filename' => $image->original_filename ?? basename($image->file_path),
                'created_at' => $image->created_at->format('M d, Y'),
                // File metadata
                'mime_type' => $image->mime_type,
                'file_size' => $image->file_size ? $this->formatFileSize($image->file_size) : null,
                'dimensions' => $image->width && $image->height ? "{$image->width} × {$image->height}" : null,
                'width' => $image->width,
                'height' => $image->height,
                // Camera info
                'camera_make' => $image->camera_make,
                'camera_model' => $image->camera_model,
                'lens_model' => $image->lens_model,
                'date_taken' => $image->date_taken ? $image->date_taken->format('M d, Y g:i A') : null,
                // Exposure settings
                'exposure_time' => $image->exposure_time,
                'f_number' => $image->f_number,
                'iso' => $image->iso ? 'ISO ' . $image->iso : null,
                'focal_length' => $image->focal_length ? $image->focal_length . 'mm' : null,
                // GPS
                'gps_latitude' => $image->gps_latitude,
                'gps_longitude' => $image->gps_longitude,
                'has_gps' => $image->gps_latitude && $image->gps_longitude,
            ];
        })->toArray();
    }
    
    /**
     * Format file size in human-readable format.
     */
    protected function formatFileSize($bytes): string
    {
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return round($bytes / 1024, 2) . ' KB';
        }
        return $bytes . ' bytes';
    }

    /**
     * Get the display URL for a media file.
     * Priority: thumbnail -> original file (for images) -> media type icon
     */
    protected function getDisplayUrl($image)
    {
        // Use thumbnail if available
        if ($image->thumbnail_path) {
            return asset('storage/' . str_replace('public/', '', $image->thumbnail_path));
        }

        // For images, use original file
        if ($image->media_type === 'image') {
            return asset('storage/' . str_replace('public/', '', $image->file_path));
        }

        // Return default icon URL based on media type
        return $this->getMediaTypeIcon($image->media_type);
    }

    /**
     * Get SVG data URI icon for media type.
     */
    protected function getMediaTypeIcon($mediaType)
    {
        // Return data URI SVG icons for different media types
        $icons = [
            'document' => 'data:image/svg+xml;base64,' . base64_encode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="%234285f4"><path d="M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2H18c1.1 0 2-.9 2-2V8l-6-6zM6 20V4h7v5h5v11H6z"/></svg>'),
            'video' => 'data:image/svg+xml;base64,' . base64_encode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="%23ea4335"><path d="M17 10.5V7c0-.55-.45-1-1-1H4c-.55 0-1 .45-1 1v10c0 .55.45 1 1 1h12c.55 0 1-.45 1-1v-3.5l4 4v-11l-4 4z"/></svg>'),
            'audio' => 'data:image/svg+xml;base64,' . base64_encode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="%23fbbc04"><path d="M12 3v9.28c-.47-.17-.97-.28-1.5-.28C8.01 12 6 14.01 6 16.5S8.01 21 10.5 21c2.31 0 4.2-1.75 4.45-4H15V6h4V3h-7z"/></svg>'),
            'archive' => 'data:image/svg+xml;base64,' . base64_encode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="%2334a853"><path d="M20 2H4c-1.1 0-1.99.9-1.99 2L2 22l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zM8 14H6v-2h2v2zm0-3H6V9h2v2zm0-3H6V6h2v2zm7 6h-5v-2h5v2zm3-3h-8V9h8v2zm0-3h-8V6h8v2z"/></svg>'),
            'code' => 'data:image/svg+xml;base64,' . base64_encode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="%239e9e9e"><path d="M9.4 16.6L4.8 12l4.6-4.6L8 6l-6 6 6 6 1.4-1.4zm5.2 0l4.6-4.6-4.6-4.6L16 6l6 6-6 6-1.4-1.4z"/></svg>'),
            'email' => 'data:image/svg+xml;base64,' . base64_encode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="%234285f4"><path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/></svg>'),
        ];

        // Default file icon
        return $icons[$mediaType] ?? 'data:image/svg+xml;base64,' . base64_encode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="%239e9e9e"><path d="M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2H18c1.1 0 2-.9 2-2V8l-6-6zm4 18H6V4h7v5h5v11z"/></svg>');
    }

    public function viewDetails($imageId)
    {
        $this->selectedImage = collect($this->files)->firstWhere('id', $imageId);
    }
    
    public function closeDetails()
    {
        $this->selectedImage = null;
    }
    
    public function filterByTag($tag)
    {
        $this->filterTag = $tag;
        $this->loadImages();
    }
    
    public function clearFilter()
    {
        $this->filterTag = '';
        $this->loadImages();
    }

    public function reanalyze($imageId)
    {
        $image = MediaFile::find($imageId);
        if ($image) {
            // Reset processing status to rerun analysis
            $image->update([
                'processing_status' => 'pending',
                'processing_error' => null,
                'processing_started_at' => null,
                'processing_completed_at' => null,
            ]);

            // Dispatch job for reanalysis
            \App\Jobs\ProcessImageAnalysis::dispatch($image->id)
                ->onQueue('image-processing');

            $this->loadImages();

            // Show success message
            session()->flash('message', 'File queued for reanalysis');
        }
    }

    public function render()
    {
        return view('livewire.image-gallery')
            ->layout('layouts.app');
    }
}

