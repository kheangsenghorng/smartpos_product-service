<?php

namespace App\Jobs;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class PruneOldTrashJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 180;

    public function __construct(
        public int $days = 30
    ) {}

    public function handle(): void
    {
        $cutoffDate = now()->subDays($this->days);

        // 1. Permanently delete soft-deleted products older than cutoff
        $deletedProducts = Product::onlyTrashed()
            ->where('deleted_at', '<', $cutoffDate)
            ->forceDelete();

        // 2. Permanently delete soft-deleted categories older than cutoff
        $deletedCategories = Category::onlyTrashed()
            ->where('deleted_at', '<', $cutoffDate)
            ->forceDelete();

        Log::info("PruneOldTrashJob completed: {$deletedProducts} product(s) and {$deletedCategories} category(ies) permanently purged (older than {$this->days} days).");
    }
}
