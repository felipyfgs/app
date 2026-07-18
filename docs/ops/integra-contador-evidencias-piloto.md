# Evidências piloto — Integra Contador (SERPRO)

> Relatório sanitizado e não fiscal, reconciliado em 2026-07-18. Este arquivo
> registra ausência ou insuficiência de evidência; não autoriza egress nem
> representa execução de Trial, autenticação, rota fiscal ou canário.

## Fontes e escopo verificável

| Item | Referência |
|---|---|
| Matriz operacional 119:1 | [matriz de cobertura](integra-contador-matriz-cobertura.md) |
| Catálogo canônico vigente | `backend/resources/serpro/official-service-catalog.v2026-07-16.json` — manifest `2026.07.16.1` — SHA-256 `0d742ddc7574d3b19c969fdf60362f2423bfc6e1a8f98c0da81c0c7040a13c8b` |
| Registro de fontes reconciliado | `backend/resources/serpro/official-sources.v2026-07-18.json` — SHA-256 `ee85759b013ebfea2ea127a49d1e2869b2e04883c29a71a574dd4e910d849536` |
| Matriz de poderes reconciliada | `backend/resources/serpro/power-matrix.v2026-07-18.json` — SHA-256 `1dc4f8c4400ff98dc1e2373dfdde68fe0c7d1bcec380e2f702db3093311ceedb` |

O catálogo `v2026-07-16` continua canônico porque permanece vigente e foi
reconciliado com as fontes capturadas em `v2026-07-18`. O baseline derivado é:

| Coorte | Total | Mutantes | Não mutantes | Status final atual |
|---|---:|---:|---:|---|
| `PRODUCTION` | **98** | **25** | **73** | **98 `BLOCKED`** |
| Não produtivas | **21** | **8** | **13** | **21 `INVENTORIED_NON_PROD`** |
| Total | **119** | **33** | **86** | **0 `READY_PRODUCTION`** |

## Critério de evidência vigente

Somente `PASS_REAL_SYNC`, `PASS_REAL_EMPTY`, `PASS_REAL_ASYNC_COMPLETE`
ou `PASS_REAL_CACHE`, acompanhados de `simulated=false`, proveniência
`PRODUCTION_CANARY` e pré-condições específicas comprovadas, podem liberar
uma operação produtiva. Nesta revisão documental, nenhuma das 98 linhas possui
esse conjunto de evidências.

São explicitamente insuficientes: `PASS_TRIAL`, Trial/mock,
`/authenticate`, mero transporte mTLS, Fake/Simulated, 4xx/5xx, 202/204
intermediário, HTTP 304 sem reuso de cache comprovado, `PASS_BUSINESS` e
`BLOCKED_HUB`. Cobertura local de backend, API, tenancy, codec, UI ou teste
continua útil para engenharia, mas não altera o status final.

## Decisão atual por coorte produtiva

### Leituras produtivas (73)

Todas permanecem `BLOCKED`. O blocker comum é
`CANARIO_E_PRE_CONDICOES_NAO_COMPROVADOS`: não há prova conjunta e vigente de
aprovação de canário, endpoint contratado, credencial real utilizável, Termo,
procuração/poder quando aplicável, dado piloto semanticamente válido e
`PASS_REAL_*`. Nenhuma dessas condições foi inferida.

