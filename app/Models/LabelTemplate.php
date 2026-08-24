<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class LabelTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'business_uuid',
        'name',
        'width_mm',
        'height_mm',
        'show_product_name',
        'show_variant_name',
        'show_price',
        'show_sku',
        'show_barcode',
        'show_qrcode',
        'is_default',
        'is_active',
    ];

    protected $attributes = [
        'show_product_name' => true,
        'show_variant_name' => true,
        'show_price' => true,
        'show_sku' => true,
        'show_barcode' => true,
        'show_qrcode' => false,
        'is_default' => false,
        'is_active' => true,
    ];

    protected $casts = [
        'width_mm' => 'decimal:2',
        'height_mm' => 'decimal:2',
        'show_product_name' => 'boolean',
        'show_variant_name' => 'boolean',
        'show_price' => 'boolean',
        'show_sku' => 'boolean',
        'show_barcode' => 'boolean',
        'show_qrcode' => 'boolean',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (LabelTemplate $template) {
            if (empty($template->uuid)) {
                $template->uuid = (string) Str::uuid();
            }
            if (is_null($template->is_active)) {
                $template->is_active = true;
            }
        });
    }

    public function printLogs(): HasMany
    {
        return $this->hasMany(ProductLabelPrintLog::class, 'label_template_id');
    }
}
