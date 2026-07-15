## Context

O catálogo atual lista apenas projeções `nfse_notes` via `NoteController` e a UI `/notes`. O domínio já tem `dfe_documents` imutável e `document_type` ADN (`NFSE`|`EVENTO`), mas a superfície de produto fala só em “notas” NFS-e. O escritório precisa de um catálogo genérico de DF-e (NFS-e, NF-e, NFC-e, CT-e, …) com a mesma UX, sem captura multi-fonte nesta change.

## Goals / Non-Goals

**Goals:**
- Rota e navegação **Documentos** em `/docs`.
- Contrato de API unificado `/api/v1/documents*` com `kind`, `kind_label`, `source`.
- Registry `DocumentKind` alinhado ao mapa nacional de DF-e.
- Filtro por tipo na UI; empty state quando o tipo ainda não tem captura.
- Compat: `/notes` e `/api/v1/notes*` continuam.

**Non-Goals:**
- SEFAZ DistDFe, parsers NF-e/CT-e/etc., DANFE/PDF, emissão.
- Tabela `catalog_documents` unificada (Path A) — só se o 2º connector exigir depois.

## Decisions

### 1. Path B: projeção NFS-e + kind no contrato
- **Decisão:** listar a partir de `nfse_notes`; cada item serializa `kind=NFSE`, `source=ADN`. Filtro `kind` diferente de `NFSE` (ou lista sem NFSE) retorna vazio.
- **Alternativa:** tabela `catalog_documents` já — adiada (custo/benefício baixo com uma fonte).

### 2. `DocumentKind` como enum de domínio (PHP + TS)
- Só os **mais comuns**: `NFSE`, `NFE`, `NFCE`, `CTE`, `MDFE`.
- Metadata: label pt-BR, modelo SEFAZ opcional, `capture_available` (só NFSE=true hoje).

### 3. API canônica documents; notes como alias
- `DocumentController` concentra a lógica (extraída de `NoteController`) ou `NoteController` reutiliza o mesmo serviço.
- Preferência: renomear lógica para métodos compartilhados em `NoteController` e registrar rotas documents apontando para o mesmo controller (menor diff), **ou** `DocumentController extends` / composição.
- **Escolha:** `DocumentController` com a lógica atual movida; `NoteController` delega para manter compat e nomes de teste.

### 4. Frontend: páginas `/docs`, redirect `/notes`
- Copiar/adaptar `components/notes` → manter pasta `notes` internamente **ou** renomear para `docs`. Preferência: renomear componentes para `docs/*` e utils `docs-filters.ts` para alinhar linguagem do produto.
- Nav: label “Documentos”, `to: '/docs'`.
- Atalho: `g-d` → `/docs`; `g-n` também → `/docs`.

### 5. Export
- Continua baseado em NFS-e; se filtro `kind` exclui NFS-e, botão export desabilitado com toast explicativo.

## Risks / Trade-offs

- **[Risco]** Deep-links antigos quebram → **Mitigação:** redirect Nuxt `/notes` → `/docs`.
- **[Risco]** Clientes API usam `/notes` → **Mitigação:** alias permanente na API.
- **[Risco]** Operador espera NF-e no filtro e acha bug → **Mitigação:** empty state “Captura deste tipo ainda não disponível”.
- **[Trade-off]** Path B não unifica índice SQL → ok até 2ª fonte.

## Migration Plan

1. Deploy backend (kind + rotas documents + notes alias).
2. Deploy frontend `/docs` + redirects.
3. Sem migração de dados.
4. Rollback: reverter rotas/UI; dados intactos.

## Open Questions

- Nenhuma bloqueante; Path A fica para change futura.
