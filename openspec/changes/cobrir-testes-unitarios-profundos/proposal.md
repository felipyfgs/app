## Why

A onda `cobrir-testes-unitarios-criticos` fechou inventário + smoke de superfície + fail-closed fino, mas o núcleo fiscal (SimplesMeiAdapter, mutações, pós-consulta PGMEI) e a jornada real (auth Integra → consult) ainda têm pouco unitário. Sem essa camada, regressões de carteira/MEI e `AUTHORIZATION_MISSING` vs caminho feliz escapam do CI.

## What Changes

- Entregar a onda **profundos (A+C)**: unitários de domínio Simples/MEI e política de mutação; Feature de consult PGMEI com Integra fake após autorização “pronta”; cobertura de pós-consulta/projector PGMEI.
- Incluir **Vault/`EnvelopeCrypto`** (fail-closed criptográfico) como parte desta change — risco alto e sem live SERPRO.
- Manter Playwright E2E e Sitfis/Eventos profundos como **Non-goals** (onda P3 futura).
- Reusar inventário/gates da change crítica; não duplicar L0–L1 de superfície.

## Capabilities

### New Capabilities

- `deep-fiscal-unit-coverage`: testes unitários/Feature profundos para SimplesMei (adapter/PGMEI pós-consulta), FiscalMutationPolicy, jornada PGMEI com Integra fake, e EnvelopeCrypto/Vault — sem egress real.

### Modified Capabilities

- (nenhuma em main specs; coordena com `surface-test-coverage` da change crítica)

## Impact

- API: novos testes Unit/Feature sob `apps/api/tests`; possível fixture Integra/PGMEI; sem mudança de produto salvo bug bloqueante.
- Web: só se necessário para jornada (preferir API); sem redesign.
- CI: filtros PHPUnit da onda; inventário crítico permanece fonte de totais.

### Dependências entre changes

- Nível: `C1`
- Bases estáveis: main specs vazias
- Depende de: `cobrir-testes-unitarios-criticos` (capability `surface-test-coverage`, marco `verify`, relação `coordenada` — preferir archive antes do apply desta)
- Capability/contrato: `deep-fiscal-unit-coverage` (nova)
- Marco exigido: verify (idealmente archive) da change crítica
- Relação: coordenada
- Desbloqueia: P3 Sitfis/Eventos/Playwright seletivo
- Paralelismo: não conflitar ownership com changes de produto SimplesMei ativas; testes apenas

### Non-goals

- Playwright no gate CI; live SERPRO/mei Compose
- Sitfis/Eventos flow completo; 1 teste por rota/página
- Ligar flags; mutações fiscais novas de produto
- Targets Make backup/restore/ops indisponíveis
