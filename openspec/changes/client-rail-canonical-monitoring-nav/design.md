## Context

O detalhe fiscal por empresa (`/monitoring/clients/:id`) tem um rail com labels próprios (PGDAS-D, SITFIS, Pendências, Visão geral) desalinhados do accordion Monitoramento. Esta change unifica a IA: mesmo vocabulário e ordem do catálogo global `MONITORING_NAV_ITEMS`, sem inventar seções fora dessa lista, e adiciona troca de empresa no header.

## Goals / Non-Goals

**Goals:**

- Rail e overview com labels/ordem canônicos dos 12 contextos de Monitoramento.
- Seções DCTFWeb e Caixas Postais no detalhe; Cadastro e Vínculos + Processos Fiscais reexibidos.
- MEI só para cliente MEI; Pendências/Execuções/Achados/Renúncias ocultos (deep-link → overview).
- Combobox no header do rail para trocar empresa preservando seção válida.
- Paths de seção estáveis (`overview`, `pgdasd`, `ccmei`, …).

**Non-Goals:**

- Rename de URLs; mudança do sidebar global; remoção dos atalhos CRM; chamadas SERPRO novas; ligar flags fail-closed.

## Decisions

1. **Fonte de labels/ícones:** derivar do item correspondente em `MONITORING_NAV_ITEMS` quando houver `moduleKey`; Dashboard → `overview`; Simples Nacional → `pgdasd`; MEI → `ccmei` (path legado).
2. **Ocultos:** `pending`, `runs`, `findings`, `renunciations`. Visíveis: os 12 canônicos (MEI condicional).
3. **Novas seções:** `dctfweb` e `mailbox` — panels fail-closed no mesmo `[clientId].vue`, reusando listagens já filtráveis por `client_id`.
4. **Switcher:** reusar `FiscalClientPicker` (single) no header de `ClientFiscalAside`; navegação para `/monitoring/clients/:novoId/:section?` com fallback para overview se a seção não for visível no destino (ex.: `ccmei` em não-MEI). Rail collapsed: botão compacto + popover com o mesmo picker.
5. **Exceção transversal:** duas capabilities (`client-fiscal-rail` + `company-monitoring-overview`) porque o overview espelha o rail; justificado — um único resultado de IA.

## Risks / Trade-offs

- Reabrir Cadastro/Processos aumenta o rail; aceitável para paridade com o sidebar global.
- Path `ccmei` com label “MEI” e `pgdasd` com “Simples Nacional” pode confundir quem lê URLs — mitigado mantendo paths estáveis e labels só na UI.
- Panels DCTFWeb/mailbox mínimos no detalhe podem parecer “vazios” sem evidência local — fail-closed explícito, sem inventar status.

## Migration Plan

Nenhuma migração de dados. Deep-links de seções ocultas (`/pending`, etc.) redirecionam para overview. Sem deploy flags.

## Open Questions

Nenhuma — decisões travadas no plano de implementação.
