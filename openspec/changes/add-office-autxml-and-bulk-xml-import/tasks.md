## 1. Pré-requisitos, evidências e gates

- [ ] 1.1 Fixar no repositório as fontes oficiais da NT 2014.002 v1.40, leiaute `autXML`, ausência de NSU retroativo e rejeição do modelo 65, com data e URLs verificadas.
- [ ] 1.2 Criar testes de caracterização do `NFE_DISTDFE` atual e do import síncrono antes da refatoração, cobrindo respostas e efeitos já suportados.
- [ ] 1.3 Inventariar e documentar qualquer ERP, robô ou fornecedor que consuma `distNSU` com o CNPJ-base do escritório; manter ativação externa bloqueada enquanto o ownership não estiver resolvido.
- [ ] 1.4 Coordenar com `build-ma-outbound-nfe-nfce-capture` a migration e o ownership de `document_acquisitions`, evitando criação concorrente da mesma tabela e registrando a ordem de aplicação.
- [ ] 1.5 Executar e registrar backup+restore da instância antes das novas tabelas de identidade, credencial, cursor, quarentena e importação fiscal.
- [ ] 1.6 Adicionar feature flag e kill switch de `NFE_AUTXML_DISTDFE` desligados por padrão, com allowlist opcional de `office_id`.
- [ ] 1.7 Definir em configuração os limites iniciais de upload/ZIP e alinhar os valores pretendidos de Laravel, PHP-FPM, Nginx e workers sem ainda habilitar processamento externo.
- [ ] 1.8 Registrar baseline de logs, métricas e respostas para detectar XML, PFX, senha, PEM, stack trace, caminho temporário ou referência de vault durante os testes posteriores.

## 2. Schema e modelo de domínio

- [ ] 2.1 Criar `office_fiscal_identities` com `office_id`, CNPJ completo/root em texto uppercase, estado e timestamps, com unicidade e índices tenant-aware.
- [ ] 2.2 Criar `office_credentials` com identidade, finalidade, estado, titular, CNPJ, fingerprint, validade e referência opaca ao cofre, garantindo no máximo uma ACTIVE por identidade/finalidade.
- [ ] 2.3 Criar `office_autxml_enrollments` por identidade+estabelecimento com estados `PENDING`, `CONFIRMED`, `INACTIVE`, `activated_at`, `first_seen_at` e `last_seen_at`.
- [ ] 2.4 Criar `office_distribution_cursors` sem `establishment_id`, únicos por `office_id+cnpj_base+environment+channel`, conservando `query_cnpj`, NSUs, agenda, lock, cStat e contadores de falha.
- [ ] 2.5 Criar histórico de execuções do stream do escritório com cursor inicial/final, páginas, itens, resultado, tentativas e mensagens sanitizadas.
- [ ] 2.6 Criar `fiscal_document_quarantine` para objeto criptografado, SHA-256, identidade fiscal, motivo tipado, origem, NSU/item de lote e estado de resolução, invisível ao catálogo comum.
- [ ] 2.7 Criar `document_import_batches` com office, ator, restrição opcional, idempotency key/digest, estados, cotas observadas, contadores e timestamps.
- [ ] 2.8 Criar `document_import_batch_items` com batch, índice/nome sanitizado, SHA-256, chave/modelo quando validados, vínculo, estado, tentativas e código de resultado.
- [ ] 2.9 Estender `document_acquisitions` para `AUTXML_DIST_NSU`, `MANUAL_XML` e `MANUAL_ZIP`, com referências reais de NSU ou batch item e sem NSU sintético para import.
- [ ] 2.10 Ajustar `document_interests`/índices para tornar estabelecimento+papel+direção+canal idempotentes e suportar a mesma chave como entrada e saída para clientes distintos.
- [ ] 2.11 Adicionar constraints contra mistura de `office_id`, dois cursores da mesma raiz/ambiente e promoção de aquisição/quarentena para documento de outro tenant.
- [ ] 2.12 Criar models, enums, factories e scopes `BelongsToOffice` para as novas tabelas sem expor campos secretos em serialização.
- [ ] 2.13 Escrever testes de migration, constraints, CNPJ alfanumérico, índices, `down()` seguro e compatibilidade com banco que já possua `document_acquisitions` da change MA.

## 3. Identidade fiscal e cofre do A1 do escritório

