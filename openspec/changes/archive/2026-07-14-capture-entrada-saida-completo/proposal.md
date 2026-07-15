## Why

O escritório precisa do **XML de tudo que importa contábilmente**: **entradas e saídas**, não só um pedaço do DistDFe. Hoje o monorepo cobre bem **NFS-e (ADN)** nos papéis prestador/tomador/intermediário e **NF-e de interesse DistDFe** (entrada típica). **Falta** um modelo explícito entrada/saída, canais de **saída NF-e/NFC-e**, **CT-e/MDF-e**, e desbloqueio de full NF-e para entrega. Sem isso o catálogo `/docs` não fecha o ciclo do contador.

## What Changes

- Introduzir **direção fiscal** no catálogo: `IN` (entrada) / `OUT` (saída) / `UNKNOWN`, derivada de papel + tipo de documento + CNPJ do estabelecimento.
- **Completar entradas:** NF-e DistDFe (já) + ciência unlock full + CT-e DistDFe + MDF-e quando aplicável + NFS-e ADN (já).
- **Completar saídas:**
  - **NFS-e:** já chegam no ADN como `ISSUER` — classificar como `OUT` e garantir filtros/export.
  - **NF-e / NFC-e emitidas:** canal oficial DistDFe **não entrega a própria nota ao emitente** → implementar **ingestão de XML** (upload/import em lote + API) e, quando houver chave conhecida, consulta pontual; opcional integração futura com emissor.
- Filtros UI/API: direção + kind + cliente.
- Export ZIP com pastas `entrada/` e `saida/` (e por kind).
- Alinhar changes irmãs: `capture-multi-dfe-sefaz`, `nfe-manifestacao-destinatario` (unlock XML, não MD-e fiscal forçada).

## Capabilities

### New Capabilities

- `fiscal-document-direction`: classificação e filtro entrada/saída no catálogo.
- `outbound-xml-ingestion`: importação de XML de saídas (NF-e/NFC-e/outros) para vault + projeção.
- `cte-mdfe-full-capture`: captura CT-e e MDF-e (entrada e, se emitente, saída via mesmo canal/import).

### Modified Capabilities

- `fiscal-document-catalog`: kind + direction + listagem unificada.
- `adn-document-sync`: projetar direction a partir de fiscal_role (ISSUER→OUT, TAKER→IN, etc.).
- `sefaz-distdfe-sync` / captura NFE: direction IN (e OUT só se papel permitir).
- `xml-delivery`: export por direção e kind.
- `frontend-dashboard-experience`: filtros Entrada/Saída; import de saídas.
- `nfe-xml-unlock-via-ciencia`: full de **entrada** destinatário para entrega.

## Impact

- Schema: `direction` em projeções ou `catalog_documents`.
- Novos jobs de import; CT-e/MDF-e clients.
- UI import + filtros.
- Piloto client 8: entradas NF-e já ok; saídas NF-e via import se o ERP exportar XML.

## Não-objetivos

- Emissão de NF-e/NFC-e/NFS-e pelo painel.
- Substituir o ERP emissor do cliente.
- Scraping de portal.
- Confirmação/desconhecimento em massa (só unlock opcional + MD-e opcional).
