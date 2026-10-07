<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Laravel\Scout\Searchable;

class Unit extends Model
{
    use HasFactory, Searchable;

    protected $fillable = [
        'uuid',
        'business_uuid',
        'name',
        'code',
        'symbol',
        'precision',
        'is_active',
    ];

    protected $casts = [
        'precision' => 'integer',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (Unit $unit) {
            if (empty($unit->uuid)) {
                $unit->uuid = (string) Str::uuid();
            }
            if (empty($unit->code)) {
                $unit->code = strtoupper(Str::slug($unit->symbol ?: ($unit->name ?: 'UNIT')));
            }
            if (is_null($unit->is_active)) {
                $unit->is_active = true;
            }
        });
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'unit_id');
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
            'symbol' => (string) ($this->symbol ?? ''),
            'is_active' => (bool) $this->is_active,
        ];
    }
}
