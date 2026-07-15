# Inventário: reuso de `add-svrs-nfce-outbound-xml-retrieval`

**Change:** `add-resilient-svrs-nfe55-outbound-xml-retrieval` · task 1.2  
**Arquivo origem:** `openspec/changes/archive/2026-07-15-add-svrs-nfce-outbound-xml-retrieval`

## Componentes existentes (reusar / estender)

| Peça | Caminho | Ação nesta change |
|------|---------|-------------------|
| Config tipada NFC-e | `SvrsNfceConfig` + `config/sefaz.php` `svrs_nfce_xml` | Defaults de taxa → governador compartilhado; host/paths NFC-e mantidos |
| Cliente HTTP mTLS | `HttpSvrsNfceOutboundXmlRetrievalClient` | Manter; passa a reservar via governador antes de rede |
| Cliente desabilitado / fake | `Disabled*` / `FakeSvrsNfce*` | Manter padrão fail-closed |
| Parser wrapper | `SvrsNfceDownloadResponseParser` | Estender detecção de bloqueio múltiplas consultas (HTTP 200) |
| Validador XML | `SvrsNfceXmlValidator` | Reusar para 65; NFe 55 com validador/contrato de modelo 55 |
| Ingestão | `SvrsNfceXmlIngestionService` | Padrão para 55 com proveniência `SVRS_NFE55_DOWNLOAD_XML_DFE` |
| Elegibilidade | `SvrsNfceRetrievalEligibility` | Espelhar para 55 ou generalizar por modelo |
| Rate limiter isolado | `SvrsNfceRateLimiter` | **Substituído** por `SvrsPortalEgressGovernor` |
| Breaker cache-only | `SvrsNfceCircuitBreaker` | Evolui / coexiste com estado durável de coorte no PG |
| Kill switch | `SvrsNfceKillSwitchService` | Reuso; canal 55 compartilha kill master do portal |
| Orquestrador | `OutboundXmlRecoveryOrchestrator` | Usar governador; router multi-fonte |
| Job | `RecoverSvrsNfceXmlJob` | 1 chave; job 55 irmão ou unificado |
| API | `SvrsNfceRecoveryController` | Ampliar saúde de coorte |
| Migration | `2026_07_15_050000_create_svrs_nfce_xml_recovery_tables` | Estender attempts + tabela de coorte |
| Fixtures NFC-e | `tests/fixtures/svrs-nfce/` | Manter; adicionar `svrs-portal/` e `svrs-nfe55/` |
| Testes Feature/Unit | `tests/**/SvrsNfce*` | Atualizar budgets e governador |

## Não duplicar

- Host allowlist e TLS: um só coordenador de egress.
- Vault / `dfe_documents` / projeção `nfe_documents`.
- DistDFe (canal separado; não entra no governador do portal).
