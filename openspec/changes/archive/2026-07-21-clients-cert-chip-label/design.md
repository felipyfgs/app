## Context

A lista de clientes (`ClientCatalogList`) renderiza a coluna **Certificado digital** via `clientCredentialInfo` em `apps/web/app/utils/clients-credential.ts`. O payload já traz `credential_summary` montado na API a partir de `client_credentials` (status, `valid_to`, alertas de vencimento). Quando o cliente não tem credencial, o chip mostra "Sem A1" — jargão técnico inadequado ao copy do produto.

## Goals / Non-Goals

**Goals:**

- Exibir **Sem certificado** no chip quando `credential_summary` é ausente.
- Manter estados com certificado (válido / a vencer / vencido) inalterados na lógica, só alinhando filtros KPI da mesma lista ao termo "certificado".
- Fonte de verdade continua sendo `client_credentials` via `credential_summary` (não inventar outro critério).

**Non-Goals:**

- Mudança de schema, API ou campos de `credential_summary`.
- Renomear "A1" em settings do escritório, SERPRO, upload PFX ou textos técnicos de integração.
- Redesign do shell/tabela; alteração de cores/tons do chip além do copy.

## Decisions

1. **Copy centralizado em `clientCredentialInfo`**  
   Única função pura do chip da coluna; troca `Sem A1` → `Sem certificado`. Alternativa (i18n keys) rejeitada: o catálogo já usa strings pt-BR inline.

2. **Filtros KPI da lista acompanham o termo**  
   Em `ClientCatalogList`, títulos/aria "Com A1" / "Sem A1" / "A1 vencido" → "Com certificado" / "Sem certificado" / "Certificado vencido", sem mudar query params/códigos de filtro (`without_credential`, etc.).

3. **Badges de filiais (`ClientBranchesPanel`)**  
   Alinhar "Sem A1" / "A1" para "Sem certificado" / "Com certificado" na mesma linguagem de produto da lista. Escopo limitado a UI de filiais do cliente; não tocar dashboard legado `ClientListDashboard` se não estiver na rota ativa da lista (evitar escopo creep) — se ainda for superfície viva na mesma navegação de clientes, alinhar só o chip de ausência.

4. **Sem mudança de API**  
   `subject_name` já existe no model; a coluna da lista não precisa exibir o nome do titular neste change — só corrigir ausência. Critério = presença de registro de certificado (`credential_summary`), não o termo "A1".

## Risks / Trade-offs

- [Copy inconsistente em outras telas que ainda dizem A1] → Mitigação: escopo explícito à lista/filiais; settings SERPRO fora.
- [Testes quebrados esperando "Sem A1"] → Mitigação: atualizar `clients-table.test.ts` no mesmo PR.
- [Filtros salvos por label] → Mitigação: filtros usam códigos (`without_credential`), não o título do KPI.

## Migration Plan

Deploy só frontend. Rollback = reverter o PR. Sem migração de dados.