- [ ] 3.1 Implementar normalização/validação de CNPJ completo e raiz como texto de 14/8 caracteres, numérico ou alfanumérico, uppercase e sem cast numérico.
- [ ] 3.2 Implementar serviço de identidade fiscal do escritório que deriva `office_id` da sessão e mantém o ambiente apenas no cursor, não em cópias do A1.
- [ ] 3.3 Implementar leitura de metadados PFX/P12 somente em memória para confirmar A1, chave privada, titular, cadeia ICP-Brasil, finalidade de client auth e validade.
- [ ] 3.4 Validar a igualdade de CNPJ-base entre identidade e certificado antes de persistir, emitindo código sanitizado equivalente à RV 593 quando divergir.
- [ ] 3.5 Armazenar PFX e senha via `SecureObjectStore` com AAD do office/proprietário/finalidade e descartar buffers/temporários em claro após cada uso.
- [ ] 3.6 Implementar resolvedor `OfficeCredentialResolver` que só materializa credencial ACTIVE para `NFE_AUTXML_DISTDFE` e rejeita credenciais de clientes ou outro office.
- [ ] 3.7 Implementar criação, leitura de metadados, substituição atômica, desativação e revogação lógica da credencial do escritório, sem rota de download/recuperação.
- [ ] 3.8 Aplicar policy de ADMIN+2FA recente antes de receber arquivo ou mutar identidade/credencial; manter OPERATOR/VIEWER em leitura dos metadados permitidos.
- [ ] 3.9 Implementar alertas de validade em 30/7/1 dia e bloqueio somente dos cursores autXML após expiração, sem propagar estado a credenciais de clientes.
- [ ] 3.10 Auditar ciclo de vida da identidade/A1 com ator, fingerprint e resultado, comprovando ausência de PFX, senha, PEM, `vault_object_id` e exceção bruta.
- [ ] 3.11 Cobrir com testes raiz incompatível, A3, senha errada, PFX sem chave, certificado vencido/futuro, rotação falha, cross-tenant e ausência de endpoint de recuperação.

## 4. Transporte DistDFe e cursor central

- [ ] 4.1 Extrair/reusar transporte SOAP, DTOs, parser de resposta e `DocumentDecoder` neutros sem alterar a semântica do job `NFE_DISTDFE` do cliente.
- [ ] 4.2 Manter implementações/job/processador separados para contexto do escritório e adicionar testes que impedem troca de credencial, cursor ou classificação entre os dois contextos.
- [ ] 4.3 Implementar pedido `NFeDistribuicaoDFe` v1.40 com `query_cnpj` completo canônico, ambiente, `ultNSU` e XML/SOAP coerentes com o endpoint oficial.
- [ ] 4.4 Configurar mTLS com PFX BLOB em memória, TLS >=1.2, verificação de hostname e cadeia habilitadas e timeouts/retries que não duplicam consulta fiscal.
- [ ] 4.5 Implementar parser sanitizado de cStat, `ultNSU`, `maxNSU` e até 50 `docZip`, sem armazenar envelope SOAP bruto em banco/log.
- [ ] 4.6 Implementar serviço de cursor por `office_id+cnpj_base+environment+channel`, mantendo `query_cnpj` completo e proibindo cursores paralelos da mesma raiz.
- [ ] 4.7 Implementar lock distribuído e lock persistido por stream, com recuperação de lease órfã e garantia de uma chamada externa por vez.
- [ ] 4.8 Implementar ativação inicial com `last_nsu=0`, registro de `activated_at`, tratamento normal de `cStat=137` e próxima tentativa somente após uma hora.
- [ ] 4.9 Implementar gate `EXTERNAL_CONSUMER_CONFLICT` e fluxo auditado de transferência de ownership, sem adotar NSU externo ou resetar para zero cegamente.
- [ ] 4.10 Aplicar intervalo entre chamadas, máximo de 20 páginas por job, quiet de uma hora quando alcançado e rate limit global/per-ator compatível com os demais canais.
- [ ] 4.11 Criar fixtures e testes de contrato para 137, 138, 593, 618, 656, `ultNSU=maxNSU`, TLS inválido, timeout e resposta malformada.

## 5. Captura NF-e 55 via autXML

