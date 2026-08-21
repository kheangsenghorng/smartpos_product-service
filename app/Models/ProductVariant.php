<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class ProductVariant extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'business_uuid',
        'product_id',
        'name',
        'sku',
        'sort_order',
        'is_default',
        'is_active',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
        'product_id' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (ProductVariant $variant) {
            if (empty($variant->uuid)) {
                $variant->uuid = (string) Str::uuid();
            }
        });
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function codes(): HasMany
    {
        return $this->hasMany(ProductCode::class, 'product_variant_id');
    }

    public function prices(): HasMany
    {
        return $this->hasMany(ProductPrice::class, 'product_variant_id');
    }

    public function currentPrice(): HasOne
    {
        return $this->hasOne(ProductPrice::class, 'product_variant_id')
            ->where('is_active', true)
            ->latest('id');
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class, 'product_variant_id');
    }
}
