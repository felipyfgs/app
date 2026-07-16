## 1. Contenção, fontes e decisões externas

- [x] 1.1 Manter kill switch e drivers reais desligados e adicionar `prod-check` que bloqueie egress faturável enquanto a versão de credencial exposta não estiver `RETIRED`/`COMPROMISED`.
- [x] 1.2 Inventariar e segregar Offices/clientes demo, tokens fake, ledger shadow e evidências antigas, sem apagar trilha histórica nem promover registros por inferência.
- [x] 1.3 Criar registro versionado das fontes oficiais (URL, data, hash, tipo, revisão e capacidades afetadas) para API Reference, OAuth, Termo, cache, procurações, cobrança, Eventos, cadeia TLS e CNPJ alfanumérico.
- [ ] 1.4 Abrir e registrar chamado SERPRO sobre a divergência entre o curl da Área do Cliente e o endpoint canônico `/authenticate`; manter o fluxo alternativo bloqueado até resposta formal.
  - Scaffold de gate em código/docs; evidência em `evidence/external-gates.md` ainda pendente de resposta formal.
- [ ] 1.5 Solicitar/confirmar com o SERPRO o XSD oficial do Termo e a serialização de CNPJ alfanumérico no Termo/Eventos, registrando respostas como evidência sem segredo.
  - Scaffold ok; resposta oficial externa pendente.
- [ ] 1.6 Submeter a divergência de vigência do contrato, tabela/ciclo tarifário e modelo software-house à revisão jurídico-comercial e transformar pendências em gates documentais.
  - Gates documentais modelados; aceite jurídico-comercial externo pendente.
- [ ] 1.7 Definir responsáveis, on-call, RPO/RTO, escrow/KMS, política de custódia A1 e Office/cliente não-demo candidatos ao smoke gratuito sem gravar suas identidades nos artefatos OpenSpec.
  - Template em evidence; papéis/RPO-RTO ainda TBD.

## 2. Persistência e modelo de domínio

- [x] 2.1 Criar migrations/modelos para versões globais de credencial, estado `PENDING|VERIFIED|ACTIVE|RETIRED|COMPROMISED`, approvals de quatro olhos e referências opacas ao vault.
- [x] 2.2 Criar `serpro_readiness_runs` e evidências/gates hierárquicos com escopos global, Office, cliente e operação, validade e motivos sanitizados.
- [x] 2.3 Versionar Termos, consentimentos e transições de autorização, ligando contrato, ambiente, Office, autor, hash, vigência e referências de token/ETag no vault.
- [x] 2.4 Acrescentar ambiente, authorization, autor, contribuinte, origem/proveniência, aceite, freshness e encerramento aos poderes/procurações, com índices tenant-safe.
- [x] 2.5 Evoluir reservations/entries para ambiente, contrato, tentativa, catálogo, preço, rota, request tag, estado remoto e resultado durável; corrigir `serviceCode`/`operationCode`.
- [x] 2.6 Modelar schedules/faixas contratuais, ciclo 21–20, budgets global/Office/canário, linhas de consumo/fatura, conciliações e incidentes.
- [x] 2.7 Criar journal/catálogo de objetos do vault, versão criptográfica real, estado de rewrap, retenção e coleta segura de órfãos.
- [x] 2.8 Auditar colunas/índices/DTOs de CPF/CNPJ e aplicar migrations aditivas para identidade string alfanumérica sem coerção ou perda de zeros.

## 3. Vault, credenciais e OAuth produtivo

- [x] 3.1 Implementar keyring com chave atual e anteriores, leitura por versão criptográfica e testes que diferenciem versão de chave de AAD.
- [x] 3.2 Implementar comando de rewrap retomável, idempotente e auditado com dry-run, lock e verificação antes/depois; testar rotação e rollback.
- [x] 3.3 Unificar backup DB+vault/private storage, cifrar/autenticar o pacote e implementar restore drill que valide referências DB→vault e descriptografia com chave externa.
- [x] 3.4 Remover token do procurador e ETag sensível de Redis/banco em texto claro; ampliar scanners de segredo para cache, jobs, logs, exceções, CLI e APIs.
- [x] 3.5 Implementar cadastro e verificação de versão pendente de Key/Secret/PFX sem aceitar segredo por argumento CLI ou reexibi-lo em resposta.
- [x] 3.6 Validar PFX do contratante: chave privada, titular/CNPJ do contrato, finalidade, validade/horizonte, algoritmo e cadeia confiável; persistir somente fingerprint/metadados.
- [x] 3.7 Endurecer `HttpSerproContractAuthenticator` para protocolo oficial, par Bearer/JWT atômico, proveniência/versão/ambiente e retry único após 401.
- [x] 3.8 Implementar aprovação dupla e cutover atômico de credencial/contrato com OAuth prévio, invalidação de caches/tokens e retirada segura da versão anterior.
- [x] 3.9 Fazer boot/readiness de produção rejeitar `SERPRO_USE_FAKE_CLIENTS`, qualquer binding Fake/simulated, TLS sem validação, endpoint fora da allowlist ou token de proveniência divergente.