- [ ] 5.1 Adicionar canal `NFE_AUTXML_DISTDFE`, origem/aquisição `AUTXML_DIST_NSU`, labels, configuração, elegibilidade e isolamento nos enums/DTOs.
- [ ] 5.2 Implementar `SyncOfficeAutXmlDistDfeJob` e Scheduler com feature flag, allowlist, deterministic spread, lock e no máximo 20 páginas por execução.
- [ ] 5.3 Estender o parser de NF-e para retornar modelo, ambiente, `tpNF`, emitente, destinatário e todas as ocorrências `autXML/CNPJ` normalizadas, inclusive alfanuméricas.
- [ ] 5.4 Validar `procNFe` modelo 55, assinatura, chave/DV vigente, protocolo, cStat de autorização, ambiente, `tpNF=1` e presença exata do CNPJ completo consultado em `autXML`.
- [ ] 5.5 Processar cada página em duas passagens, promovendo notas antes de relacionar `procEventoNFe`, cancelamento, CC-e e demais eventos allowlisted.
- [ ] 5.6 Resolver o emitente somente por CNPJ completo exato em estabelecimento ativo/enrolled do mesmo office; atualizar `first_seen_at`/`last_seen_at` quando observado.
- [ ] 5.7 Criar interesse adicional `TAKER/IN` quando o destinatário também for estabelecimento do mesmo escritório, sem duplicar o documento canônico.
- [ ] 5.8 Preservar em quarentena criptografada XML bem-formado sem tag esperada, enrollment, emitente inequívoco, schema conhecido ou vínculo de evento, antes de avançar a página.
- [ ] 5.9 Vincular eventos pela chave somente a documento já roteado no mesmo office; manter evento órfão em quarentena até vínculo autorizado.
- [ ] 5.10 Registrar NSU real, schema, ambiente e aquisição idempotente sem classificar o próprio escritório como Cliente ou papel fiscal da operação.
- [ ] 5.11 Persistir/promover/quarentenar todos os itens e avançar `ultNSU` na mesma transação lógica; qualquer falha de banco/cofre antes do destino durável impede o avanço.
- [ ] 5.12 Impedir avanço em Base64/GZip inválido, incrementar falhas por ponto e bloquear o stream na quinta falha consecutiva sem pular NSU.
- [ ] 5.13 Mapear 593/618/656 e anomalias de schema/modelo para estados e ações sanitizados, sem tentar outro certificado, outro modelo ou consulta de descoberta.
- [ ] 5.14 Garantir por teste que autXML nunca enfileira ciência, manifestação conclusiva, unlock, emissão, cancelamento, inutilização ou reconsulta de destinatário.
- [ ] 5.15 Tratar `resNFe` no papel de terceiro e o edge case escritório=destinatário como XML incompleto/quarentena, indicando fallback por arquivo sem manifestação automática.
- [ ] 5.16 Manter heartbeat `distNSU` estritamente antes de 60 dias e registrar `NSU_GENERATION_GAP` após hiato, sem prometer backfill retroativo.

## 6. Infraestrutura do import assíncrono

- [ ] 6.1 Implementar serviços e enums de batch/item com estados normativos e transições atômicas, incluindo watchdog de `PROCESSING` órfão.
- [ ] 6.2 Criar endpoint canônico de lote que aceita múltiplos XML/ZIP, deriva office/ator, valida admissão e responde HTTP 202 com ID opaco.
- [ ] 6.3 Implementar chave de idempotência e digest da seleção para devolver o batch existente em repetição equivalente sem duplicar jobs.
- [ ] 6.4 Persistir uploads em spool privado criptografado/cofre antes do enqueue, sem XML bruto ou caminho local em tabela, payload de fila ou resposta.
- [ ] 6.5 Implementar jobs idempotentes de descoberta e processamento por item, capazes de retomar após crash sem repetir itens terminais.
- [ ] 6.6 Implementar endpoints tenant-aware de resumo, progresso e itens paginados/filtráveis por resultado.
- [ ] 6.7 Implementar retry apenas para `UNMATCHED` e `FAILED` transitório enquanto o upload privado estiver retido, sem aceitar conflito/assinatura inválida cegamente.
- [ ] 6.8 Implementar exportação CSV do relatório com metadados sanitizados e sem XML, assinatura, caminho, vault ou segredo.
- [ ] 6.9 Manter `/api/v1/documents/import` como alias de transição e registrar/deprecar consumidores da resposta síncrona antes de removê-la.
- [ ] 6.10 Implementar limpeza imediata e coletor de resíduos para uploads/temporários expirados, preservando documentos aceitos e metadados do batch.
- [ ] 6.11 Auditar criação, retomada e resolução de lote sem nome de caminho malicioso, conteúdo XML ou stack trace.

