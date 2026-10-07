<?php

namespace App\Console\Commands;

use App\Models\Category;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SeedCategoriesCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'categories:seed 
                            {--count=100000 : Total number of categories to generate}
                            {--business= : Target business UUID}
                            {--chunk=2000 : Batch size for database inserts}
                            {--prefix=CAT : Category code prefix}
                            {--tree : Create a parent-child category tree hierarchy}';

    /**
     * The console command description.
     */
    protected $description = 'High-performance bulk seeder to generate 100,000+ realistic categories with optional hierarchy';

    /**
     * Departments / Primary Sectors for realistic naming.
     */
    protected array $departments = [
        'Beverages', 'Bakery', 'Dairy & Eggs', 'Fresh Produce', 'Meat & Poultry',
        'Seafood', 'Pantry & Dry Goods', 'Frozen Foods', 'Snacks & Confectionery',
        'Condiments & Spices', 'Prepared Foods', 'Deli & Charcuterie', 'Breakfast & Cereal',
        'Coffee & Tea', 'Canned Foods', 'Pasta & Grains', 'Organic & Gluten-Free',
        'Health & Beauty', 'Personal Care', 'Skincare & Cosmetics', 'Hair Care',
        'Oral Care', 'Vitamins & Supplements', 'Baby Essentials', 'Cleaning Supplies',
        'Household Paper', 'Laundry Care', 'Pet Food & Supplies', 'Home & Living',
        'Kitchenware & Dining', 'Bedding & Bath', 'Home Organization', 'Outdoor & Garden',
        'Hardware & Tools', 'Electronics & Gadgets', 'Mobile Accessories', 'Audio & Video',
        'Computer & Office', 'Stationery & Crafts', 'Books & Magazines', 'Apparel & Fashion',
        'Men Clothing', 'Women Clothing', 'Kids & Toddlers', 'Footwear & Shoes',
        'Bags & Luggage', 'Jewelry & Watches', 'Sports & Fitness', 'Camping & Hiking',
        'Automotive Supplies'
    ];

    /**
     * Qualifiers / Subtypes.
     */
    protected array $qualifiers = [
        'Classic', 'Premium', 'Artisanal', 'Organic', 'Everyday',
        'Gourmet', 'Specialty', 'Eco-Friendly', 'Imported', 'Local Farm',
        'Instant', 'Handcrafted', 'Deluxe', 'Essential', 'Select',
        'Signature', 'Pro Series', 'Vintage', 'Modern', 'Compact',
        'Ultra', 'Botanical', 'Herbal', 'Natural', 'Whole Grain'
    ];

    /**
     * Segment descriptors.
     */
    protected array $segments = [
        'Collection', 'Selection', 'Assortment', 'Essentials', 'Picks',
        'Favorites', 'Varieties', 'Supplies', 'Goods', 'Packs',
        'Kits', 'Blends', 'Classics', 'Editions', 'Items'
    ];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $count = (int) $this->option('count');
        if ($count <= 0) {
            $this->error('The --count option must be a positive integer.');
            return Command::FAILURE;
        }

        $chunkSize = max(100, min(5000, (int) $this->option('chunk')));
        $prefix = strtoupper(trim((string) $this->option('prefix')) ?: 'CAT');
        $withTree = (bool) $this->option('tree');

        // Resolve target business UUID
        $businessUuid = $this->option('business')
            ?: Category::whereNotNull('business_uuid')->value('business_uuid')
            ?: config('app.default_business_uuid', '01a10b77-7409-72e5-a47c-3913ca999eb8');

        $this->info("=====================================================");
        $this->info(" SmartPOS High-Performance Category Seeder");
        $this->info("=====================================================");
        $this->info(" Target Count   : " . number_format($count) . " categories");
        $this->info(" Chunk Size     : " . number_format($chunkSize) . " per batch");
        $this->info(" Business UUID  : {$businessUuid}");
        $this->info(" Code Prefix    : {$prefix}-XXXXXX");
        $this->info(" Tree Hierarchy : " . ($withTree ? 'Enabled (30% root, 70% subcategories)' : 'Flat Catalog'));
        $this->info("=====================================================");

        // Disable query log to prevent memory leaks over large datasets
        DB::disableQueryLog();

        // Determine starting index based on existing codes for this business to avoid unique key collisions
        $lastCode = DB::table('categories')
            ->where('business_uuid', $businessUuid)
            ->where('code', 'like', "{$prefix}-%")
            ->orderByDesc('id')
            ->value('code');

        $startIndex = 1;
        if ($lastCode && preg_match('/' . preg_quote($prefix, '/') . '-(\d+)/', $lastCode, $matches)) {
            $startIndex = ((int) $matches[1]) + 1;
            $this->comment(" Detected existing {$prefix} codes. Starting from index: " . number_format($startIndex));
        }

        $startTime = microtime(true);
        $totalInserted = 0;
        $bar = $this->output->createProgressBar($count);
        $bar->setFormat(" %current%/%max% [%bar%] %percent:3s%% | Elapsed: %elapsed:6s% | Memory: %memory:6s%");
        $bar->start();

        // 1. If hierarchy requested, create a pool of root parent categories first
        $rootParentIds = [];
        if ($withTree) {
            $rootCount = min($count, min(500, max(5, (int) ($count * 0.05))));
            $rootBatch = [];
            $now = now()->toDateTimeString();

            for ($r = 0; $r < $rootCount; $r++) {
                $idx = $startIndex + $r;
                $dept = $this->departments[$r % count($this->departments)];
                $qualifier = $this->qualifiers[($r * 3) % count($this->qualifiers)];

                $rootBatch[] = [
                    'uuid' => (string) Str::uuid(),
                    'business_uuid' => $businessUuid,
                    'parent_id' => null,
                    'name' => "{$qualifier} {$dept}",
                    'code' => sprintf('%s-%06d', $prefix, $idx),
                    'description' => "Root category for {$qualifier} {$dept} and related items.",
                    'image_path' => null,
                    'sort_order' => $r,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            DB::table('categories')->insert($rootBatch);
            $totalInserted += count($rootBatch);
            $startIndex += $rootCount;
            $bar->advance($rootCount);

            // Fetch newly inserted root IDs
            $rootParentIds = DB::table('categories')
                ->where('business_uuid', $businessUuid)
                ->whereNull('parent_id')
                ->pluck('id')
                ->all();
        }

        // 2. Stream and batch insert remaining categories
        $remainingCount = $count - $totalInserted;
        $deptCount = count($this->departments);
        $qualCount = count($this->qualifiers);
        $segCount = count($this->segments);
        $rootPoolCount = count($rootParentIds);

        $now = now()->toDateTimeString();
        $batch = [];

        for ($i = 0; $i < $remainingCount; $i++) {
            $seq = $startIndex + $i;
            $dept = $this->departments[$i % $deptCount];
            $qualifier = $this->qualifiers[($i * 7) % $qualCount];
            $segment = $this->segments[($i * 13) % $segCount];

            // 70% subcategories if tree enabled and root pool exists, otherwise root
            $parentId = null;
            if ($withTree && $rootPoolCount > 0 && ($i % 10) >= 3) {
                $parentId = $rootParentIds[$i % $rootPoolCount];
            }

            $batch[] = [
                'uuid' => (string) Str::uuid(),
                'business_uuid' => $businessUuid,
                'parent_id' => $parentId,
                'name' => "{$qualifier} {$dept} {$segment} #" . ($i + 1),
                'code' => sprintf('%s-%06d', $prefix, $seq),
                'description' => "Catalog item classification for {$dept} - {$segment}.",
                'image_path' => null,
                'sort_order' => $i % 100,
                'is_active' => ($i % 25) !== 0, // 96% active, 4% inactive for realistic filtering
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (count($batch) >= $chunkSize) {
                DB::table('categories')->insert($batch);
                $totalInserted += count($batch);
                $bar->advance(count($batch));
                $batch = [];
            }
        }

        // Flush trailing records
        if (!empty($batch)) {
            DB::table('categories')->insert($batch);
            $totalInserted += count($batch);
            $bar->advance(count($batch));
            $batch = [];
        }

        $bar->finish();
        $this->newLine(2);

        $duration = round(microtime(true) - $startTime, 2);
        $rate = $duration > 0 ? round($totalInserted / $duration) : $totalInserted;

        $this->info("✅ Successfully seeded " . number_format($totalInserted) . " categories!");
        $this->info("⏱️ Time taken: {$duration}s (~" . number_format($rate) . " categories/sec)");
        $this->info("📊 Total categories now in database: " . number_format(DB::table('categories')->count()));

        return Command::SUCCESS;
    }
}