## 4. Termo de Autorização e Autentica Procurador

- [x] 4.1 Substituir o XSD lax por contrato estrito derivado e claramente identificado com versão/fonte/hash; incorporar XSD oficial se fornecido pelo SERPRO.
- [x] 4.2 Implementar gerador canônico do `termoDeAutorizacao` com elementos, atributos, textos legais, identidades e datas da versão oficial fixada.
- [x] 4.3 Reescrever `TermoXmlValidator` para XML seguro, uma assinatura/referência, dados extraídos do nó assinado, transforms permitidos e rejeição de wrapping/duplicidade/URI externa.
- [x] 4.4 Validar XMLDSig RSA-SHA256, digest SHA-256, C14N, Enveloped, X509 final, validade/finalidade/cadeia/revogação disponível e identidade do certificado.
- [x] 4.5 Implementar fluxo de assinatura externa A1/A3: geração, download protegido de draft e upload do XML final com validação e armazenamento canônico no vault.
- [x] 4.6 Implementar assinador A1 gerenciado atrás de consentimento versionado, Office ADMIN + 2FA, job dedicado, temporário protegido e limpeza comprovada.
- [x] 4.7 Fazer mudança de contrato, ambiente, autor, A1, destinatário ou Termo invalidar atomicamente token, ETag, cache e poderes derivados, respeitando retenção/auditoria.
- [x] 4.8 Corrigir `HttpAutenticarProcuradorClient` para `/Apoiar`, coordenadas oficiais e `pedidoDados.dados` como JSON string com `xml` Base64; bloquear `xmlAssinado`/XML cru.
- [x] 4.9 Implementar cache por contrato/ambiente/Office/autor/hash do Termo, `If-None-Match`, 304 seguro e expiração por `Expires`/resposta ou meia-noite seguinte em Brasília.
- [x] 4.10 Criar fixtures sintéticas/oficiais permitidas para layout, Base64, assinatura, wrapping, identidades, expiração, 200/304/erros e provar que só resposta real gera `SERPRO_ACCEPTED`.

## 5. Onboarding, procurações e identidades

- [x] 5.1 Implementar objeto/serviço da cadeia contratante → autor → contribuinte e exigir todos os elos antes de resolver uma operação real.
- [x] 5.2 Versionar/importar a matriz oficial `idSistema+idServico → poder`, com hash/revisão e `REVIEW_REQUIRED` quando a fonte mudar.
- [x] 5.3 Endurecer eligibility para Office, ambiente, autorização, autor, contribuinte, serviço/poder, status, origem, início/fim, aceite e freshness.
- [x] 5.4 Corrigir sync completo de procurações para encerrar/revogar ausentes e impedir que evidência simulada/manual vire `ACTIVE` sem verificação/aprovação explícita.
- [x] 5.5 Implementar regra D-1 de Eventos e estados de aceite da autorização RFB, sem chamar `OBTERPROCURACAO41` faturável durante o smoke gratuito.
- [x] 5.6 Implementar normalizador/validador único de CPF/CNPJ, incluindo CNPJ alfanumérico, e contract tests de round-trip em banco, cache, XML, JSON e UI.
- [x] 5.7 Criar gate `OFFICIAL_CLARIFICATION_REQUIRED` para campos numéricos conflitantes do Termo/Eventos e remover regexes/coerções numéricas fora desses gates.
- [x] 5.8 Exigir seleção explícita de Office real e ambiente, consentimento, ADMIN + 2FA para certificado/Termo/token e impedir Office/demo de usar endpoint real.
- [x] 5.9 Adicionar scheduler/jobs para PFX contratante, A1, Termo, token e procurações com skew/locks e alertas 90/60/30/15/7/1 dias, sem assinatura ou mutação automática.

## 6. Executor central, catálogo e idempotência

