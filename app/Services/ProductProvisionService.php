<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductPrice;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;

class ProductProvisionService
{
    public function __construct(
        protected ProductCodeService $codeService
    ) {}

    /**
     * Atomically create a product with optional variants, code generation, and initial price.
     */
    public function createProduct(array $data, string $businessUuid, ?string $userUuid = null): Product
    {
        return DB::transaction(function () use ($data, $businessUuid, $userUuid) {
            $codeOption = $data['code_option'] ?? 'none';

            // 1. Create main product record
            $product = Product::create([
                'business_uuid' => $businessUuid,
                'category_id' => $data['category_id'] ?? null,
                'brand_id' => $data['brand_id'] ?? null,
                'unit_id' => $data['unit_id'],
                'name' => $data['name'],
                'sku' => $data['sku'],
                'slug' => $data['slug'] ?? null,
                'description' => $data['description'] ?? null,
                'track_inventory' => $data['track_inventory'] ?? true,
                'allow_negative_stock' => $data['allow_negative_stock'] ?? false,
                'is_taxable' => $data['is_taxable'] ?? true,
                'is_active' => $data['is_active'] ?? true,
                'available_from' => $data['available_from'] ?? null,
                'available_until' => $data['available_until'] ?? null,
                'created_by_uuid' => $userUuid,
                'updated_by_uuid' => $userUuid,
            ]);

            // 2. Handle variants if supplied
            if (!empty($data['variants']) && is_array($data['variants'])) {
                foreach ($data['variants'] as $index => $variantData) {
                    $variant = ProductVariant::create([
                        'business_uuid' => $businessUuid,
                        'product_id' => $product->id,
                        'name' => $variantData['name'],
                        'sku' => $variantData['sku'],
                        'sort_order' => $variantData['sort_order'] ?? $index,
                        'is_default' => $variantData['is_default'] ?? ($index === 0),
                        'is_active' => $variantData['is_active'] ?? true,
                    ]);

                    // Generate variant codes if code_option applies
                    $variantCodeOption = $variantData['code_option'] ?? $codeOption;
                    $this->codeService->generateForVariant(
                        $variant,
                        $variantCodeOption,
                        $variantData['barcode'] ?? null,
                        $variantData['qrcode'] ?? null
                    );

                    // Add variant price if specified
                    if (isset($variantData['selling_price'])) {
                        ProductPrice::create([
                            'business_uuid' => $businessUuid,
                            'product_id' => $product->id,
                            'product_variant_id' => $variant->id,
                            'currency_code' => $variantData['currency_code'] ?? ($data['currency_code'] ?? 'USD'),
                            'selling_price' => $variantData['selling_price'],
                            'cost_price' => $variantData['cost_price'] ?? null,
                            'minimum_price' => $variantData['minimum_price'] ?? null,
                            'is_active' => true,
                        ]);
                    }
                }
            } else {
                // Generate product level codes
                $this->codeService->generateForProduct(
                    $product,
                    $codeOption,
                    $data['barcode'] ?? null,
                    $data['qrcode'] ?? null
                );
            }

            // 3. Handle base product price if supplied
            if (isset($data['selling_price'])) {
                ProductPrice::create([
                    'business_uuid' => $businessUuid,
                    'product_id' => $product->id,
                    'product_variant_id' => null,
                    'currency_code' => $data['currency_code'] ?? 'USD',
                    'selling_price' => $data['selling_price'],
                    'cost_price' => $data['cost_price'] ?? null,
                    'minimum_price' => $data['minimum_price'] ?? null,
                    'is_active' => true,
                ]);
            }

            // 4. Handle initial images if supplied
            if (!empty($data['images']) && is_array($data['images'])) {
                foreach ($data['images'] as $imgIndex => $img) {
                    ProductImage::create([
                        'business_uuid' => $businessUuid,
                        'product_id' => $product->id,
                        'product_variant_id' => null,
                        'image_path' => is_array($img) ? ($img['image_path'] ?? '') : (string) $img,
                        'alt_text' => is_array($img) ? ($img['alt_text'] ?? null) : null,
                        'sort_order' => $imgIndex,
                        'is_primary' => $imgIndex === 0,
                    ]);
                }
            }

            return $product->load(['category', 'brand', 'unit', 'variants', 'codes', 'prices', 'images']);
        });
    }
}