## 7. Leitores seguros de ZIP e XML

- [ ] 7.1 Aplicar limites coerentes de 50 arquivos top-level e 20 MiB compactados totais em Nginx, PHP-FPM e Laravel, com margem para multipart.
- [ ] 7.2 Implementar preflight de central directory com teto de 5.000 XML, 5 MiB por XML, 250 MiB descompactados e razão 100:1, todos configuráveis.
- [ ] 7.3 Reforçar contagem, bytes e razão durante leitura streaming de cada entrada, descartando buffers por item e sem `getFromIndex` irrestrito.
- [ ] 7.4 Rejeitar ZIP aninhado por magic bytes, criptografado, multidisco, symlink, caminho absoluto, traversal, NUL, entrada normalizada duplicada e metadado inconsistente.
- [ ] 7.5 Garantir que nenhuma entrada use seu caminho para extração e que toda entrada não diretório receba estado explícito, inclusive tipo não suportado.
- [ ] 7.6 Configurar parser XML com rede, filesystem, DTD, entidades e XInclude desabilitados e limites de profundidade/nós.
- [ ] 7.7 Implementar detector estrito de `procNFe`/`nfeProc` modelos 55/65 e `procEventoNFe`, sem assumir modelo 55 quando ausente.
- [ ] 7.8 Validar assinatura, `infNFe/@Id`, chave/DV conforme leiaute vigente, protocolo, autorização, ambiente, emitente e `tpNF=1` antes da promoção.
- [ ] 7.9 Validar assinatura, chave, tipo, sequência, protocolo e situação registrada de cancelamento 55/65 e CC-e 55.
- [ ] 7.10 Preservar XML bem-formado de versão XSD desconhecida com parse alert somente quando identidade, assinatura e autorização puderem ser verificadas.
- [ ] 7.11 Classificar `<NFe>` sem protocolo, `resNFe`, PDF/DANFE, HTML, modelo fora de 55/65 e XML não fiscal como `INVALID`/`UNSUPPORTED`, nunca como full.
- [ ] 7.12 Criar métricas de bytes/entradas/razão/tempo e backpressure dos workers sem labels contendo chave completa, XML ou nome inseguro.

## 8. Processamento e reconciliação do import

- [ ] 8.1 Processar notas antes de eventos em cada batch, independentemente da ordem dos arquivos/entradas, mantendo falhas item a item.
- [ ] 8.2 Associar cada nota pelo `emit/CNPJ` completo exato a estabelecimento ativo do office, inclusive em ZIP multiempresa sem filtro.
- [ ] 8.3 Tratar `client_id`/`establishment_id` selecionado somente como restrição e produzir `CLIENT_MISMATCH` quando divergir do XML.
- [ ] 8.4 Preservar emitente ausente/inativo/desconhecido em quarentena `UNMATCHED`, sem criar documento órfão ou revelar cadastro de outro office.
- [ ] 8.5 Criar/reutilizar `dfe_document` canônico com bytes originais e SHA-256 sem sobrescrever conteúdo existente.
- [ ] 8.6 Ao reencontrar o mesmo SHA, registrar aquisição/batch item e criar interesses ausentes, sem duplicar vault ou retornar cedo antes da reconciliação.
- [ ] 8.7 Quarentenar mesma chave/tipo com SHA diferente e manter download/projeção canônicos até resolução auditada.
- [ ] 8.8 Reconciliar corrida de constraints entre workers como duplicata idempotente, sem estado parcial ou erro permanente.
- [ ] 8.9 Criar simultaneamente interesses `ISSUER/OUT` e `TAKER/IN` quando emitente e destinatário forem clientes do escritório, mantendo um XML.
- [ ] 8.10 Remover o NSU sintético por CRC32 do import e usar somente aquisição/batch item como proveniência manual.
- [ ] 8.11 Consolidar contadores e estado `COMPLETED`/`COMPLETED_WITH_ERRORS` após todos os itens, preservando sucessos em falha parcial.
- [ ] 8.12 Provar por teste de isolamento que nenhum caminho de criação, retry ou evento do import carrega A1/CSC ou chama SEFAZ.

## 9. Catálogo, direção e entrega

