## 1. Pré-condições e segurança operacional

- [x] 1.1 Confirmar que migrations, estados e contratos necessários da change `build-ma-outbound-nfe-nfce-capture` estão aplicados e registrar incompatibilidades antes de codificar.
- [x] 1.2 Executar backup da instância local e restore drill, registrando evidência sanitizada antes das novas migrations.
- [x] 1.3 Adicionar as flags `SEFAZ_SVRS_NFCE_XML_RETRIEVAL_ENABLED`, `SEFAZ_SVRS_NFCE_XML_AUTO_QUEUE_ENABLED` e `SEFAZ_SVRS_NFCE_XML_PILOT_ALLOWLIST_ONLY` com defaults desligados.
- [x] 1.4 Documentar matriz de habilitação por instância, escritório, raiz, estabelecimento, ambiente e perfil.
- [x] 1.5 Criar configuração tipada e validada para hosts/paths SVRS, TLS mínimo, timeouts e limites, sem aceitar override por request.
- [x] 1.6 Adicionar kill switch específico do canal SVRS e teste de que o estado fiscal/documental é preservado.

## 2. Schema e modelo de domínio

- [x] 2.1 Criar migration aditiva para o modo/origem `SVRS_PORTAL_BY_KEY` nas solicitações de recuperação MA.
- [x] 2.2 Criar `outbound_xml_recovery_attempts` com `office_id`, request, correlação, número da tentativa, tempos, classe HTTP, versão do parser, resultado e motivo sanitizado.
- [x] 2.3 Adicionar índices/unique constraints para uma recuperação lógica ativa por escritório+perfil+chave+origem.
- [x] 2.4 Adicionar proveniência `SVRS_NFCE_DOWNLOAD_XML_DFE` a `document_acquisitions` sem inventar NSU.
- [x] 2.5 Implementar enums/VOs de estado, motivo de falha, resultado do transporte e elegibilidade.
- [x] 2.6 Implementar casts e validações que mantenham CNPJ/chave como texto maiúsculo, sem máscara e sem conversão numérica.
- [x] 2.7 Aplicar scopes/policies de tenancy e provar que toda query inclui `office_id` derivado do servidor.
- [x] 2.8 Criar testes de migration, rollback estrutural e constraints concorrentes em PostgreSQL.

## 3. Contratos, fakes e fixtures

- [x] 3.1 Criar interface `SvrsNfceOutboundXmlRetrievalClient` com request/result tipados e sem HTML bruto fora da infraestrutura.
- [x] 3.2 Criar interface/parser `SvrsNfceDownloadResponseParser` com versão explícita do contrato do wrapper.
- [x] 3.3 Criar avaliador `SvrsNfceRetrievalEligibility` para UF 21, modelo 65, OUT, ambiente, perfil, allowlist e A1.
- [x] 3.4 Criar implementação disabled/fake do cliente para testes e ambientes com flag off.
- [x] 3.5 Produzir fixture sanitizada mínima do formulário GET observado, removendo chave, CNPJ, protocolo, certificado e XML real.
- [x] 3.6 Produzir fixtures sanitizadas do POST: sucesso, não disponível, autenticação negada, rate limit, manutenção e contrato alterado.
- [x] 3.7 Criar gerador de `nfeProc` fixture assinado com certificado de teste exclusivo do repositório, sem certificado fiscal real.
- [x] 3.8 Adicionar casos maliciosos de wrapper: concatenação, template string, múltiplos candidatos, escapes inválidos, truncamento e payload excessivo.

## 4. Parser seguro do wrapper

- [x] 4.1 Implementar detecção do formulário/marcadores esperados no GET e falha `RESPONSE_CONTRACT_CHANGED` para divergências.
- [x] 4.2 Implementar extração determinística do único literal associado ao `Blob`/download oficial.
- [x] 4.3 Implementar decoder mínimo de escapes JavaScript sem `eval`, engine JS ou `stripcslashes` genérico.
- [x] 4.4 Rejeitar expressão, concatenação, template string, entidade ambígua e múltiplos XML candidatos.
- [x] 4.5 Aplicar limites configurados de HTML, literal e XML decodificado antes de alocar/persistir conteúdo excessivo.
- [x] 4.6 Garantir que o parser devolva exatamente os bytes do `Blob`, sem normalização ou reserialização.
- [x] 4.7 Cobrir parser/decoder com testes unitários, property/fuzz tests de escapes e snapshots sanitizados por versão.

