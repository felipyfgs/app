# Matriz de responsabilidade, payload e documento por página

Esta matriz é parte do desenho da change. Ela usa o catálogo versionado `backend/resources/serpro/official-service-catalog.v2026-07-16.json` e as páginas oficiais referenciadas por cada `operation_key`.

## Regra de leitura

- **Payload estruturado**: o `dados` escapado do envelope SERPRO é decodificado no backend e somente campos normalizados/permitidos aparecem na página. O JSON bruto, o envelope, Base64, tokens, coordenadas e mensagens técnicas não aparecem no painel.
- **Documento oficial público**: PDF/recibo retornado como Base64 é decodificado, persistido como `FiscalEvidenceArtifact` no `SecureObjectStore` e acessado por URL tenant-scoped sem path permanente. XML, envelope e resposta integral podem ser preservados no cofre para auditoria, mas não recebem download no painel.
- **Protocolo assíncrono**: protocolo e polling permanecem internos. A página mostra fase, bloqueio e próxima tentativa; o botão do documento surge apenas quando o artefato estiver pronto.
- **Agregado**: dashboard, Declarações, Guias e detalhe do cliente não possuem payload próprio; apontam para a evidência do módulo de origem.
- **Não produtivo/sem fonte**: não há botão de documento e nenhuma fixture/demo pode aparecer como evidência oficial.

## Páginas

