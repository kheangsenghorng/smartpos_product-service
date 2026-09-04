<?php

namespace App\Console\Commands;

use App\Services\ProductImportExportService;
use Illuminate\Console\Command;

class ImportProductsCommand extends Command
{
    protected $signature = 'products:import 
                            {file : Path to the CSV, Excel, or JSON file to import} 
                            {--business= : The target business UUID} 
                            {--validate-only : Dry run to validate format without saving}
                            {--async : Dispatch import job to background queue worker}';

    protected $description = 'Import products catalog from CSV, Excel, or JSON file into the database';

    public function handle(ProductImportExportService $importService): int
    {
        $filePath = $this->argument('file');
        $businessUuid = $this->option('business') 
            ?: config('app.default_business_uuid', '01a06674-dc36-7152-b557-27e8e3851f9d');
        $validateOnly = (bool) $this->option('validate-only');
        $isAsync = (bool) $this->option('async');

        if (!file_exists($filePath)) {
            $this->error("File not found: {$filePath}");
            return Command::FAILURE;
        }

        $this->info("Starting catalog import...");
        $this->info("File: {$filePath}");
        $this->info("Target Business UUID: {$businessUuid}");

        if ($isAsync) {
            $this->info("Mode: Background Queue Worker (Async)");
            $disk = config('filesystems.default', 'public');
            $ext = pathinfo($filePath, PATHINFO_EXTENSION) ?: 'csv';
            $tempPath = 'temp_imports/' . \Illuminate\Support\Str::uuid() . '.' . $ext;
            \Illuminate\Support\Facades\Storage::disk($disk)->put($tempPath, file_get_contents($filePath));

            $report = \App\Models\ProductReport::create([
                'business_uuid' => $businessUuid,
                'user_uuid' => null,
                'name' => 'CLI Async Catalog Import',
                'type' => 'import_log',
                'format' => $ext,
                'status' => 'pending',
                'metadata' => [
                    'filename' => basename($filePath),
                ],
            ]);

            \App\Jobs\ImportProductsJob::dispatch($tempPath, $businessUuid, null, $report);

            $this->info("🚀 Dispatched import job to background queue worker!");
            $this->info("Report UUID: {$report->uuid}");
            $this->info("Watch live execution with: docker compose logs -f product-worker");
            return Command::SUCCESS;
        }

        $this->info("Mode: " . ($validateOnly ? "Validation Only (Dry Run)" : "Full Database Import (Sync)"));

        $content = file_get_contents($filePath);
        $result = $importService->import(
            fileOrContent: $content,
            businessUuid: $businessUuid,
            userUuid: null,
            validateOnly: $validateOnly
        );

        if (!$result['success']) {
            $this->error("❌ " . $result['message']);
            if (!empty($result['errors'])) {
                $this->table(['Row', 'Field / SKU', 'Error'], array_map(fn($err) => [
                    $err['row'] ?? 'N/A',
                    $err['field'] ?? ($err['sku'] ?? 'N/A'),
                    $err['message'] ?? ($err['error'] ?? 'Unknown error'),
                ], array_slice($result['errors'], 0, 20)));
            }
            return Command::FAILURE;
        }

        $this->info("✅ " . $result['message']);
        $this->table(['Total Rows', 'Imported', 'Updated', 'Failed'], [
            [
                $result['total_rows'] ?? 0,
                $result['imported_count'] ?? 0,
                $result['updated_count'] ?? 0,
                $result['failed_count'] ?? 0,
            ]
        ]);

        return Command::SUCCESS;
    }
}