## 5. Validação fiscal e criptográfica

- [x] 5.1 Implementar parser XML sem DTD, entidade externa, acesso à rede ou filesystem.
- [x] 5.2 Validar raiz/namespace/versão `nfeProc`, chave de `infNFe/@Id`, DV, `cUF=21`, modelo 65 e ambiente.
- [x] 5.3 Validar CNPJ completo do emitente contra estabelecimento e raiz associada à credencial A1.
- [x] 5.4 Validar `protNFe/infProt/chNFe`, status de autorização permitido e coerência com a chave solicitada.
- [x] 5.5 Validar digest de todas as referências XMLDSig relevantes sem aceitar algoritmo inseguro não allowlisted.
- [x] 5.6 Validar assinatura XMLDSig com o X.509 embutido e registrar somente fingerprint/metadados permitidos.
- [x] 5.7 Calcular SHA-256 sobre os bytes exatos antes do `SecureObjectStore`.
- [x] 5.8 Classificar versão XML desconhecida, identidade divergente, protocolo ausente e assinatura inválida sem promover documento canônico.
- [x] 5.9 Criar testes positivos e negativos para chave, DV, CNPJ alfanumérico, ambiente, protocolo, digest, assinatura, DTD/XXE e encoding.

## 6. Transporte HTTP+mTLS

- [x] 6.1 Implementar transporte libcurl com PFX/senha por BLOB em memória, TLS >= 1.2 e hostname/chain verification obrigatórios.
- [x] 6.2 Implementar GET do formulário no host/path allowlisted com timeouts, limites e correlação.
- [x] 6.3 Implementar cookie engine somente em memória para a duração da recuperação e limpeza garantida ao final.
- [x] 6.4 Implementar POST form-urlencoded com campos oficiais, `Referer`/`Accept` controlados e chave nunca registrada em log.
- [x] 6.5 Bloquear downgrade, redirect externo, DNS/host não allowlisted e alteração de método insegura.
- [x] 6.6 Mapear classes HTTP/rede para resultados tipados e respeitar `Retry-After` válido sem expor corpo remoto.
- [x] 6.7 Garantir limpeza de handles, buffers e referências de credencial em sucesso, timeout e exception.
- [x] 6.8 Criar testes de integração com servidor mTLS local para certificado aceito, rejeitado, vencido, redirect e TLS inválido.
- [x] 6.9 Criar teste de contrato com fake HTTP cobrindo sequência GET->POST, cookie em memória e headers esperados.

## 7. Ingestão imutável e proveniência

- [x] 7.1 Adaptar o serviço de ingestão de saída para receber fonte automática SVRS sem passar por DTO de upload humano.
- [x] 7.2 Persistir os bytes validados via `SecureObjectStore` antes de concluir aquisição, projeção e recovery.
- [x] 7.3 Registrar `document_acquisition` SVRS com hash, correlação, ambiente e horário sem HTML/XML bruto.
- [x] 7.4 Reutilizar projeção NF-e/NFC-e com `kind=NFCE`, modelo 65, `fiscal_role=ISSUER` e `direction=OUT` derivados no backend.
- [x] 7.5 Implementar idempotência para mesma chave+hash já capturada por upload, pacote ou SVRS.
- [x] 7.6 Implementar quarentena e alerta para mesma chave com bytes/hash divergentes, preservando o documento canônico.
- [x] 7.7 Implementar reconciliação após falha entre objeto seguro, aquisição e projeção sem duplicar bytes.
- [x] 7.8 Provar em testes que ingestão SVRS não chama autorização, inutilização, cancelamento ou recepção de evento.

## 8. Orquestração e filas

