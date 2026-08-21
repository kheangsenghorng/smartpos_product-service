<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Brand extends Model
{
    use HasFactory, SoftDeletes;

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

    protected static function booted(): void
    {
        static::creating(function (Brand $brand) {
            if (empty($brand->uuid)) {
                $brand->uuid = (string) Str::uuid();
            }
        });
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'brand_id');
    }
}
