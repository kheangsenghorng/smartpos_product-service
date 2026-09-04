<?php

namespace App\Jobs;

use App\Models\ProductReport;
use App\Services\ProductImportExportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class ImportProductsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 3600;

    public function __construct(
        public string $tempFilePath,
        public string $businessUuid,
        public ?string $userUuid = null,
        public ?ProductReport $report = null
    ) {}

    public function handle(ProductImportExportService $importService): void
    {
        @ini_set('memory_limit', '2048M');
        @set_time_limit(3600);

        if ($this->report) {
            $this->report->update(['status' => 'processing']);
        }

        try {
            $disk = config('filesystems.default', 'public');
            $content = Storage::disk($disk)->get($this->tempFilePath);

            if (!$content) {
                throw new \RuntimeException("Could not read uploaded import file at: {$this->tempFilePath}");
            }

            $result = $importService->import(
                fileOrContent: $content,
                businessUuid: $this->businessUuid,
                userUuid: $this->userUuid,
                validateOnly: false,
                progressCallback: function (int $percentage, int $processed, int $total) {
                    if ($this->report) {
                        $this->report->update([
                            'status' => 'processing',
                            'total_records' => $total,
                            'metadata' => array_merge($this->report->metadata ?? [], [
                                'progress_percentage' => $percentage,
                                'processed_records' => $processed,
                                'total_records' => $total,
                            ]),
                        ]);
                    }
                }
            );

            if ($this->report) {
                $status = $result['success'] ? 'completed' : 'failed';
                $this->report->update([
                    'status' => $status,
                    'total_records' => $result['total_rows'] ?? 0,
                    'metadata' => [
                        'progress_percentage' => 100,
                        'processed_records' => $result['total_rows'] ?? 0,
                        'total_records' => $result['total_rows'] ?? 0,
                        'imported_count' => $result['imported_count'] ?? 0,
                        'updated_count' => $result['updated_count'] ?? 0,
                        'failed_count' => $result['failed_count'] ?? 0,
                        'errors' => $result['errors'] ?? [],
                    ],
                    'error_message' => $result['success'] ? null : ($result['message'] ?? 'Import failed.'),
                    'completed_at' => now(),
                ]);
            }

            // Cleanup temp file
            Storage::disk($disk)->delete($this->tempFilePath);
        } catch (\Throwable $e) {
            if ($this->report) {
                $this->report->update([
                    'status' => 'failed',
                    'error_message' => $e->getMessage(),
                    'completed_at' => now(),
                ]);
            }
            throw $e;
        }
    }

    public function failed(?\Throwable $exception): void
    {
        if ($this->report) {
            $this->report->update([
                'status' => 'failed',
                'error_message' => $exception?->getMessage() ?? 'Job exceeded maximum attempts or timed out.',
                'completed_at' => now(),
            ]);
        }
    }
}