- [x] 6.1 Definir interface de executor produtivo único e restringir `HttpIntegraContadorClient`/transportes à camada de infraestrutura.
- [x] 6.2 Migrar SITFIS, DCTF/MIT, Simples/MEI, mailbox, parcelamentos, guias, cadastro, processos e procurações para o executor central.
- [x] 6.3 Adicionar teste de arquitetura que falhe para qualquer chamada/injeção direta de `IntegraContadorClient::execute` fora da allowlist.
- [x] 6.4 Aplicar no executor, imediatamente antes do HTTP, contrato, driver/proveniência, flags/allowlist, subscription, kill switches, Termo, token, poder, catálogo/codec, budget, limiter e breaker.
- [x] 6.5 Implementar attempt/result durável e state machine `reserved→dispatched→acknowledged|uncertain→reconciled`, com chave namespaced por Office/ambiente/operação/entidade.
- [x] 6.6 Corrigir replay concorrente/finalizado para retornar resultado persistido ou aguardar/bloquear, demonstrando no máximo um HTTP por operação lógica.
- [x] 6.7 Substituir o booleano genérico de gates mutantes por autorização tipada e manter todos os adapters Emitir/Declarar/mutantes bloqueados nesta change.
- [x] 6.8 Gerar matriz de coverage por operação e reclassificar `IMPLEMENTED` somente com fonte, coordenadas, auth, poder, cobrança, codec, driver, fixture e testes.
- [x] 6.9 Tornar snapshots/versões de catálogo imutáveis, suportar mais de uma revisão por dia e proibir fallback/default inventado em produção quando banco/fonte falhar.
- [x] 6.10 Gerar `X-Request-Tag` opaca de até 32 caracteres, sem PII, distinta da chave interna de idempotência e correlacionável com ledger/CSV.

## 7. Cobrança, orçamento, limiter e breaker

- [x] 7.1 Importar todas as faixas do contrato aprovado em micros de BRL com fonte/hash/vigência e retirar tabelas shadow da elegibilidade produtiva.
- [x] 7.2 Implementar ciclos de faturamento 21–20 e agregação transacional separada do mês calendário, sem escrita em endpoints GET.
- [x] 7.3 Implementar classificador pré-transporte por rota/status: `/Apoiar`/`/Monitorar` não bilhetados; lista oficial de status isentos; 200/202/403 faturáveis nas demais rotas; unknown fail-closed.
- [x] 7.4 Exigir budgets monetários positivos global, Office, operação/período e canário, com reserva atômica e proteção contra tenant ruidoso.
- [x] 7.5 Corrigir ledger para ambiente/contrato/coords canônicas, custo zero simulado, um request tag por attempt e `POSSIBLY_BILLABLE` após timeout incerto.
- [x] 7.6 Implementar limiter Redis atômico, limites versionados e política em que zero/ausente não signifique ilimitado para egress produtivo.
- [x] 7.7 Implementar circuit breaker atômico por dependência/solução, probes half-open limitados, classificação de falhas técnicas e persistência do estado operacional crítico.
- [x] 7.8 Implementar import/reconciliação de detalhamento e fatura por período/rota/status/tag, incidentes de divergência e isolamento de totais tenant/global.
- [x] 7.9 Segregar/reconciliar as entradas shadow/legadas atuais e impedir que cascades de offboarding destruam ledger/auditoria sujeitos a retenção.

## 8. Jobs, Eventos, readiness e observabilidade

- [x] 8.1 Criar `serpro:readiness` read-only e API sanitizada que avaliem gates globais/tenant/operação sem emitir token ou fazer chamada fiscal implícita.
- [x] 8.2 Persistir readiness/smoke com contrato, ambiente, operação, fingerprints, versões, resultado, expiração e distinção entre evidência offline/live.
- [x] 8.3 Corrigir `RefreshRegistrationLinksJob`/`RefreshTaxProcessesJob` e demais jobs para fila com supervisor Horizon, erro/retry real, backoff e run/cursor durável.
- [x] 8.4 Adicionar teste/inventário que compare todos os `onQueue()` com supervisores Horizon e verificar flags no dispatch e novamente dentro do job.
- [x] 8.5 Implementar fluxo Eventos solicitar→aguardar→obter com protocolo, `TempoEsperaMedioEmMs`, `TempoLimiteEmMin`, one-shot, persistência transacional e sem TTL hardcoded.
- [x] 8.6 Aplicar limites versionados de 1.000 PF/dia, 1.000 PJ/dia e 1.000 contribuintes/lote enquanto vigentes; tratar 429 sem retry até a janela permitida.
- [x] 8.7 Exportar métricas sem PII para OAuth/gateway, latência, 401/403/429/5xx, breaker, filas, retries, expirações, orçamento/custo, unknown e conciliação.
- [x] 8.8 Configurar alertas/runbook links e scheduler para certificados, Termo, token, procurações, fila parada, breaker, budget, drift, backup/restore, catálogo/preço e Horizon snapshot.

