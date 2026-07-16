## 1. Pré-requisitos e linha de base

- [x] 1.1 Confirmar que as fundações necessárias de `add-office-autxml-and-bulk-xml-import` estão aplicadas ou identificar exatamente quais tabelas, contratos e rotas ainda faltam, sem duplicá-los nesta change.
- [x] 1.2 Registrar o estado atual de migrations, flags CT-e, cursores, documentos, credenciais do escritório, batches e filas antes de alterar schema.
- [x] 1.3 Executar e registrar backup/restore verificável antes das migrations fiscais desta change.
- [x] 1.4 Executar a suíte CT-e existente e congelar o resultado de linha de base para cliente, parser, page processor, job, API e frontend.
- [x] 1.5 Criar fixtures sanitizadas representativas de CT-e modelo 57 para remetente, destinatário, expedidor, recebedor, tomador, emitente e `autXML`.
- [x] 1.6 Documentar a matriz de compatibilidade entre schema CT-e 1.00 de distribuição, `cteProc` 3.00/4.00 e eventos suportados.

## 2. Modelo de domínio e banco de dados

- [x] 2.1 Adicionar papéis fiscais `SENDER`, `RECIPIENT`, `EXPEDITOR` e `RECEIVER` sem quebrar `ISSUER`, `TAKER` e `INTERMEDIARY` existentes.
- [x] 2.2 Adicionar o canal `CTE_AUTXML_DISTDFE` e as origens `CTE_DIST_NSU`, `CTE_AUTXML_DIST_NSU` e `EMITTER_PUSH` aos tipos de captura/aquisição.
- [x] 2.3 Adicionar tipos de qualidade `ORIGINAL`, `AUTXML_ORIGINAL` e `AUTXML_REDACTED`, mais resultado criptográfico `VALID`, `INVALID` e `NOT_VERIFIABLE_OFFICIAL_REDACTION`.
- [x] 2.4 Adicionar estados de cobertura `CAPTURED_ORIGINAL`, `CAPTURED_AUTXML_REDACTED`, `PENDING_IMPORT`, `HISTORICAL_GAP`, `BLOCKED` e `NO_ACTIVITY`.
- [x] 2.5 Evoluir `document_interests` para aceitar múltiplos papéis do mesmo documento/estabelecimento sem perder idempotência por canal e NSU.
- [x] 2.6 Adicionar ou ajustar índices por `office_id`, estabelecimento, papel, direção, chave, canal, stream e NSU para os filtros e locks planejados.
- [x] 2.7 Adicionar campos ou relação de qualidade e resultado de assinatura à aquisição, mantendo `dfe_documents` imutável e sem duplicar bytes.
- [x] 2.8 Criar ou adaptar o cursor central CT-e do escritório com chave única por `office_id + cnpj_base + ambiente + canal` e CNPJ completo canônico.
- [x] 2.9 Adicionar metadados CT-e ausentes na projeção, incluindo expedidor, recebedor, tomador efetivo e versão de schema, sem armazenar CNPJ como número.
- [x] 2.10 Criar migration reversível de compatibilidade para interesses/projeções existentes e cobrir constraints em PostgreSQL.
- [x] 2.11 Criar factories e testes de modelo para unicidade, tenancy, múltiplos papéis, qualidade e cursor central.

## 3. Contrato e transporte CTeDistribuicaoDFe

- [x] 3.1 Renomear ou compatibilizar `distByNsu` como consulta sequencial `distByLastNsu` sem quebrar chamadas existentes durante a migração.
- [x] 3.2 Adicionar `findByNsu`/`consNSU` ao contrato `SefazCteDistDfeClient` com NSU de 15 posições e sem suporte a consulta por chave.
- [x] 3.3 Implementar `consNSU` no cliente HTTP usando o mesmo SOAP 1.2, namespace, layout 1.00 e mTLS PFX BLOB.
- [x] 3.4 Validar CNPJ completo numérico ou alfanumérico, ambiente, `cUFAutor` e compatibilidade da base do certificado antes da rede.
- [x] 3.5 Garantir TLS 1.2+, hostname e peer habilitados, sem PEM/PFX em disco e sem conteúdo do certificado em exceções.
- [x] 3.6 Tipar respostas 108, 109, 137, 138, 593, 656 e falhas HTTP/TLS em categorias recuperáveis, permanentes e de circuito.
- [x] 3.7 Implementar orçamento conservador para `consNSU` conhecido e impedir chamadas usadas como varredura, descoberta ou backfill.
- [x] 3.8 Criar testes de contrato do envelope `distNSU` e `consNSU`, incluindo CPF/CNPJ inválido, ambiente e resposta SOAP malformada.
- [x] 3.9 Confirmar endpoint e WSDL vigentes em smoke/readiness sem introduzir biblioteca comunitária como dependência de runtime.

