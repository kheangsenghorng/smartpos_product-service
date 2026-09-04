<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProductReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'business_uuid',
        'user_uuid',
        'name',
        'type',
        'format',
        'file_path',
        'status',
        'total_records',
        'metadata',
        'error_message',
        'completed_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'total_records' => 'integer',
        'completed_at' => 'datetime',
    ];

    protected $appends = [
        'download_url',
        'progress_percentage',
        'processed_records',
        'is_completed',
    ];

    protected static function booted(): void
    {
        static::creating(function (ProductReport $report) {
            if (empty($report->uuid)) {
                $report->uuid = (string) Str::uuid();
            }
        });
    }

    public function resolveRouteBinding($value, $field = null): ?Model
    {
        return $this->where('uuid', $value)
            ->orWhere('id', $value)
            ->firstOrFail();
    }

    public function getDownloadUrlAttribute(): ?string
    {
        if (empty($this->file_path) || $this->status !== 'completed') {
            return null;
        }

        if (str_starts_with($this->file_path, 'http://') || str_starts_with($this->file_path, 'https://')) {
            return $this->file_path;
        }

        return Storage::disk(config('filesystems.default', 'public'))->url($this->file_path);
    }

    public function getProgressPercentageAttribute(): int
    {
        if ($this->status === 'completed') {
            return 100;
        }

        if ($this->status === 'pending') {
            return 0;
        }

        return (int) ($this->metadata['progress_percentage'] ?? 0);
    }

    public function getProcessedRecordsAttribute(): int
    {
        if ($this->status === 'completed') {
            return (int) $this->total_records;
        }

        return (int) ($this->metadata['processed_records'] ?? 0);
    }

    public function getIsCompletedAttribute(): bool
    {
        return in_array($this->status, ['completed', 'failed'], true);
    }
}
