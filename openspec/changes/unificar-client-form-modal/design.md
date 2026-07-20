## Context

Hoje `ClientForm` é compartilhado entre create e edit, mas:

- Lista e filiais abrem `ClientFormModal` (`ShellFormModal`).
- Na ficha, `ClientRegistration` alterna dossiê ↔ formulário inline via `registrationEditRequested` / `startEditing`.
- Título do modal em edit usa o nome do cliente; RFB “Atualizar cadastro RFB” existe dentro do form em edit e também no header da aba Cadastro.

Stakeholders: operadores do escritório que cadastram/editam clientes no painel Nuxt.

## Goals / Non-Goals

**Goals:**

- Um único container modal para criar e editar cliente.
- Dossiê de cadastro somente-leitura; edição só via modal no shell da ficha.
- Título/descrição de edição padronizados; RFB só no header da aba; pós-save fecha + `load()`.

**Non-Goals:**

- Expandir payload de `clients.update`.
- Migrar contato/departamento/observações/dados-adicionais (follow-up: lista/dossiê + `ShellFormModal`).
- Alterar conteúdo do dossiê (CNAEs/IEs — change `fix-cnpj-ws-dossier`).
- Mudanças de API, flags SERPRO/MEI/SEFAZ, Compose mei.

## Decisions

### 1. Modal no shell `[id].vue`, não só em `/cadastro`

- **Escolha:** montar `ClientFormModal` em `pages/clients/[id].vue` e expor `openClientEdit` via `useClientDetail`.
- **Por quê:** header “Editar cliente” e botão Editar do dossiê precisam do mesmo entry point sem depender da aba ativa.
- **Alternativa:** só em `cadastro.vue` — falha quando o usuário edita a partir do header em outra aba.

### 2. Remover inline de `ClientRegistration`

- **Escolha:** apagar estado `editing`, `ClientForm` embutido, props `startEditing` / `editingChange`.
- **Por quê:** um caminho só; evita dual path e código morto.
- **Alternativa:** esconder inline — rejeitada (dívida).

### 3. Título fixo “Editar cliente” + subtítulo identidade

- **Escolha:** `title = "Editar cliente"`; `description` = razão social + CNPJ formatado (fallback).
- **Por quê:** paridade lista/ficha e ação clara.
- **Alternativa:** título = nome do cliente (atual) — menos explícito sobre a ação.

### 4. RFB fora do form

- **Escolha:** manter `refreshRegistration` no header de `cadastro.vue`; remover botão RFB de `ClientForm` em edit.
- **Por quê:** modal edita dados locais; refresh é ação do dossiê.
- **Alternativa:** RFB nos dois lugares — redundante e confuso.

### 5. Payload de update inalterado

- **Escolha:** manter subset atual (nome, regime, porte, natureza, ativo…).
- **Por quê:** escopo = unificar container, não expandir contrato.
- **Alternativa:** edit tão amplo quanto create — exige API e fora de escopo.

### 6. Follow-up de cadastros da ficha

- **Escolha:** documentar no proposal/non-goals o padrão futuro (lista/dossiê + `ShellFormModal`); zero implementação.
- **Por quê:** entrega focada e verificável.

## Risks / Trade-offs

- [Conflito de ownership com `fix-cnpj-ws-dossier` em `ClientRegistration`] → Mitigação: esta change só remove inline; não adiciona seções CNAE/IE; merge consciente se ambas editarem o arquivo.
- [Regressão de testes que assumem inline / `registrationEditRequested`] → Mitigação: atualizar testes no mesmo nível N da implementação.
- [Usuário perde “salvar e continuar vendo o form”] → Mitigação aceita: fecha + `load()` conforme decisão de produto.

## Migration Plan

1. Deploy frontend apenas (sem migration DB).
2. Rollback: reverter componentes web; sem estado server-side novo.
3. Sem feature flag — comportamento de UX imediato.

## Open Questions

- Nenhuma — decisões fechadas no grill.
