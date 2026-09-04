<?php

namespace App\Jobs;

use App\Models\ProductReport;
use App\Services\ProductReportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateProductReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 180;

    public function __construct(
        public ProductReport $report
    ) {}

    public function handle(ProductReportService $reportService): void
    {
        $reportService->processReport($this->report);
    }
}
