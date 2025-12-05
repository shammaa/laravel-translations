<?php

declare(strict_types=1);

namespace Shammaa\LaravelTranslations\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BenchmarkCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'translations:benchmark 
                            {--count=100 : Number of items to test}
                            {--model= : Model class to test}';

    /**
     * The console command description.
     */
    protected $description = 'Benchmark translation performance (run tests to measure speed)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $count = (int) $this->option('count');
        $modelClass = $this->option('model');

        if (!$modelClass) {
            $this->error('Please specify a model class with --model option');
            return 1;
        }

        if (!class_exists($modelClass)) {
            $this->error("Model class {$modelClass} not found");
            return 1;
        }

        if (!method_exists($modelClass, 'translationStats')) {
            $this->error("Model {$modelClass} must use IsTranslatable trait");
            return 1;
        }

        $this->info("Running Translation Performance Benchmark");
        $this->info("Model: {$modelClass}");
        $this->info("Count: {$count}");
        $this->newLine();

        $results = [];

        // Test 1: Single item fetch
        $this->info("1️⃣ Testing single item fetch...");
        $singleItem = $modelClass::first();
        if ($singleItem) {
            $start = microtime(true);
            for ($i = 0; $i < 100; $i++) {
                $title = $singleItem->title;
            }
            $time = ((microtime(true) - $start) * 1000) / 100;
            $results['single_item'] = round($time, 2);
            $this->line("   [OK] Single item: {$results['single_item']}ms (average of 100 calls)");
        } else {
            $this->warn("   [WARN] No items found in database");
        }

        // Test 2: Bulk fetch with eager loading
        $this->info("2️⃣ Testing bulk fetch with eager loading...");
        $start = microtime(true);
        $items = $modelClass::withTranslations()->limit($count)->get();
        $loadTime = (microtime(true) - $start) * 1000;
        
        $start = microtime(true);
        foreach ($items as $item) {
            $title = $item->title;
            $slug = $item->slug;
        }
        $accessTime = (microtime(true) - $start) * 1000;
        
        $results['bulk_fetch'] = round($loadTime, 2);
        $results['bulk_access'] = round($accessTime, 2);
        $this->line("   [OK] Fetch {$count} items: {$results['bulk_fetch']}ms");
        $this->line("   [OK] Access all fields: {$results['bulk_access']}ms");
        $this->line("   [OK] Total: " . round($loadTime + $accessTime, 2) . "ms");

        // Test 3: Search query
        $this->info("3️⃣ Testing search query...");
        $firstItem = $modelClass::first();
        if ($firstItem && $firstItem->title) {
            $searchTerm = substr($firstItem->title, 0, 5);
            $start = microtime(true);
            $results_found = $modelClass::whereTranslationLike('title', $searchTerm)
                ->withTranslations()
                ->limit(50)
                ->get();
            $time = (microtime(true) - $start) * 1000;
            $results['search'] = round($time, 2);
            $this->line("   [OK] Search (found {$results_found->count()}): {$results['search']}ms");
        } else {
            $this->warn("   [WARN] Cannot test search - no items found");
        }

        // Test 4: View query performance
        $this->info("4️⃣ Testing view query performance...");
        $table = (new $modelClass)->getTable();
        $viewName = config('translations.translations_view', 'translations_view');
        
        $start = microtime(true);
        $viewResults = DB::table($table)
            ->join("{$viewName} as t", function($join) use ($table, $modelClass) {
                $join->on("{$table}.id", '=', 't.translatable_id')
                     ->where('t.translatable_type', '=', $modelClass)
                     ->where('t.locale', '=', app()->getLocale());
            })
            ->limit($count)
            ->select("{$table}.*", 't.title', 't.slug')
            ->get();
        $time = (microtime(true) - $start) * 1000;
        $results['view_query'] = round($time, 2);
        $this->line("   [OK] View query ({$count} items): {$results['view_query']}ms");

        // Test 5: Translation save
        $this->info("5️⃣ Testing translation save...");
        $testItem = $modelClass::first();
        if ($testItem) {
            $start = microtime(true);
            $testItem->translateTo([
                'title' => 'Benchmark Test ' . time(),
                'slug' => 'benchmark-test-' . time(),
            ])->save();
            $time = (microtime(true) - $start) * 1000;
            $results['save'] = round($time, 2);
            $this->line("   [OK] Save translation: {$results['save']}ms");
        } else {
            $this->warn("   [WARN] Cannot test save - no items found");
        }

        // Summary
        $this->newLine();
        $this->info("📊 Performance Summary:");
        $this->table(
            ['Operation', 'Time (ms)'],
            [
                ['Single Item Fetch', $results['single_item'] ?? 'N/A'],
                ['Bulk Fetch (' . $count . ' items)', $results['bulk_fetch'] ?? 'N/A'],
                ['Bulk Access', $results['bulk_access'] ?? 'N/A'],
                ['Search Query', $results['search'] ?? 'N/A'],
                ['View Query (' . $count . ' items)', $results['view_query'] ?? 'N/A'],
                ['Save Translation', $results['save'] ?? 'N/A'],
            ]
        );

        // Performance rating
        $this->newLine();
        $avgTime = array_sum(array_filter($results)) / count(array_filter($results));
        
        if ($avgTime < 50) {
            $this->info("[EXCELLENT] Performance! Average: " . round($avgTime, 2) . "ms");
        } elseif ($avgTime < 100) {
            $this->info("[GOOD] Performance! Average: " . round($avgTime, 2) . "ms");
        } else {
            $this->warn("[SLOW] Performance could be improved. Average: " . round($avgTime, 2) . "ms");
            $this->line("   Tip: Try enabling cache or using eager loading");
        }

        return 0;
    }
}