## 9. APIs, policies e frontend

- [x] 9.1 Criar/ajustar rotas globais `/api/v1/platform/serpro/*` com `PLATFORM_ADMIN` + TOTP, approvals duplos e responses sanitizadas para credencial, readiness, orçamento e rollout.
- [x] 9.2 Criar/ajustar rotas tenant `/api/v1/serpro/*` com Sanctum, usuário ativo, `EnsureOfficeContext`, papéis/2FA e rejeição de `office_id` do cliente HTTP.
- [x] 9.3 Adicionar policies/serializers que impeçam `PLATFORM_ADMIN` sem membership de obter dados fiscais e nunca exponham segredo, XML bruto ou ID de vault.
- [x] 9.4 Usar `panel-ui` → `ui-archetype` para implementar console global em `frontend/app/pages/admin/` com contrato, readiness, kill switch, coverage, preços, budgets, conciliação e rollout.
- [x] 9.5 Usar os mesmos arquétipos para transformar settings em checklist ambiente→autor→certificado/Termo→token→procuração/poder→cliente/operação.
- [x] 9.6 Implementar seletores tipados de cliente/serviço/poder, badges `simulado|real|estimado|conciliado` e motivos/próximas ações por papel.
- [x] 9.7 Corrigir inbox/deep-links SERPRO e filtros para que cada alerta navegue a uma rota existente e tenant-safe.
- [x] 9.8 Adicionar testes unitários/E2E do frontend para papéis, estados, CNPJ alfanumérico, redaction, checklist e navegação, sem fixture produtiva.

## 10. Segurança, privacidade e operação

- [x] 10.1 Implementar auditoria append-only com hash encadeado/armazenamento imutável para rotação, consentimento, Termo, approvals, gates, kill switch e conciliação.
- [x] 10.2 Criar rotina/verificação de integridade da auditoria e alertar quebra sem registrar payload, token ou PII como label.
- [x] 10.3 Definir retenção/offboarding para PFX, tokens, Termos, poderes, evidências e ledger; implementar revogação imediata e GC seguro após prazo legal.
- [x] 10.4 Documentar papéis de controlador/operador, finalidade, hipótese legal, sigilo fiscal, categorias, retenção, instruções do Office, atendimento ao titular e resposta a incidente.
- [x] 10.5 Criar runbooks para chave/PFX comprometido, expiração, Termo rejeitado, 401, 403 faturável, 429, 5xx, custo anômalo, procuração revogada, cross-tenant, vault/key loss e indisponibilidade.
- [x] 10.6 Tornar kill switch/rollout/approvals persistentes, ensaiar restart/flush Redis e provar que segurança não reabre por perda de cache.
- [x] 10.7 Atualizar `.env.example`, `backend/.env.example`, `.env.prod.example`, Makefile, deploy e verify com variáveis/gates, sem inserir segredo ou tornar flags reais default ON.
- [x] 10.8 Atualizar secret scan e `.gitignore`/build context para PFX/PEM/PDF sensível/dumps/vault e criar procedimento de remoção segura de cópias transitórias após importação.

## 11. Verificação automatizada e gates de qualidade

- [x] 11.1 Rodar `cd backend && php artisan test --filter=SerproCredential` para rotação, OAuth, fake em produção, vault e respostas sanitizadas.
- [x] 11.2 Rodar `cd backend && php artisan test --filter=Termo` para schema, geração, XMLDSig, wrapping, Base64, cache/304, invalidação e aceite separado.
- [x] 11.3 Rodar `cd backend && php artisan test --filter=SerproEligibility` para isolamento, papéis, cadeia de representação, procurações, freshness e CNPJ alfanumérico.
- [x] 11.4 Rodar `cd backend && php artisan test --filter=SerproBilling` para classificação, preços, ciclo 21–20, budget, ledger, reconciliação e timeout incerto.
- [x] 11.5 Rodar testes PostgreSQL+Redis reais de concorrência para idempotência, locks, limiter, breaker e unique indexes; anexar resultado sanitizado ao readiness.
- [x] 11.6 Rodar testes de arquitetura/filas para executor único, ausência de Fake em produção, controllers tenant-safe e consumidores Horizon completos.
- [x] 11.7 Rodar `cd backend && vendor/bin/pint --test && php artisan test` e `cd frontend && pnpm run lint && pnpm run typecheck && pnpm run generate && pnpm run test`.
- [x] 11.8 Rodar `./docker/ops/verify.sh --full`, secret scan, backup/restore drill e `openspec validate operacionalizar-integra-contador-producao --strict`; corrigir todos os FAILs.

