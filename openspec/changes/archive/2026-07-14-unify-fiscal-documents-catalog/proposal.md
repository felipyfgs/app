## Why

O painel expõe apenas “Notas” (NFS-e via ADN). O escritório precisa de um **catálogo unificado de documentos fiscais eletrônicos (DF-e)** — NFS-e, NF-e, NFC-e, CT-e e demais — com a mesma experiência operacional da tela atual, preparado para novos tipos e fontes sem reinventar listagem/detalhe/export.

## What Changes

- Renomear a superfície de produto **Notas → Documentos**, rota canônica **`/docs`** (redirect de `/notes`).
- Introduzir **`DocumentKind`** só com os tipos **mais comuns** (`NFSE`, `NFE`, `NFCE`, `CTE`, `MDFE`) no contrato de API e na UI (filtro, coluna Tipo, empty states).
- Expor API canônica **`/api/v1/documents*`**; manter **`/api/v1/notes*`** como alias compatível.
- Serializar cada item de catálogo com `kind`, `kind_label` e `source` (hoje: `NFSE` + `ADN`).
- Reutilizar a base visual/operacional de `/notes` (workspace, tabela, insights, detalhe modal, export).
- **Não** implementar captura SEFAZ/DistDFe nem parsers de mercadoria/transporte nesta change.

## Capabilities

### New Capabilities

- (nenhuma capability nova de produto; extensão do catálogo existente)

### Modified Capabilities

- `fiscal-document-catalog`: catálogo multi-tipo com `kind`/fonte; listagem e detalhe unificados; NFS-e continua a projeção implementada.
- `frontend-dashboard-experience`: navegação e rotas Documentos (`/docs`); filtros e empty states por tipo; redirects de `/notes`.
- `xml-delivery`: export e download XML via identidade de documento (compat com notes; filtro `kind` quando aplicável).

## Impact

- **Frontend:** `pages/notes` → `pages/docs`, nav, atalhos, `useApi`, tipos TS, filtros.
- **Backend:** enum `DocumentKind`, controller de documentos, rotas API, serialização.
- **OpenSpec:** deltas nas specs listadas; capture ADN inalterada.
- **Compat:** deep-links e API `/notes` continuam funcionando via redirect/alias.

## Não-objetivos

- Captura SEFAZ DistDFe (NF-e, NFC-e, CT-e, MDF-e, BP-e, etc.).
- Parsers/leiautes de mercadoria/transporte/energia/comunicação.
- DANFE/DANFSe/PDF, emissão/cancelamento, portais municipais legados.
- Tabela unificada `catalog_documents` (Path A) — opcional em change futura se o 2º connector exigir.
- Multi-escritório SaaS, KMS cloud, portal do cliente.