| Superfície | Responsabilidade exclusiva | Fonte e operações de leitura/artefato | Retorno confiável | Onde o usuário vê | Persistência local esperada |
|---|---|---|---|---|---|
| `/monitoring` | Priorizar problemas acionáveis da carteira, sem criar conclusão fiscal nova | Agrega overviews internos; não chama uma operação SERPRO própria | `AGGREGATE` | Cards e linhas levam ao módulo responsável; não existe botão de payload no dashboard | DTOs de overview; nenhuma cópia de documento |
| `/monitoring/simples-mei/pgdasd` | Declarações PGDAS-D e seus documentos | `pgdasd.consdeclaracao` (`CONSDECLARACAO13`), `pgdasd.consdecrec` (`CONSDECREC15`) e `pgdasd.consextrato` (`CONSEXTRATO16`); documentos emitidos já persistidos podem vir de `pgdasd.gerardas`, `pgdasd.gerardascobranca`, `pgdasd.gerardasprocesso` ou `pgdasd.gerardasavulso` | JSON de declaração + PDF de declaração/recibo, extrato e DAS | Grade mostra PA, declaração, transmissão, malha e estado do DAS; detalhe oferece `Ver declaração/recibo`, `Ver extrato` ou `Baixar DAS` somente com artefato | Projeção/snapshot allowlisted + `FiscalEvidenceArtifact` |
| `/monitoring/simples-mei/pgmei` | Dívida ativa e DAS do MEI | `pgmei.dividaativa` (`DIVIDAATIVA24`); documentos já emitidos por `pgmei.gerardaspdf` (`GERARDASPDF21`) ou código de barras por `pgmei.gerardascodbarra` (`GERARDASCODBARRA22`) | JSON de débito + PDF ou código de barras do DAS | Grade mostra período, tributo, ente, situação e valor; detalhe oferece `Baixar DAS` quando persistido | Projeção Simples/MEI + `TaxGuide`/`FiscalEvidenceArtifact` |
| `/monitoring/simples-mei/dasn-simei` | Declarar explicitamente a indisponibilidade atual de DASN-SIMEI | `dasnsimei.transdeclaracao`, `dasnsimei.consultimadecrec` e `dasnsimei.gerardasexcesso` estão `PROSPECTION`/`INVENTORIED` | `UNAVAILABLE` | Aviso `Operação ainda não produtiva no catálogo SERPRO`; sem linhas sintéticas e sem botão de documento | Nenhum snapshot produtivo |
| `/monitoring/simples-mei/regime` | Opção e resolução do regime de apuração | `regimeapuracao.consultaropcaoregime` (`CONSULTAROPCAOREGIME103`), `regimeapuracao.consultaranoscalendarios` (`CONSULTARANOSCALENDARIOS102`) e `regimeapuracao.consultarresolucao` (`CONSULTARRESOLUCAO104`) | JSON de opção/resolução + `demonstrativoPdf` quando retornado | Grade mostra ano, regime, data da opção e resolução; detalhe oferece `Ver demonstrativo oficial` quando houver | `ClientTaxRegimePeriod` + `FiscalEvidenceArtifact` |
| `/monitoring/dctfweb/dctfweb` | Declaração DCTFWeb e artefatos oficiais, sem misturar MIT | `dctfweb.consrecibo` (`CONSRECIBO32`), `dctfweb.consdeccompleta` (`CONSDECCOMPLETA33`) e `dctfweb.consxmldeclaracao` (`CONSXMLDECLARACAO38`); DARF já emitido pode vir de `dctfweb.gerarguia` ou `dctfweb.gerarguiaandamento` | PDF de recibo/relatório, XML protegido da declaração e PDF de DARF | A lista mostra categoria e PA; o detalhe oferece separadamente `Ver recibo`, `Ver declaração` e `Baixar DARF` conforme artefatos existentes. Do XML aparecem somente campos normalizados permitidos; o arquivo bruto permanece no cofre | `DctfwebDeclaration`, `DctfwebEvidenceVersion`, `DctfwebDarfDocument` e cofre |
| `/monitoring/dctfweb/mit` | Apurações e encerramento MIT, sem reutilizar colunas da DCTFWeb | `mit.listaapuracoes` (`LISTAAPURACOES317`), `mit.consapuracao` (`CONSAPURACAO316`) e `mit.situacaoenc` (`SITUACAOENC315`) | JSON estruturado; encerramento mutante retorna protocolo, não documento de consulta | Grade/detalhe mostram PA, `idApuracao`, situação, data de encerramento, avisos e total apurado; sem botão de PDF por inferência | `MitApuracao`; protocolo mutante interno quando aplicável |
| `/monitoring/fgts` | Fechamento e totalizações FGTS/eSocial | Não é Integra Contador: eventos eSocial S-1299, S-5003 e S-5013 | Metadados/recibo do evento quando realmente capturado; não há guia ou pagamento SERPRO | Grade mostra competência, fechamento, totalização, fonte e observação; detalhe mostra metadados sanitizados do evento/recibo. O XML bruto permanece no cofre e não vira ação pública | `FgtsCompetenceStatus`, `EsocialEventEvidence` e cofre |
| `/monitoring/installments` | Pedidos, saldo, parcelas, pagamentos e documento da parcela por modalidade | Famílias produtivas `*.pedidosparc`, `*.obterparc`, `*.parcelasparagerar`, `*.detpagtoparc` e `*.gerardas`; PAEX/SIPADE em prospecção ficam indisponíveis | JSON de pedido/parcelamento/pagamento + `docArrecadacaoPdfB64` | Grade mostra modalidade, pedido, situação, saldo, parcelas e próximo vencimento; detalhe oferece `Baixar documento de arrecadação` quando já emitido/persistido | `TaxInstallmentOrder`, `TaxInstallmentParcel` e `FiscalEvidenceArtifact` |
| `/monitoring/sitfis` | Estado da consulta assíncrona e relatório oficial | `sitfis.solicitar_protocolo` (`SOLICITARPROTOCOLO91`, Apoiar) seguido de `sitfis.emitir_relatorio` (`RELATORIOSITFIS92`, Emitir) | `ASYNC_PDF`: `tempoEspera`/202 até `pdf` Base64/200 | Enquanto processa, mostra fase e próxima tentativa; concluído, oferece `Ver relatório oficial`; não exibe protocolo bruto nem findings extraídos do PDF | Estado de execução/snapshot + PDF em `FiscalEvidenceArtifact` |
| `/monitoring/mailbox` | Carteira de mensagens e prazos por cliente | `caixa_postal.lista` (`MSGCONTRIBUINTE61`), `caixa_postal.indicador` (`INNOVAMSG63`) e `dte.consultar` (`CONSULTASITUACAODTE111`) | JSON estruturado | Lista mostra cliente, assunto, origem, envio, leitura/ciência/validade oficiais e triagem interna separada; abre o detalhe pelo `isn` | `MailboxMessage` e `MailboxContributorState` |
| `/monitoring/mailbox/:id` | Conteúdo oficial de uma mensagem específica | `caixa_postal.detalhe` (`MSGDETALHAMENTO62`) | JSON com assunto/corpo/variáveis e metadados; a operação produtiva catalogada não documenta anexo | Painel de leitura mostra o conteúdo oficial sanitizado e não oferece ação de anexo para esta operação | `MailboxMessage`; nenhum artefato presumido |
| `/monitoring/declarations` | Agenda e entrega consolidadas, delegando evidência à obrigação de origem | Agrega PGDAS-D, `defis.consdeclaracao`/`defis.consdecrec`, DCTFWeb e outras projeções implementadas; não possui `operation_key` único | `AGGREGATE` + evidência do módulo originador | Lista mostra obrigação, período, aplicabilidade, vencimento e entrega; ação `Ver recibo/evidência` usa o deep-link da projeção | `TaxObligationProjection`, `TaxDeliveryEvidence` e artefato referenciado |
| `/monitoring/guides` | Guias/documentos de arrecadação e confirmação oficial de pagamento | Documentos de PGDAS-D, PGMEI, DCTFWeb e parcelamentos; `pagtoweb.pagamentos` (`PAGAMENTOS71`), `pagtoweb.comparrecadacao` (`COMPARRECADACAO72`), `sicalc.consolidargerardarf` e `sicalc.gerardarfcodbarra` | JSON de valores/pagamento, código de barras e PDF de guia/comprovante conforme operação | Lista mostra tipo, PA, principal/multa/juros/total, vencimento e pagamento; detalhe oferece `Ver guia`/`Ver comprovante` somente com artefato | `TaxGuide` + `FiscalEvidenceArtifact`; origem da guia preservada |
| `/monitoring/registrations` | Vínculos cadastrais PNR/Redesim | `pnr_contador.consultar_vinculos` (`CONSVINCULOS261`) | JSON estruturado; comprovante pertence ao fluxo separado de renúncia | Lista mostra CNPJ vinculado, tipo, situação, UF e município; nenhuma ação de documento para consulta de vínculos | `FiscalRegistrationLink`; sem artefato para a consulta simples |
| `/monitoring/tax-processes` | Processos do contribuinte | `eprocesso.consultar_por_interessado` (`CONSPROCPORINTER271`) | JSON estruturado; lista/obtenção de documentos e comunicações estão `PROSPECTION` | Lista/detalhe mostram número, relação, protocolo, tipo/subtipo, localização, situação e último encaminhamento; `Documentos indisponíveis via API produtiva` | `FiscalTaxProcess`; nenhum artefato de documento nesta fase |
| `/monitoring/clients/:clientId` | Consolidar módulos de um cliente sem duplicar payload | Agrega os contratos das páginas acima; não executa operação própria | `AGGREGATE` | Cada seção mostra resumo e encaminha ao detalhe/documento do módulo de origem; paginação evita truncamento silencioso | Somente referências às projeções/evidências existentes |

