# Auditoria de fundações — CT-e completo (task 1.1)

**Change:** `complete-cte-capture-with-distdfe-autxml-and-import`  
**Dependência:** `add-office-autxml-and-bulk-xml-import`  
**Data:** 2026-07-15

## Resultado

As fundações necessárias da change dependente **já estão aplicadas no repositório**. Esta change **não deve** recriar as tabelas/contratos listados abaixo.

| Área | Artefato | Status |
|------|----------|--------|
| Identidade fiscal do escritório | `office_fiscal_identities` | Presente (migration `2026_07_15_040000`) |
| A1 do escritório | `office_credentials` + `OfficeCredentialService` / `OfficeCredentialResolver` | Presente — finalidade `NFE_AUTXML_DISTDFE` (reutilizar no canal CT-e `autXML`) |
| Enrollment | `office_autxml_enrollments` | Presente (NF-e; estender status/observação para CT-e sem nova tabela) |
| Cursor central | `office_distribution_cursors` (unique `office_id + root + env + channel`) | Presente — canal `NFE_AUTXML_DISTDFE`; CT-e usará canal novo `CTE_AUTXML_DISTDFE` na mesma tabela |
| Runs do stream | `office_distribution_runs` | Presente |
| Quarentena | `fiscal_document_quarantine` | Presente |
| Import batch | `document_import_batches` / `document_import_batch_items` | Presente |
| Aquisições | `document_acquisitions` (MA + extensão autXML) | Presente — estender fontes CT-e |
| Interesses | `document_interests` (role+channel unique) | Presente — papéis CT-e novos |
| Job NF-e autXML | `SyncOfficeAutXmlDistDfeJob` + `OfficeAutXmlPageProcessor` | Presente (referência; CT-e terá job próprio) |
| Feature flag autXML | `sefaz.autxml.enabled` + kill switch + allowlist | Presente |
| ZIP/XML seguro | import async + limites | Presente |

## Lacunas conscientes (escopo desta change, não da fundação)

- Canal `CTE_AUTXML_DISTDFE` e cursor CT-e do escritório
- Papéis `SENDER` / `RECIPIENT` / `EXPEDITOR` / `RECEIVER` e qualidade de artefato
- `consNSU` no cliente CT-e
- Parser multi-papel sem fallback `TAKER`
- Job/page processor CT-e do escritório e import `cteProc`
- Endpoint `EMITTER_PUSH`
- Projeção de cobertura CT-e

## Decisão

**Não duplicar** schema de identidade, credencial, batch ou quarentena. Extensões ficam em migration nova desta change e em enums/serviços CT-e.
