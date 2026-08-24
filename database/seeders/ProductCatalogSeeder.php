<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Category;
use App\Models\LabelTemplate;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\Unit;
use App\Services\ProductCodeService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ProductCatalogSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $businessUuid = env('DEFAULT_BUSINESS_UUID', 'c2ba4fe5-d018-4248-9ef4-9fcfc2b1aba7');
        $userUuid = env('DEFAULT_USER_UUID', '97f0693c-91dc-4b11-ab4a-cadcd6c09907');

        // 1. Units
        $unitPcs = Unit::firstOrCreate([
            'business_uuid' => $businessUuid,
            'code' => 'PCS',
        ], [
            'uuid' => (string) Str::uuid(),
            'name' => 'Piece',
            'symbol' => 'pcs',
            'is_active' => true,
        ]);

        $unitCan = Unit::firstOrCreate([
            'business_uuid' => $businessUuid,
            'code' => 'CAN',
        ], [
            'uuid' => (string) Str::uuid(),
            'name' => 'Can',
            'symbol' => 'can',
            'is_active' => true,
        ]);

        $unitKg = Unit::firstOrCreate([
            'business_uuid' => $businessUuid,
            'code' => 'KG',
        ], [
            'uuid' => (string) Str::uuid(),
            'name' => 'Kilogram',
            'symbol' => 'kg',
            'is_active' => true,
        ]);

        // 2. Categories
        $catBeverages = Category::firstOrCreate([
            'business_uuid' => $businessUuid,
            'code' => 'BEV',
        ], [
            'uuid' => (string) Str::uuid(),
            'name' => 'Beverages',
            'description' => 'Cold soft drinks, sodas, juices and tea',
            'is_active' => true,
        ]);

        $catSnacks = Category::firstOrCreate([
            'business_uuid' => $businessUuid,
            'code' => 'SNK',
        ], [
            'uuid' => (string) Str::uuid(),
            'name' => 'Snacks',
            'description' => 'Chips, biscuits, and chocolates',
            'is_active' => true,
        ]);

        // 3. Brands
        $brandCoke = Brand::firstOrCreate([
            'business_uuid' => $businessUuid,
            'code' => 'COCA-COLA',
        ], [
            'uuid' => (string) Str::uuid(),
            'name' => 'Coca-Cola',
            'description' => 'The Coca-Cola Company',
            'is_active' => true,
        ]);

        $brandPepsi = Brand::firstOrCreate([
            'business_uuid' => $businessUuid,
            'code' => 'PEPSI',
        ], [
            'uuid' => (string) Str::uuid(),
            'name' => 'PepsiCo',
            'description' => 'PepsiCo Beverages',
            'is_active' => true,
        ]);

        // 4. Products
        $product1 = Product::firstOrCreate([
            'business_uuid' => $businessUuid,
            'sku' => 'COKE-330',
        ], [
            'uuid' => (string) Str::uuid(),
            'category_id' => $catBeverages->id,
            'brand_id' => $brandCoke->id,
            'unit_id' => $unitCan->id,
            'name' => 'Coca-Cola 330ml Can',
            'slug' => 'coca-cola-330ml-can',
            'description' => 'Coca-Cola original taste carbonated drink 330ml can',
            'track_inventory' => true,
            'allow_negative_stock' => false,
            'is_taxable' => true,
            'is_active' => true,
            'available_from' => '2026-01-01',
            'available_until' => '2030-12-31',
            'created_by_uuid' => $userUuid,
            'updated_by_uuid' => $userUuid,
        ]);

        ProductPrice::firstOrCreate([
            'business_uuid' => $businessUuid,
            'product_id' => $product1->id,
            'product_variant_id' => null,
            'is_active' => true,
        ], [
            'uuid' => (string) Str::uuid(),
            'currency_code' => 'USD',
            'selling_price' => 0.75,
            'cost_price' => 0.45,
            'minimum_price' => 0.60,
        ]);

        $codeService = app(ProductCodeService::class);
        $codeService->generateForProduct($product1, 'both', 'COKE-330');

        // 5. Label Templates
        LabelTemplate::firstOrCreate([
            'business_uuid' => $businessUuid,
            'name' => 'Standard Shelf Label (50x30mm)',
        ], [
            'uuid' => (string) Str::uuid(),
            'width_mm' => 50.0,
            'height_mm' => 30.0,
            'show_product_name' => true,
            'show_variant_name' => true,
            'show_price' => true,
            'show_sku' => true,
            'show_barcode' => true,
            'show_qrcode' => false,
            'is_default' => true,
            'is_active' => true,
        ]);

        LabelTemplate::firstOrCreate([
            'business_uuid' => $businessUuid,
            'name' => 'Compact QR & Barcode Label (40x30mm)',
        ], [
            'uuid' => (string) Str::uuid(),
            'width_mm' => 40.0,
            'height_mm' => 30.0,
            'show_product_name' => true,
            'show_variant_name' => false,
            'show_price' => true,
            'show_sku' => true,
            'show_barcode' => true,
            'show_qrcode' => true,
            'is_default' => false,
            'is_active' => true,
        ]);
    }
}