## Contrato de acesso

Cada item público com evidência deverá expor somente:

```json
{
  "document": {
    "available": true,
    "kind": "PDF",
    "label": "Ver relatório oficial",
    "content_type": "application/pdf",
    "observed_at": "2026-07-17T12:00:00-03:00",
    "source_surface": "sitfis",
    "source_label": "SITFIS — Relatório de Situação Fiscal",
    "href": "/api/v1/fiscal/evidence/123/download"
  }
}
```

Quando não houver documento, `available=false`, `href=null` e a resposta deverá trazer motivo público como `STRUCTURED_ONLY`, `PROCESSING`, `NOT_SUPPORTED`, `NOT_PRODUCTION` ou `NOT_COLLECTED`. A UI não fabricará link a partir do nome do módulo.

## Onde o payload pode ser inspecionado

| Público | Local correto |
|---|---|
| Campos de negócio | Projeção tipada e detalhe tenant-scoped da própria página |
| PDF/recibo público | Ação `Ver/Baixar documento oficial` → `/api/v1/fiscal/evidence/{id}/download` |
| XML, resposta integral e Base64 | Cofre interno; a página recebe somente a projeção allowlisted e metadados sanitizados |
| Origem e observação | Rótulo/família sanitizados do item/documento; coordenadas técnicas ficam no backend |
| JSON bruto de `dados`, envelope, Base64, XML, `idSistema`, `idServico`, `operation_key`, protocolo, hash, run id, vault id | **Não disponível no painel**; somente processamento interno e fixtures não secretas de teste |

