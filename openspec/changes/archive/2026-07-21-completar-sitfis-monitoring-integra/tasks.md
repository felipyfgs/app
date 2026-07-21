## 1. N0 — Contrato API e config Integra

- [x] 1.1 Expor `evidence_artifact_id` e `links.evidence_download` em `SitfisSnapshotService::publicView`
- [x] 1.2 Registrar cenários Trial `sitfis.solicitar_protocolo` e `sitfis.emitir_relatorio` em `config/serpro.php` (identidades oficiais + `source_url`)
- [x] 1.3 Validar fixtures solicit/emit em `contract-fixtures` (protocolo + tempoEspera; pdf/`dados`) — ajustar só se o driver fixture não consumir campos necessários

## 2. N1 — Testes API

- [x] 2.1 Teste unitário das fases de `SitfisFlowService` (solicit → wait → emit 202 → emit 200)
  Depende de: 1.3
- [x] 2.2 Teste unitário de `SitfisReportParser` (layout conhecido vs desconhecido; sem certidão negativa)
- [x] 2.3 Feature test de `SitfisSituationController` (show com evidência; refresh WITHIN_TTL / ERROR / force)
  Depende de: 1.1
- [x] 2.4 Feature smoke de communication preference/preview para `module=sitfis` (fail-closed provider)

## 3. N1 — Cliente web tipado e comunicação

- [x] 3.1 Adicionar tipos `SitfisShowResponse` / `SitfisRefreshResponse` e bloco `sitfis.communication` em `createFiscalApi.ts`
  Depende de: 1.1
- [x] 3.2 Wire `sitfis.vue`: modais comunicação (padrão DCTF), switch/prefs, download de evidência no slideover
  Depende de: 3.1
- [x] 3.3 Testes unitários web de tipagem/handlers de comunicação SITFIS
  Depende de: 3.2

## 4. N2 — Gates integrados

- [x] 4.1 API: `vendor/bin/pint --test` + `php artisan test --filter=Sitfis`
  Depende de: 2.1, 2.2, 2.3, 2.4
- [x] 4.2 Web: `pnpm run lint` + `pnpm run typecheck` + `pnpm run test` (filtros sitfis/comunicação)
  Depende de: 3.3
- [x] 4.3 OpenSpec: `npx @fission-ai/openspec@1.6.0 validate --specs --strict` e validação da change ativa
  Depende de: 1.1
