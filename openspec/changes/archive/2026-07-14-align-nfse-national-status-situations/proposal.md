## Why

O parser e a UI tratam situações da NFS-e com vocabulário híbrido (ACTIVE/CANCELLED/AUTHORIZED) e mapeiam **cStat 101 → CANCELLED**, o que contradiz o leiaute nacional (**101 = NFS-e de Substituição Gerada**). Cancelamento e substituição reais no Portal/ADN ocorrem sobretudo por **eventos**, não por esse cStat. Sem alinhar cStat + eventos + labels, a triagem (lista, chips, modal, export) engana o contador — nota substituta parece cancelada.

## What Changes

- Definir modelo de **situação operacional** da nota alinhado à NFS-e Nacional (cStat + eventos), distinto de status de cadastro CNPJ ou de NF-e de mercadoria.
- Corrigir mapeamento `cStat` → `status` no parse (ex.: 100 → gerada/ativa; **101 → substituta**, não cancelada; 102 → decisão judicial quando aplicável).
- Atualizar projeção quando eventos de cancelamento / cancelamento por substituição forem processados (`CANCELLED` / `SUPERSEDED`).
- Labels e chips na UI (lista, filtros, insights, modal): Gerada, Substituta, Cancelada, Substituída, Decisão judicial, Em revisão; sempre expor `cStat` no detalhe.
- Ajustar filas de triagem (não misturar AUTHORIZED de NF-e com “em revisão”).
- Testes de parser e, se viável, backfill/remap de notas já capturadas no piloto.
- **BREAKING** (contrato de status): valores e labels de `status` mudam para operadores e filtros; API continua com `official_status_code` + `status`, com semântica nova documentada.

## Capabilities

### New Capabilities

Nenhuma capability de domínio nova.

### Modified Capabilities

- `fiscal-document-catalog`: situação da projeção NFS-e MUST refletir cStat nacional e eventos de cancelamento/substituição; labels e filtros coerentes.
- `frontend-dashboard-experience`: chips, filtros, insights e modal de nota MUST apresentar situações oficiais legíveis (não vocabulário NF-e genérico).
- `adn-document-sync` (delta leve): parse/projeção de status a partir do XML ADN MUST usar o mapeamento nacional.

## Impact

| Área | Efeito |
|------|--------|
| `NfseXmlParser` / projeção `nfse_notes` | Novo mapa cStat; possível status pós-evento |
| Processamento de eventos ADN | Atualizar status da nota original |
| Frontend labels, `AppStatusBadge`, filtros Notes/Export, insights | Labels e filas |
| Testes unitários parser + feature notes | Cobertura 100/101/102 e cancelamento |
| Dados piloto | Remap ou reparse opcional |

## Não-objetivos

- Emitir, cancelar ou substituir NFS-e pelo painel.
- DANFSe/PDF visual oficial.
- Portal do contribuinte ou scraping do Emissor Nacional.
- Reescrever distribuição ADN/NSU.
- Tabela completa de todos os cStat de *resposta de webservice* (só situações de **documento** + eventos que afetam a nota).
- Analytics de reforma tributária além da situação do documento.
