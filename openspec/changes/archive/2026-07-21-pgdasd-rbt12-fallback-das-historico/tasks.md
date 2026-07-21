## 1. Descoberta + reserva (N0)

- [x] 1.1 Spike: artefato local da declaração do PA Sem DAS (ex. Eliane) — confirma se RBT12 está no PDF da declaração e se o parser atual serve
- [x] 1.2 Ajustar `PgdasdRbt12Service::reserveFromOperations` para, sem DAS no PA esperado, reservar a partir da declaração do mesmo PA (não DAS histórico; não `NO_DAS` cego)
- [x] 1.3 Pipeline de parse/resolve a partir do artefato da declaração (reuso ou variante do parser)

## 2. Testes e gates (N1)

- [x] 2.1 Testes unitários: PA sem DAS + declaração → reserva/parse; sem declaração → sem inventar
- [x] 2.2 `php artisan test` no foco + `openspec validate` da change
