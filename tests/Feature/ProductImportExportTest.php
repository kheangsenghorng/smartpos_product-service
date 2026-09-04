<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Unit;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProductImportExportTest extends TestCase
{
    public function test_can_dry_run_validate_csv(): void
    {
        $businessUuid = (string) Str::uuid();

        $csv = "name,sku,category,brand,unit,selling_price,cost_price,barcode\n" .
               "Organic Milk 1L,MILK-01,Dairy,FarmFresh,bottle,2.99,1.50,885000000001\n" .
               ",INVALID-NO-NAME,Dairy,FarmFresh,bottle,1.99,1.00,885000000002\n";

        $response = $this->actingAsJwt($businessUuid)
            ->postJson('/api/v1/products/import', [
                'csv_data' => $csv,
                'validate_only' => true,
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('validate_only', true)
            ->assertJsonPath('failed_count', 1)
            ->assertJsonPath('errors.0.field', 'name');

        $this->assertDatabaseMissing('products', ['sku' => 'MILK-01']);
    }

    public function test_can_import_products_from_csv_synchronously(): void
    {
        $businessUuid = (string) Str::uuid();

        $csv = "name,sku,category,brand,unit,selling_price,cost_price,barcode\n" .
               "Sparkling Water,WATER-SPK-01,Beverages,AquaPure,can,1.50,0.70,885000999991\n" .
               "Potato Chips,CHIPS-SALT-01,Snacks,CrispyCo,bag,2.25,1.10,885000999992\n";

        $response = $this->actingAsJwt($businessUuid)
            ->postJson('/api/v1/products/import', [
                'csv_data' => $csv,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('imported_count', 2);

        $this->assertDatabaseHas('products', [
            'business_uuid' => $businessUuid,
            'sku' => 'WATER-SPK-01',
            'name' => 'Sparkling Water',
        ]);

        $this->assertDatabaseHas('product_prices', [
            'business_uuid' => $businessUuid,
            'selling_price' => 1.50,
        ]);

        $this->assertDatabaseHas('product_codes', [
            'business_uuid' => $businessUuid,
            'code_value' => '885000999991',
        ]);
    }

    public function test_can_export_products_to_csv(): void
    {
        $businessUuid = (string) Str::uuid();

        $unit = Unit::create([
            'business_uuid' => $businessUuid,
            'name' => 'Piece',
            'symbol' => 'pcs',
        ]);

        Product::create([
            'business_uuid' => $businessUuid,
            'unit_id' => $unit->id,
            'name' => 'Export Item Alpha',
            'sku' => 'EXP-ALPHA',
            'is_active' => true,
        ]);

        $response = $this->actingAsJwt($businessUuid)
            ->get('/api/v1/products/export');

        $response->assertStatus(200)
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $content = $response->getContent();
        $this->assertStringContainsString('sku,name,category,brand', $content);
        $this->assertStringContainsString('EXP-ALPHA', $content);
        $this->assertStringContainsString('Export Item Alpha', $content);
    }

    public function test_can_dispatch_async_import_job(): void
    {
        Queue::fake();

        $businessUuid = (string) Str::uuid();

        $file = UploadedFile::fake()->createWithContent(
            'products.csv',
            "name,sku,unit,selling_price\nJuice,JUC-01,bottle,3.00\n"
        );

        $response = $this->actingAsJwt($businessUuid)
            ->postJson('/api/v1/products/import', [
                'file' => $file,
                'async' => true,
            ]);

        $response->assertStatus(202)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'pending');

        Queue::assertPushed(\App\Jobs\ImportProductsJob::class);
    }

    public function test_can_export_products_to_excel_xlsx(): void
    {
        $businessUuid = (string) Str::uuid();

        $unit = Unit::create([
            'business_uuid' => $businessUuid,
            'name' => 'Piece',
            'symbol' => 'pcs',
        ]);

        Product::create([
            'business_uuid' => $businessUuid,
            'unit_id' => $unit->id,
            'name' => 'Excel Export Item',
            'sku' => 'EXP-XLSX-01',
            'is_active' => true,
        ]);

        $response = $this->actingAsJwt($businessUuid)
            ->get('/api/v1/products/export?format=xlsx');

        $response->assertStatus(200)
            ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $content = $response->getContent();
        // Check standard ZIP / XLSX magic header
        $this->assertStringStartsWith("PK\x03\x04", $content);
    }

    public function test_can_import_products_from_excel_xlsx(): void
    {
        $businessUuid = (string) Str::uuid();

        // Create in-memory XLSX file
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([
            ['name', 'sku', 'unit', 'selling_price'],
            ['Excel Imported Soda', 'SODA-XLSX-01', 'can', 1.99],
        ]);

        $temp = tempnam(sys_get_temp_dir(), 'test_xlsx_') . '.xlsx';
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save($temp);

        $uploadedFile = new UploadedFile(
            $temp,
            'catalog.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );

        $response = $this->actingAsJwt($businessUuid)
            ->postJson('/api/v1/products/import', [
                'file' => $uploadedFile,
            ]);

        @unlink($temp);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('imported_count', 1);

        $this->assertDatabaseHas('products', [
            'business_uuid' => $businessUuid,
            'sku' => 'SODA-XLSX-01',
            'name' => 'Excel Imported Soda',
        ]);
    }
}
