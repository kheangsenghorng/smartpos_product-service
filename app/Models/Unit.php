<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Unit extends Model
{
    use HasFactory;

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
            if (is_null($unit->is_active)) {
                $unit->is_active = true;
            }
        });
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'unit_id');
    }
}
