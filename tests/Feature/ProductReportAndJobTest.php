<?php

namespace Tests\Feature;

use App\Jobs\GenerateProductReportJob;
use App\Models\Product;
use App\Models\ProductReport;
use App\Models\Unit;
use App\Services\ProductReportService;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProductReportAndJobTest extends TestCase
{
    public function test_can_get_real_time_catalog_summary(): void
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
            'name' => 'Report Item 1',
            'sku' => 'REP-01',
            'is_active' => true,
        ]);

        $response = $this->actingAsJwt($businessUuid)
            ->getJson('/api/v1/products/reports/summary');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.catalog.total_products', 1)
            ->assertJsonPath('data.catalog.active_products', 1);
    }

    public function test_can_dispatch_and_execute_report_job(): void
    {
        Storage::fake('public');

        $businessUuid = (string) Str::uuid();

        $unit = Unit::create([
            'business_uuid' => $businessUuid,
            'name' => 'Box',
            'symbol' => 'bx',
        ]);

        Product::create([
            'business_uuid' => $businessUuid,
            'unit_id' => $unit->id,
            'name' => 'Box Item',
            'sku' => 'BX-001',
            'is_active' => true,
        ]);

        // Dispatch generation endpoint
        $response = $this->actingAsJwt($businessUuid)
            ->postJson('/api/v1/products/reports/generate', [
                'type' => 'full_catalog',
                'format' => 'csv',
            ]);

        $response->assertStatus(202)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'pending');

        $reportId = $response->json('data.id');
        $report = ProductReport::findOrFail($reportId);

        // Execute job directly to test job execution
        $reportService = app(ProductReportService::class);
        $job = new GenerateProductReportJob($report);
        $job->handle($reportService);

        $report->refresh();
        $this->assertEquals('completed', $report->status);
        $this->assertNotNull($report->file_path);
        $downloadResponse = $this->actingAsJwt($businessUuid)
            ->get("/api/v1/products/reports/{$report->id}/download");

        $downloadResponse->assertStatus(200)
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    }

    public function test_can_execute_import_products_job(): void
    {
        Storage::fake('public');

        $businessUuid = (string) Str::uuid();

        $csv = "name,sku,unit,selling_price\nQueue Item,QUE-01,bottle,4.50\n";
        $tempPath = "temp_imports/test_job.csv";
        Storage::disk('public')->put($tempPath, $csv);

        $report = ProductReport::create([
            'business_uuid' => $businessUuid,
            'name' => 'Test Async Import',
            'type' => 'import_log',
            'status' => 'pending',
        ]);

        $importService = app(\App\Services\ProductImportExportService::class);
        $job = new \App\Jobs\ImportProductsJob($tempPath, $businessUuid, null, $report);
        $job->handle($importService);

        $report->refresh();
        $this->assertEquals('completed', $report->status);
        $this->assertEquals(1, $report->total_records);

        $this->assertDatabaseHas('products', [
            'business_uuid' => $businessUuid,
            'sku' => 'QUE-01',
            'name' => 'Queue Item',
        ]);
    }

    public function test_can_query_report_by_uuid_with_progress_attributes(): void
    {
        $businessUuid = (string) Str::uuid();

        $report = ProductReport::create([
            'business_uuid' => $businessUuid,
            'name' => 'Progress Test Report',
            'type' => 'import_log',
            'status' => 'processing',
            'total_records' => 100,
            'metadata' => [
                'progress_percentage' => 45,
                'processed_records' => 45,
            ],
        ]);

        $response = $this->actingAsJwt($businessUuid)
            ->getJson("/api/v1/products/reports/{$report->uuid}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.uuid', $report->uuid)
            ->assertJsonPath('data.progress_percentage', 45)
            ->assertJsonPath('data.processed_records', 45)
            ->assertJsonPath('data.is_completed', false);
    }

    public function test_import_job_failed_handler_updates_report(): void
    {
        $businessUuid = (string) Str::uuid();

        $report = ProductReport::create([
            'business_uuid' => $businessUuid,
            'name' => 'Failure Handler Test',
            'type' => 'import_log',
            'status' => 'processing',
        ]);

        $job = new \App\Jobs\ImportProductsJob('non_existent.csv', $businessUuid, null, $report);
        $job->failed(new \RuntimeException('Execution timed out'));

        $report->refresh();
        $this->assertEquals('failed', $report->status);
        $this->assertStringContainsString('Execution timed out', $report->error_message);
        $this->assertTrue($report->is_completed);
    }
}