| Família | Qtd | operation_keys | status final | blocker |
|---|---:|---|---|---|
| `authorization` | 6 | `procuracoes.obter`; `autentica_procurador.envio_xml_assinado`; `eventosatualizacao.soliceventospf`; `eventosatualizacao.soliceventospj`; `eventosatualizacao.obtereventospf`; `eventosatualizacao.obtereventospj` | `BLOCKED` | `CANARIO_E_PRE_CONDICOES_NAO_COMPROVADOS` |
| `dctfweb` | 6 | `dctfweb.consrecibo`; `dctfweb.consdeccompleta`; `dctfweb.consxmldeclaracao`; `mit.situacaoenc`; `mit.consapuracao`; `mit.listaapuracoes` | `BLOCKED` | `CANARIO_E_PRE_CONDICOES_NAO_COMPROVADOS` |
| `guides` | 4 | `sicalc.consultaapoioreceitas`; `pagtoweb.pagamentos`; `pagtoweb.comparrecadacao`; `pagtoweb.contaconsdocarrpg` | `BLOCKED` | `CANARIO_E_PRE_CONDICOES_NAO_COMPROVADOS` |
| `installments` | 32 | `parcsn.parcelasparagerar`; `parcsn.pedidosparc`; `parcsn.obterparc`; `parcsn.detpagtoparc`; `parcsn_esp.parcelasparagerar`; `parcsn_esp.pedidosparc`; `parcsn_esp.obterparc`; `parcsn_esp.detpagtoparc`; `pertsn.parcelasparagerar`; `pertsn.pedidosparc`; `pertsn.obterparc`; `pertsn.detpagtoparc`; `relpsn.parcelasparagerar`; `relpsn.pedidosparc`; `relpsn.obterparc`; `relpsn.detpagtoparc`; `parcmei.parcelasparagerar`; `parcmei.pedidosparc`; `parcmei.obterparc`; `parcmei.detpagtoparc`; `parcmei_esp.parcelasparagerar`; `parcmei_esp.pedidosparc`; `parcmei_esp.obterparc`; `parcmei_esp.detpagtoparc`; `pertmei.parcelasparagerar`; `pertmei.pedidosparc`; `pertmei.obterparc`; `pertmei.detpagtoparc`; `relpmei.parcelasparagerar`; `relpmei.pedidosparc`; `relpmei.obterparc`; `relpmei.detpagtoparc` | `BLOCKED` | `CANARIO_E_PRE_CONDICOES_NAO_COMPROVADOS` |
| `mailbox` | 4 | `caixa_postal.lista`; `caixa_postal.detalhe`; `caixa_postal.indicador`; `dte.consultar` | `BLOCKED` | `CANARIO_E_PRE_CONDICOES_NAO_COMPROVADOS` |
| `registrations` | 4 | `pnr_contador.consultar_vinculos`; `pnr_contador.consultar_renuncias`; `pnr_contador.emitir_comprovante`; `pnr_contador.situacao_renuncia` | `BLOCKED` | `CANARIO_E_PRE_CONDICOES_NAO_COMPROVADOS` |
| `simples_mei` | 14 | `pgdasd.consdeclaracao`; `pgdasd.consultimadecrec`; `pgdasd.consdecrec`; `pgdasd.consextrato`; `regimeapuracao.consultaranoscalendarios`; `regimeapuracao.consultaropcaoregime`; `regimeapuracao.consultarresolucao`; `defis.consdeclaracao`; `defis.consultimadecrec`; `defis.consdecrec`; `pgmei.dividaativa`; `ccmei.emitirccmei`; `ccmei.dadosccmei`; `ccmei.ccmeisitcadastral` | `BLOCKED` | `CANARIO_E_PRE_CONDICOES_NAO_COMPROVADOS` |
| `sitfis` | 2 | `sitfis.solicitar_protocolo`; `sitfis.emitir_relatorio` | `BLOCKED` | `CANARIO_E_PRE_CONDICOES_NAO_COMPROVADOS` |
| `tax_processes` | 1 | `eprocesso.consultar_por_interessado` | `BLOCKED` | `CANARIO_E_PRE_CONDICOES_NAO_COMPROVADOS` |

### Mutações produtivas (25)

Todas permanecem `BLOCKED`. Além das pré-condições de negócio e do canário,
exigem manifest e aprovação operacional específicos; nenhuma autorização é
inferida deste documento.

| Família | Qtd | operation_keys | status final | blocker |
|---|---:|---|---|---|
| `dctfweb` | 4 | `dctfweb.gerarguia`; `dctfweb.transdeclaracao`; `dctfweb.gerarguiaandamento`; `mit.encapuracao` | `BLOCKED` | `MANIFEST_E_APROVACAO_DE_MUTACAO_AUSENTES` |
| `guides` | 2 | `sicalc.consolidargerardarf`; `sicalc.gerardarfcodbarra` | `BLOCKED` | `MANIFEST_E_APROVACAO_DE_MUTACAO_AUSENTES` |
| `installments` | 8 | `parcsn.gerardas`; `parcsn_esp.gerardas`; `pertsn.gerardas`; `relpsn.gerardas`; `parcmei.gerardas`; `parcmei_esp.gerardas`; `pertmei.gerardas`; `relpmei.gerardas` | `BLOCKED` | `MANIFEST_E_APROVACAO_DE_MUTACAO_AUSENTES` |
| `registrations` | 1 | `pnr_contador.solicitar_renuncia` | `BLOCKED` | `MANIFEST_E_APROVACAO_DE_MUTACAO_AUSENTES` |
| `simples_mei` | 10 | `pgdasd.transdeclaracao`; `pgdasd.gerardas`; `pgdasd.gerardascobranca`; `pgdasd.gerardasprocesso`; `pgdasd.gerardasavulso`; `regimeapuracao.efetuaropcaoregime`; `defis.transdeclaracao`; `pgmei.gerardaspdf`; `pgmei.gerardascodbarra`; `pgmei.atubeneficio` | `BLOCKED` | `MANIFEST_E_APROVACAO_DE_MUTACAO_AUSENTES` |

