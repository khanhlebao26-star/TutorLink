<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\Support\ProgramApiFixtures;
use Tests\TestCase;

class MarketplaceBenchmarkTest extends TestCase
{
    use RefreshDatabase;

    public function test_marketplace_queries_on_five_thousand_listings(): void
    {
        $first = null;
        for ($i = 0; $i < 20; $i++) {
            $tutor = ProgramApiFixtures::eligibleTutor();
            $first ??= $tutor;
            $id = ProgramApiFixtures::listing($tutor);
            DB::table('service_listings')->where('id', $id)->update(['status' => 'active']);
            $template = (array) DB::table('service_listings')->find($id);
            unset($template['id']);
            $rows = [];
            for ($j = 1; $j < 250; $j++) {
                $rows[] = [...$template, 'slug' => "benchmark-{$i}-{$j}", 'title' => "Benchmark English {$j}", 'price_from_minor' => 100000 + $j];
            }
            DB::table('service_listings')->insert($rows);
        }
        DB::statement('ANALYZE service_listings');
        $report = ['dataset' => ['tutors' => 20, 'listings' => 5000], 'postgres' => DB::selectOne('select version()')->version];
        foreach (['listings' => '/api/v1/listings', 'tutor' => '/api/v1/tutors/'.$first->tutorProfile->public_slug, 'catalog' => '/api/v1/catalog/categories'] as $name => $url) {
            Cache::flush();
            $samples = [];
            for ($n = 0; $n < 21; $n++) {
                DB::flushQueryLog();
                DB::enableQueryLog();
                $start = hrtime(true);
                $response = $this->getJson($url)->assertOk();
                $ms = (hrtime(true) - $start) / 1e6;
                $queries = DB::getQueryLog();
                DB::disableQueryLog();
                if ($n === 0) {
                    $report[$name]['cold'] = ['queries' => count($queries), 'ms' => round($ms, 3), 'bytes' => strlen($response->getContent())];
                    foreach ($queries as $query) {
                        if (str_contains($query['query'], 'select') && str_contains($query['query'], '"service_listings"') && ! str_contains($query['query'], 'count(*)')) {
                            $report[$name]['plan'] = DB::select('EXPLAIN (ANALYZE, BUFFERS, FORMAT JSON) '.$query['query'], $query['bindings']);
                            break;
                        }
                    }
                } else {
                    $samples[] = $ms;
                    $report[$name]['warm_queries'] = count($queries);
                }
            }
            sort($samples);
            $report[$name]['warm_median_ms'] = round(($samples[9] + $samples[10]) / 2, 3);
            $report[$name]['warm_p95_ms'] = round($samples[18], 3);
        }
        $this->assertDatabaseCount('service_listings', 5000);
        if ($output = getenv('PHASE5_BENCHMARK_OUTPUT')) {
            file_put_contents($output, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        }
    }
}
