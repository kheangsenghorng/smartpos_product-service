<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'business_uuid',
        'category_id',
        'brand_id',
        'unit_id',
        'name',
        'sku',
        'slug',
        'description',
        'track_inventory',
        'allow_negative_stock',
        'is_taxable',
        'is_active',
        'available_from',
        'available_until',
        'created_by_uuid',
        'updated_by_uuid',
    ];

    protected $attributes = [
        'track_inventory' => true,
        'allow_negative_stock' => false,
        'is_taxable' => true,
        'is_active' => true,
    ];

    protected $casts = [
        'track_inventory' => 'boolean',
        'allow_negative_stock' => 'boolean',
        'is_taxable' => 'boolean',
        'is_active' => 'boolean',
        'available_from' => 'date:Y-m-d',
        'available_until' => 'date:Y-m-d',
        'category_id' => 'integer',
        'brand_id' => 'integer',
        'unit_id' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (Product $product) {
            if (empty($product->uuid)) {
                $product->uuid = (string) Str::uuid();
            }
            if (empty($product->slug)) {
                $product->slug = Str::slug($product->name);
            }
        });
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class, 'brand_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class, 'product_id')->orderBy('sort_order', 'asc');
    }

    public function codes(): HasMany
    {
        return $this->hasMany(ProductCode::class, 'product_id');
    }

    public function prices(): HasMany
    {
        return $this->hasMany(ProductPrice::class, 'product_id');
    }

    public function currentPrice(): HasOne
    {
        $today = Carbon::now();
        return $this->hasOne(ProductPrice::class, 'product_id')
            ->where('is_active', true)
            ->whereNull('product_variant_id')
            ->where(function (Builder $query) use ($today) {
                $query->whereNull('starts_at')->orWhere('starts_at', '<=', $today);
            })
            ->where(function (Builder $query) use ($today) {
                $query->whereNull('ends_at')->orWhere('ends_at', '>=', $today);
            })
            ->latest('id');
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class, 'product_id')->orderBy('sort_order', 'asc');
    }

    public function primaryImage(): HasOne
    {
        return $this->hasOne(ProductImage::class, 'product_id')
            ->where('is_primary', true)
            ->whereNull('product_variant_id');
    }

    public function printLogs(): HasMany
    {
        return $this->hasMany(ProductLabelPrintLog::class, 'product_id');
    }

    /**
     * Check if product is currently within its active availability window.
     */
    public function isAvailable(?Carbon $date = null): bool
    {
        if (!$this->is_active) {
            return false;
        }

        $checkDate = ($date ?? Carbon::today())->startOfDay();

        if ($this->available_from && $checkDate->lt(Carbon::parse($this->available_from)->startOfDay())) {
            return false;
        }

        if ($this->available_until && $checkDate->gt(Carbon::parse($this->available_until)->endOfDay())) {
            return false;
        }

        return true;
    }
}