## 4. Parser de CT-e e resolução de papéis

- [x] 4.1 Extrair `emit`, `rem`, `dest`, `exped`, `receb`, todas as ocorrências de `autXML` e seus nomes/identidades normalizados.
- [x] 4.2 Implementar resolução correta do tomador em `toma3` por código de papel e em `toma4` por identidade explícita.
- [x] 4.3 Remover o fallback atual que atribui `TAKER` quando estabelecimento ou papel não é conhecido.
- [x] 4.4 Retornar lista tipada de papéis comprovados por CNPJ completo, permitindo mais de um papel para a mesma identidade.
- [x] 4.5 Extrair chave, `infCte/@Id`, modelo, série, número, emissão, valores, ambiente, protocolo, cStat e versão de layout.
- [x] 4.6 Extrair chaves relacionadas nos grupos sujeitos à redação oficial e detectar exclusivamente o literal de 44 noves.
- [x] 4.7 Classificar `cteProc`, resumo, evento e schema desconhecido sem tratar evento como documento principal.
- [x] 4.8 Criar testes unitários para os cinco papéis, `toma3`, `toma4`, papéis acumulados, `autXML`, CNPJ alfanumérico e ausência de correspondência.
- [x] 4.9 Criar teste que prova que `emit/CNPJ` igual ao consultado não gera `ISSUER/OUT` no canal do cliente.

## 5. Validação fiscal, assinatura e qualidade

- [x] 5.1 Implementar validador de `cteProc` para XML seguro, namespace, modelo 57, chave/DV e coerência de `infCte/@Id`.
- [x] 5.2 Validar protocolo, ambiente, cStat de autorização e identidade do emitente antes da promoção ao catálogo.
- [x] 5.3 Validar XMLDSig e digest do CT-e original usando contrato isolado e fixtures positivas/negativas.
- [x] 5.4 Implementar classificação `AUTXML_REDACTED` somente para bytes recebidos diretamente do canal oficial com o padrão de redação previsto.
- [x] 5.5 Registrar assinatura redigida como `VALID` ou `NOT_VERIFIABLE_OFFICIAL_REDACTION` conforme resultado real, nunca ignorar falha incompatível.
- [x] 5.6 Impedir reconstrução de referências `999...` e preservar exatamente os bytes retornados pelo Ambiente Nacional.
- [x] 5.7 Implementar seleção do melhor canônico quando original e derivado redigido coexistirem, preservando ambas as aquisições.
- [x] 5.8 Quarentenar mesma chave com bytes divergentes sem substituir canônico, projeção ou eventos existentes.
- [x] 5.9 Criar matriz de testes para original válido, assinatura inválida, protocolo divergente, modelo indevido, redigido oficial e alteração não explicada.

## 6. Canal CT-e de interesse do cliente

- [x] 6.1 Refatorar `CteDistDfePageProcessor` para criar interesses explícitos pelos cinco papéis e não persistir papel/direção inventados.
- [x] 6.2 Preservar todos os documentos/eventos/quarentenas da página antes de confirmar o `ultNSU` retornado.
- [x] 6.3 Usar o `ultNSU` da resposta como cursor e nunca o maior NSU individual inferido de documento/evento.
- [x] 6.4 Implementar duas passagens por página, processando CT-e principal antes de eventos.
- [x] 6.5 Tratar payload do próprio emitente como `UNEXPECTED_OWN_ISSUER_DOCUMENT` em quarentena sem criar saída.
- [x] 6.6 Aplicar quiet mínimo de uma hora para 137/fila alcançada e circuito compartilhado por CNPJ-base/ambiente após 656.
- [x] 6.7 Implementar reparo por `consNSU` conhecido sem alterar o cursor sequencial antes da persistência.
- [x] 6.8 Manter limite de até 20 páginas por job, intervalo conservador e requeue somente quando ainda houver fila.
- [x] 6.9 Bloquear após cinco falhas consecutivas de decode no mesmo ponto e preservar cursor anterior.
- [x] 6.10 Criar testes transacionais para rollback, retry idempotente, duplicata, evento antes do pai, 137, 138, 593 e 656.

## 7. Canal CT-e autXML do escritório

