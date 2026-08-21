<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ProductPrice extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'business_uuid',
        'product_id',
        'product_variant_id',
        'currency_code',
        'selling_price',
        'cost_price',
        'minimum_price',
        'starts_at',
        'ends_at',
        'is_active',
    ];

    protected $casts = [
        'selling_price' => 'decimal:4',
        'cost_price' => 'decimal:4',
        'minimum_price' => 'decimal:4',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'is_active' => 'boolean',
        'product_id' => 'integer',
        'product_variant_id' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (ProductPrice $price) {
            if (empty($price->uuid)) {
                $price->uuid = (string) Str::uuid();
            }
        });
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }
}
