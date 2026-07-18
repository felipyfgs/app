# Inventário SERPRO Integra Contador × Hub

- Fonte oficial: https://apicenter.estaleiro.serpro.gov.br/documentacao/api-integra-contador/pt/catalogo_de_servicos/
- Catálogo canônico vigente: `backend/resources/serpro/official-service-catalog.v2026-07-16.json` (manifest `2026.07.16.1`)
- Fontes reconciliadas: `backend/resources/serpro/official-sources.v2026-07-18.json`
- Poderes reconciliados: `backend/resources/serpro/power-matrix.v2026-07-18.json`
- Gerado em: 2026-07-18
- Total SERPRO: **119** operações = **98 produtivas** + **21 não produtivas**
- Mutabilidade: **33** mutantes = **25 produtivas** + **8 não produtivas**; **73** leituras produtivas + **13** leituras não produtivas
- Status final: **98 `BLOCKED`** + **21 `INVENTORIED_NON_PROD`** + **0 `READY_PRODUCTION`**

## Resumo

| Camada | Qtd | Significado |
|--------|-----|-------------|
| SERPRO cancelado/em construção — não implementar | **2** | `A_SERPRO_INDISPONIVEL` |
| SERPRO prospecção — só inventário no hub | **19** | `B_SERPRO_PROSPECCAO` |
| No JSON local, sem referência de código | **53** | `C0_HUB_SO_CATALOGO_JSON` |
| Referenciado (catálogo/import/guard) sem fluxo dedicado | **19** | `C_HUB_COORDENADA_REFERENCIADA` |
| Serviço/fluxo parcial no backend | **18** | `D_HUB_SERVICO_PARCIAL` |
| Fluxo backend + testes | **6** | `E_HUB_FLUXO_COM_TESTE` |
| Fluxo backend + UI/FE | **2** | `F_HUB_BACKEND_E_UI` |

### Atenção: `platform_support=IMPLEMENTED` no JSON

O snapshot marca **98 PRODUCTION → IMPLEMENTED**. Isso significa **coordenadas executáveis no catálogo/resolver**, **não** produto completo (UI, codec de domínio, e2e piloto). Use a coluna de maturidade abaixo.

O catálogo `v2026-07-16` continua canônico por estar vigente e reconciliado
com as fontes `v2026-07-18`. Maturidade de código nunca substitui
`PASS_REAL_*` fresco com proveniência `PRODUCTION_CANARY`.

### Caveats da varredura de código

