<?php

namespace Tests\Feature;

use App\Http\Middleware\MarketplaceCacheControl;
use App\Support\PublicCatalogCache;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\Support\PostgresConnections;
use Tests\Support\ProgramApiFixtures;
use Tests\TestCase;

class MarketplaceCacheTransactionTest extends TestCase
{
    use DatabaseTruncation;

    public function test_other_connections_use_the_old_cache_until_commit_then_immediately_deny_hidden_data(): void
    {
        $tutor = ProgramApiFixtures::eligibleTutor();
        $id = ProgramApiFixtures::listing($tutor);
        DB::table('service_listings')->where('id', $id)->update(['status' => 'active']);
        $url = '/api/v1/listings/'.DB::table('service_listings')->where('id', $id)->value('slug');
        $this->getJson($url)->assertOk();
        $this->getJson($url)->assertHeader('X-Marketplace-Cache', 'HIT');
        $default = DB::getDefaultConnection();
        $writer = DB::connection();
        PostgresConnections::run(function ($reader) use ($default, $writer, $tutor, $url): void {
            $writer->beginTransaction();
            try {
                $writer->table('users')->where('id', $tutor->id)->update(['email_verified_at' => null]);
                DB::setDefaultConnection($reader->getName());
                $this->getJson($url)->assertOk()->assertHeader('X-Marketplace-Cache', 'HIT');
                $writer->commit();
                $this->getJson($url)->assertNotFound()->assertHeader('Cache-Control', 'no-store, private');
            } finally {
                DB::setDefaultConnection($default);
                if ($writer->transactionLevel() > 0) {
                    $writer->rollBack();
                }
            }
        });
    }

    public function test_catalog_uses_longer_ttl_and_does_not_scan_document_expiry(): void
    {
        $cache = app(PublicCatalogCache::class);
        $request = Request::create('/api/v1/catalog/categories');
        $this->assertSame(300, $cache->ttl($request));
        $this->assertSame(60, $cache->ttl(Request::create('/api/v1/listings')));
        DB::enableQueryLog();
        $cache->key($request);
        $queries = DB::getQueryLog();
        DB::disableQueryLog();
        $this->assertCount(1, $queries);
        $this->assertStringNotContainsString('verification_documents', $queries[0]['query']);
    }

    public function test_rebuild_lock_is_released_on_controller_error(): void
    {
        $request = Request::create('/api/v1/catalog/categories');
        $key = app(PublicCatalogCache::class)->key($request);
        Cache::forget($key);
        try {
            app(MarketplaceCacheControl::class)->handle($request, fn () => throw new \RuntimeException('controller failed'));
        } catch (\RuntimeException $error) {
            $this->assertSame('controller failed', $error->getMessage());
        }
        $lock = Cache::lock($key.':build', 15);
        $this->assertTrue($lock->get());
        $lock->release();
    }
}
