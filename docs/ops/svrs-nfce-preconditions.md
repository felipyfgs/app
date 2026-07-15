# Pré-condições — canal SVRS NFC-e XML retrieval

**Change:** `add-svrs-nfce-outbound-xml-retrieval` · task 1.1  
**Data:** 2026-07-15  
**Dependência:** `build-ma-outbound-nfe-nfce-capture`

## Verificação de contratos e schema base

| Artefato | Status | Observação |
|----------|--------|------------|
| Migration `2026_07_15_030000_create_ma_outbound_capture_tables` | OK | Tabelas de perfil, série/`nNF`, números, retrieval MA, runs e `document_acquisitions` |
| Estados `KEY_DISCOVERED` / `XML_PENDING` / `XML_CAPTURED` | OK | `OutboundNumberStatus` |
| Modelos `OutboundCaptureProfile`, `OutboundNumberState`, `MaOutboundRetrievalRequest` | OK | Com `BelongsToOffice` |
| `SecureObjectStore` | OK | Envelope crypto; A1 por raiz |
| `OutboundXmlIngestionService` / pacote oficial MA | OK | Base de ingestão imutável reutilizável |
| Kill switch MA outbound | OK | `OutboundKillSwitchService` (canal MA; SVRS terá kill switch próprio) |
| `MaOutboundXmlRetrievalClient` (M2M) | OK | Default disabled — **não** é o cliente SVRS |
| Proveniência `document_acquisitions` | OK | Sem NSU inventado; fontes MA existentes |
| Cursor `last_nsu` em tabelas outbound | Ausente (correto) | Posição por `nNF` / `discovery_position` |

## Incompatibilidades registradas

| Item | Severidade | Tratamento |
|------|------------|------------|
| `ma_outbound_retrieval_requests` modela recuperação por competência/pacote, não por chave | Esperado | Migration aditiva: origem `SVRS_PORTAL_BY_KEY`, chave e tentativas |
| `OutboundRetrievalStatus` focado em fluxo M2M/pacote | Esperado | Enums novos de recovery SVRS (`ELIGIBLE`…`BLOCKED`) sem reutilizar estados M2M |
| `DocumentAcquisitionSource` sem `SVRS_NFCE_DOWNLOAD_XML_DFE` | Esperado | Case aditivo nesta change |
| `MaOutboundXmlRetrievalClient` é interface M2M SEFAZ-MA | Sem conflito | Nova interface `SvrsNfceOutboundXmlRetrievalClient` isolada |
| HTML/wrapper SVRS sem fixture versionada no repo | Esperado | Fixtures sanitizadas entregues na seção 3 |

## Conclusão

Pré-condições da change MA **satisfeitas** no código. Nenhum bloqueio estrutural impede migrations aditivas nem o adapter SVRS com flags desligadas. Não há necessidade de alterar a change MA arquivada; extensões são aditivas.
