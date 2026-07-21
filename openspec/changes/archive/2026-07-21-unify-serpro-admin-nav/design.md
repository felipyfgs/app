## Context

`restore-serpro-admin-nav` recolocou `SERPRO_NAV_ITEMS` como três filhos do grupo Admin. O console já tem shell em `pages/admin/serpro.vue` e catálogo `SERPRO_NAV_ITEMS` (Operação → `/admin/serpro`, Integração → `/admin/serpro/configuration`, Canário → `/admin/serpro/dte-canary`). Conta e processos usam toolbar + navegação de seção; SERPRO ainda empurrava as três folhas para o sidebar global.

## Goals / Non-Goals

**Goals:**

- Um item **SERPRO** no sidebar Admin (`to: /admin/serpro`, ativo em `/admin/serpro/*`).
- Abas Operação / Integração / Canário DTE no shell via `SectionNavigation` + `SERPRO_NAV_ITEMS`.
- Testes alinhados ao contrato.

**Non-Goals:**

- Mudar tabs internas Status/Consumo/Liberação ou Acesso/Contratos/Cobertura.
- APIs/flags/Compose mei.

## Decisions

### 1. Um destino no sidebar, catálogo nas abas do shell

- **Escolha:** `platformAdminDestinations` ganha um filho único `{ id: platform-serpro, label: SERPRO, to: /admin/serpro }`; `serpro.vue` monta `SectionNavigation` com `SERPRO_NAV_ITEMS`.
- **Por quê:** o usuário pediu “uma rota SERPRO”; as três superfícies continuam necessárias dentro do console.
- **Alternativa:** manter três itens no sidebar — rejeitada.

### 2. Entry point `/admin/serpro` (Operação)

- **Escolha:** o link do sidebar abre Operação; Integração/Canário via abas.
- **Por quê:** path canônico do shell e primeira superfície do catálogo.
- **Alternativa:** deep-link direto para `/configuration` — pior para status/kill switch.

### 3. `UDashboardToolbar` + `SectionNavigation`

- **Escolha:** mesmo padrão de `work/processes/[id].vue` (toolbar sob a navbar).
- **Por quê:** `SERPRO_NAV_ITEMS` já é `NavLayerItem[]`; `SectionNavigation` cobre desktop/mobile.
- **Alternativa:** migrar o shell inteiro para `ShellSettingsShell` — fora de escopo.

## Risks / Trade-offs

- [Usuário não vê Integração no sidebar] → Mitigação: abas no console; label único SERPRO.
- [Conflito com `restore-serpro-admin-nav` ainda ativa] → Mitigação: esta change é C1 e sobrescreve o contrato do sidebar.

## Migration Plan

1. Deploy frontend.
2. Rollback: reverter nav + `serpro.vue` + teste.
3. Sem flag/DB.

## Open Questions

- Nenhuma.
