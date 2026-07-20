## Why

Criar e editar cliente compartilham `ClientForm`, mas a edição na ficha (`/clients/[id]/cadastro`) ainda é inline no dossiê, enquanto a lista usa `ClientFormModal`. Isso gera dois UX para a mesma ação e impede padronizar cadastros da ficha no padrão modal + dossiê/lista.

## What Changes

- Unificar criar e editar cliente em um único `ClientFormModal` (`ShellFormModal`).
- Aba Cadastro vira dossiê somente-leitura; CTAs **Editar** / **Editar cliente** abrem o modal no shell `/clients/[id]`.
- Título de edição padronizado: **“Editar cliente”** + subtítulo (razão social/CNPJ) na lista e na ficha.
- Consulta RFB (**Atualizar**) permanece só no header da aba Cadastro; remove-se o botão equivalente de dentro do formulário em modo edição.
- Pós-save na ficha: fecha o modal e recarrega o cliente (`load()`).
- Remover modo de edição inline de `ClientRegistration`.

## Capabilities

### New Capabilities

- `client-form-modal`: modal único de criar/editar cliente, dossiê de cadastro somente-leitura na ficha, e contrato de CTAs/título/RFB/pós-save.

### Modified Capabilities

- (nenhuma — `openspec/specs/` está vazio)

## Impact

- Web: `ClientFormModal.vue`, `ClientForm.vue`, `ClientRegistration.vue`, `pages/clients/[id].vue`, `pages/clients/[id]/cadastro.vue`, `useClientDetail.ts`, `ClientIdentityHeader.vue`; lista herda título via o mesmo modal.
- API: sem mudança de contrato HTTP; payload de `update` permanece o subset atual.
- Testes/gates web da área (lint, typecheck, vitest/fidelity tocados).

### Dependências entre changes

- Nível: `C0`
- Bases estáveis: main specs vazias; archive fora do DAG
- Depende de: nenhuma
- Capability/contrato: `client-form-modal` (nova)
- Marco exigido: n/a
- Relação: n/a
- Desbloqueia: nenhuma change ativa
- Paralelismo: independente de `fix-cnpj-ws-dossier` (dossiê CNAE/IE), `declarations-obligation-tabs` e `reconstruir-build-deploy-docker`; ownership distinto (`ClientRegistration` só remove inline aqui — não adicionar seções CNAE/IE nesta change)

### Non-goals

- Expandir campos persistidos no edit (payload `update` atual)
- Migrar abas contato, departamento, observações, dados-adicionais para o mesmo padrão (follow-up: lista/dossiê + `ShellFormModal`)
- Integração SERPRO live / parecer jurídico / mutações fiscais
- Ligar feature flags SERPRO/MEI/SEFAZ
- Serviços `mei`/`mei-worker` no Compose
- Targets Make de backup/restore/ops indisponíveis
- Conteúdo do dossiê CNAE/IE (`fix-cnpj-ws-dossier`)