- [x] 8.1 Implementar `OutboundXmlRecoveryOrchestrator` para criar recovery a partir de `KEY_DISCOVERED`/`XML_PENDING` elegível.
- [x] 8.2 Implementar job Horizon dedicado com payload apenas de identificadores internos e queue do canal MA outbound.
- [x] 8.3 Implementar transições duráveis `ELIGIBLE -> QUEUED -> RUNNING -> CAPTURED|RETRY_SCHEDULED|NOT_AVAILABLE_VISIBLE|BLOCKED`.
- [x] 8.4 Marcar `XML_CAPTURED` somente após vault, aquisição e projeção confirmados na ordem definida.
- [x] 8.5 Implementar backoff 15m/1h/6h/12h com jitter e máximo de cinco tentativas recuperáveis.
- [x] 8.6 Bloquear retry cego para contrato alterado, identidade/assinatura inválida e autenticação persistente.
- [x] 8.7 Implementar lock por estabelecimento+ambiente+modelo+série, dedupe por chave e recuperação de worker morto.
- [x] 8.8 Implementar lote máximo de vinte chaves e requeue sem perder posição/estado.
- [x] 8.9 Encerrar jobs futuros quando upload/pacote de outra fonte capturar a mesma chave.
- [x] 8.10 Preservar `XML_PENDING` e abrir fallback assistido quando o canal estiver off, bloqueado ou esgotado.
- [x] 8.11 Criar scheduler de auto-queue com spread determinístico e dependência explícita da flag própria.
- [x] 8.12 Cobrir concorrência scheduler+operador, retry, crash, duplicata e resolução por fallback em testes PostgreSQL/Redis.

## 9. Rate limit e circuit breaker

- [x] 9.1 Implementar semáforo global de uma recuperação em voo por instância.
- [x] 9.2 Implementar intervalo global mínimo de cinco segundos e intervalo de trinta segundos por raiz.
- [x] 9.3 Implementar breaker global para contrato/autenticação sistêmica e breaker por raiz para A1/identidade.
- [x] 9.4 Abrir breaker nos limiares definidos e impedir GET/POST enquanto aberto.
- [x] 9.5 Implementar half-open com uma única chave allowlisted e transição auditada para closed/open.
- [x] 9.6 Integrar rate limit, breaker, flags e kill switch ao job antes de materializar o A1.
- [x] 9.7 Criar testes com relógio falso para intervalos, jitter, `Retry-After`, abertura, half-open e reset.

## 10. API, policies e auditoria

- [x] 10.1 Criar endpoints same-origin para resumo do canal e lista paginada/filtrável de recoveries/tentativas.
- [x] 10.2 Criar ação idempotente de enfileirar/reprocessar uma recuperação elegível para OPERATOR/ADMIN.
- [x] 10.3 Criar endpoints ADMIN+2FA para allowlist, kill switch e consulta/reset de breaker.
- [x] 10.4 Aplicar policies ADMIN/OPERATOR/VIEWER e negar recurso de outro escritório sem revelar sua existência.
- [x] 10.5 Ignorar/rejeitar `office_id`, URL, host, headers, cookies e referência de credencial enviados pelo cliente.
- [x] 10.6 Criar resources/DTOs sanitizados sem HTML, XML bruto, PFX, senha, PEM, cookie, token ou `vault_object_id`.
- [x] 10.7 Registrar auditoria para queue/retry, allowlist, breaker, kill switch, captura e fallback com correlação e motivo sanitizado.
- [x] 10.8 Criar feature tests de auth, 2FA, tenancy, validação 422, idempotência e varredura anti-segredo.

## 11. Métricas, inbox e runbooks

- [x] 11.1 Instrumentar contadores de queued/captured/duplicate/retry/blocked/fallback e histogramas de latência GET/POST/total.
- [x] 11.2 Instrumentar backlog, idade do item mais antigo e estado do breaker sem CNPJ/chave como labels.
- [x] 11.3 Adicionar itens tipados de inbox para A1, autenticação, rate limit, contrato alterado, XML/assinatura, divergência, breaker e tentativas esgotadas.
- [x] 11.4 Incluir saúde SVRS no resumo operacional e deep-links para estabelecimento/recovery autorizados.
- [x] 11.5 Sanitizar logs/exceptions do transporte, parser, validator, job e ingestão e adicionar teste automático de marcadores proibidos.
- [x] 11.6 Criar runbook de ativação, diagnóstico, breaker, kill switch, fallback assistido e rollback sem apagar estado.
- [x] 11.7 Criar alerta operacional para backlog/idade, breaker aberto e queda abrupta da taxa de captura.