- Maturidade por **presença literal** de `operation_key` em PHP/FE. Serviços genéricos (ex. **parcelamento** via `ParcelamentoServiceCatalog` + adapters read/emit) podem estar wireados **sem** string `parcsn.gerardas` no código — caem em `C0` mesmo com fluxo de família.
- Explorador manual (`ManualConsultActionCatalog`) cobre dezenas de ações read-only; mutações (`is_mutating=true`) ficam fora por design (fail-closed).
- Doc oficial consultada em **2026-07-18**: [Catálogo de Serviços](https://apicenter.estaleiro.serpro.gov.br/documentacao/api-integra-contador/pt/catalogo_de_servicos/) — **119** sequenciais, alinhado ao snapshot local `2026.07.16.1` (última atualização da página oficial listada: 12/02/2026).

## Lista completa

### SERPRO cancelado/em construção — não implementar (2)

| Cód. | operation_key | idSistema | idServico | Rota | Estado SERPRO | platform_support | refs | flags |
|------|---------------|-----------|-----------|------|---------------|------------------|------|-------|
| 8.2 | `autenticaprocurador.expirarautorizacao` | AUTENTICAPROCURADOR | `EXPIRARAUTORIZACAO82` | Apoiar | CANCELED | INVENTORIED | php=0 fe=0 | — |
| 5.4 | `sicalc.consolidar` | SICALC | `CONSOLIDAR54` | Consultar | UNDER_CONSTRUCTION | INVENTORIED | php=6 fe=0 | test |

### SERPRO prospecção — só inventário no hub (19)

| Cód. | operation_key | idSistema | idServico | Rota | Estado SERPRO | platform_support | refs | flags |
|------|---------------|-----------|-----------|------|---------------|------------------|------|-------|
| 15.1 | `dasnsimei.transdeclaracao` | DASNSIMEI | `TRANSDECLARACAO151` | Declarar | PROSPECTION | INVENTORIED | php=2 fe=0 | test,MUT |
| 15.2 | `dasnsimei.consultimadecrec` | DASNSIMEI | `CONSULTIMADECREC152` | Consultar | PROSPECTION | INVENTORIED | php=1 fe=0 | — |
| 15.3 | `dasnsimei.gerardasexcesso` | DASNSIMEI | `GERARDASEXCESSO153` | Emitir | PROSPECTION | INVENTORIED | php=0 fe=0 | MUT |
| 3.11 | `dctfweb.gerarguiacomabatimento` | DCTFWEB | `GERARGUIACOMABATIMENTO311` | Emitir | PROSPECTION | INVENTORIED | php=0 fe=0 | MUT |
| 3.12 | `dctfweb.editarvalorsuspenso` | DCTFWEB | `EDITARVALORSUSPENSO312` | Emitir | PROSPECTION | INVENTORIED | php=0 fe=0 | MUT |
| 3.4 | `dctfweb.consrelcredito` | DCTFWEB | `CONSRELCREDITO34` | Consultar | PROSPECTION | INVENTORIED | php=0 fe=0 | — |
| 3.5 | `dctfweb.consreldebito` | DCTFWEB | `CONSRELDEBITO35` | Consultar | PROSPECTION | INVENTORIED | php=0 fe=0 | — |
| 3.6 | `dctfweb.gerarguiamaed` | DCTFWEB | `GERARGUIAMAED36` | Emitir | PROSPECTION | INVENTORIED | php=0 fe=0 | MUT |
| 3.7 | `dctfweb.consnotifmaed` | DCTFWEB | `CONSNOTIFMAED37` | Consultar | PROSPECTION | INVENTORIED | php=0 fe=0 | — |
| 3.9 | `dctfweb.aplvinculacao` | DCTFWEB | `APLVINCULACAO39` | Emitir | PROSPECTION | INVENTORIED | php=0 fe=0 | MUT |
| 27.2 | `eprocesso.obtlistdocsproc` | EPROCESSO | `OBTLISTDOCSPROC272` | Consultar | PROSPECTION | INVENTORIED | php=0 fe=0 | — |
| 27.3 | `eprocesso.obtdocproc` | EPROCESSO | `OBTDOCPROC273` | Consultar | PROSPECTION | INVENTORIED | php=0 fe=0 | — |
| 27.4 | `eprocesso.conscomunintim` | EPROCESSO | `CONSCOMUNINTIM274` | Consultar | PROSPECTION | INVENTORIED | php=0 fe=0 | — |
| 24.1 | `parc_paex.obterextratopdf` | PARC-PAEX | `OBTEREXTRATOPDF171` | Consultar | PROSPECTION | INVENTORIED | php=0 fe=0 | — |
| 24.2 | `parc_paex.obterextratojson` | PARC-PAEX | `OBTEREXTRATOJSON172` | Consultar | PROSPECTION | INVENTORIED | php=0 fe=0 | — |
| 24.3 | `parc_paex.emitirdocarrecadacao` | PARC-PAEX | `EMITIRDOCARRECADACAO173` | Emitir | PROSPECTION | INVENTORIED | php=0 fe=0 | MUT |
| 25.1 | `parc_sipade.obterextratopdf` | PARC-SIPADE | `OBTEREXTRATOPDF181` | Consultar | PROSPECTION | INVENTORIED | php=0 fe=0 | — |
| 25.2 | `parc_sipade.obterextratojson` | PARC-SIPADE | `OBTEREXTRATOJSON182` | Consultar | PROSPECTION | INVENTORIED | php=0 fe=0 | — |
| 25.3 | `parc_sipade.emitirdocarrecadacao` | PARC-SIPADE | `EMITIRDOCARRECADACAO183` | Emitir | PROSPECTION | INVENTORIED | php=0 fe=0 | MUT |

### No JSON local, sem referência de código (53)

| Cód. | operation_key | idSistema | idServico | Rota | Estado SERPRO | platform_support | refs | flags |
|------|---------------|-----------|-----------|------|---------------|------------------|------|-------|
| 12.1 | `ccmei.emitirccmei` | CCMEI | `EMITIRCCMEI121` | Emitir | PRODUCTION | IMPLEMENTED | php=1 fe=1 | contrato documental, cofre tenant-scoped e painel de emissão |
| 13.1 | `eventosatualizacao.soliceventospf` | EVENTOSATUALIZACAO | `SOLICEVENTOSPF131` | Monitorar | PRODUCTION | IMPLEMENTED | php=1 fe=0 | — |
| 13.2 | `eventosatualizacao.soliceventospj` | EVENTOSATUALIZACAO | `SOLICEVENTOSPJ132` | Monitorar | PRODUCTION | IMPLEMENTED | php=1 fe=0 | — |
| 13.4 | `eventosatualizacao.obtereventospj` | EVENTOSATUALIZACAO | `OBTEREVENTOSPJ134` | Monitorar | PRODUCTION | IMPLEMENTED | php=1 fe=0 | — |
| 20.1 | `parcmei.gerardas` | PARCMEI | `GERARDAS201` | Emitir | PRODUCTION | IMPLEMENTED | php=0 fe=0 | MUT |
| 20.2 | `parcmei.parcelasparagerar` | PARCMEI | `PARCELASPARAGERAR202` | Consultar | PRODUCTION | IMPLEMENTED | php=0 fe=0 | — |
| 20.3 | `parcmei.pedidosparc` | PARCMEI | `PEDIDOSPARC203` | Consultar | PRODUCTION | IMPLEMENTED | php=0 fe=0 | — |
| 20.4 | `parcmei.obterparc` | PARCMEI | `OBTERPARC204` | Consultar | PRODUCTION | IMPLEMENTED | php=0 fe=0 | — |
| 20.5 | `parcmei.detpagtoparc` | PARCMEI | `DETPAGTOPARC205` | Consultar | PRODUCTION | IMPLEMENTED | php=0 fe=0 | — |
| 21.1 | `parcmei_esp.gerardas` | PARCMEI-ESP | `GERARDAS211` | Emitir | PRODUCTION | IMPLEMENTED | php=0 fe=0 | MUT |
| 21.2 | `parcmei_esp.parcelasparagerar` | PARCMEI-ESP | `PARCELASPARAGERAR212` | Consultar | PRODUCTION | IMPLEMENTED | php=0 fe=0 | — |
| 21.3 | `parcmei_esp.pedidosparc` | PARCMEI-ESP | `PEDIDOSPARC213` | Consultar | PRODUCTION | IMPLEMENTED | php=0 fe=0 | — |
| 21.4 | `parcmei_esp.obterparc` | PARCMEI-ESP | `OBTERPARC214` | Consultar | PRODUCTION | IMPLEMENTED | php=0 fe=0 | — |
| 21.5 | `parcmei_esp.detpagtoparc` | PARCMEI-ESP | `DETPAGTOPARC215` | Consultar | PRODUCTION | IMPLEMENTED | php=0 fe=0 | — |
| 16.1 | `parcsn.gerardas` | PARCSN | `GERARDAS161` | Emitir | PRODUCTION | IMPLEMENTED | php=0 fe=0 | MUT |
| 16.2 | `parcsn.parcelasparagerar` | PARCSN | `PARCELASPARAGERAR162` | Consultar | PRODUCTION | IMPLEMENTED | php=0 fe=0 | — |
| 16.4 | `parcsn.obterparc` | PARCSN | `OBTERPARC164` | Consultar | PRODUCTION | IMPLEMENTED | php=0 fe=0 | — |
| 16.5 | `parcsn.detpagtoparc` | PARCSN | `DETPAGTOPARC165` | Consultar | PRODUCTION | IMPLEMENTED | php=0 fe=0 | — |
| 17.1 | `parcsn_esp.gerardas` | PARCSN-ESP | `GERARDAS171` | Emitir | PRODUCTION | IMPLEMENTED | php=0 fe=0 | MUT |
| 17.2 | `parcsn_esp.parcelasparagerar` | PARCSN-ESP | `PARCELASPARAGERAR172` | Consultar | PRODUCTION | IMPLEMENTED | php=0 fe=0 | — |
| 17.3 | `parcsn_esp.pedidosparc` | PARCSN-ESP | `PEDIDOSPARC173` | Consultar | PRODUCTION | IMPLEMENTED | php=0 fe=0 | — |
| 17.5 | `parcsn_esp.detpagtoparc` | PARCSN-ESP | `DETPAGTOPARC175` | Consultar | PRODUCTION | IMPLEMENTED | php=0 fe=0 | — |
| 7.4 | `parcsn_esp.obterparc` | PARCSN-ESP | `OBTERPARC174` | Consultar | PRODUCTION | IMPLEMENTED | php=0 fe=0 | — |
| 22.1 | `pertmei.gerardas` | PERTMEI | `GERARDAS221` | Emitir | PRODUCTION | IMPLEMENTED | php=0 fe=0 | MUT |
| 22.2 | `pertmei.parcelasparagerar` | PERTMEI | `PARCELASPARAGERAR222` | Consultar | PRODUCTION | IMPLEMENTED | php=0 fe=0 | — |
| 22.3 | `pertmei.pedidosparc` | PERTMEI | `PEDIDOSPARC223` | Consultar | PRODUCTION | IMPLEMENTED | php=0 fe=0 | — |
| 22.4 | `pertmei.obterparc` | PERTMEI | `OBTERPARC224` | Consultar | PRODUCTION | IMPLEMENTED | php=0 fe=0 | — |
| 22.5 | `pertmei.detpagtoparc` | PERTMEI | `DETPAGTOPARC225` | Consultar | PRODUCTION | IMPLEMENTED | php=0 fe=0 | — |
| 18.1 | `pertsn.gerardas` | PERTSN | `GERARDAS181` | Emitir | PRODUCTION | IMPLEMENTED | php=0 fe=0 | MUT |
| 18.2 | `pertsn.parcelasparagerar` | PERTSN | `PARCELASPARAGERAR182` | Consultar | PRODUCTION | IMPLEMENTED | php=0 fe=0 | — |
| 18.3 | `pertsn.pedidosparc` | PERTSN | `PEDIDOSPARC183` | Consultar | PRODUCTION | IMPLEMENTED | php=0 fe=0 | — |
| 18.4 | `pertsn.obterparc` | PERTSN | `OBTERPARC184` | Consultar | PRODUCTION | IMPLEMENTED | php=0 fe=0 | — |
| 18.5 | `pertsn.detpagtoparc` | PERTSN | `DETPAGTOPARC185` | Consultar | PRODUCTION | IMPLEMENTED | php=0 fe=0 | — |
| 1.7 | `pgdasd.gerardascobranca` | PGDASD | `GERARDASCOBRANCA17` | Emitir | PRODUCTION | IMPLEMENTED | php=0 fe=0 | MUT |
| 1.8 | `pgdasd.gerardasprocesso` | PGDASD | `GERARDASPROCESSO18` | Emitir | PRODUCTION | IMPLEMENTED | php=0 fe=0 | MUT |
| 1.9 | `pgdasd.gerardasavulso` | PGDASD | `GERARDASAVULSO19` | Emitir | PRODUCTION | IMPLEMENTED | php=0 fe=0 | MUT |
| 2.2 | `pgmei.gerardascodbarra` | PGMEI | `GERARDASCODBARRA22` | Emitir | PRODUCTION | IMPLEMENTED | php=0 fe=0 | MUT |
| 2.3 | `pgmei.atubeneficio` | PGMEI | `ATUBENEFICIO23` | Emitir | PRODUCTION | IMPLEMENTED | php=0 fe=0 | MUT |
| 26.2 | `pnr_contador.solicitar_renuncia` | PNRCONTADOR | `SOLICRENUNCIA262` | Declarar | PRODUCTION | IMPLEMENTED | php=1 fe=0 | MUT |
| 26.3 | `pnr_contador.consultar_renuncias` | PNRCONTADOR | `CONSRENUNCIA263` | Consultar | PRODUCTION | IMPLEMENTED | php=1 fe=0 | — |
| 26.4 | `pnr_contador.emitir_comprovante` | PNRCONTADOR | `COMPRENUNCIA264` | Emitir | PRODUCTION | IMPLEMENTED | php=1 fe=0 | — |
| 26.5 | `pnr_contador.situacao_renuncia` | PNRCONTADOR | `SITSOLICRENUNCIA265` | Consultar | PRODUCTION | IMPLEMENTED | php=1 fe=0 | — |
| 10.1 | `regimeapuracao.efetuaropcaoregime` | REGIMEAPURACAO | `EFETUAROPCAOREGIME101` | Declarar | PRODUCTION | IMPLEMENTED | php=0 fe=0 | MUT |
| 23.1 | `relpmei.gerardas` | RELPMEI | `GERARDAS231` | Emitir | PRODUCTION | IMPLEMENTED | php=0 fe=0 | MUT |
| 23.2 | `relpmei.parcelasparagerar` | RELPMEI | `PARCELASPARAGERAR232` | Consultar | PRODUCTION | IMPLEMENTED | php=0 fe=0 | — |
| 23.3 | `relpmei.pedidosparc` | RELPMEI | `PEDIDOSPARC233` | Consultar | PRODUCTION | IMPLEMENTED | php=0 fe=0 | — |
| 23.4 | `relpmei.obterparc` | RELPMEI | `OBTERPARC234` | Consultar | PRODUCTION | IMPLEMENTED | php=0 fe=0 | — |
| 23.5 | `relpmei.detpagtoparc` | RELPMEI | `DETPAGTOPARC235` | Consultar | PRODUCTION | IMPLEMENTED | php=0 fe=0 | — |
| 19.1 | `relpsn.gerardas` | RELPSN | `GERARDAS191` | Emitir | PRODUCTION | IMPLEMENTED | php=0 fe=0 | MUT |
| 19.2 | `relpsn.parcelasparagerar` | RELPSN | `PARCELASPARAGERAR192` | Consultar | PRODUCTION | IMPLEMENTED | php=0 fe=0 | — |
| 19.3 | `relpsn.pedidosparc` | RELPSN | `PEDIDOSPARC193` | Consultar | PRODUCTION | IMPLEMENTED | php=0 fe=0 | — |
| 19.4 | `relpsn.obterparc` | RELPSN | `OBTERPARC194` | Consultar | PRODUCTION | IMPLEMENTED | php=0 fe=0 | — |
| 19.5 | `relpsn.detpagtoparc` | RELPSN | `DETPAGTOPARC195` | Consultar | PRODUCTION | IMPLEMENTED | php=0 fe=0 | — |

### Referenciado (catálogo/import/guard) sem fluxo dedicado (19)

| Cód. | operation_key | idSistema | idServico | Rota | Estado SERPRO | platform_support | refs | flags |
|------|---------------|-----------|-----------|------|---------------|------------------|------|-------|
| 6.2 | `caixa_postal.detalhe` | CAIXAPOSTAL | `MSGDETALHAMENTO62` | Consultar | PRODUCTION | IMPLEMENTED | php=5 fe=0 | — |
| 6.3 | `caixa_postal.indicador` | CAIXAPOSTAL | `INNOVAMSG63` | Monitorar | PRODUCTION | IMPLEMENTED | php=4 fe=0 | — |
| 3.1 | `dctfweb.gerarguia` | DCTFWEB | `GERARGUIA31` | Emitir | PRODUCTION | IMPLEMENTED | php=2 fe=0 | MUT |
| 3.10 | `dctfweb.transdeclaracao` | DCTFWEB | `TRANSDECLARACAO310` | Declarar | PRODUCTION | IMPLEMENTED | php=1 fe=0 | MUT |
| 3.13 | `dctfweb.gerarguiaandamento` | DCTFWEB | `GERARGUIAANDAMENTO313` | Emitir | PRODUCTION | IMPLEMENTED | php=1 fe=0 | MUT |
| 3.3 | `dctfweb.consdeccompleta` | DCTFWEB | `CONSDECCOMPLETA33` | Consultar | PRODUCTION | IMPLEMENTED | php=3 fe=0 | — |
| 3.8 | `dctfweb.consxmldeclaracao` | DCTFWEB | `CONSXMLDECLARACAO38` | Consultar | PRODUCTION | IMPLEMENTED | php=3 fe=0 | — |
| 14.1 | `defis.transdeclaracao` | DEFIS | `TRANSDECLARACAO141` | Declarar | PRODUCTION | IMPLEMENTED | php=1 fe=0 | MUT |
| 11.1 | `dte.consultar` | DTE | `CONSULTASITUACAODTE111` | Consultar | PRODUCTION | IMPLEMENTED | php=7 fe=1 | fe |
| 13.3 | `eventosatualizacao.obtereventospf` | EVENTOSATUALIZACAO | `OBTEREVENTOSPF133` | Monitorar | PRODUCTION | IMPLEMENTED | php=2 fe=0 | test,flow |
| 3.14 | `mit.encapuracao` | MIT | `ENCAPURACAO314` | Declarar | PRODUCTION | IMPLEMENTED | php=1 fe=0 | MUT |
| 3.15 | `mit.situacaoenc` | MIT | `SITUACAOENC315` | Apoiar | PRODUCTION | IMPLEMENTED | php=3 fe=0 | — |
| 7.2 | `pagtoweb.comparrecadacao` | PAGTOWEB | `COMPARRECADACAO72` | Emitir | PRODUCTION | IMPLEMENTED | php=2 fe=1 | fe |
| 16.3 | `parcsn.pedidosparc` | PARCSN | `PEDIDOSPARC163` | Consultar | PRODUCTION | IMPLEMENTED | php=1 fe=0 | test |
| 1.1 | `pgdasd.transdeclaracao` | PGDASD | `TRANSDECLARACAO11` | Declarar | PRODUCTION | IMPLEMENTED | php=1 fe=0 | MUT |
| 1.2 | `pgdasd.gerardas` | PGDASD | `GERARDAS12` | Emitir | PRODUCTION | IMPLEMENTED | php=2 fe=0 | MUT |
| 2.1 | `pgmei.gerardaspdf` | PGMEI | `GERARDASPDF21` | Emitir | PRODUCTION | IMPLEMENTED | php=1 fe=0 | MUT |
| 10.3 | `regimeapuracao.consultaropcaoregime` | REGIMEAPURACAO | `CONSULTAROPCAOREGIME103` | Consultar | PRODUCTION | IMPLEMENTED | php=5 fe=0 | — |
| 5.3 | `sicalc.gerardarfcodbarra` | SICALC | `GERARDARFCODBARRA53` | Emitir | PRODUCTION | IMPLEMENTED | php=1 fe=0 | MUT |

### Serviço/fluxo parcial no backend (18)

| Cód. | operation_key | idSistema | idServico | Rota | Estado SERPRO | platform_support | refs | flags |
|------|---------------|-----------|-----------|------|---------------|------------------|------|-------|
| 8.1 | `autentica_procurador.envio_xml_assinado` | AUTENTICAPROCURADOR | `ENVIOXMLASSINADO81` | Apoiar | PRODUCTION | IMPLEMENTED | php=8 fe=0 | test |
| 6.1 | `caixa_postal.lista` | CAIXAPOSTAL | `MSGCONTRIBUINTE61` | Consultar | PRODUCTION | IMPLEMENTED | php=6 fe=0 | test |
| 12.3 | `ccmei.ccmeisitcadastral` | CCMEI | `CCMEISITCADASTRAL123` | Consultar | PRODUCTION | IMPLEMENTED | php=7 fe=0 | flow |
| 3.2 | `dctfweb.consrecibo` | DCTFWEB | `CONSRECIBO32` | Consultar | PRODUCTION | IMPLEMENTED | php=8 fe=0 | flow |
| 14.2 | `defis.consdeclaracao` | DEFIS | `CONSDECLARACAO142` | Consultar | PRODUCTION | IMPLEMENTED | php=5 fe=0 | flow |
| 14.3 | `defis.consultimadecrec` | DEFIS | `CONSULTIMADECREC143` | Consultar | PRODUCTION | IMPLEMENTED | php=7 fe=0 | flow |
| 14.4 | `defis.consdecrec` | DEFIS | `CONSDECREC144` | Consultar | PRODUCTION | IMPLEMENTED | php=8 fe=0 | flow |
| 27.1 | `eprocesso.consultar_por_interessado` | EPROCESSO | `CONSPROCPORINTER271` | Consultar | PRODUCTION | IMPLEMENTED | php=7 fe=0 | test |
| 3.16 | `mit.consapuracao` | MIT | `CONSAPURACAO316` | Consultar | PRODUCTION | IMPLEMENTED | php=4 fe=0 | test |
| 3.17 | `mit.listaapuracoes` | MIT | `LISTAAPURACOES317` | Consultar | PRODUCTION | IMPLEMENTED | php=7 fe=0 | test |
| 7.3 | `pagtoweb.contaconsdocarrpg` | PAGTOWEB | `CONTACONSDOCARRPG73` | Consultar | PRODUCTION | IMPLEMENTED | php=5 fe=0 | flow |
| 1.5 | `pgdasd.consdecrec` | PGDASD | `CONSDECREC15` | Consultar | PRODUCTION | IMPLEMENTED | php=11 fe=0 | flow |
| 1.6 | `pgdasd.consextrato` | PGDASD | `CONSEXTRATO16` | Consultar | PRODUCTION | IMPLEMENTED | php=11 fe=0 | flow |
| 26.1 | `pnr_contador.consultar_vinculos` | PNRCONTADOR | `CONSVINCULOS261` | Consultar | PRODUCTION | IMPLEMENTED | php=6 fe=0 | test |
| 4.1 | `procuracoes.obter` | PROCURACOES | `OBTERPROCURACAO41` | Consultar | PRODUCTION | IMPLEMENTED | php=6 fe=0 | test |
| 10.2 | `regimeapuracao.consultaranoscalendarios` | REGIMEAPURACAO | `CONSULTARANOSCALENDARIOS102` | Consultar | PRODUCTION | IMPLEMENTED | php=7 fe=0 | flow |
| 10.4 | `regimeapuracao.consultarresolucao` | REGIMEAPURACAO | `CONSULTARRESOLUCAO104` | Consultar | PRODUCTION | IMPLEMENTED | php=7 fe=0 | test |
| 5.1 | `sicalc.consolidargerardarf` | SICALC | `CONSOLIDARGERARDARF51` | Emitir | PRODUCTION | IMPLEMENTED | php=5 fe=0 | test,MUT |

### Fluxo backend + testes (6)

| Cód. | operation_key | idSistema | idServico | Rota | Estado SERPRO | platform_support | refs | flags |
|------|---------------|-----------|-----------|------|---------------|------------------|------|-------|
| 7.1 | `pagtoweb.pagamentos` | PAGTOWEB | `PAGAMENTOS71` | Consultar | PRODUCTION | IMPLEMENTED | php=8 fe=0 | test,flow |
| 1.3 | `pgdasd.consdeclaracao` | PGDASD | `CONSDECLARACAO13` | Consultar | PRODUCTION | IMPLEMENTED | php=13 fe=0 | test,flow |
| 1.4 | `pgdasd.consultimadecrec` | PGDASD | `CONSULTIMADECREC14` | Consultar | PRODUCTION | IMPLEMENTED | php=11 fe=0 | test,flow |
| 2.4 | `pgmei.dividaativa` | PGMEI | `DIVIDAATIVA24` | Consultar | PRODUCTION | IMPLEMENTED | php=8 fe=0 | test,flow |
| 5.2 | `sicalc.consultaapoioreceitas` | SICALC | `CONSULTAAPOIORECEITAS52` | Apoiar | PRODUCTION | IMPLEMENTED | php=7 fe=0 | test,flow |
| 9.2 | `sitfis.emitir_relatorio` | SITFIS | `RELATORIOSITFIS92` | Emitir | PRODUCTION | IMPLEMENTED | php=14 fe=0 | test,flow |

### Fluxo backend + UI/FE (2)

| Cód. | operation_key | idSistema | idServico | Rota | Estado SERPRO | platform_support | refs | flags |
|------|---------------|-----------|-----------|------|---------------|------------------|------|-------|
| 12.2 | `ccmei.dadosccmei` | CCMEI | `DADOSCCMEI122` | Consultar | PRODUCTION | IMPLEMENTED | php=5 fe=1 | test,flow,fe |
| 9.1 | `sitfis.solicitar_protocolo` | SITFIS | `SOLICITARPROTOCOLO91` | Apoiar | PRODUCTION | IMPLEMENTED | php=18 fe=2 | test,flow,fe |

## TODO list (prioridade de produto)

### P0 — `PRODUCTION` no catálogo, ainda `BLOCKED`; preparar canário aprovado
- [ ] SITFIS: somente após aprovação, validar solicitar→aguardar→emitir até estado terminal e documento sanitizado
- [ ] Autentica Procurador + poder e-CAC (TODOS/matriz) estável no piloto
- [ ] Procurações OBTERPROCURACAO41 sync + inventory
- [ ] PGDASD consultas read-only no explorador/manual
- [ ] Caixa Postal lista/detalhe/indicador + DTE

### P1 — PRODUCTION SERPRO com fluxo parcial / pouco UI
- [ ] `autentica_procurador.envio_xml_assinado` (AUTENTICAPROCURADOR/ENVIOXMLASSINADO81) — maturidade D_HUB_SERVICO_PARCIAL
- [ ] `caixa_postal.detalhe` (CAIXAPOSTAL/MSGDETALHAMENTO62) — maturidade C_HUB_COORDENADA_REFERENCIADA
- [ ] `caixa_postal.indicador` (CAIXAPOSTAL/INNOVAMSG63) — maturidade C_HUB_COORDENADA_REFERENCIADA
- [ ] `caixa_postal.lista` (CAIXAPOSTAL/MSGCONTRIBUINTE61) — maturidade D_HUB_SERVICO_PARCIAL
- [ ] `ccmei.ccmeisitcadastral` (CCMEI/CCMEISITCADASTRAL123) — maturidade D_HUB_SERVICO_PARCIAL
- [x] `ccmei.emitirccmei` (CCMEI/EMITIRCCMEI121) — maturidade D_HUB_SERVICO_LOCAL_VALIDADO; Trial/canário produtivo pendentes
- [ ] `dctfweb.consdeccompleta` (DCTFWEB/CONSDECCOMPLETA33) — maturidade C_HUB_COORDENADA_REFERENCIADA
- [ ] `dctfweb.consrecibo` (DCTFWEB/CONSRECIBO32) — maturidade D_HUB_SERVICO_PARCIAL
- [ ] `dctfweb.consxmldeclaracao` (DCTFWEB/CONSXMLDECLARACAO38) — maturidade C_HUB_COORDENADA_REFERENCIADA
- [ ] `dctfweb.gerarguia` (DCTFWEB/GERARGUIA31) — maturidade C_HUB_COORDENADA_REFERENCIADA
- [ ] `dctfweb.gerarguiaandamento` (DCTFWEB/GERARGUIAANDAMENTO313) — maturidade C_HUB_COORDENADA_REFERENCIADA
- [ ] `dctfweb.transdeclaracao` (DCTFWEB/TRANSDECLARACAO310) — maturidade C_HUB_COORDENADA_REFERENCIADA
- [ ] `defis.consdeclaracao` (DEFIS/CONSDECLARACAO142) — maturidade D_HUB_SERVICO_PARCIAL
- [ ] `defis.consdecrec` (DEFIS/CONSDECREC144) — maturidade D_HUB_SERVICO_PARCIAL
- [ ] `defis.consultimadecrec` (DEFIS/CONSULTIMADECREC143) — maturidade D_HUB_SERVICO_PARCIAL
- [ ] `defis.transdeclaracao` (DEFIS/TRANSDECLARACAO141) — maturidade C_HUB_COORDENADA_REFERENCIADA
- [ ] `dte.consultar` (DTE/CONSULTASITUACAODTE111) — maturidade C_HUB_COORDENADA_REFERENCIADA
- [ ] `eprocesso.consultar_por_interessado` (EPROCESSO/CONSPROCPORINTER271) — maturidade D_HUB_SERVICO_PARCIAL
- [ ] `eventosatualizacao.obtereventospf` (EVENTOSATUALIZACAO/OBTEREVENTOSPF133) — maturidade C_HUB_COORDENADA_REFERENCIADA
- [ ] `eventosatualizacao.obtereventospj` (EVENTOSATUALIZACAO/OBTEREVENTOSPJ134) — maturidade C0_HUB_SO_CATALOGO_JSON
- [ ] `eventosatualizacao.soliceventospf` (EVENTOSATUALIZACAO/SOLICEVENTOSPF131) — maturidade C0_HUB_SO_CATALOGO_JSON
- [ ] `eventosatualizacao.soliceventospj` (EVENTOSATUALIZACAO/SOLICEVENTOSPJ132) — maturidade C0_HUB_SO_CATALOGO_JSON
- [ ] `mit.consapuracao` (MIT/CONSAPURACAO316) — maturidade D_HUB_SERVICO_PARCIAL
- [ ] `mit.encapuracao` (MIT/ENCAPURACAO314) — maturidade C_HUB_COORDENADA_REFERENCIADA
- [ ] `mit.listaapuracoes` (MIT/LISTAAPURACOES317) — maturidade D_HUB_SERVICO_PARCIAL
- [ ] `mit.situacaoenc` (MIT/SITUACAOENC315) — maturidade C_HUB_COORDENADA_REFERENCIADA
- [x] `pagtoweb.comparrecadacao` (PAGTOWEB/COMPARRECADACAO72) — maturidade B_HUB_FLUXO_LOCAL_COM_COFRE
- [ ] `pagtoweb.contaconsdocarrpg` (PAGTOWEB/CONTACONSDOCARRPG73) — maturidade D_HUB_SERVICO_PARCIAL
- [ ] `parcmei.detpagtoparc` (PARCMEI/DETPAGTOPARC205) — maturidade C0_HUB_SO_CATALOGO_JSON
- [ ] `parcmei.gerardas` (PARCMEI/GERARDAS201) — maturidade C0_HUB_SO_CATALOGO_JSON
- [ ] `parcmei.obterparc` (PARCMEI/OBTERPARC204) — maturidade C0_HUB_SO_CATALOGO_JSON
- [ ] `parcmei.parcelasparagerar` (PARCMEI/PARCELASPARAGERAR202) — maturidade C0_HUB_SO_CATALOGO_JSON
- [ ] `parcmei.pedidosparc` (PARCMEI/PEDIDOSPARC203) — maturidade C0_HUB_SO_CATALOGO_JSON
- [ ] `parcmei_esp.detpagtoparc` (PARCMEI-ESP/DETPAGTOPARC215) — maturidade C0_HUB_SO_CATALOGO_JSON
- [ ] `parcmei_esp.gerardas` (PARCMEI-ESP/GERARDAS211) — maturidade C0_HUB_SO_CATALOGO_JSON
- [ ] `parcmei_esp.obterparc` (PARCMEI-ESP/OBTERPARC214) — maturidade C0_HUB_SO_CATALOGO_JSON
- [ ] `parcmei_esp.parcelasparagerar` (PARCMEI-ESP/PARCELASPARAGERAR212) — maturidade C0_HUB_SO_CATALOGO_JSON
- [ ] `parcmei_esp.pedidosparc` (PARCMEI-ESP/PEDIDOSPARC213) — maturidade C0_HUB_SO_CATALOGO_JSON
- [ ] `parcsn.detpagtoparc` (PARCSN/DETPAGTOPARC165) — maturidade C0_HUB_SO_CATALOGO_JSON
- [ ] `parcsn.gerardas` (PARCSN/GERARDAS161) — maturidade C0_HUB_SO_CATALOGO_JSON
- [ ] `parcsn.obterparc` (PARCSN/OBTERPARC164) — maturidade C0_HUB_SO_CATALOGO_JSON
- [ ] `parcsn.parcelasparagerar` (PARCSN/PARCELASPARAGERAR162) — maturidade C0_HUB_SO_CATALOGO_JSON
- [ ] `parcsn.pedidosparc` (PARCSN/PEDIDOSPARC163) — maturidade C_HUB_COORDENADA_REFERENCIADA
- [ ] `parcsn_esp.detpagtoparc` (PARCSN-ESP/DETPAGTOPARC175) — maturidade C0_HUB_SO_CATALOGO_JSON
- [ ] `parcsn_esp.gerardas` (PARCSN-ESP/GERARDAS171) — maturidade C0_HUB_SO_CATALOGO_JSON
- [ ] `parcsn_esp.obterparc` (PARCSN-ESP/OBTERPARC174) — maturidade C0_HUB_SO_CATALOGO_JSON
- [ ] `parcsn_esp.parcelasparagerar` (PARCSN-ESP/PARCELASPARAGERAR172) — maturidade C0_HUB_SO_CATALOGO_JSON
- [ ] `parcsn_esp.pedidosparc` (PARCSN-ESP/PEDIDOSPARC173) — maturidade C0_HUB_SO_CATALOGO_JSON
- [ ] `pertmei.detpagtoparc` (PERTMEI/DETPAGTOPARC225) — maturidade C0_HUB_SO_CATALOGO_JSON
- [ ] `pertmei.gerardas` (PERTMEI/GERARDAS221) — maturidade C0_HUB_SO_CATALOGO_JSON
- [ ] `pertmei.obterparc` (PERTMEI/OBTERPARC224) — maturidade C0_HUB_SO_CATALOGO_JSON
- [ ] `pertmei.parcelasparagerar` (PERTMEI/PARCELASPARAGERAR222) — maturidade C0_HUB_SO_CATALOGO_JSON
- [ ] `pertmei.pedidosparc` (PERTMEI/PEDIDOSPARC223) — maturidade C0_HUB_SO_CATALOGO_JSON
- [ ] `pertsn.detpagtoparc` (PERTSN/DETPAGTOPARC185) — maturidade C0_HUB_SO_CATALOGO_JSON
- [ ] `pertsn.gerardas` (PERTSN/GERARDAS181) — maturidade C0_HUB_SO_CATALOGO_JSON
- [ ] `pertsn.obterparc` (PERTSN/OBTERPARC184) — maturidade C0_HUB_SO_CATALOGO_JSON
- [ ] `pertsn.parcelasparagerar` (PERTSN/PARCELASPARAGERAR182) — maturidade C0_HUB_SO_CATALOGO_JSON
- [ ] `pertsn.pedidosparc` (PERTSN/PEDIDOSPARC183) — maturidade C0_HUB_SO_CATALOGO_JSON
- [ ] `pgdasd.consdecrec` (PGDASD/CONSDECREC15) — maturidade D_HUB_SERVICO_PARCIAL
- [ ] `pgdasd.consextrato` (PGDASD/CONSEXTRATO16) — maturidade D_HUB_SERVICO_PARCIAL
- [ ] `pgdasd.gerardas` (PGDASD/GERARDAS12) — maturidade C_HUB_COORDENADA_REFERENCIADA
- [ ] `pgdasd.gerardasavulso` (PGDASD/GERARDASAVULSO19) — maturidade C0_HUB_SO_CATALOGO_JSON
- [ ] `pgdasd.gerardascobranca` (PGDASD/GERARDASCOBRANCA17) — maturidade C0_HUB_SO_CATALOGO_JSON
- [ ] `pgdasd.gerardasprocesso` (PGDASD/GERARDASPROCESSO18) — maturidade C0_HUB_SO_CATALOGO_JSON
- [ ] `pgdasd.transdeclaracao` (PGDASD/TRANSDECLARACAO11) — maturidade C_HUB_COORDENADA_REFERENCIADA
- [ ] `pgmei.atubeneficio` (PGMEI/ATUBENEFICIO23) — maturidade C0_HUB_SO_CATALOGO_JSON
- [ ] `pgmei.gerardascodbarra` (PGMEI/GERARDASCODBARRA22) — maturidade C0_HUB_SO_CATALOGO_JSON
- [ ] `pgmei.gerardaspdf` (PGMEI/GERARDASPDF21) — maturidade C_HUB_COORDENADA_REFERENCIADA
- [ ] `pnr_contador.consultar_renuncias` (PNRCONTADOR/CONSRENUNCIA263) — maturidade C0_HUB_SO_CATALOGO_JSON
- [ ] `pnr_contador.consultar_vinculos` (PNRCONTADOR/CONSVINCULOS261) — maturidade D_HUB_SERVICO_PARCIAL
- [ ] `pnr_contador.emitir_comprovante` (PNRCONTADOR/COMPRENUNCIA264) — maturidade C0_HUB_SO_CATALOGO_JSON
- [ ] `pnr_contador.situacao_renuncia` (PNRCONTADOR/SITSOLICRENUNCIA265) — maturidade C0_HUB_SO_CATALOGO_JSON
- [ ] `pnr_contador.solicitar_renuncia` (PNRCONTADOR/SOLICRENUNCIA262) — maturidade C0_HUB_SO_CATALOGO_JSON
- [ ] `procuracoes.obter` (PROCURACOES/OBTERPROCURACAO41) — maturidade D_HUB_SERVICO_PARCIAL
- [ ] `regimeapuracao.consultaranoscalendarios` (REGIMEAPURACAO/CONSULTARANOSCALENDARIOS102) — maturidade D_HUB_SERVICO_PARCIAL
- [ ] `regimeapuracao.consultaropcaoregime` (REGIMEAPURACAO/CONSULTAROPCAOREGIME103) — maturidade C_HUB_COORDENADA_REFERENCIADA
- [ ] `regimeapuracao.consultarresolucao` (REGIMEAPURACAO/CONSULTARRESOLUCAO104) — maturidade D_HUB_SERVICO_PARCIAL
- [ ] `regimeapuracao.efetuaropcaoregime` (REGIMEAPURACAO/EFETUAROPCAOREGIME101) — maturidade C0_HUB_SO_CATALOGO_JSON
- [ ] `relpmei.detpagtoparc` (RELPMEI/DETPAGTOPARC235) — maturidade C0_HUB_SO_CATALOGO_JSON
- [ ] `relpmei.gerardas` (RELPMEI/GERARDAS231) — maturidade C0_HUB_SO_CATALOGO_JSON
- [ ] `relpmei.obterparc` (RELPMEI/OBTERPARC234) — maturidade C0_HUB_SO_CATALOGO_JSON
- [ ] `relpmei.parcelasparagerar` (RELPMEI/PARCELASPARAGERAR232) — maturidade C0_HUB_SO_CATALOGO_JSON
- [ ] `relpmei.pedidosparc` (RELPMEI/PEDIDOSPARC233) — maturidade C0_HUB_SO_CATALOGO_JSON
- [ ] `relpsn.detpagtoparc` (RELPSN/DETPAGTOPARC195) — maturidade C0_HUB_SO_CATALOGO_JSON
- [ ] `relpsn.gerardas` (RELPSN/GERARDAS191) — maturidade C0_HUB_SO_CATALOGO_JSON
- [ ] `relpsn.obterparc` (RELPSN/OBTERPARC194) — maturidade C0_HUB_SO_CATALOGO_JSON
- [ ] `relpsn.parcelasparagerar` (RELPSN/PARCELASPARAGERAR192) — maturidade C0_HUB_SO_CATALOGO_JSON
- [ ] `relpsn.pedidosparc` (RELPSN/PEDIDOSPARC193) — maturidade C0_HUB_SO_CATALOGO_JSON
- [ ] `sicalc.consolidargerardarf` (SICALC/CONSOLIDARGERARDARF51) — maturidade D_HUB_SERVICO_PARCIAL
- [ ] `sicalc.gerardarfcodbarra` (SICALC/GERARDARFCODBARRA53) — maturidade C_HUB_COORDENADA_REFERENCIADA

### P2 — PROSPECTION SERPRO (não executar como produtivo; reavaliar quando virar PRODUCTION)
- [ ] inventário/acompanhar: `dasnsimei.consultimadecrec`
- [ ] inventário/acompanhar: `dasnsimei.gerardasexcesso`
- [ ] inventário/acompanhar: `dasnsimei.transdeclaracao`
- [ ] inventário/acompanhar: `dctfweb.aplvinculacao`
- [ ] inventário/acompanhar: `dctfweb.consnotifmaed`
- [ ] inventário/acompanhar: `dctfweb.consrelcredito`
- [ ] inventário/acompanhar: `dctfweb.consreldebito`
- [ ] inventário/acompanhar: `dctfweb.editarvalorsuspenso`
- [ ] inventário/acompanhar: `dctfweb.gerarguiacomabatimento`
- [ ] inventário/acompanhar: `dctfweb.gerarguiamaed`
- [ ] inventário/acompanhar: `eprocesso.conscomunintim`
- [ ] inventário/acompanhar: `eprocesso.obtdocproc`
- [ ] inventário/acompanhar: `eprocesso.obtlistdocsproc`
- [ ] inventário/acompanhar: `parc_paex.emitirdocarrecadacao`
- [ ] inventário/acompanhar: `parc_paex.obterextratojson`
- [ ] inventário/acompanhar: `parc_paex.obterextratopdf`
- [ ] inventário/acompanhar: `parc_sipade.emitirdocarrecadacao`
- [ ] inventário/acompanhar: `parc_sipade.obterextratojson`
- [ ] inventário/acompanhar: `parc_sipade.obterextratopdf`

### P3 — Não implementar
- [x] **não fazer** `autenticaprocurador.expirarautorizacao` (CANCELED)
- [x] **não fazer** `sicalc.consolidar` (UNDER_CONSTRUCTION)

## Histórico de probe legado (piloto 2026-07-18) — insuficiente

A trilha abaixo é preservada para auditoria, mas não é evidência vigente. O
resumo legado foi **31 `PASS_BUSINESS` + 34 `FAIL_SERPRO` + 33
`BLOCKED_HUB` = 98**. Nenhuma linha satisfaz `PASS_REAL_*` fresco com
proveniência `PRODUCTION_CANARY`; por isso, todas permanecem `BLOCKED`.

| operation_key | classificação legada | HTTP legado | erro legado | simulated reportado | status final atual | motivo da insuficiência |
|---|---|---:|---|---|---|---|
| `autentica_procurador.envio_xml_assinado` | `FAIL_SERPRO` | 403 | `REQUEST_FAILED` | False | `BLOCKED` | 4xx/5xx é falha, não aceite de negócio |
| `caixa_postal.detalhe` | `FAIL_SERPRO` | 400 | `REQUEST_FAILED` | False | `BLOCKED` | 4xx/5xx é falha, não aceite de negócio |
| `caixa_postal.indicador` | `PASS_BUSINESS` | 200 | `—` | False | `BLOCKED` | sem `PASS_REAL_*` fresco + `PRODUCTION_CANARY` |
| `caixa_postal.lista` | `FAIL_SERPRO` | 400 | `REQUEST_FAILED` | False | `BLOCKED` | 4xx/5xx é falha, não aceite de negócio |
| `ccmei.ccmeisitcadastral` | `FAIL_SERPRO` | 400 | `REQUEST_FAILED` | False | `BLOCKED` | 4xx/5xx é falha, não aceite de negócio |
| `ccmei.dadosccmei` | `PASS_BUSINESS` | 200 | `—` | False | `BLOCKED` | sem `PASS_REAL_*` fresco + `PRODUCTION_CANARY` |
| `ccmei.emitirccmei` | `PASS_BUSINESS` | 200 | `—` | False | `BLOCKED` | sem `PASS_REAL_*` fresco + `PRODUCTION_CANARY` |
| `dctfweb.consdeccompleta` | `PASS_BUSINESS` | 200 | `—` | False | `BLOCKED` | sem `PASS_REAL_*` fresco + `PRODUCTION_CANARY` |
| `dctfweb.consrecibo` | `PASS_BUSINESS` | 200 | `—` | False | `BLOCKED` | sem `PASS_REAL_*` fresco + `PRODUCTION_CANARY` |
| `dctfweb.consxmldeclaracao` | `PASS_BUSINESS` | 200 | `—` | False | `BLOCKED` | sem `PASS_REAL_*` fresco + `PRODUCTION_CANARY` |
| `dctfweb.gerarguia` | `BLOCKED_HUB` | 423 | `MUTATION_DISABLED` | False | `BLOCKED` | gate local impediu chamada ou conclusão |
| `dctfweb.gerarguiaandamento` | `BLOCKED_HUB` | 423 | `MUTATION_DISABLED` | False | `BLOCKED` | gate local impediu chamada ou conclusão |
| `dctfweb.transdeclaracao` | `BLOCKED_HUB` | 423 | `MUTATION_DISABLED` | False | `BLOCKED` | gate local impediu chamada ou conclusão |
| `defis.consdeclaracao` | `FAIL_SERPRO` | 400 | `REQUEST_FAILED` | False | `BLOCKED` | 4xx/5xx é falha, não aceite de negócio |
| `defis.consdecrec` | `FAIL_SERPRO` | 400 | `REQUEST_FAILED` | False | `BLOCKED` | 4xx/5xx é falha, não aceite de negócio |
| `defis.consultimadecrec` | `FAIL_SERPRO` | 400 | `REQUEST_FAILED` | False | `BLOCKED` | 4xx/5xx é falha, não aceite de negócio |
| `defis.transdeclaracao` | `BLOCKED_HUB` | 423 | `MUTATION_DISABLED` | False | `BLOCKED` | gate local impediu chamada ou conclusão |
| `dte.consultar` | `PASS_BUSINESS` | 200 | `—` | False | `BLOCKED` | sem `PASS_REAL_*` fresco + `PRODUCTION_CANARY` |
| `eprocesso.consultar_por_interessado` | `PASS_BUSINESS` | 200 | `—` | False | `BLOCKED` | sem `PASS_REAL_*` fresco + `PRODUCTION_CANARY` |
| `eventosatualizacao.obtereventospf` | `FAIL_SERPRO` | 400 | `REQUEST_FAILED` | False | `BLOCKED` | 4xx/5xx é falha, não aceite de negócio |
| `eventosatualizacao.obtereventospj` | `FAIL_SERPRO` | 400 | `REQUEST_FAILED` | False | `BLOCKED` | 4xx/5xx é falha, não aceite de negócio |
| `eventosatualizacao.soliceventospf` | `FAIL_SERPRO` | 400 | `REQUEST_FAILED` | False | `BLOCKED` | 4xx/5xx é falha, não aceite de negócio |
| `eventosatualizacao.soliceventospj` | `FAIL_SERPRO` | 400 | `REQUEST_FAILED` | False | `BLOCKED` | 4xx/5xx é falha, não aceite de negócio |
| `mit.consapuracao` | `FAIL_SERPRO` | 400 | `REQUEST_FAILED` | False | `BLOCKED` | 4xx/5xx é falha, não aceite de negócio |
| `mit.encapuracao` | `BLOCKED_HUB` | 423 | `MUTATION_DISABLED` | False | `BLOCKED` | gate local impediu chamada ou conclusão |
| `mit.listaapuracoes` | `FAIL_SERPRO` | 400 | `REQUEST_FAILED` | False | `BLOCKED` | 4xx/5xx é falha, não aceite de negócio |
| `mit.situacaoenc` | `FAIL_SERPRO` | 400 | `REQUEST_FAILED` | False | `BLOCKED` | 4xx/5xx é falha, não aceite de negócio |
| `pagtoweb.comparrecadacao` | `PASS_BUSINESS` | 200 | `—` | False | `BLOCKED` | sem `PASS_REAL_*` fresco + `PRODUCTION_CANARY` |
| `pagtoweb.contaconsdocarrpg` | `FAIL_SERPRO` | 400 | `REQUEST_FAILED` | False | `BLOCKED` | 4xx/5xx é falha, não aceite de negócio |
| `pagtoweb.pagamentos` | `PASS_BUSINESS` | 200 | `—` | False | `BLOCKED` | sem `PASS_REAL_*` fresco + `PRODUCTION_CANARY` |
| `parcmei.detpagtoparc` | `PASS_BUSINESS` | 200 | `—` | False | `BLOCKED` | sem `PASS_REAL_*` fresco + `PRODUCTION_CANARY` |
| `parcmei.gerardas` | `BLOCKED_HUB` | 423 | `MUTATION_DISABLED` | False | `BLOCKED` | gate local impediu chamada ou conclusão |
| `parcmei.obterparc` | `PASS_BUSINESS` | 200 | `—` | False | `BLOCKED` | sem `PASS_REAL_*` fresco + `PRODUCTION_CANARY` |
| `parcmei.parcelasparagerar` | `FAIL_SERPRO` | 400 | `REQUEST_FAILED` | False | `BLOCKED` | 4xx/5xx é falha, não aceite de negócio |
| `parcmei.pedidosparc` | `FAIL_SERPRO` | 400 | `REQUEST_FAILED` | False | `BLOCKED` | 4xx/5xx é falha, não aceite de negócio |
| `parcmei_esp.detpagtoparc` | `BLOCKED_HUB` | 422 | `PROXY_POWER_MISSING` | False | `BLOCKED` | gate local impediu chamada ou conclusão |
| `parcmei_esp.gerardas` | `BLOCKED_HUB` | 423 | `MUTATION_DISABLED` | False | `BLOCKED` | gate local impediu chamada ou conclusão |
| `parcmei_esp.obterparc` | `BLOCKED_HUB` | 422 | `PROXY_POWER_MISSING` | False | `BLOCKED` | gate local impediu chamada ou conclusão |
| `parcmei_esp.parcelasparagerar` | `BLOCKED_HUB` | 422 | `PROXY_POWER_MISSING` | False | `BLOCKED` | gate local impediu chamada ou conclusão |
| `parcmei_esp.pedidosparc` | `BLOCKED_HUB` | 422 | `PROXY_POWER_MISSING` | False | `BLOCKED` | gate local impediu chamada ou conclusão |
| `parcsn.detpagtoparc` | `PASS_BUSINESS` | 200 | `—` | False | `BLOCKED` | sem `PASS_REAL_*` fresco + `PRODUCTION_CANARY` |
| `parcsn.gerardas` | `BLOCKED_HUB` | 423 | `MUTATION_DISABLED` | False | `BLOCKED` | gate local impediu chamada ou conclusão |
| `parcsn.obterparc` | `PASS_BUSINESS` | 200 | `—` | False | `BLOCKED` | sem `PASS_REAL_*` fresco + `PRODUCTION_CANARY` |
| `parcsn.parcelasparagerar` | `FAIL_SERPRO` | 400 | `REQUEST_FAILED` | False | `BLOCKED` | 4xx/5xx é falha, não aceite de negócio |
| `parcsn.pedidosparc` | `FAIL_SERPRO` | 400 | `REQUEST_FAILED` | False | `BLOCKED` | 4xx/5xx é falha, não aceite de negócio |
| `parcsn_esp.detpagtoparc` | `BLOCKED_HUB` | 422 | `PROXY_POWER_MISSING` | False | `BLOCKED` | gate local impediu chamada ou conclusão |
| `parcsn_esp.gerardas` | `BLOCKED_HUB` | 423 | `MUTATION_DISABLED` | False | `BLOCKED` | gate local impediu chamada ou conclusão |
| `parcsn_esp.obterparc` | `BLOCKED_HUB` | 422 | `PROXY_POWER_MISSING` | False | `BLOCKED` | gate local impediu chamada ou conclusão |
| `parcsn_esp.parcelasparagerar` | `BLOCKED_HUB` | 422 | `PROXY_POWER_MISSING` | False | `BLOCKED` | gate local impediu chamada ou conclusão |
| `parcsn_esp.pedidosparc` | `BLOCKED_HUB` | 422 | `PROXY_POWER_MISSING` | False | `BLOCKED` | gate local impediu chamada ou conclusão |
| `pertmei.detpagtoparc` | `PASS_BUSINESS` | 200 | `—` | False | `BLOCKED` | sem `PASS_REAL_*` fresco + `PRODUCTION_CANARY` |
| `pertmei.gerardas` | `BLOCKED_HUB` | 423 | `MUTATION_DISABLED` | False | `BLOCKED` | gate local impediu chamada ou conclusão |
| `pertmei.obterparc` | `PASS_BUSINESS` | 200 | `—` | False | `BLOCKED` | sem `PASS_REAL_*` fresco + `PRODUCTION_CANARY` |
| `pertmei.parcelasparagerar` | `FAIL_SERPRO` | 400 | `REQUEST_FAILED` | False | `BLOCKED` | 4xx/5xx é falha, não aceite de negócio |
| `pertmei.pedidosparc` | `FAIL_SERPRO` | 400 | `REQUEST_FAILED` | False | `BLOCKED` | 4xx/5xx é falha, não aceite de negócio |
| `pertsn.detpagtoparc` | `PASS_BUSINESS` | 200 | `—` | False | `BLOCKED` | sem `PASS_REAL_*` fresco + `PRODUCTION_CANARY` |
| `pertsn.gerardas` | `BLOCKED_HUB` | 423 | `MUTATION_DISABLED` | False | `BLOCKED` | gate local impediu chamada ou conclusão |
| `pertsn.obterparc` | `PASS_BUSINESS` | 200 | `—` | False | `BLOCKED` | sem `PASS_REAL_*` fresco + `PRODUCTION_CANARY` |
| `pertsn.parcelasparagerar` | `FAIL_SERPRO` | 400 | `REQUEST_FAILED` | False | `BLOCKED` | 4xx/5xx é falha, não aceite de negócio |
| `pertsn.pedidosparc` | `FAIL_SERPRO` | 400 | `REQUEST_FAILED` | False | `BLOCKED` | 4xx/5xx é falha, não aceite de negócio |
| `pgdasd.consdeclaracao` | `PASS_BUSINESS` | 200 | `—` | False | `BLOCKED` | sem `PASS_REAL_*` fresco + `PRODUCTION_CANARY` |
| `pgdasd.consdecrec` | `FAIL_SERPRO` | 400 | `REQUEST_FAILED` | False | `BLOCKED` | 4xx/5xx é falha, não aceite de negócio |
| `pgdasd.consextrato` | `FAIL_SERPRO` | 403 | `REQUEST_FAILED` | False | `BLOCKED` | 4xx/5xx é falha, não aceite de negócio |
| `pgdasd.consultimadecrec` | `FAIL_SERPRO` | 503 | `DOCUMENT_SECURE_CAPTURE_FAILED` | False | `BLOCKED` | 4xx/5xx é falha, não aceite de negócio |
| `pgdasd.gerardas` | `BLOCKED_HUB` | 423 | `MUTATION_DISABLED` | False | `BLOCKED` | gate local impediu chamada ou conclusão |
| `pgdasd.gerardasavulso` | `BLOCKED_HUB` | 423 | `MUTATION_DISABLED` | False | `BLOCKED` | gate local impediu chamada ou conclusão |
| `pgdasd.gerardascobranca` | `BLOCKED_HUB` | 423 | `MUTATION_DISABLED` | False | `BLOCKED` | gate local impediu chamada ou conclusão |
| `pgdasd.gerardasprocesso` | `BLOCKED_HUB` | 423 | `MUTATION_DISABLED` | False | `BLOCKED` | gate local impediu chamada ou conclusão |
| `pgdasd.transdeclaracao` | `BLOCKED_HUB` | 423 | `MUTATION_DISABLED` | False | `BLOCKED` | gate local impediu chamada ou conclusão |
| `pgmei.atubeneficio` | `BLOCKED_HUB` | 423 | `MUTATION_DISABLED` | False | `BLOCKED` | gate local impediu chamada ou conclusão |
| `pgmei.dividaativa` | `PASS_BUSINESS` | 200 | `—` | False | `BLOCKED` | sem `PASS_REAL_*` fresco + `PRODUCTION_CANARY` |
| `pgmei.gerardascodbarra` | `BLOCKED_HUB` | 423 | `MUTATION_DISABLED` | False | `BLOCKED` | gate local impediu chamada ou conclusão |
| `pgmei.gerardaspdf` | `BLOCKED_HUB` | 423 | `MUTATION_DISABLED` | False | `BLOCKED` | gate local impediu chamada ou conclusão |
| `pnr_contador.consultar_renuncias` | `FAIL_SERPRO` | 400 | `REQUEST_FAILED` | False | `BLOCKED` | 4xx/5xx é falha, não aceite de negócio |
| `pnr_contador.consultar_vinculos` | `FAIL_SERPRO` | 400 | `REQUEST_FAILED` | False | `BLOCKED` | 4xx/5xx é falha, não aceite de negócio |
| `pnr_contador.emitir_comprovante` | `PASS_BUSINESS` | 200 | `—` | False | `BLOCKED` | sem `PASS_REAL_*` fresco + `PRODUCTION_CANARY` |
| `pnr_contador.situacao_renuncia` | `PASS_BUSINESS` | 200 | `—` | False | `BLOCKED` | sem `PASS_REAL_*` fresco + `PRODUCTION_CANARY` |
| `pnr_contador.solicitar_renuncia` | `BLOCKED_HUB` | 423 | `MUTATION_DISABLED` | False | `BLOCKED` | gate local impediu chamada ou conclusão |
| `procuracoes.obter` | `FAIL_SERPRO` | 500 | `UPSTREAM_ERROR` | False | `BLOCKED` | 4xx/5xx é falha, não aceite de negócio |
| `regimeapuracao.consultaranoscalendarios` | `PASS_BUSINESS` | 200 | `—` | False | `BLOCKED` | sem `PASS_REAL_*` fresco + `PRODUCTION_CANARY` |
| `regimeapuracao.consultaropcaoregime` | `PASS_BUSINESS` | 200 | `—` | False | `BLOCKED` | sem `PASS_REAL_*` fresco + `PRODUCTION_CANARY` |
| `regimeapuracao.consultarresolucao` | `PASS_BUSINESS` | 200 | `—` | False | `BLOCKED` | sem `PASS_REAL_*` fresco + `PRODUCTION_CANARY` |
| `regimeapuracao.efetuaropcaoregime` | `BLOCKED_HUB` | 423 | `MUTATION_DISABLED` | False | `BLOCKED` | gate local impediu chamada ou conclusão |
| `relpmei.detpagtoparc` | `PASS_BUSINESS` | 200 | `—` | False | `BLOCKED` | sem `PASS_REAL_*` fresco + `PRODUCTION_CANARY` |
| `relpmei.gerardas` | `BLOCKED_HUB` | 423 | `MUTATION_DISABLED` | False | `BLOCKED` | gate local impediu chamada ou conclusão |
| `relpmei.obterparc` | `PASS_BUSINESS` | 200 | `—` | False | `BLOCKED` | sem `PASS_REAL_*` fresco + `PRODUCTION_CANARY` |
| `relpmei.parcelasparagerar` | `FAIL_SERPRO` | 400 | `REQUEST_FAILED` | False | `BLOCKED` | 4xx/5xx é falha, não aceite de negócio |
| `relpmei.pedidosparc` | `FAIL_SERPRO` | 400 | `REQUEST_FAILED` | False | `BLOCKED` | 4xx/5xx é falha, não aceite de negócio |
| `relpsn.detpagtoparc` | `PASS_BUSINESS` | 200 | `—` | False | `BLOCKED` | sem `PASS_REAL_*` fresco + `PRODUCTION_CANARY` |
| `relpsn.gerardas` | `BLOCKED_HUB` | 423 | `MUTATION_DISABLED` | False | `BLOCKED` | gate local impediu chamada ou conclusão |
| `relpsn.obterparc` | `PASS_BUSINESS` | 200 | `—` | False | `BLOCKED` | sem `PASS_REAL_*` fresco + `PRODUCTION_CANARY` |
| `relpsn.parcelasparagerar` | `FAIL_SERPRO` | 400 | `REQUEST_FAILED` | False | `BLOCKED` | 4xx/5xx é falha, não aceite de negócio |
| `relpsn.pedidosparc` | `FAIL_SERPRO` | 400 | `REQUEST_FAILED` | False | `BLOCKED` | 4xx/5xx é falha, não aceite de negócio |
| `sicalc.consolidargerardarf` | `BLOCKED_HUB` | 423 | `MUTATION_DISABLED` | False | `BLOCKED` | gate local impediu chamada ou conclusão |
| `sicalc.consultaapoioreceitas` | `PASS_BUSINESS` | 200 | `—` | False | `BLOCKED` | sem `PASS_REAL_*` fresco + `PRODUCTION_CANARY` |
| `sicalc.gerardarfcodbarra` | `BLOCKED_HUB` | 423 | `MUTATION_DISABLED` | False | `BLOCKED` | gate local impediu chamada ou conclusão |
| `sitfis.emitir_relatorio` | `FAIL_SERPRO` | 500 | `UPSTREAM_ERROR` | False | `BLOCKED` | 4xx/5xx é falha, não aceite de negócio |
| `sitfis.solicitar_protocolo` | `PASS_BUSINESS` | 304 | `NOT_MODIFIED` | False | `BLOCKED` | HTTP 304 sem cache/protocolo/terminal assíncrono comprovados |

### Interpretação obrigatória

- `PASS_BUSINESS` é um rótulo legado e não comprova ambiente contratado,
  payload semanticamente válido, resultado aceito ou `PRODUCTION_CANARY`.
- `FAIL_SERPRO` preserva a falha 4xx/5xx; falha de transporte ou negócio
  nunca é sucesso.
- `BLOCKED_HUB` preserva o gate local; uma chamada impedida ou incompleta
  nunca é sucesso.
- `sitfis.solicitar_protocolo` com HTTP 304 não comprovou cache válido,
  protocolo reutilizado nem estado terminal assíncrono.
- `simulated=false` isolado não prova endpoint, contrato ou proveniência.
- `PASS_TRIAL`, Trial/mock, `/authenticate`, mero mTLS, Fake/Simulated,
  202/204 intermediário e 304 sem reuso comprovado também são insuficientes.

### Evidência de UI histórica

O ciclo antigo percorreu superfícies do painel em navegador, mas as capturas
regeneráveis não provam o código atual e não promovem operação. Uma revisão
futura deve gerar evidência nova desktop/mobile, sanitizada, vinculada ao
canário aprovado e ao resultado de negócio específico.

Esta revisão documental não executou egress, Trial, autenticação, rota fiscal
ou canário e não acessou banco piloto, env, vault ou `dados/`.
