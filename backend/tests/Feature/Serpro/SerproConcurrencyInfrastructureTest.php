<?php

namespace Tests\Feature\Serpro;

use App\Enums\SerproAttemptState;
use App\Models\Client;
use App\Models\Office;
use App\Models\SerproOperationAttempt;
use App\Services\Serpro\SerproCircuitBreaker;
use App\Services\Serpro\SerproRateLimiter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;
use Throwable;

/**
 * Concorrência / índices de idempotência (task 11.5).
 *
 * - SQLite (CI default): unique indexes + replay/inflight semântica.
 * - Redis (quando reachable no runtime): limiter, Cache::lock e circuit breaker.
 * - PostgreSQL: unique indexes via app pgsql **ou** PDO de leitura no host de stack
 *   (phpunit força sqlite/:memory:; no docker DB_HOST=postgres ainda é visível).
 */
final class SerproConcurrencyInfrastructureTest extends TestCase
{
    use RefreshDatabase;

    public function test_idempotency_key_unique_em_operation_attempts(): void
    {
        $this->assertTrue(Schema::hasTable('serpro_operation_attempts'));

        $office = Office::factory()->create();
        $client = Client::factory()->forOffice($office)->create();
        $key = 'ic|TRIAL|'.$office->id.'|sitfis.solicitar_protocolo|client:'.$client->id.'|uq-1';

        $payload = [
            'office_id' => $office->id,
            'environment' => 'TRIAL',
            'operation_key' => 'sitfis.solicitar_protocolo',
            'entity_key' => 'client:'.$client->id,
            'idempotency_key' => $key,
            'request_tag' => 'ic'.substr(hash('sha256', $key), 0, 30),
            'correlation_id' => 'corr-uq-1',
            'attempt_state' => SerproAttemptState::Acknowledged,
            'client_id' => $client->id,
            'reserved_at' => now(),
            'dispatched_at' => now(),
            'finished_at' => now(),
        ];

        SerproOperationAttempt::query()->create($payload);

        $this->expectException(Throwable::class);
        SerproOperationAttempt::query()->create(array_merge($payload, [
            'correlation_id' => 'corr-uq-2',
            'request_tag' => 'ic'.substr(hash('sha256', $key.'b'), 0, 30),
        ]));
    }

