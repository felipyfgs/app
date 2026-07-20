## Context

O console SERPRO do Proprietário (`/admin/serpro`, `/admin/serpro/configuration`, `/admin/serpro/dte-canary`) já existe e funciona por URL. O catálogo `SERPRO_NAV_ITEMS` em `serpro-navigation.ts` permanece válido. O commit `b1cd468` removeu o spread desses itens de `platformAdminDestinations`, deixando o sidebar Admin só com Escritórios e Módulos fiscais — o PLATFORM_ADMIN não encontra a tela de configuração pelo menu.

## Goals / Non-Goals

**Goals:**

- Restaurar os atalhos SERPRO no grupo Admin da sidebar para `canAccessPlatformAdmin`.
- Reutilizar `SERPRO_NAV_ITEMS` (fonte única) com labels `SERPRO · {label}`.
- Atualizar o teste unitário de navegação que hoje exige a ausência desses itens.

**Non-Goals:**

- Mudar APIs, credenciais, contratos, kill switch ou bilhetagem SERPRO.
- Ligar flags SERPRO/MEI/SEFAZ.
- Redesign do shell além de repor os links.
- Serviços `mei`/`mei-worker` no Compose.

## Decisions

### 1. Restaurar via `SERPRO_NAV_ITEMS`, sem inventar destinos

- **Escolha:** reimportar `SERPRO_NAV_ITEMS` + helpers `groupEntryTo` / `isNavTabGroup` e mapear como antes do `b1cd468`.
- **Por quê:** o catálogo e as rotas já existem; evita drift de labels/paths.
- **Alternativa:** um único link “SERPRO” — rejeitada; o console tem três superfícies (Operação / Integração / Canário).

### 2. Ordem: Escritórios → Módulos fiscais → SERPRO

- **Escolha:** manter Escritórios e Módulos fiscais primeiro; SERPRO depois.
- **Por quê:** preserva a ordem já usada antes da remoção parcial do grupo e a governança fiscal neutra no topo.

### 3. Sem feature flag

- **Escolha:** visível para todo PLATFORM_ADMIN assim que o frontend subir.
- **Por quê:** é descoberta de superfície já autorizada; não é experimento.

## Risks / Trade-offs

- [Sidebar Admin fica mais longa] → Mitigação aceita: três itens SERPRO já eram o contrato anterior e são necessários para configurar.
- [Teste `expõe somente Escritórios e Módulos fiscais`] → Mitigação: reescrever o cenário para incluir SERPRO.

## Migration Plan

1. Deploy frontend apenas.
2. Rollback: reverter `navigation.ts` + teste.
3. Sem migration DB / flag.

## Open Questions

- Nenhuma.
