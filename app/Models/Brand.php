<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Scout\Searchable;

class Brand extends Model
{
    use HasFactory, SoftDeletes, Searchable;

    protected $fillable = [
        'uuid',
        'business_uuid',
        'name',
        'code',
        'description',
        'logo_path',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected $appends = [
        'logo_url',
    ];

    protected static function booted(): void
    {
        static::creating(function (Brand $brand) {
            if (empty($brand->uuid)) {
                $brand->uuid = (string) Str::uuid();
            }
            if (is_null($brand->is_active)) {
                $brand->is_active = true;
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function resolveRouteBinding($value, $field = null): ?Model
    {
        return $this->where('uuid', $value)
            ->orWhere('id', $value)
            ->firstOrFail();
    }

    public function getLogoUrlAttribute(): ?string
    {
        if (empty($this->logo_path)) {
            return null;
        }

        if (str_starts_with($this->logo_path, 'http://') || str_starts_with($this->logo_path, 'https://')) {
            return $this->logo_path;
        }

        return Storage::disk(config('filesystems.default', 'public'))->url($this->logo_path);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'brand_id');
    }

    /**
     * Get the indexable data array for the model.
     *
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        return [
            'id' => (int) $this->id,
            'uuid' => (string) $this->uuid,
            'business_uuid' => (string) $this->business_uuid,
            'name' => (string) $this->name,
            'code' => (string) $this->code,
            'description' => (string) ($this->description ?? ''),
            'is_active' => (bool) $this->is_active,
        ];
    }

    /**
     * Determine if the model should be searchable.
     */
    public function shouldBeSearchable(): bool
    {
        return is_null($this->deleted_at);
    }
}