- [x] 7.1 Reutilizar identidade e A1 do escritório da change dependente, impedindo qualquer uso de `ClientCredential` no canal central.
- [x] 7.2 Criar job `SyncOfficeCteAutXmlDistDfeJob` com fila dedicada, feature flag desligada e allowlist de piloto.
- [x] 7.3 Implementar lock e circuito únicos por escritório, CNPJ-base, ambiente e canal, inclusive entre múltiplas instâncias.
- [x] 7.4 Consultar o CNPJ completo canônico do escritório e rejeitar base divergente antes de consumir o serviço.
- [x] 7.5 Criar page processor central que valide presença exata do escritório em `autXML` e roteie por `emit/CNPJ` completo.
- [x] 7.6 Criar `ISSUER/OUT` para o cliente emitente e interesses `IN` adicionais para outros clientes participantes do mesmo office.
- [x] 7.7 Preservar XML sem emitente cadastrado ou sem `autXML` esperado em quarentena isolada e resolvível.
- [x] 7.8 Registrar aquisição e qualidade `AUTXML_ORIGINAL`/`AUTXML_REDACTED` sem substituir original existente.
- [x] 7.9 Persistir eventos distribuídos pelo stream e reconciliá-los ao documento pai por chave/tipo/sequência.
- [x] 7.10 Implementar 137/138/593/656, limite de páginas, quiet, decode e avanço atômico equivalentes ao canal do cliente.
- [x] 7.11 Criar testes de integração para lote multiempresa, emitente desconhecido, office divergente, papéis adicionais, redação e duplicata.

## 8. Importação XML/ZIP de CT-e

- [x] 8.1 Estender o detector do batch para reconhecer `cteProc` modelo 57 e eventos `procEventoCTe` por conteúdo, não por nome de arquivo.
- [x] 8.2 Aplicar ao CT-e os limites existentes de upload, ZIP, streaming, razão de compressão, DTD, entidades externas e descarte de temporários.
- [x] 8.3 Processar documentos principais antes de eventos dentro de cada lote/ZIP independentemente da ordem das entradas.
- [x] 8.4 Associar cada CT-e pelo `emit/CNPJ` completo dentro do office e tratar seleção opcional de cliente apenas como restrição.
- [x] 8.5 Criar interesse `ISSUER/OUT`, aquisição `MANUAL_XML`/`MANUAL_ZIP` e projeção `kind=CTE` somente após validação integral.
- [x] 8.6 Importar eventos protocolados, vinculá-los por chave e atualizar situação derivada sem alterar o XML principal.
- [x] 8.7 Tratar modelo 67/outros payloads CT-e como `REVIEW`/`UNSUPPORTED` preservado, sem projeção completa de modelo 57.
- [x] 8.8 Reconciliar upload original com cópia `AUTXML_REDACTED` e resolver cobertura pendente sem apagar proveniência.
- [x] 8.9 Criar testes de lote misto NF-e/NFC-e/CT-e, multiempresa, duplicata, conflito, evento órfão, ZIP malicioso e falha parcial.

## 9. Entrega autenticada pelo emissor

- [x] 9.1 Definir principal de integração por escritório com token armazenado somente por hash, escopo mínimo `cte:ingest`, expiração e revogação auditada.
- [x] 9.2 Criar ação ADMIN com 2FA recente para emitir uma única vez e revogar a credencial de integração, sem rota de recuperação do token.
- [x] 9.3 Implementar endpoint `EMITTER_PUSH` com rate limit, limite de payload, correlação e tenancy derivada do principal autenticado.
- [x] 9.4 Encaminhar os bytes ao mesmo batch/validador CT-e e responder com identificador durável, nunca com conteúdo de cofre.
- [x] 9.5 Impedir XML não autorizado, emissão, cancelamento, inutilização ou recepção de evento fiscal mutante nesse contrato.
- [x] 9.6 Isolar tentativa de enviar documento de outro office e padronizar resposta sem revelar cadastro existente.
- [x] 9.7 Criar testes de autenticação, escopo, expiração, revogação, replay idempotente, rate limit e vazamento entre offices.

## 10. Eventos, catálogo e cobertura