- [ ] 9.1 Tornar `document_interests` a fonte de verdade de papel/direção por estabelecimento nas queries e serializers do catálogo.
- [ ] 9.2 Ajustar filtros `kind`, cliente, estabelecimento e `direction` para interesses, permitindo a mesma chave satisfazer IN e OUT em contextos distintos sem duplicar bytes.
- [ ] 9.3 Expor no detalhe todos os interesses e aquisições permitidos, com origens autXML/import separadas e NSU apenas quando real.
- [ ] 9.4 Manter uma linha documental estável por página ampla, sinalizando múltiplos papéis/direções sem escolher uma direção global predominante.
- [ ] 9.5 Garantir que `NFCE` modelo 65 importada nunca seja rotulada como aquisição autXML e que `NFE` 55 possa vir de ambas as origens.
- [ ] 9.6 Excluir quarentena de catálogo, insights, contagens, exportação e download comuns mesmo quando a chave é conhecida.
- [ ] 9.7 Fazer o download sempre devolver os bytes canônicos integrais e nunca candidato divergente, resumo ou spool do batch.
- [ ] 9.8 Aplicar isolamento de `office_id` em aquisição, interesse, detalhe, download, batch, quarentena e resolução, com respostas que não revelam existência externa.

## 10. Interface interna Nuxt

- [ ] 10.1 Antes de editar Vue, aplicar o encadeamento `frontend-nuxt-stack`/`nuxt-dashboard-template` e reutilizar o arquétipo oficial fixado do painel.
- [ ] 10.2 Criar Configurações > Identidade fiscal do escritório com CNPJ copiável, metadados permitidos e gestão do A1 restrita a ADMIN+2FA recente.
- [ ] 10.3 Implementar upload/substituição do A1 sem persistir senha no estado além da requisição e sem ação de recuperação/download.
- [ ] 10.4 Criar checklist de onboarding `autXML` por estabelecimento com estados, aviso não retroativo, instrução do ERP e cobertura explícita “NF-e 55”.
- [ ] 10.5 Mostrar `first_seen_at`/`last_seen_at` e impedir confirmação operacional antes da ativação inicial/quiet obrigatório do stream.
- [ ] 10.6 Criar card de sincronização central do escritório com NSUs, heartbeat, cStat, próxima tentativa e backoff, sem edição/reset de cursor.
- [ ] 10.7 Evoluir Importar saídas para drag-and-drop/seleção múltipla XML+ZIP, limites visíveis, associação automática por emitente e restrição opcional de cliente.
- [ ] 10.8 Criar histórico e rota reproduzível de batch com upload/processamento distintos, polling controlado e progresso que sobrevive ao fechamento do modal.
- [ ] 10.9 Criar tabela paginada/filtrável e CSV por item com retry orientado de `UNMATCHED`, sem ação de aceitar conflito/arquivo inválido cegamente.
- [ ] 10.10 Cobrir teclado, foco, leitores de tela, mobile e ausência de XML/segredos/stack trace no DOM, toast, console e fixtures.

## 11. Operação, segurança e observabilidade

- [ ] 11.1 Adicionar saúde/alertas do A1 do escritório separados das credenciais de clientes, incluindo marcos 30/7/1, expiração e último uso.
- [ ] 11.2 Adicionar métricas e histórico do cursor central para atraso, páginas, documentos, last/max NSU, 137, 138, 593, 618, 656, decode e bloqueio.
- [ ] 11.3 Criar circuit breaker de 656 que suspende todas as consultas da raiz/ambiente por pelo menos uma hora desde a tentativa mais recente e impede retry antecipado.
- [ ] 11.4 Criar inbox e fluxo de resolução para emitente sem vínculo, tag divergente, evento órfão, bytes conflitantes e schema incompleto, sem XML bruto.
- [ ] 11.5 Adicionar monitoramento de batches `UPLOADED/QUEUED/PROCESSING/COMPLETED/COMPLETED_WITH_ERRORS/FAILED`, watchdog, contadores e retomada elegível.
- [ ] 11.6 Sanitizar logs, exceções, auditoria, métricas e payloads de fila para remover PFX, senha, PEM, XML, `vault_object_id`, path e stack trace.
- [ ] 11.7 Implementar policies de retry/resolução por papel, 2FA quando segredo estiver envolvido e motivo auditável, sempre derivando owner/office no backend.
- [ ] 11.8 Criar comandos/rotinas de manutenção para limpar spool expirado, revisar quarantine aging e verificar heartbeat sem editar NSU.

