<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ProductCode extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'business_uuid',
        'product_id',
        'product_variant_id',
        'code_type',
        'symbology',
        'code_value',
        'image_path',
        'is_primary',
        'is_auto_generated',
        'is_active',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'is_auto_generated' => 'boolean',
        'is_active' => 'boolean',
        'product_id' => 'integer',
        'product_variant_id' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (ProductCode $code) {
            if (empty($code->uuid)) {
                $code->uuid = (string) Str::uuid();
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