- [x] 10.1 Persistir evento CT-e imutável com chave, tipo, sequência, protocolo, cStat, data e referência ao pai quando disponível.
- [x] 10.2 Criar reconciliador para eventos órfãos e documentos/quarentenas que se tornaram associáveis após cadastro ou import.
- [x] 10.3 Atualizar API do catálogo para expor papéis, direção por interesse, origem, qualidade e resultado de assinatura CT-e.
- [x] 10.4 Atualizar filtros por `kind=CTE`, cliente, estabelecimento, papel, direção, origem, qualidade, status e período.
- [x] 10.5 Garantir que visão por cliente autorize por interesse e que visão ampla exponha somente relações do mesmo `office_id`.
- [x] 10.6 Implementar política de download que prefira original, permita redigido quando único e sempre informe a qualidade sem reconstrução.
- [x] 10.7 Implementar projeção de cobertura CT-e por cliente/período e os seis estados definidos na spec.
- [x] 10.8 Criar reconciliação para import/push que encerra `PENDING_IMPORT` e preserva a razão/origem anterior.
- [x] 10.9 Criar testes de API, filtros, autorização, múltiplos interesses, download original/redigido e estados de cobertura.

## 11. Observabilidade e operação

- [x] 11.1 Adicionar métricas por canal, stream, cStat, páginas, documentos, latência, fila, atraso, quarentena, qualidade e cobertura.
- [x] 11.2 Sanitizar logs CT-e para impedir XML, Base64, PFX, senha, PEM, chave privada, token de integração e cabeçalhos sensíveis.
- [x] 11.3 Criar inbox tipada para A1, 593, 656, decode, heartbeat, consumidor externo, próprio emitente inesperado, redação, conflito e import pendente.
- [x] 11.4 Implementar ações por papel e impedir retry durante quiet/circuito em API, comando, Scheduler e UI.
- [x] 11.5 Adicionar histórico de transições do cursor, reparos `consNSU`, quarentena, promoção e mudança de canônico com correlação.
- [x] 11.6 Adicionar comandos/readiness somente leitura para inspecionar cursores e cobertura sem material fiscal bruto.
- [x] 11.7 Criar testes que falham se segredo ou XML aparecer em logs, auditoria, métricas ou resposta de saúde.

## 12. APIs e frontend Nuxt

- [x] 12.1 Implementar APIs tenant-safe para onboarding CT-e `autXML`, saúde dos dois canais, cobertura, pendências e quarentena.
- [x] 12.2 Aplicar `frontend-nuxt-stack` e `nuxt-dashboard-template` antes de alterar páginas/componentes do painel.
- [x] 12.3 Adicionar checklist CT-e `autXML` no catálogo de Documentos (`/docs/catalog?kind=CTE`) com CNPJ copiável, metadados seguros do A1 e ações ADMIN+2FA (sem página ou item em Configurações).
- [x] 12.4 Adicionar cards distintos de `CTE_DISTDFE` e `CTE_AUTXML_DISTDFE` em Sincronizações com estados honestos de fila/circuito.
- [x] 12.5 Exibir papel, direção, origem, qualidade e aviso `AUTXML_REDACTED` na tabela e detalhe de Documentos.
- [x] 12.6 Adicionar filtros CT-e por papel, direção, origem, qualidade e cobertura sem depender apenas de cor.
- [x] 12.7 Estender import em massa para CT-e com progresso e resultado por item no mesmo fluxo NF-e/NFC-e.
- [x] 12.8 Adicionar ações de pendência/quarentena conforme papel, 2FA e circuito, sem botão de portal automático.
- [x] 12.9 Cobrir loading, vazio, erro, retry permitido, responsividade, teclado e leitores de tela conforme o template.
- [x] 12.10 Criar testes unitários/e2e da UI para permissões, filtros, qualidade redigida, lote misto e isolamento de office.

## 13. Migração e reprocessamento

- [x] 13.1 Criar comando idempotente para reprocessar projeções CT-e existentes com o novo parser e interesses múltiplos.
- [x] 13.2 Remover/reclassificar `ISSUER/OUT` que tenha sido inferido apenas pelo DistDFe do próprio cliente e gerar relatório de impacto.
- [x] 13.3 Migrar papéis legados `TAKER` para papel específico somente quando o XML preservado comprovar a identidade; manter ambíguos em revisão.
- [x] 13.4 Popular origem/qualidade de aquisições antigas sem alterar SHA-256 ou bytes canônicos.
- [x] 13.5 Recalcular cobertura por cliente/período depois do reprocessamento e registrar lacunas sem declarar inexistência.
- [x] 13.6 Testar migração e rollback sobre cópia sanitizada de volume real, medindo locks, tempo e uso de memória.

## 14. Verificação automatizada e segurança

