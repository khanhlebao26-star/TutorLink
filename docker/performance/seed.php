<?php

use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\Support\ProgramApiFixtures;

require '/var/www/html/vendor/autoload.php';
require '/benchmark/ProgramApiFixtures.php';

$app = require '/var/www/html/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$identity = DB::selectOne('SELECT current_database() AS name, current_user AS actor');
if (
    config('database.connections.pgsql.host') !== 'db'
    || config('database.connections.pgsql.url')
    || $identity->name !== 'tutorlink_capacity'
    || $identity->actor !== 'capacity_runner'
    || DB::table('users')->exists()
) {
    throw new RuntimeException('Fixture seeding requires an empty, isolated tutorlink_capacity database.');
}

$count = filter_var(getenv('PERF_LISTINGS') ?: '100000', FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 100, 'max_range' => 1000000],
]);
if ($count === false) {
    throw new RuntimeException('PERF_LISTINGS must be between 100 and 1000000.');
}

function actor(string $role, int $index, string $password): User
{
    return User::forceCreate([
        'full_name' => ucfirst($role).' '.$index,
        'email' => "capacity-{$role}-{$index}@example.test",
        'password_hash' => $password,
        'email_verified_at' => now(),
        'role' => $role,
        'status' => 'active',
        'permissions' => [],
        'date_of_birth' => '1990-01-01',
    ]);
}

function credentials(User $actor, int $program): array
{
    $session = app('session')->driver();
    $session->setId(null);
    $session->setExists(false);
    $session->flush();
    $session->regenerateToken();
    $session->put(Auth::guard('web')->getName(), $actor->id);
    $token = $session->token();
    $session->save();
    $name = $session->getName();
    $encrypter = app('encrypter');
    $cookie = $encrypter->encrypt(CookieValuePrefix::create($name, $encrypter->getKey()).$session->getId(), false);

    return ['program' => $program, 'cookie' => $name.'='.rawurlencode($cookie), 'csrf' => $token];
}

$passwordHash = Hash::make(bin2hex(random_bytes(24)));
$fixture = ['listings' => $count, 'tutors' => [], 'customers' => [], 'owners' => []];
$tutorCount = 20;
for ($index = 0; $index < $tutorCount; $index++) {
    $tutor = actor('tutor', $index, $passwordHash);
    $profile = $tutor->tutorProfile()->create(['status' => 'approved', 'headline' => 'Capacity tutor']);
    ProgramApiFixtures::approveIdentity($tutor);
    $customer = actor('customer', $index, $passwordHash);
    $program = ProgramApiFixtures::draft($tutor, $customer, ['status' => 'active']);
    ProgramApiFixtures::addMember($program, $customer, 'customer');
    $fixture['tutors'][] = $profile->fresh()->public_slug;
    $fixture['customers'][] = credentials($customer, $program);
    $fixture['owners'][] = credentials($tutor, $program);
    $template = ProgramApiFixtures::listing($tutor);

    $rows = intdiv($count - $tutorCount + $index, $tutorCount);
    DB::insert(<<<'SQL'
        INSERT INTO service_listings (
            tutor_profile_id, specialization_id, slug, title, description, delivery_mode, city,
            district, price_from_minor, currency, price_unit, status, created_at, updated_at
        )
        SELECT template.tutor_profile_id, template.specialization_id, 'capacity-' || template.id || '-' || sample.n,
            CASE WHEN sample.n % 400 = 0 THEN 'Vietnamese calculus ' || sample.n
                 WHEN sample.n % 13 = 0 THEN 'English grammar ' || sample.n
                 ELSE 'Mathematics ' || sample.n END,
            CASE WHEN sample.n % 1100 = 0 THEN 'Vietnamese calculus and practice'
                 ELSE 'Individual practice ' || md5(sample.n::text) END,
            template.delivery_mode, template.city, template.district, 100000 + sample.n % 300 * 1000,
            template.currency, template.price_unit,
            CASE WHEN sample.n % 20 = 1 THEN 'inactive' ELSE 'active' END,
            CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
        FROM service_listings AS template CROSS JOIN generate_series(1, ?::integer) AS sample(n)
        WHERE template.id = ?
        SQL, [$rows, $template]);
}
DB::statement('CREATE EXTENSION IF NOT EXISTS pg_stat_statements');
DB::statement('ANALYZE');
umask(0077);
file_put_contents(storage_path('app/private/actors.json'), json_encode($fixture, JSON_THROW_ON_ERROR));
fwrite(STDOUT, "Seeded {$count} listings and {$tutorCount} isolated Tutor/Customer pairs.\n");