    public function test_usage_reservation_idempotency_unique(): void
    {
        if (! Schema::hasTable('serpro_api_usage_reservations')) {
            $this->markTestSkipped('Tabela serpro_api_usage_reservations ausente.');
        }

        $office = Office::factory()->create();
        $row = [
            'office_id' => $office->id,
            'system_code' => 'SITFIS',
            'service_code' => 'SITFIS',
            'operation_code' => 'CONSULTAR_SITUACAO',
            'consumption_class' => 'BILLABLE',
            'idempotency_key' => 'res-uq-'.uniqid(),
            'status' => 'RESERVED',
            'estimated_cost_micros' => 1000,
            'reserved_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ];

        // Colunas opcionais conforme migrations evolutivas
        $columns = Schema::getColumnListing('serpro_api_usage_reservations');
        $insert = array_intersect_key($row, array_flip($columns));
        DB::table('serpro_api_usage_reservations')->insert($insert);

        $this->expectException(Throwable::class);
        DB::table('serpro_api_usage_reservations')->insert($insert);
    }

    public function test_rate_limiter_atomico_com_redis_quando_disponivel(): void
    {
        if (! $this->redisAvailable()) {
            $this->markTestSkipped(
                'Redis indisponível no runtime de teste (phpunit default CACHE_STORE=array). '
                .'Rode com Redis reachable (stack docker) para validar atomicidade real.'
            );
        }

        $previous = config('cache.default');
        config(['cache.default' => 'redis']);
        Cache::setDefaultDriver('redis');
        Cache::flush();

        try {
            config([
                'serpro.rate_limit.global_per_minute' => 3,
                'serpro.rate_limit.per_office_per_minute' => 0,
                'serpro.rate_limit.default_operation_per_minute' => 0,
                'serpro_usage.rate_limit_version' => 'conc-redis-'.uniqid(),
            ]);

            $limiter = app(SerproRateLimiter::class);
            $limiter->attempt(99, 'OP_REDIS', productiveEgress: false);
            $limiter->attempt(99, 'OP_REDIS', productiveEgress: false);
            $limiter->attempt(99, 'OP_REDIS', productiveEgress: false);

            $blocked = false;
            try {
                $limiter->attempt(99, 'OP_REDIS', productiveEgress: false);
            } catch (RuntimeException $e) {
                $blocked = str_contains($e->getMessage(), 'limite global');
            }
            $this->assertTrue($blocked, '4ª tentativa deve ser bloqueada pelo limiter Redis');
        } finally {
            Cache::flush();
            config(['cache.default' => $previous]);
            Cache::setDefaultDriver($previous);
        }
    }

    public function test_postgres_unique_indexes_serpro_quando_pgsql(): void
    {
        $names = $this->serproUniqueIndexNames();
        if ($names === null) {
            $this->markTestSkipped(
                'Postgres indisponível (phpunit default DB_CONNECTION=sqlite). '
                .'Com stack docker (DB_HOST=postgres), este teste valida índices UNIQUE via PDO de leitura.'
            );
        }

        $this->assertContains('serpro_operation_attempts_idempotency_key_unique', $names);
        $this->assertContains('serpro_api_usage_reservations_idempotency_key_unique', $names);
        $this->assertContains('serpro_circuit_breaker_states_scope_key_unique', $names);
        $this->assertContains('serpro_api_usage_entries_idempotency_key_unique', $names);
        $this->assertContains('office_serpro_auth_office_env_uq', $names);
    }

    public function test_cache_lock_atomico_com_redis_quando_disponivel(): void
    {
        if (! $this->redisAvailable()) {
            $this->markTestSkipped('Redis indisponível — lock atômico SERPRO (oauth/lifecycle) não exercitado.');
        }

        $previous = config('cache.default');
        config(['cache.default' => 'redis']);
        Cache::setDefaultDriver('redis');

        $name = 'serpro.conc.lock.'.uniqid('', true);
        $first = Cache::lock($name, 10);
        $this->assertTrue($first->get(), 'primeiro owner deve adquirir o lock');

        $second = Cache::lock($name, 10);
        $this->assertFalse($second->get(), 'segundo owner deve falhar enquanto lock está ativo');

        $first->release();
        $this->assertTrue($second->get(), 'após release o segundo owner adquire o lock');
        $second->release();

        config(['cache.default' => $previous]);
        Cache::setDefaultDriver($previous);
    }

    public function test_circuit_breaker_com_redis_quando_disponivel(): void
    {
        if (! $this->redisAvailable()) {
            $this->markTestSkipped('Redis indisponível — breaker SERPRO com cache redis não exercitado.');
        }

        $previous = config('cache.default');
        config([
            'cache.default' => 'redis',
            'serpro.circuit_breaker.failure_threshold' => 1,
            'serpro.circuit_breaker.open_seconds' => 60,
        ]);
        Cache::setDefaultDriver('redis');

        try {
            $scope = 'CONC_'.strtoupper(substr(uniqid(), -6));
            $breaker = app(SerproCircuitBreaker::class);
            $this->assertTrue($breaker->isCallAllowed($scope));
            $breaker->recordFailure($scope, '5xx', technicalFailure: true);
            $this->assertSame('open', $breaker->solutionStatus($scope)['state']);
            $this->assertFalse($breaker->isCallAllowed($scope));
        } finally {
            config(['cache.default' => $previous]);
            Cache::setDefaultDriver($previous);
        }
    }

    private function redisAvailable(): bool
    {
        try {
            $host = (string) config('database.redis.default.host', '127.0.0.1');
            $port = (int) config('database.redis.default.port', 6379);
            $errno = 0;
            $errstr = '';
            $fp = @fsockopen($host, $port, $errno, $errstr, 0.3);
            if ($fp === false) {
                return false;
            }
            fclose($fp);
            Redis::connection()->ping();

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Nomes de índices UNIQUE SERPRO no catálogo Postgres.
     * Preferência: conexão app se pgsql; senão PDO de leitura no host de stack (docker).
     *
     * @return list<string>|null
     */
    private function serproUniqueIndexNames(): ?array
    {
        $sql = <<<'SQL'
            SELECT indexname
            FROM pg_indexes
            WHERE schemaname = 'public'
              AND indexdef ILIKE '%UNIQUE%'
              AND indexname IN (
                'serpro_operation_attempts_idempotency_key_unique',
                'serpro_api_usage_reservations_idempotency_key_unique',
                'serpro_api_usage_entries_idempotency_key_unique',
                'serpro_circuit_breaker_states_scope_key_unique',
                'office_serpro_auth_office_env_uq'
              )
            ORDER BY indexname
            SQL;

        if (DB::getDriverName() === 'pgsql') {
            $rows = DB::select($sql);

            return array_map(static fn ($r) => (string) $r->indexname, $rows);
        }

        // phpunit força DB_CONNECTION=sqlite e DB_DATABASE=:memory:; no container
        // da stack o host real ainda vem de getenv('DB_HOST') (não forçado).
        // dbname/user caem nos defaults do compose — somente SELECT em pg_indexes.
        $host = (string) (getenv('DB_HOST') ?: '');
        $port = (int) (getenv('DB_PORT') ?: 5432);
        $database = (string) (getenv('DB_DATABASE') ?: 'nfse');
        if ($database === '' || $database === ':memory:') {
            $database = 'nfse';
        }
        $username = (string) (getenv('DB_USERNAME') ?: 'nfse');
        if ($username === '') {
            $username = 'nfse';
        }
        $password = (string) (getenv('DB_PASSWORD') ?: '');

        if ($host === '') {
            return null;
        }

        try {
            $errno = 0;
            $errstr = '';
            $fp = @fsockopen($host, $port, $errno, $errstr, 0.3);
            if ($fp === false) {
                return null;
            }
            fclose($fp);

            $dsn = sprintf('pgsql:host=%s;port=%d;dbname=%s', $host, $port, $database);
            $pdo = new \PDO($dsn, $username, $password, [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_TIMEOUT => 2,
            ]);
            $stmt = $pdo->query($sql);
            $rows = $stmt === false ? [] : $stmt->fetchAll(\PDO::FETCH_ASSOC);

            return array_map(static fn (array $r) => (string) $r['indexname'], $rows);
        } catch (Throwable) {
            return null;
        }
    }
}