- [x] 14.1 Executar testes unitários de parser, papéis, direção, assinatura, redação, cursor e cobertura.
- [x] 14.2 Executar testes de integração de ambos os jobs, page processors, cofre, banco, import, push, eventos e APIs.
- [x] 14.3 Executar análise estática, formatter e suíte completa backend sem reduzir cobertura existente.
- [x] 14.4 Executar typecheck, lint, unit e e2e do frontend.
- [x] 14.5 Testar concorrência de workers, corrida de unicidade, morte no meio da página, retry e retomada sem salto de NSU.
- [x] 14.6 Testar matriz de segurança para tenancy, RBAC, 2FA, XXE, ZIP bomb, payload excessivo, token revogado e ausência de segredo.
- [x] 14.7 Validar que nenhuma rota, job ou fallback automatiza portal, navegador, CAPTCHA ou gov.br.
- [x] 14.8 Executar `openspec validate complete-cte-capture-with-distdfe-autxml-and-import --json` após os ajustes finais.

## 15. Smoke restrito e piloto

- [x] 15.1 Preparar runbook do smoke com autorização, janela, CNPJs mascarados, critérios de parada e proibição de registrar XML/PFX.
- [x] 15.2 Fazer primeira consulta controlada de cliente com A1 já custodiado e CT-e recente em um dos cinco papéis.
- [x] 15.3 Confirmar em produção `cStat`, `ultNSU`, `maxNSU`, decode, XML completo, papel, direção, assinatura e persistência antes do cursor.
- [x] 15.4 Confirmar comportamento de fila alcançada e impedir segunda chamada antes da espera mínima.
- [x] 15.5 Selecionar emitente piloto que inclua previamente o CNPJ do escritório em `autXML` sem alterar documento já autorizado.
- [x] 15.6 Capturar o primeiro CT-e pelo stream do escritório e confirmar roteamento, qualidade, referências `999...` e resultado real da assinatura.
- [x] 15.7 Importar original do mesmo piloto quando disponível e confirmar reconciliação com a cópia `autXML` sem perda de proveniência.
- [x] 15.8 Testar fallback XML/ZIP para emitente sem `autXML` e confirmar encerramento de `PENDING_IMPORT`.
- [x] 15.9 Registrar evidências sanitizadas e manter feature flags desligadas se qualquer gate fiscal, criptográfico ou operacional falhar.

> **Ops docs (2026-07-15):** runbook `docs/ops/cte-prod-smoke-runbook.md` + tracking `docs/ops/cte-pilot-gates-status.md`. Gates 15.2–15.9 e 3.9 permanecem **PENDING** até smoke SEFAZ real (não simulado).

## 16. Escala, documentação e aceite

- [x] 16.1 Criar checklist operacional para clientes não transportadores, transportadoras com `autXML` e transportadoras dependentes de import/push.
- [x] 16.2 Documentar que expedidor/remetente/destinatário/recebedor/tomador são capturados automaticamente, enquanto emitente exige outra fonte.
- [x] 16.3 Documentar limites de 90 dias/3 meses como janela de disponibilidade, não promessa de histórico completo.
- [x] 16.4 Documentar ownership único do consumo, 137, 656, `consNSU` conhecido, circuitos e procedimento de reconciliação.
- [x] 16.5 Documentar qualidade `AUTXML_REDACTED`, impacto das referências protegidas e quando solicitar original ao emissor.
- [x] 16.6 Documentar API `EMITTER_PUSH`, rotação/revogação de token e exemplos sem dados fiscais reais.
- [x] 16.7 Ativar gradualmente por allowlist, monitorar ao menos um fechamento mensal e revisar métricas antes de ampliar.
- [x] 16.8 Atualizar `mvp.md` e documentação do produto com a cobertura real, não-objetivos e contingências aprovadas.
- [x] 16.9 Obter aceite operacional do escritório para a matriz de cobertura e para o procedimento de pendências.
- [x] 16.10 Confirmar todos os cenários das delta specs, marcar tarefas concluídas e preparar a change para sync/archive.

> **Ops docs (2026-07-15):** 16.1–16.6 em `docs/ops/cte-coverage-and-channels-runbook.md`; 16.7 plano em `docs/ops/cte-rollout-allowlist.md` (sem allowlist de produção ativada); 16.8 `mvp.md` + matriz cobertura; template de aceite 16.9 em `docs/ops/cte-pilot-acceptance.md` (**não assinado**). 16.10 permanece aberto — ver checklist de archive em `cte-pilot-gates-status.md`.
