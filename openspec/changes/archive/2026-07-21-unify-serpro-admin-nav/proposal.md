## Why

O Admin voltou a listar três atalhos SERPRO no sidebar (`Operação`, `Integração`, `Canário DTE`). Para configurar e operar o console basta um único destino; as três superfícies já têm rotas internas e devem viver como abas do shell `/admin/serpro`, não como irmãos de Escritórios/Módulos.

## What Changes

- Sidebar Admin: um único item **SERPRO** apontando para `/admin/serpro`, ativo em qualquer `/admin/serpro/*`.
- Shell `admin/serpro.vue`: navegação contextual (Operação / Integração / Canário DTE) via `SectionNavigation` + `SERPRO_NAV_ITEMS`.
- Atualizar testes de navegação e o contrato da capability `platform-admin-navigation`.

## Capabilities

### New Capabilities

- (nenhuma)

### Modified Capabilities

- `platform-admin-navigation`: Admin expõe um único atalho SERPRO no sidebar; Operação/Integração/Canário passam a ser abas do console.

## Impact

- Web: `navigation.ts`, `admin/serpro.vue`, `navigation.test.ts` (e gates web da área).
- Rotas `/admin/serpro/*` inalteradas; só muda descoberta no menu e chrome interno.
- API / Compose / flags: sem mudança.

### Dependências entre changes

- Nível: `C1`
- Bases estáveis: main specs vazias; archive fora do DAG
- Depende de: `restore-serpro-admin-nav` (capability `platform-admin-navigation`, marco `apply`, relação `bloqueante` — reutiliza o atalho SERPRO já reintroduzido)
- Capability/contrato: `platform-admin-navigation` (MODIFIED)
- Desbloqueia: nenhuma
- Paralelismo: não paralelizar com `restore-serpro-admin-nav` (mesmo ownership de nav Admin)

### Non-goals

- Colapsar Status/Consumo/Liberação ou Acesso/Contratos/Cobertura (continuam tabs locais nas páginas)
- APIs, credenciais, kill switch, bilhetagem SERPRO
- Ligar flags SERPRO/MEI/SEFAZ
- Serviços `mei`/`mei-worker` no Compose
- Targets Make de backup/restore/ops indisponíveis
