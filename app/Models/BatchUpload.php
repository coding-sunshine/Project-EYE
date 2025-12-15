<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class BatchUpload extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'batch_id',
        'total_files',
        'successful_files',
        'failed_files',
        'pending_files',
        'status',
        'metadata',
        'completed_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'metadata' => 'array',
        'completed_at' => 'datetime',
    ];

    /**
     * Boot the model.
     */
    protected static function booted()
    {
        // Automatically generate UUID on creation
        static::creating(function ($batch) {
            if (!$batch->batch_id) {
                $batch->batch_id = (string) Str::uuid();
            }
        });
    }

    /**
     * Get all media files associated with this batch.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function mediaFiles()
    {
        return $this->hasMany(MediaFile::class, 'batch_id', 'batch_id');
    }

    /**
     * Increment successful file count.
     *
     * @return void
     */
    public function incrementSuccessful(): void
    {
        $this->increment('successful_files');
        $this->decrement('pending_files');
        $this->checkCompletion();
    }

    /**
     * Increment failed file count.
     *
     * @return void
     */
    public function incrementFailed(): void
    {
        $this->increment('failed_files');
        $this->decrement('pending_files');
        $this->checkCompletion();
    }

    /**
     * Check if batch is complete and update status.
     *
     * @return void
     */
    public function checkCompletion(): void
    {
        $this->refresh();

        if ($this->pending_files <= 0) {
            $this->update([
                'status' => $this->failed_files === 0 ? 'completed' : 'completed_with_errors',
                'completed_at' => now(),
            ]);
        }
    }

    /**
     * Mark batch as processing.
     *
     * @return void
     */
    public function markProcessing(): void
    {
        $this->update(['status' => 'processing']);
    }

    /**
     * Mark batch as failed.
     *
     * @param string|null $error
     * @return void
     */
    public function markFailed(?string $error = null): void
    {
        $metadata = $this->metadata ?? [];
        if ($error) {
            $metadata['error'] = $error;
        }

        $this->update([
            'status' => 'failed',
            'metadata' => $metadata,
            'completed_at' => now(),
        ]);
    }

    /**
     * Get progress percentage.
     *
     * @return float
     */
    public function getProgressPercentage(): float
    {
        if ($this->total_files === 0) {
            return 0.0;
        }

        $processed = $this->successful_files + $this->failed_files;
        return round(($processed / $this->total_files) * 100, 2);
    }

    /**
     * Get batch status summary.
     *
     * @return array
     */
    public function getStatusSummary(): array
    {
        return [
            'batch_id' => $this->batch_id,
            'status' => $this->status,
            'progress' => $this->getProgressPercentage(),
            'total_files' => $this->total_files,
            'successful_files' => $this->successful_files,
            'failed_files' => $this->failed_files,
            'pending_files' => $this->pending_files,
            'created_at' => $this->created_at->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
        ];
    }

    /**
     * Scope to filter by status.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $status
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to get recent batches.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $days
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeRecent($query, int $days = 7)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }
}
