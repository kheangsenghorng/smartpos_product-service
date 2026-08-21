<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ProductLabelPrintLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'business_uuid',
        'product_id',
        'product_variant_id',
        'label_template_id',
        'printed_by_uuid',
        'quantity_printed',
        'printed_at',
    ];

    protected $casts = [
        'quantity_printed' => 'integer',
        'printed_at' => 'datetime',
        'product_id' => 'integer',
        'product_variant_id' => 'integer',
        'label_template_id' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (ProductLabelPrintLog $log) {
            if (empty($log->uuid)) {
                $log->uuid = (string) Str::uuid();
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

    public function template(): BelongsTo
    {
        return $this->belongsTo(LabelTemplate::class, 'label_template_id');
    }
}
