## 1. Domínio e API

- [x] 1.1 Criar `App\Enums\DocumentKind` (NFSE, NFE, NFCE, CTE, MDFE) e helpers
- [x] 1.2 Estender serialização de list/detail com `kind`, `kind_label`, `source`
- [x] 1.3 Aplicar filtro `kind` na query do catálogo (NFSE = nfse_notes; demais = vazio)
- [x] 1.4 Registrar rotas `GET /api/v1/documents*` e manter `/notes*` como alias no mesmo controller/handler
- [x] 1.5 Testes Feature: documents lista com kind; kind=NFE vazio; notes alias; detalhe/xml

## 2. Frontend catálogo Documentos

- [x] 2.1 Tipos TS `DocumentKind` / `FiscalDocument` (alias de NfseNote) e `api.documents`
- [x] 2.2 Estender notes-filters com `kind` (tipos comuns)
- [x] 2.3 Páginas `/docs` e `/docs/[accessKey]` reutilizando workspace de notas
- [x] 2.4 Redirect `/notes` → `/docs` (e accessKey)
- [x] 2.5 Nav “Documentos”, atalho `g-d`/`g-n`, coluna Tipo, filtro kind, empty state
- [x] 2.6 Detalhe com badge de tipo; export com aviso se kind ≠ NFS-e

## 3. Verificação

- [x] 3.1 PHPUnit Feature Notes/Documents
- [x] 3.2 E2E paths atualizados para /docs; tipos restritos aos comuns
