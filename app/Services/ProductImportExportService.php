<?php

namespace App\Services;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductCode;
use App\Models\ProductPrice;
use App\Models\Unit;
use App\Observers\ProductObserver;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProductImportExportService
{
    /**
     * Parse and import products from a CSV file or content.
     *
     * @return array<string, mixed>
     */
    public function import(
        UploadedFile|string $fileOrContent,
        string $businessUuid,
        ?string $userUuid = null,
        bool $validateOnly = false,
        ?callable $progressCallback = null
    ): array {
        $rows = $this->parseFileOrContent($fileOrContent);

        if (empty($rows)) {
            return [
                'success' => false,
                'message' => 'CSV file is empty or does not contain a header row.',
                'total_rows' => 0,
                'imported_count' => 0,
                'updated_count' => 0,
                'failed_count' => 0,
                'errors' => [],
            ];
        }

        $validationErrors = $this->validateRows($rows, $businessUuid);

        if ($validateOnly) {
            return [
                'success' => empty($validationErrors),
                'message' => empty($validationErrors)
                    ? 'CSV validation passed successfully. ' . count($rows) . ' row(s) ready to import.'
                    : 'CSV validation failed with ' . count($validationErrors) . ' error(s).',
                'validate_only' => true,
                'total_rows' => count($rows),
                'imported_count' => 0,
                'updated_count' => 0,
                'failed_count' => count($validationErrors),
                'errors' => $validationErrors,
            ];
        }

        if (!empty($validationErrors)) {
            return [
                'success' => false,
                'message' => 'CSV validation failed. Please fix the reported errors.',
                'total_rows' => count($rows),
                'imported_count' => 0,
                'updated_count' => 0,
                'failed_count' => count($validationErrors),
                'errors' => $validationErrors,
            ];
        }

        $totalRows = count($rows);

        if ($progressCallback) {
            $progressCallback(0, 0, $totalRows);
        }

        // Execute import in transaction (processed in chunks of 50 for efficient commits & real-time progress)
        $importedCount = 0;
        $updatedCount = 0;
        $errors = [];
        $processedCount = 0;

        $chunks = array_chunk($rows, 50, true);

        foreach ($chunks as $chunk) {
            DB::beginTransaction();
            try {
                foreach ($chunk as $index => $row) {
                    $lineNumber = $index + 2; // +1 for 0-index, +1 for header
                    try {
                        $result = $this->processRow($row, $businessUuid, $userUuid);
                        if ($result === 'created') {
                            $importedCount++;
                        } elseif ($result === 'updated') {
                            $updatedCount++;
                        }
                    } catch (\Throwable $e) {
                        $errors[] = [
                            'row' => $lineNumber,
                            'sku' => $row['sku'] ?? 'N/A',
                            'error' => $e->getMessage(),
                        ];
                    }
                    $processedCount++;
                }

                DB::commit();
            } catch (\Throwable $e) {
                DB::rollBack();
                throw $e;
            }

            if ($progressCallback) {
                $percentage = (int) round(($processedCount / $totalRows) * 100);
                $progressCallback($percentage, $processedCount, $totalRows);
            }
        }

        ProductObserver::invalidateScanCache($businessUuid);

        return [
            'success' => empty($errors),
            'message' => empty($errors)
                ? "Successfully processed " . count($rows) . " product(s) ({$importedCount} created, {$updatedCount} updated)."
                : "Processed with errors. {$importedCount} created, {$updatedCount} updated, " . count($errors) . " failed.",
            'total_rows' => count($rows),
            'imported_count' => $importedCount,
            'updated_count' => $updatedCount,
            'failed_count' => count($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Export products to CSV string.
     */
    public function export(string $businessUuid, array $filters = []): string
    {
        $query = Product::where('business_uuid', $businessUuid)
            ->with(['category', 'brand', 'unit', 'currentPrice', 'codes']);

        if (!empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }
        if (!empty($filters['brand_id'])) {
            $query->where('brand_id', $filters['brand_id']);
        }
        if (isset($filters['is_active'])) {
            $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        $products = $query->orderBy('name')->get();

        $headers = [
            'sku',
            'name',
            'category',
            'brand',
            'unit',
            'selling_price',
            'cost_price',
            'currency_code',
            'barcode',
            'is_taxable',
            'track_inventory',
            'is_active',
            'description',
        ];

        $output = fopen('php://temp', 'r+');
        fputcsv($output, $headers);

        foreach ($products as $product) {
            $primaryBarcode = $product->codes->firstWhere('code_type', 'barcode')?->code_value
                ?? $product->codes->first()?->code_value;

            fputcsv($output, [
                $product->sku,
                $product->name,
                $product->category?->name ?? '',
                $product->brand?->name ?? '',
                $product->unit?->symbol ?? ($product->unit?->name ?? 'pcs'),
                $product->currentPrice?->selling_price ?? 0.00,
                $product->currentPrice?->cost_price ?? '',
                $product->currentPrice?->currency_code ?? 'USD',
                $primaryBarcode ?? '',
                $product->is_taxable ? '1' : '0',
                $product->track_inventory ? '1' : '0',
                $product->is_active ? '1' : '0',
                $product->description ?? '',
            ]);
        }

        rewind($output);
        $csvContent = stream_get_contents($output);
        fclose($output);

        return $csvContent ?: '';
    }

    /**
     * Parse uploaded file or string content detecting JSON vs CSV vs Excel spreadsheet.
     */
    protected function parseFileOrContent(UploadedFile|string|array $fileOrContent): array
    {
        if (is_array($fileOrContent)) {
            return $fileOrContent;
        }

        if ($fileOrContent instanceof UploadedFile) {
            $ext = strtolower($fileOrContent->getClientOriginalExtension());
            if (in_array($ext, ['xlsx', 'xls'], true)) {
                return $this->parseSpreadsheet($fileOrContent->getRealPath());
            }
            if ($ext === 'json') {
                $decoded = json_decode(file_get_contents($fileOrContent->getRealPath()), true);
                return is_array($decoded) ? (isset($decoded[0]) ? $decoded : [$decoded]) : [];
            }
        } elseif (is_string($fileOrContent)) {
            $trimmed = trim($fileOrContent);
            if (str_starts_with($trimmed, '[') || str_starts_with($trimmed, '{')) {
                $decoded = json_decode($trimmed, true);
                if (is_array($decoded)) {
                    return isset($decoded[0]) ? $decoded : [$decoded];
                }
            }

            if (str_starts_with($fileOrContent, "PK\x03\x04")) {
                // Raw binary XLSX content (ZIP container)
                $temp = tempnam(sys_get_temp_dir(), 'xlsx_');
                file_put_contents($temp, $fileOrContent);
                $rows = $this->parseSpreadsheet($temp);
                @unlink($temp);
                return $rows;
            }
        }

        return $this->parseCsv($fileOrContent);
    }

    /**
     * Export products catalog to JSON format string.
     */
    public function exportJson(string $businessUuid, array $filters = []): string
    {
        $query = Product::where('business_uuid', $businessUuid)
            ->with(['category', 'brand', 'unit', 'currentPrice', 'codes']);

        if (!empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }
        if (!empty($filters['brand_id'])) {
            $query->where('brand_id', $filters['brand_id']);
        }
        if (isset($filters['is_active'])) {
            $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        $products = $query->orderBy('name')->get();

        $data = $products->map(function ($product) {
            $primaryBarcode = $product->codes->firstWhere('code_type', 'barcode')?->code_value
                ?? $product->codes->first()?->code_value ?? '';

            return [
                'name' => $product->name,
                'sku' => $product->sku,
                'category' => $product->category?->name,
                'brand' => $product->brand?->name,
                'unit' => $product->unit?->symbol ?? ($product->unit?->name ?? 'pcs'),
                'selling_price' => (float) ($product->currentPrice?->selling_price ?? 0.00),
                'cost_price' => $product->currentPrice?->cost_price !== null ? (float) $product->currentPrice->cost_price : null,
                'currency_code' => $product->currentPrice?->currency_code ?? 'USD',
                'barcode' => $primaryBarcode,
                'is_taxable' => (bool) $product->is_taxable,
                'track_inventory' => (bool) $product->track_inventory,
                'is_active' => (bool) $product->is_active,
                'description' => $product->description,
            ];
        });

        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '[]';
    }

    /**
     * Parse an Excel XLSX / XLS file using PhpSpreadsheet into associative rows.
     */
    protected function parseSpreadsheet(string $filePath): array
    {
        $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($filePath);
        if (method_exists($reader, 'setReadDataOnly')) {
            $reader->setReadDataOnly(true);
        }
        if (method_exists($reader, 'setReadEmptyCells')) {
            $reader->setReadEmptyCells(false);
        }
        $spreadsheet = $reader->load($filePath);
        $sheet = $spreadsheet->getActiveSheet();
        $sheetData = $sheet->toArray(null, true, true, false);

        if (empty($sheetData)) {
            return [];
        }

        $headerRow = array_shift($sheetData);
        if (empty($headerRow)) {
            return [];
        }

        $header = array_map(fn($col) => strtolower(trim(preg_replace('/[^A-Za-z0-9_]/', '_', (string) $col))), $headerRow);
        $rows = [];

        foreach ($sheetData as $row) {
            // Skip completely empty rows
            $hasContent = false;
            foreach ($row as $v) {
                if (!is_null($v) && trim((string) $v) !== '') {
                    $hasContent = true;
                    break;
                }
            }
            if (!$hasContent) {
                continue;
            }

            if (count($row) === count($header)) {
                $rows[] = array_combine($header, array_map(fn($v) => is_null($v) ? '' : trim((string) $v), $row));
            }
        }

        return $rows;
    }

    /**
     * Export products as an Excel XLSX binary string.
     */
    public function exportXlsx(string $businessUuid, array $filters = []): string
    {
        $query = Product::where('business_uuid', $businessUuid)
            ->with(['category', 'brand', 'unit', 'currentPrice', 'codes']);

        if (!empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }
        if (!empty($filters['brand_id'])) {
            $query->where('brand_id', $filters['brand_id']);
        }
        if (isset($filters['is_active'])) {
            $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        $products = $query->orderBy('name')->get();

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Products Catalog');

        $headers = [
            'SKU', 'Name', 'Category', 'Brand', 'Unit', 
            'Selling Price', 'Cost Price', 'Currency', 'Barcode', 
            'Is Taxable', 'Track Inventory', 'Is Active', 'Description'
        ];

        $sheet->fromArray([$headers], null, 'A1');

        // Style header row with bold font and grey background
        $sheet->getStyle('A1:M1')->getFont()->setBold(true);
        $sheet->getStyle('A1:M1')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFEFEFEF');

        $rowIndex = 2;
        foreach ($products as $product) {
            $primaryBarcode = $product->codes->firstWhere('code_type', 'barcode')?->code_value
                ?? $product->codes->first()?->code_value ?? '';

            $sheet->fromArray([[
                $product->sku,
                $product->name,
                $product->category?->name ?? '',
                $product->brand?->name ?? '',
                $product->unit?->symbol ?? ($product->unit?->name ?? 'pcs'),
                $product->currentPrice?->selling_price ?? 0.00,
                $product->currentPrice?->cost_price ?? '',
                $product->currentPrice?->currency_code ?? 'USD',
                $primaryBarcode,
                $product->is_taxable ? '1' : '0',
                $product->track_inventory ? '1' : '0',
                $product->is_active ? '1' : '0',
                $product->description ?? '',
            ]], null, 'A' . $rowIndex);

            $rowIndex++;
        }

        // Auto-fit column widths
        foreach (range('A', 'M') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        ob_start();
        $writer->save('php://output');
        return ob_get_clean() ?: '';
    }

    /**
     * Parse CSV file or raw string into array of associative rows.
     */
    protected function parseCsv(UploadedFile|string $fileOrContent): array
    {
        if ($fileOrContent instanceof UploadedFile) {
            $handle = fopen($fileOrContent->getRealPath(), 'r');
        } elseif (is_string($fileOrContent) && @file_exists($fileOrContent)) {
            $handle = fopen($fileOrContent, 'r');
        } else {
            $handle = fopen('php://temp', 'r+');
            fwrite($handle, (string) $fileOrContent);
            rewind($handle);
        }

        if (! $handle) {
            return [];
        }

        $header = null;
        $rows = [];

        while (($fields = fgetcsv($handle)) !== false) {
            if (empty($fields) || (count($fields) === 1 && $fields[0] === null)) {
                continue;
            }

            if ($header === null) {
                // Normalize headers to lowercase snake_case
                $header = array_map(fn($col) => strtolower(trim(preg_replace('/[^A-Za-z0-9_]/', '_', (string) $col))), $fields);
                continue;
            }

            if (count($fields) === count($header)) {
                $rows[] = array_combine($header, array_map('trim', $fields));
            }
        }

        fclose($handle);

        return $rows;
    }

    /**
     * Validate CSV rows against required rules.
     */
    protected function validateRows(array $rows, string $businessUuid): array
    {
        $errors = [];
        $skusSeen = [];

        foreach ($rows as $index => $row) {
            $line = $index + 2;

            if (empty($row['name'])) {
                $errors[] = ['row' => $line, 'field' => 'name', 'message' => 'Product name is required.'];
            }

            if (empty($row['sku'])) {
                $errors[] = ['row' => $line, 'field' => 'sku', 'message' => 'Product SKU is required.'];
            } else {
                $sku = strtoupper($row['sku']);
                if (isset($skusSeen[$sku])) {
                    $errors[] = ['row' => $line, 'field' => 'sku', 'message' => "Duplicate SKU '{$sku}' found in CSV at row {$skusSeen[$sku]} and {$line}."];
                }
                $skusSeen[$sku] = $line;
            }

            if (isset($row['selling_price']) && $row['selling_price'] !== '') {
                if (!is_numeric($row['selling_price']) || (float) $row['selling_price'] < 0) {
                    $errors[] = ['row' => $line, 'field' => 'selling_price', 'message' => 'Selling price must be a non-negative number.'];
                }
            } else {
                $errors[] = ['row' => $line, 'field' => 'selling_price', 'message' => 'Selling price is required.'];
            }

            if (!empty($row['cost_price']) && (!is_numeric($row['cost_price']) || (float) $row['cost_price'] < 0)) {
                $errors[] = ['row' => $line, 'field' => 'cost_price', 'message' => 'Cost price must be a non-negative number.'];
            }
        }

        return $errors;
    }

    /**
     * Process a single CSV row into database models.
     */
    protected function processRow(array $row, string $businessUuid, ?string $userUuid): string
    {
        $category = null;
        if (!empty($row['category'])) {
            $category = Category::firstOrCreate([
                'business_uuid' => $businessUuid,
                'name' => $row['category'],
            ], [
                'code' => strtoupper(Str::slug($row['category'])),
                'is_active' => true,
            ]);
        }

        $brand = null;
        if (!empty($row['brand'])) {
            $brand = Brand::firstOrCreate([
                'business_uuid' => $businessUuid,
                'name' => $row['brand'],
            ], [
                'code' => strtoupper(Str::slug($row['brand'])),
                'is_active' => true,
            ]);
        }

        $unitSymbol = !empty($row['unit']) ? $row['unit'] : 'pcs';
        $unit = Unit::firstOrCreate([
            'business_uuid' => $businessUuid,
            'symbol' => $unitSymbol,
        ], [
            'name' => ucfirst($unitSymbol),
            'precision' => 0,
            'is_active' => true,
        ]);

        $productData = [
            'category_id' => $category?->id,
            'brand_id' => $brand?->id,
            'unit_id' => $unit->id,
            'name' => $row['name'],
            'description' => $row['description'] ?? null,
            'is_taxable' => isset($row['is_taxable']) ? filter_var($row['is_taxable'], FILTER_VALIDATE_BOOLEAN) : true,
            'track_inventory' => isset($row['track_inventory']) ? filter_var($row['track_inventory'], FILTER_VALIDATE_BOOLEAN) : true,
            'is_active' => isset($row['is_active']) ? filter_var($row['is_active'], FILTER_VALIDATE_BOOLEAN) : true,
            'updated_by_uuid' => $userUuid,
        ];

        $product = Product::where('business_uuid', $businessUuid)
            ->where('sku', $row['sku'])
            ->first();

        $action = 'created';
        if ($product) {
            $product->update($productData);
            $action = 'updated';
        } else {
            $product = Product::create(array_merge($productData, [
                'business_uuid' => $businessUuid,
                'sku' => $row['sku'],
                'created_by_uuid' => $userUuid,
            ]));
        }

        // Setup / Update price
        $sellingPrice = (float) $row['selling_price'];
        $costPrice = !empty($row['cost_price']) ? (float) $row['cost_price'] : null;
        $currencyCode = !empty($row['currency_code']) ? strtoupper($row['currency_code']) : 'USD';

        ProductPrice::updateOrCreate([
            'business_uuid' => $businessUuid,
            'product_id' => $product->id,
            'product_variant_id' => null,
            'currency_code' => $currencyCode,
        ], [
            'selling_price' => $sellingPrice,
            'cost_price' => $costPrice,
            'is_active' => true,
        ]);

        // Setup barcode if present
        $barcodeValue = !empty($row['barcode']) ? trim($row['barcode']) : $row['sku'];
        if ($barcodeValue !== '') {
            ProductCode::updateOrCreate([
                'business_uuid' => $businessUuid,
                'product_id' => $product->id,
                'product_variant_id' => null,
                'code_type' => 'barcode',
            ], [
                'symbology' => 'CODE128',
                'code_value' => $barcodeValue,
                'is_primary' => true,
                'is_active' => true,
            ]);
        }

        return $action;
    }
}