## 12. Matriz de testes e verificação

- [ ] 12.1 Criar fixtures oficiais/sanitizadas para múltiplos `autXML`, tag ausente/divergente, CNPJ alfanumérico, 55/65, `procNFe`, `resNFe`, eventos e cStats previstos.
- [ ] 12.2 Testar identidade/A1 do escritório ponta a ponta com rotação, expiração, finalidade, 2FA, isolamento e scanners de segredo.
- [ ] 12.3 Testar dois workers/instâncias e duas filiais da mesma raiz para provar cursor/lock único e ausência de chamadas concorrentes.
- [ ] 12.4 Testar página autXML atômica, reprocessamento, duplicata, falha de banco/cofre e bloqueio após cinco falhas sem avanço de NSU.
- [ ] 12.5 Testar roteamento por emitente, enrollment, outro tenant, evento órfão, escritório também destinatário e proibição de manifestação.
- [ ] 12.6 Testar lotes com XML direto, vários XML, vários ZIP, lote misto 55/65, subdiretórios e ZIP multiempresa.
- [ ] 12.7 Testar limites/bomb, compression ratio, milhares de entradas, nested ZIP, encrypted/multidisk, symlink, traversal, path absoluto, NUL e nomes duplicados.
- [ ] 12.8 Testar XXE/DTD/XInclude/rede, profundidade/nós, assinatura, protocolo, chave/DV, ambiente, `tpNF`, modelo ausente e versão XSD desconhecida.
- [ ] 12.9 Testar mesmo SHA entre fontes, aquisição/interesse faltante, chave com bytes divergentes, corrida de unique e documento simultaneamente IN/OUT.
- [ ] 12.10 Testar API 202, idempotency key, paginação, retry, CSV, retenção/cleanup, papéis e ausência de vazamento cross-tenant.
- [ ] 12.11 Criar testes unitários/componentes e Playwright para A1 do escritório, onboarding, cursor, upload, histórico, relatório e acessibilidade.
- [ ] 12.12 Executar testes backend/frontend, análise estática, lint, `git diff --check` e `openspec validate`, corrigindo toda regressão antes do piloto.
- [ ] 12.13 Garantir que CI use somente fixtures sanitizadas e não contenha certificado real de homologação/produção.

## 13. Migração, smoke, piloto e escala

- [ ] 13.1 Aplicar schema e interfaces com flags desligadas e executar novamente backup+restore para provar recuperação das novas tabelas e objetos do cofre.
- [ ] 13.2 Migrar a UI/import para batches em ambiente local, observar filas/limites e concluir a transição da rota síncrona sem consumidor oculto.
- [ ] 13.3 Cadastrar identidade/A1 do escritório em produção restrita e verificar que metadados/segredo/auditoria obedecem o contrato antes de qualquer chamada.
- [ ] 13.4 Executar smoke de produção allowlisted: primeira `distNSU`, registrar `cStat=137`, esperar pelo menos uma hora e executar a segunda consulta sem retry antecipado.
- [ ] 13.5 Confirmar ausência ou transferência do consumidor externo da mesma raiz e manter o gate fechado se houver 656/NSU incompatível.
- [ ] 13.6 Somente após o stream saudável, orientar um estabelecimento piloto a incluir o CNPJ completo do escritório em `autXML` no ERP antes da autorização.
- [ ] 13.7 Verificar o primeiro `procNFe` 55 real, tag exata, assinatura/protocolo, aquisição, interesse `ISSUER/OUT`, download byte a byte e ausência de manifestação.
- [ ] 13.8 Importar XML/ZIP histórico NF-e/NFC-e do piloto e reconciliar duplicatas, múltiplas aquisições, eventos, interesses e lacunas declaradas.
- [ ] 13.9 Observar durante a janela piloto cursor, heartbeat, 656, decode, quarentena, fila, uso de memória/disco, tempo de lote e validade do A1.
- [ ] 13.10 Executar drill de kill switch/rollback sem apagar cursor ou documentos e provar retomada pelo último NSU persistido.
- [ ] 13.11 Publicar runbooks de ERP/autXML, novo usuário sem retroatividade, consumidor concorrente, A1, 656, quarantine, import histórico e NFC-e por arquivo.
- [ ] 13.12 Registrar decisão formal de go/no-go e escalar em lotes pequenos de estabelecimentos, mantendo allowlist e métricas até cumprir SLOs definidos.