## 12. Rollout e smoke sem cobrança

Tooling/scaffold marcado nos sub-bullets; checkboxes de live ops permanecem abertos até evidence preenchida.

- [ ] 12.1 Rotacionar Consumer Key/Secret expostos no canal SERPRO, coordenar APIs compartilhadas, marcar a versão anterior comprometida e não registrar os novos valores em issue/log/change.
  - Scaffold: `serpro:credential-version compromise|register-pending|verify|approve-cutover|cutover`; runbook `docs/ops/runbooks/serpro-credential-rotation.md`; evidence template `evidence/12-credential-rotation.md`. **Live ops-gated.**
- [ ] 12.2 Avaliar reexportação/revogação dos PFX expostos, importar versões aprovadas no vault e remover cópias transitórias do workspace/host conforme procedimento auditado.
  - Scaffold: mesmo CLI + `docs/ops/serpro-transient-secret-removal.md`; dual-approval cutover. **Live ops-gated.**
- [ ] 12.3 Implantar em produção limpa com drivers/flags `OFF`, dados demo segregados, budgets positivos preparados e kill switch testado.
  - Scaffold: `serpro:smoke checklist`, `serpro:go-live checklist`, `serpro:prod-check`; runbook `serpro-clean-prod-deploy.md`. **Deploy live ops-gated.**
- [ ] 12.4 Executar handshake TLS/cadeia no endpoint oficial de validação e `serpro:readiness` global; registrar evidência sem chamar serviço fiscal.
  - Scaffold: `serpro:smoke tls` (opt-in `SERPRO_SMOKE_ENABLED` + confirm; bloqueado em CI); `--record-readiness` → `TLS_OK`. **Live ops-gated.**
- [ ] 12.5 Executar OAuth mTLS real com a nova versão, validar Bearer+JWT/expiração e encerrar sem rota de negócio.
  - Scaffold: `serpro:smoke oauth` (mesmos gates; saída sanitizada sem token). **Live ops-gated.**
- [ ] 12.6 Selecionar explicitamente um Office real não-demo, confirmar autor/consentimento e gerar/assinar/validar o Termo pelo fluxo aprovado.
  - Scaffold: runbook free-smoke ladder + readiness office; identidade **não** versionada no OpenSpec. **Live ops-gated.**
- [ ] 12.7 Enviar o Termo somente por `/Apoiar`, validar 200/304, token/Expires/ETag no vault e promover `TERM_SERPRO_ACCEPTED`; confirmar zero segredo em logs/Redis/API.
  - Scaffold: runbook 12.7 + fluxos Termo existentes; promoção via readiness/promotion service. **Live ops-gated.**
- [ ] 12.8 Confirmar poderes/autorização do canário por evidência válida sem usar `/Consultar` como smoke e executar um canário `/Monitorar` dentro dos limites oficiais.
  - Scaffold: free-smoke ladder docs; bloqueio lookup faturável em free smoke; limites `serpro.eventos`. **Live ops-gated.**
- [ ] 12.9 Testar kill switch, alertas, breaker, restart Redis, ledger e reconciliação; confirmar que nenhuma chamada `/Consultar`, `/Emitir` ou `/Declarar` ocorreu.
  - Scaffold: `serpro:go-live kill-switch-*|breaker-status|ledger-dry-run`; kill switch durable pós-flush; testes unitários. **Drill live ops-gated.**
- [ ] 12.10 Promover somente `FREE_SMOKE_OK`, registrar bloqueios remanescentes e deixar qualquer canário faturável dependente de aprovação operacional separada, teto unitário e reconciliação posterior.
  - Scaffold: `serpro:go-live free-smoke-promote` + `canary-blocked-check`; `SerproReadinessPromotionService` bloqueia CANARY sem dual+teto. **Promoção live ops-gated.**