O download resolve o `CurrentOffice`, lê bytes pelo `FiscalEvidenceStore::readAuthorized`, envia `Cache-Control: no-store` e não revela `vault_object_id` ou path do cofre.

## Referências oficiais de payload

As coordenadas e os campos SHALL ser lidos do catálogo versionado, sem hard-code de versão na página. As referências primárias para os formatos mais sensíveis desta matriz são:

- [PGDAS-D — consultar declaração/recibo](https://apicenter.estaleiro.serpro.gov.br/documentacao/api-integra-contador/pt/solucoes/integra-sn/pgdasd/servicos/consultar_declaracao_recibo/)
- [PGMEI — gerar DAS](https://apicenter.estaleiro.serpro.gov.br/documentacao/api-integra-contador/pt/solucoes/integra-mei/pgmei/servicos/gerar_das/)
- [Regime de apuração — consultar opção](https://apicenter.estaleiro.serpro.gov.br/documentacao/api-integra-contador/pt/solucoes/integra-sn/regime/servicos/consultar_opcao/)
- [DCTFWeb — consultar recibo](https://apicenter.estaleiro.serpro.gov.br/documentacao/api-integra-contador/pt/solucoes/integra-dctfweb/dctfweb/servicos/consultar_recibo/)
- [MIT — listar apurações](https://apicenter.estaleiro.serpro.gov.br/documentacao/api-integra-contador/pt/solucoes/integra-dctfweb/mit/servicos/consultar_apuracao_ano_mes/)
- [Parcelamentos — consultar parcelamento](https://apicenter.estaleiro.serpro.gov.br/documentacao/api-integra-contador/pt/solucoes/integra-parcelamento/parcsn/servicos/consulta_parcelamento/)
- [SITFIS — emitir relatório](https://apicenter.estaleiro.serpro.gov.br/documentacao/api-integra-contador/pt/solucoes/integra-sitfis/sitfis/servicos/emitir_relatorio/)
- [Caixa Postal — detalhar mensagem](https://apicenter.estaleiro.serpro.gov.br/documentacao/api-integra-contador/pt/solucoes/integra-caixapostal/caixapostal/servicos/obter_detalhes_de_uma_mensagem_especifica/)
- [Pagamento — emitir comprovante](https://apicenter.estaleiro.serpro.gov.br/documentacao/api-integra-contador/pt/solucoes/integra-pagamento/pagtoweb/servicos/emite_comprovante_pagamento/)
- [e-Processo — consultar processos](https://apicenter.estaleiro.serpro.gov.br/documentacao/api-integra-contador/pt/solucoes/integra-e-processo/eprocesso/servicos/consultar_processos/)