## 12. Frontend Nuxt UI

- [x] 12.1 Ler e aplicar `nuxt-dashboard-template`, `frontend-nuxt-stack`, `nuxt` e `nuxt-ui` antes de alterar componentes Vue.
- [x] 12.2 Estender a seção Sincronização do estabelecimento com card “XML NFC-e via SVRS” derivado do arquétipo Settings.
- [x] 12.3 Exibir elegibilidade, flags efetivas, backlog, última tentativa/captura, próximo retry, breaker e motivo sanitizado.
- [x] 12.4 Criar lista server-side de recoveries/tentativas com filtros em URL, paginação e estados loading/empty/error.
- [x] 12.5 Implementar vocabulário visual distinto para chave descoberta, XML pendente, em recuperação, capturado, fallback e bloqueado.
- [x] 12.6 Implementar retry/fallback para OPERATOR/ADMIN e controles de allowlist/breaker/kill switch somente para ADMIN+2FA.
- [x] 12.7 Integrar upload XML/ZIP existente como fallback preservando contexto e proveniência.
- [x] 12.8 Impedir renderização de HTML/JavaScript remoto e mascarar chaves/identificadores conforme política.
- [x] 12.9 Cobrir componentes, composables, route state, permissões, responsividade, teclado e estados assíncronos com testes.
- [x] 12.10 Executar validação visual contra o template fixado em desktop/mobile e claro/escuro.

## 13. Qualidade e segurança ponta a ponta

- [x] 13.1 Executar testes unitários e de integração backend para parser, XMLDSig, transporte, ingestão, orquestração, rate limit e breaker.
- [x] 13.2 Executar testes frontend, lint, typecheck e build de produção.
- [x] 13.3 Executar suíte completa do backend e confirmar ausência de regressão nos canais ADN, DistDFe, CT-e e import XML/ZIP.
- [x] 13.4 Executar testes de tenancy cruzada para API, jobs, inbox, métricas e downloads.
- [x] 13.5 Executar teste anti-XXE, payload excessivo, redirect/SSRF, HTML malicioso e algoritmo XMLDSig não permitido.
- [x] 13.6 Executar varredura anti-segredo sobre logs, traces, audit, banco, Redis/Horizon, exceptions e responses.
- [x] 13.7 Executar drill de kill switch/rollback com backlog e confirmar preservação de documentos, tentativas e posições `nNF`.
- [x] 13.8 Validar a change com `openspec validate add-svrs-nfce-outbound-xml-retrieval --json` após a implementação.

## 14. Piloto e expansão

- [ ] 14.1 Registrar decisão operacional/jurídica sobre uso automatizado, limites assumidos e canal de contato SVRS/SEFAZ-MA antes do primeiro auto-queue.
- [ ] 14.2 Executar smoke mTLS restrito com uma chave allowlisted, sem persistência automática, e comparar contrato/fixture sem registrar dados fiscais.
- [ ] 14.3 Habilitar ingestão manual de uma chave piloto e confirmar `nfeProc`, chave, protocolo, digest, assinatura, hash, vault e catálogo.
- [ ] 14.4 Comparar amostra, quando disponível, com o XML original do PDV e registrar igualdade/divergência de bytes.
- [ ] 14.5 Testar uma NFC-e cancelada para documentar tratamento de eventos sem inferir situação final incorreta.
- [ ] 14.6 Executar piloto de uma raiz/uma série com auto-queue desligado, limites conservadores e métricas observadas.
- [ ] 14.7 Ativar auto-queue somente para a raiz piloto após critérios de segurança, erro, backlog e breaker aprovados.
- [ ] 14.8 Acompanhar período piloto, documentar taxa de captura, retries, bloqueios, latência e fallback sem dados fiscais.
- [ ] 14.9 Ampliar allowlist gradualmente por decisão registrada, sem aumentar limites de taxa sem evidência.
- [x] 14.10 Atualizar matriz de cobertura do produto marcando NFC-e 65/MA automática e mantendo NF-e 55 sem este canal.
