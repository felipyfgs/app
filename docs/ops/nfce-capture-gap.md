# Gap: captura NFC-e (modelo 65)

**Status:** documentado (MAY da spec) — **sem captura falsa** no MVP multi-DF-e.

## Por que não há canal DistDFe de NFC-e no MVP

- A **Distribuição DFe (NF-e)** do Ambiente Nacional atende sobretudo **NF-e modelo 55** de interesse do destinatário (e eventos).
- **NFC-e (modelo 65)** é documento de **PDV/consumidor final**; o escritório contábil raramente recebe volume B2B útil via DistDFe de entrada.
- Não existe no monorepo um canal oficial equivalente “NFC-eDistDFe” homologado para o caso de uso do painel.

## O que o produto faz

| Capacidade | Comportamento |
|------------|----------------|
| `DocumentKind::Nfce` | Existe no catálogo e filtros UI |
| `SEFAZ_NFCE_ENABLED` | Default **off**; `capture_available=false` |
| Listagem `kind=NFCE` | Vazia até haver fonte real |
| Import de saídas | Pode gravar XML NFC-e se o ERP exportar (canal **import**, não DistDFe) |

## Quando reavaliar

1. Cliente piloto com volume real de NFC-e de interesse contábil.
2. Canal oficial (consulta por chave / outro WS) com contrato estável e rate limit.
3. Feature flag `SEFAZ_NFCE_ENABLED` + processor dedicado — **sem** reutilizar DistDFe NF-e fingindo modelo 65.

## Referência

- Change: `capture-multi-dfe-sefaz` · design D4 · task 6.1
- Spec: `fiscal-document-catalog` / `multi-dfe-catalog-projection` (MAY NFC-e)
