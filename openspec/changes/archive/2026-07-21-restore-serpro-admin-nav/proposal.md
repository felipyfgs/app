## Why

O console de configuração SERPRO (`/admin/serpro/*`) é obrigatório para o Proprietário cadastrar/ativar credenciais, mas o commit `b1cd468` removeu os atalhos do sidebar Admin. Sem o link, o PLATFORM_ADMIN só encontra Escritórios e Módulos fiscais e não consegue chegar à página de configuração pelo menu.

## What Changes

- Restaurar os destinos SERPRO (`SERPRO_NAV_ITEMS`) no grupo Admin da sidebar para usuários com acesso de plataforma.
- Labels no padrão anterior: `SERPRO · Operação`, `SERPRO · Integração`, `SERPRO · Canário DTE` (ou equivalente derivado do catálogo).
- Atualizar o teste de navegação que cobria a presença desses itens.

## Capabilities

### New Capabilities

- `platform-admin-navigation`: contrato da sidebar Admin para PLATFORM_ADMIN, incluindo atalhos do console SERPRO além de Escritórios e Módulos fiscais.

### Modified Capabilities

- (nenhuma — `openspec/specs/` está vazio)

## Impact

- Web: `apps/web/app/utils/navigation.ts`, `apps/web/tests/unit/navigation.test.ts` (e gates web da área).
- API / Compose / flags: sem mudança.
- Páginas `/admin/serpro/*` já existem; apenas descoberta via menu.

### Dependências entre changes

- Nível: `C0`
- Bases estáveis: main specs vazias; archive fora do DAG
- Depende de: nenhuma
- Capability/contrato: `platform-admin-navigation` (nova)
- Marco exigido: n/a
- Relação: n/a
- Desbloqueia: nenhuma change ativa
- Paralelismo: independente das changes ativas (ownership só em navegação Admin)

### Non-goals

- Alterar fluxos/APIs de credenciais, contratos, kill switch ou bilhetagem SERPRO
- Ligar feature flags SERPRO/MEI/SEFAZ ou mutações fiscais
- Serviços `mei`/`mei-worker` no Compose
- Targets Make de backup/restore/ops indisponíveis
- Redesign do shell Admin além de repor os links
