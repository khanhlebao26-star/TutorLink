<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTruncation;
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
}
