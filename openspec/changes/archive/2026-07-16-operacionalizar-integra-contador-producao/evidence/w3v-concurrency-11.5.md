# 11.5 Concurrency / infrastructure evidence (sanitized)

Date: 2026-07-16
Agent: W3-V

## PHPUnit (default: sqlite + CACHE_STORE=array)

Filter: `SerproConcurrencyInfrastructureTest|SerproOperationServiceTest|SerproBillingLimiterBreakerTest`

Results:
- idempotency unique on `serpro_operation_attempts` — PASS
- idempotency unique on `serpro_api_usage_reservations` — PASS
- rate limiter atomic with Redis when available — PASS (stack redis reachable; rebinds cache to redis for the test)
- postgres unique indexes in-process — SKIPPED (phpunit DB_CONNECTION=sqlite)

Also covered by existing unit suite:
- executor replay (no second HTTP) — PASS
- concurrent inflight wait/block — PASS
- rate limit atomic ceiling — PASS
- breaker half-open probe limit — PASS
- breaker durable state — PASS
- kill switch survives Cache::flush (DB durable) — PASS

## Live PostgreSQL inventory (dev stack, not phpunit)

Unique indexes required for SERPRO concurrency/idempotency (all present):
- serpro_operation_attempts_idempotency_key_unique — OK
- serpro_api_usage_reservations_idempotency_key_unique — OK
- serpro_api_usage_entries_idempotency_key_unique — OK
- serpro_circuit_breaker_states_scope_key_unique — OK
- office_serpro_auth_office_env_uq — OK

Total UNIQUE indexes matching serpro* tables/names: 59

Redis: PING → PONG (service healthy)

## Notes

- CI/phpunit remains sqlite+array for speed/isolation.
- Redis atomicity exercised when redis host is reachable from the php container.
- Full multi-process race under load is out of scope for this gate; unit+feature coverage demonstrates single-HTTP replay and unique index enforcement.
