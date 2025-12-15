<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\MediaFile;
use Livewire\Attributes\On;

class ProcessingStatus extends Component
{
    public $pending_files = [];
    public $processing_files = [];
    public $completed_files = [];
    public $failed_files = [];
    public $stats = [];

    // Expand/collapse state (pending expanded by default)
    public $showPending = true;
    public $showProcessing = false;
    public $showCompleted = false;
    public $showFailed = false;

    public function mount()
    {
        $this->loadStatus();
    }

    #[On('echo:image-processing,ImageProcessed')]
    public function imageProcessed($event)
    {
        // Reload status when an image is processed
        $this->loadStatus();
    }

    public function loadStatus()
    {
        // Get media files by status
        $this->pending_files = MediaFile::where('processing_status', 'pending')
            ->orderBy('created_at', 'desc')
            ->take(20)
            ->get()
            ->map(fn($img) => $this->formatImage($img))
            ->toArray();

        $this->processing_files = MediaFile::where('processing_status', 'processing')
            ->orderBy('processing_started_at', 'desc')
            ->take(20)
            ->get()
            ->map(fn($img) => $this->formatImage($img))
            ->toArray();

        $this->completed_files = MediaFile::where('processing_status', 'completed')
            ->whereDate('processing_completed_at', '>=', now()->subHours(24))
            ->orderBy('processing_completed_at', 'desc')
            ->take(20)
            ->get()
            ->map(fn($img) => $this->formatImage($img))
            ->toArray();

        $this->failed_files = MediaFile::where('processing_status', 'failed')
            ->orderBy('updated_at', 'desc')
            ->take(10)
            ->get()
            ->map(fn($img) => $this->formatImage($img))
            ->toArray();

        // Calculate stats
        $this->stats = [
            'pending' => MediaFile::where('processing_status', 'pending')->count(),
            'processing' => MediaFile::where('processing_status', 'processing')->count(),
            'completed' => MediaFile::where('processing_status', 'completed')->count(),
            'failed' => MediaFile::where('processing_status', 'failed')->count(),
            'total' => MediaFile::count(),
        ];
    }

    protected function formatImage($image)
    {
        // Determine display URL based on media type
        $displayUrl = $this->getDisplayUrl($image);

        return [
            'id' => $image->id,
            'media_type' => $image->media_type,
            'filename' => $image->original_filename ?? basename($image->file_path),
            'url' => $displayUrl,
            'file_url' => asset('storage/' . str_replace('public/', '', $image->file_path)),
            'status' => $image->processing_status,
            'description' => $image->description,
            'processing_time' => $image->processing_started_at && $image->processing_completed_at
                ? $image->processing_started_at->diffInSeconds($image->processing_completed_at) . 's'
                : null,
            'created_at' => $image->created_at?->diffForHumans(),
            'started_at' => $image->processing_started_at?->diffForHumans(),
            'completed_at' => $image->processing_completed_at?->diffForHumans(),
            'error' => $image->processing_error,
        ];
    }

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

    public function retryFailed($imageId)
    {
        $image = MediaFile::find($imageId);
        if ($image && $image->processing_status === 'failed') {
            $image->update([
                'processing_status' => 'pending',
                'processing_error' => null,
            ]);

            \App\Jobs\ProcessImageAnalysis::dispatch($image->id)
                ->onQueue('image-processing');

            $this->loadStatus();
        }
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

            $this->loadStatus();

            // Show success message
            session()->flash('message', 'File queued for reanalysis');
        }
    }

    public function toggleSection($section)
    {
        // Close all sections first
        $this->showPending = false;
        $this->showProcessing = false;
        $this->showCompleted = false;
        $this->showFailed = false;

        // Open only the selected section
        switch ($section) {
            case 'pending':
                $this->showPending = true;
                break;
            case 'processing':
                $this->showProcessing = true;
                break;
            case 'completed':
                $this->showCompleted = true;
                break;
            case 'failed':
                $this->showFailed = true;
                break;
        }
    }

    public function cancelPending($fileId)
    {
        $file = MediaFile::find($fileId);
        if ($file && $file->processing_status === 'pending') {
            // Delete the file from processing queue
            $file->delete();

            $this->loadStatus();

            // Show success message
            session()->flash('message', 'Pending file cancelled and removed');
        }
    }

    public function downloadFile($fileId)
    {
        $file = MediaFile::find($fileId);
        if ($file) {
            // Determine the appropriate download URL based on media type
            $url = match($file->media_type) {
                'image' => asset('storage/' . str_replace('public/', '', $file->file_path)),
                'document', 'code', 'other' => route('documents.download', $file->id),
                'video', 'audio', 'archive' => route('media.download', $file->id),
                default => asset('storage/' . str_replace('public/', '', $file->file_path)),
            };

            $filename = $file->original_filename ?? basename($file->file_path);
            $this->dispatch('download-image', url: $url, filename: $filename);
        }
    }

    public function render()
    {
        return view('livewire.processing-status')
            ->layout('layouts.app');
    }
}