## Operações não produtivas (21)

As **8 mutantes** e as **13 não mutantes** continuam inventariadas e recusadas
pelo executor. Elas não podem ser usadas para completar cobertura produtiva.

| Coorte | Qtd | operation_keys | status final | blocker |
|---|---:|---|---|---|
| `dctfweb` | 4 | `dctfweb.gerarguiamaed`; `dctfweb.aplvinculacao`; `dctfweb.gerarguiacomabatimento`; `dctfweb.editarvalorsuspenso` | `INVENTORIED_NON_PROD` | `ESTADO_OFICIAL_NAO_PRODUTIVO` |
| `installments` | 2 | `parc_paex.emitirdocarrecadacao`; `parc_sipade.emitirdocarrecadacao` | `INVENTORIED_NON_PROD` | `ESTADO_OFICIAL_NAO_PRODUTIVO` |
| `simples_mei` | 2 | `dasnsimei.transdeclaracao`; `dasnsimei.gerardasexcesso` | `INVENTORIED_NON_PROD` | `ESTADO_OFICIAL_NAO_PRODUTIVO` |
| `authorization` | 1 | `autenticaprocurador.expirarautorizacao` | `INVENTORIED_NON_PROD` | `ESTADO_OFICIAL_NAO_PRODUTIVO` |
| `dctfweb` | 3 | `dctfweb.consrelcredito`; `dctfweb.consreldebito`; `dctfweb.consnotifmaed` | `INVENTORIED_NON_PROD` | `ESTADO_OFICIAL_NAO_PRODUTIVO` |
| `guides` | 1 | `sicalc.consolidar` | `INVENTORIED_NON_PROD` | `ESTADO_OFICIAL_NAO_PRODUTIVO` |
| `installments` | 4 | `parc_paex.obterextratopdf`; `parc_paex.obterextratojson`; `parc_sipade.obterextratopdf`; `parc_sipade.obterextratojson` | `INVENTORIED_NON_PROD` | `ESTADO_OFICIAL_NAO_PRODUTIVO` |
| `simples_mei` | 1 | `dasnsimei.consultimadecrec` | `INVENTORIED_NON_PROD` | `ESTADO_OFICIAL_NAO_PRODUTIVO` |
| `tax_processes` | 3 | `eprocesso.obtlistdocsproc`; `eprocesso.obtdocproc`; `eprocesso.conscomunintim` | `INVENTORIED_NON_PROD` | `ESTADO_OFICIAL_NAO_PRODUTIVO` |

## Trilha histórica preservada, mas insuficiente

O probe antigo registrou **31 `PASS_BUSINESS`**, **34 `FAIL_SERPRO`** e
**33 `BLOCKED_HUB`** para as 98 operações produtivas. As contagens são
preservadas para rastreabilidade, mas nenhuma é evidência vigente de prontidão:

- `PASS_BUSINESS` antigo não prova endpoint contratado, payload válido,
  semântica aceita nem `PRODUCTION_CANARY`;
- `FAIL_SERPRO` 4xx/5xx é falha, não sucesso;
- `BLOCKED_HUB` significa que o gate local impediu a chamada ou a conclusão;
- o HTTP 304 de `sitfis.solicitar_protocolo` não comprovou cache válido,
  protocolo reaproveitado nem término assíncrono e permanece `BLOCKED`;
- `simulated=false` isolado não comprova ambiente, contrato ou proveniência;
- nenhum artefato temporário de execução anterior é fonte de verdade atual.

## Resultado desta revisão

- Chamada SERPRO de negócio executada nesta change: **0**.
- Operações promovidas: **0**.
- Status final: **98 `BLOCKED` + 21 `INVENTORIED_NON_PROD`**.
- Próxima evidência aceitável: por operação, aprovação explícita e canário real
  fresco pelo endpoint contratado, sem reduzir os gates de segurança.

Esta revisão não acessou rede, banco piloto, env, vault, PFX/P12, tokens, Termo,
XML, payload fiscal bruto ou `dados/`. O documento não contém identificador
fiscal completo, segredo, Base64 ou path interno de vault.
