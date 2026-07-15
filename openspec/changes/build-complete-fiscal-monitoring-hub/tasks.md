## 1. Alinhamento de domínio, gates e preflight

- [ ] 1.1 Atualizar `AGENTS.md`, `openspec/config.yaml` e documentação arquitetural para registrar explicitamente o SaaS multi-escritório, o contrato SERPRO global e o portal de contribuinte final como não-objetivo
- [ ] 1.2 Registrar decisão arquitetural sobre plano de controle global versus plano de dados com `office_id`, incluindo as únicas tabelas globais permitidas
- [ ] 1.3 Obter e anexar evidência comercial/jurídica sobre uso do Integra Contador como insumo de SaaS, cobrança de franquia/excedente e limites de redistribuição antes do piloto produtivo
- [ ] 1.4 Criar comando de preflight que verifique offices, memberships, duplicidades, linhas de negócio sem `office_id`, objetos seguros órfãos e migrations pendentes
- [ ] 1.5 Executar backup completo e restore drill em ambiente não produtivo antes das migrations do plano de controle, registrando resultado no estado operacional
- [ ] 1.6 Inventariar APIs, jobs, caches, locks, storage paths, exports e métricas atuais que assumem um único escritório e produzir checklist de isolamento
- [ ] 1.7 Definir feature flags globais, por tenant, por módulo e para operações mutantes, todas inicialmente desabilitadas
- [ ] 1.8 Criar testes de arquitetura que proíbam dependência de recursos globais em controllers tenant-scoped sem service autorizado

## 2. Plano de controle SaaS e ciclo de vida do tenant

- [ ] 2.1 Criar enums e migrations para `platform_memberships`, `office_subscriptions`, planos, estados, vigências, franquias e limites
- [ ] 2.2 Migrar deterministicamente o escritório existente para assinatura ativa sem alterar dados fiscais ou memberships atuais
- [ ] 2.3 Implementar models, factories e services do ciclo `TRIAL`/`ACTIVE`/`PAST_DUE`/`SUSPENDED`/`CANCELED`
- [ ] 2.4 Separar autorização `PLATFORM_ADMIN` dos papéis `ADMIN`/`OPERATOR`/`VIEWER` e impedir herança de acesso fiscal
- [ ] 2.5 Estender `CurrentOffice` e middleware para selecionar somente membership ativa e rejeitar `office_id` livre do request
- [ ] 2.6 Implementar troca explícita de tenant com rotação/atualização segura da sessão, invalidação de caches e auditoria
- [ ] 2.7 Bloquear chamadas externas e mutações de tenants suspensos mantendo leitura e retenção de histórico/evidência
- [ ] 2.8 Criar endpoints tenant-scoped de assinatura/limites e endpoints globais sanitizados de administração do tenant
- [ ] 2.9 Cobrir ciclo de vida, troca de tenant, suspensão, platform admin sem membership e tentativas de acesso cruzado com testes de feature
- [ ] 2.10 Auditar queries, jobs, cache keys, locks, storage e exports existentes com testes negativos para dois escritórios contendo o mesmo CNPJ

## 3. Cofre, backup e contrato global do SERPRO

- [ ] 3.1 Adicionar finalidades distintas no `SecureObjectStore` para certificado contratante SERPRO, credenciais OAuth, tokens, Termo XML e A1 opcional do Autor
- [ ] 3.2 Criar migrations e models globais `serpro_contracts` com unicidade de contrato ativo por ambiente e referências seguras sem `office_id`
- [ ] 3.3 Criar catálogo global versionado de ambientes, soluções, serviços, mutabilidade, procurações, cache, rate limit e classe faturável
- [ ] 3.4 Implementar service/CLI protegido para cadastrar, ativar, substituir, desativar e inspecionar metadados sanitizados do contrato sem rota de recuperação de segredo
- [ ] 3.5 Validar identidade, cadeia, validade e fingerprint do e-CNPJ contratante antes de gravar o PFX e descartar buffers temporários
- [ ] 3.6 Estender backup/restore para objetos globais e tenant-scoped novos, mantendo `VAULT_MASTER_KEY` fora do backup comum
- [ ] 3.7 Criar runbook e kill switch para revogação, rotação ou comprometimento do certificado global
- [ ] 3.8 Testar que APIs, logs, métricas, auditoria, exceptions e exports não contêm PFX, senha, PEM, chave, segredo, token ou Termo XML
- [ ] 3.9 Executar restore drill dos objetos SERPRO cifrados e validar leitura somente com custódia correta da chave mestra

## 4. Transporte mTLS, OAuth2 e trial do Integra Contador

- [ ] 4.1 Criar contratos `IntegraContadorClient`, `SerproContractAuthenticator` e DTOs de request/response sem acoplamento ao JSON bruto
- [ ] 4.2 Implementar autenticação OAuth2 com `Consumer Key`/`Consumer Secret`, `role-type: TERCEIROS`, e-CNPJ contratante e PFX via BLOB em memória
- [ ] 4.3 Implementar cache cifrado e renovação coordenada de Bearer/JWT com lock, margem de expiração e proteção contra stampede
- [ ] 4.4 Implementar transporte HTTP com TLS mínimo 1.2, verificação de hostname, timeouts, correlação, `Retry-After` e sanitização de erros
- [ ] 4.5 Criar clients fake/trial e fixtures oficiais simuladas sem persistir resultados como evidência fiscal produtiva
- [ ] 4.6 Adicionar contract tests de headers, autenticação, envelopes e códigos de erro sem certificado real em CI
- [ ] 4.7 Implementar circuit breaker e kill switch global/por solução, preservando execuções e ledger existentes
- [ ] 4.8 Criar painel/endpoint global de saúde sanitizada do contrato e endpoint tenant-scoped sem detalhes comerciais ou secretos
- [ ] 4.9 Executar smoke restrito de mTLS/OAuth2 fora de CI com certificado contratante real e registrar evidência sem material sensível

## 5. Autor do Pedido, Termo XML e procurações

- [ ] 5.1 Criar migrations, enums e models tenant-scoped para `office_serpro_authorizations`, identidades do Autor e histórico de estados
- [ ] 5.2 Criar `tax_proxy_powers` com tenant, contribuinte, autor, sistema/serviço, fonte, vigência, estado e evidência
- [ ] 5.3 Implementar parser/validador estrito do Termo XML, XSD/layout, destinatário, vigência, identidade do certificado e XMLDSig RSA-SHA256/C14N
- [ ] 5.4 Implementar upload de Termo assinado externamente com armazenamento imutável cifrado, hash e metadados sanitizados
- [ ] 5.5 Implementar A1 gerenciado opcional do Autor com consentimento, finalidade exclusiva, assinatura somente em memória e rotação
- [ ] 5.6 Manter A3 exclusivamente interativo e retornar estado `ACTION_REQUIRED` quando nova assinatura for necessária
- [ ] 5.7 Implementar adapter AUTENTICAPROCURADOR, cache 304, captura cifrada do token e expiração no horário de Brasília
- [ ] 5.8 Validar em trial/piloto se Termo ainda vigente pode ser reapresentado após expiração do token e configurar estratégia por ambiente
- [ ] 5.9 Implementar adapter Integra-Procurações e importação/verificação manual de evidência oficial quando a API não cobrir o caso
- [ ] 5.10 Implementar matriz de elegibilidade que verifique plano, contrato, Termo, token, tenant, contribuinte, poder, cobertura, papel, orçamento, rate limit e breaker
- [ ] 5.11 Criar endpoints de onboarding, estado, ações requeridas e procurações sem retornar XML, PFX ou tokens
- [ ] 5.12 Cobrir termos inválidos, signatário divergente, expiração, cache, token, poder insuficiente, contribuinte cruzado e concorrência com testes

## 6. Ledger de consumo, preços, franquias e conciliação

- [ ] 6.1 Criar migrations imutáveis para reservas/execuções de uso, entradas do ledger, versões de preço, agregados mensais e conciliações
- [ ] 6.2 Implementar catálogo das classes `CONSULTA`/`EMISSAO`/`DECLARACAO`/`NAO_FATURAVEL`/`DESCONHECIDA` por operação e vigência
- [ ] 6.3 Implementar reserva idempotente de orçamento antes da chamada e finalização pós-resposta, inclusive falha possivelmente faturável
- [ ] 6.4 Vincular cada entrada a `office_id`, contribuinte, serviço, operação, correlação e versão de preço sem registrar payload fiscal
- [ ] 6.5 Implementar cálculo por faixas configuráveis sem hardcode no client e preservar custo estimado histórico
- [ ] 6.6 Implementar franquia, limiares, saldo, bloqueio de operação não essencial e proteção contra tenant ruidoso
- [ ] 6.7 Criar agregações mensais globais e por tenant com recomputação verificável a partir do ledger
- [ ] 6.8 Implementar importação/registro do relatório e fatura SERPRO com diferenças e ajustes separados do ledger original
- [ ] 6.9 Criar APIs tenant-scoped de consumo/franquia e APIs globais de consolidação protegidas por `PLATFORM_ADMIN`
- [ ] 6.10 Rodar ledger em shadow mode no trial e reconciliar fixtures antes de qualquer regra comercial bloqueante
- [ ] 6.11 Cobrir retries idempotentes, preços por vigência, classe desconhecida, limite global/tenant e divergência de conciliação com testes

## 7. Núcleo compartilhado de monitoramento fiscal

- [ ] 7.1 Criar enums de cobertura, situação, gatilho, mutabilidade, resultado e severidade compartilhados
- [ ] 7.2 Criar migrations tenant-scoped para categorias, vínculos, agendas, competências, eventos, execuções, snapshots, findings, pendências e artefatos
- [ ] 7.3 Implementar chaves idempotentes e constraints por tenant, contribuinte, sistema, serviço, competência e evento
- [ ] 7.4 Implementar persistência atômica de evidência/hash e snapshot antes de atualizar projeções e pendências
- [ ] 7.5 Implementar storage seguro, retenção e download autorizado de artefatos sem paths internos ou URLs permanentes
- [ ] 7.6 Implementar normalização que preserve `UNKNOWN`/`UNSUPPORTED`/`NOT_APPLICABLE` e proíba inferência automática de “em dia”
- [ ] 7.7 Implementar scheduler com espalhamento determinístico, fila justa, limites global/tenant e revalidação imediatamente antes da chamada
- [ ] 7.8 Persistir/deduplicar Eventos de Última Atualização e direcionar reconciliações sem varredura indiscriminada
- [ ] 7.9 Implementar services e APIs de categorias, associação em lote, execuções, snapshots, findings e pendências
- [ ] 7.10 Cobrir concorrência, evento duplicado, requeue por limite, falha entre evidência/projeção, tenant suspenso e isolamento de cache/storage com testes

## 8. Integra-SN e Integra-MEI

- [ ] 8.1 Implementar adapters e DTOs de PGDAS-D, DEFIS e Regime de Apuração com contract tests por versão
- [ ] 8.2 Implementar adapters e DTOs de PGMEI, CCMEI e DASN-SIMEI com contract tests por versão
- [ ] 8.3 Projetar regime/aplicabilidade por vigência sem misturar competências SN e MEI
- [ ] 8.4 Implementar consultas de declarações, recibos, extratos e situação com evidência imutável
- [ ] 8.5 Integrar geração assistida de DAS ao fluxo de guias sem marcar pagamento
- [ ] 8.6 Classificar entregas de declaração como mutantes e mantê-las desabilitadas no piloto somente leitura
- [ ] 8.7 Criar jobs agendados/por evento e endpoints tenant-scoped para os módulos Simples e MEI
- [ ] 8.8 Cobrir mudança de regime, competência inconclusiva, serviço não suportado, procuração e idempotência com testes

## 9. Integra-DCTFWeb, MIT e Parcelamentos

- [ ] 9.1 Implementar ingestão/deduplicação de eventos DCTFWeb e reconciliação direcionada por contribuinte/competência
- [ ] 9.2 Implementar adapters DCTFWeb para recibo, relatório completo, XML e documento de arrecadação
- [ ] 9.3 Implementar adapters MIT para encerramento, situação e consultas de apuração preservando estado independente da DCTFWeb
- [ ] 9.4 Versionar evidências de retificação sem sobrescrever XML, relatório ou recibo anterior
- [ ] 9.5 Implementar adapters de todas as modalidades catalogadas de parcelamento SN/MEI para pedidos, parcelas, pagamentos e documentos
- [ ] 9.6 Projetar pedido, modalidade, parcela, vencimento e pagamento confirmado sem declarar inadimplência além da fonte
- [ ] 9.7 Integrar DARF e documentos de parcela à central de guias mantendo pagamento independente
- [ ] 9.8 Manter transmissão DCTFWeb, encerramento MIT, adesão, reparcelamento e desistência atrás de flags mutantes desabilitadas
- [ ] 9.9 Cobrir evento duplicado, retificação, MIT sem transmissão, modalidade/poder distintos, guia repetida e timeout incerto com testes

## 10. Situação Fiscal e Caixas Postais

- [ ] 10.1 Implementar fluxo SITFIS de solicitação/protocolo/espera/emissão com polling respeitoso e correlação
- [ ] 10.2 Preservar relatório SITFIS oficial e criar parser versionado que mantenha artefato mesmo em layout desconhecido
- [ ] 10.3 Normalizar findings de situação fiscal com rastreabilidade e sem transformar ausência de item em certidão
- [ ] 10.4 Implementar TTL/cache SITFIS e exibir idade do snapshot para evitar nova chamada por abertura de tela
- [ ] 10.5 Implementar eventos/indicadores, lista e detalhe de Caixa Postal e DTE com adapters separados
- [ ] 10.6 Criar storage/classificação/retenção de mensagens e anexos e trilha de toda visualização/download
- [ ] 10.7 Separar triagem interna `NEW`/`IN_REVIEW`/`RESOLVED` de qualquer leitura oficial remota
- [ ] 10.8 Criar alertas sanitizados de mensagem sem corpo/anexo em inbox, log ou notificação agregada
- [ ] 10.9 Cobrir SITFIS processando/layout novo/cache e Caixa Postal com evento repetido, tenant cruzado, sigilo e leitura interna com testes

## 11. Declarações, prazos e central de guias

- [ ] 11.1 Criar catálogo versionado de obrigações, regimes, aplicabilidade, prazos, timezone, fontes e operações suportadas
- [ ] 11.2 Implementar projeção de obrigação por contribuinte/competência distinguindo aplicável, não aplicável, desconhecida e não suportada
- [ ] 11.3 Exigir recibo/protocolo/resposta oficial para marcar entrega e manter artefato interno sem protocolo como não conclusivo
- [ ] 11.4 Implementar calendário versionado e recalcular apenas competências abertas quando houver prorrogação oficial
- [ ] 11.5 Criar API agregada da central de declarações com filtros e deep-links para módulo/evidência de origem
- [ ] 11.6 Criar models e migrations de guias, versões, substituições, vencimentos, estados de emissão e pagamento
- [ ] 11.7 Implementar emissão idempotente, armazenamento seguro e download temporário tenant-scoped
- [ ] 11.8 Integrar confirmações oficiais de pagamento sem inferir pagamento por emissão ou download
- [ ] 11.9 Implementar estado `UNKNOWN_RESULT`, bloqueio de retry e reconciliação de operação enviada sem resposta
- [ ] 11.10 Implementar confirmação fiscal reforçada e 2FA recente para emissões/mutações classificadas como alto risco
- [ ] 11.11 Cobrir prorrogação, aplicabilidade desconhecida, recibo ausente, substituição de guia, tenant cruzado e timeout pós-envio com testes

## 12. Cobertura parcial de FGTS via eSocial

- [ ] 12.1 Inventariar contratos oficiais e implementar clients/DTOs estritos para eventos, recibos e totalizadores eSocial realmente disponíveis
- [ ] 12.2 Persistir evidências de S-5003, S-5013, S-1299 e demais retornos aprovados por competência e estabelecimento
- [ ] 12.3 Projetar fechamento, totalização, guia e pagamento como estados independentes, deixando fontes ausentes como `UNKNOWN`/`UNSUPPORTED`
- [ ] 12.4 Implementar findings de divergência somente sobre dados eSocial conhecidos sem declarar débito do FGTS Digital
- [ ] 12.5 Proibir por testes/arquitetura clients de scraping, Gov.br, CAPTCHA, cookie ou sessão humana no módulo
- [ ] 12.6 Criar APIs e jobs tenant-scoped com cobertura e limitações explícitas
- [ ] 12.7 Cobrir totalizador, fechamento sem guia, ausência após janela, fonte não suportada e isolamento com testes

## 13. Operações fiscais mutantes e reconciliação

- [ ] 13.1 Criar policy comum de operação mutante com papel, 2FA recente, procuração, plano, feature flag, custo e kill switch
- [ ] 13.2 Criar endpoint de preflight que retorne efeito, contribuinte, competência, elegibilidade, custo estimado e confirmação exigida
- [ ] 13.3 Implementar idempotency keys, janela contra repetição e snapshot pré-operação
- [ ] 13.4 Persistir request sanitizado, correlação, resultado/evidência e auditoria sem segredo ou payload fiscal em log
- [ ] 13.5 Implementar máquina de estados para `PENDING`, `SENT`, `CONFIRMED`, `REJECTED`, `UNKNOWN_RESULT` e `RECONCILING`
- [ ] 13.6 Impedir retry de resultado incerto e implementar consultas de reconciliação específicas por serviço
- [ ] 13.7 Manter todas as mutações desabilitadas por padrão e liberar por solução/operação/coorte somente após aprovação
- [ ] 13.8 Cobrir TOTP expirado, poder revogado após preflight, clique duplo, timeout, reconciliação e kill switch com testes

## 14. APIs, dashboard operacional e observabilidade

- [ ] 14.1 Estender summary e inbox com autorizações, procurações, módulos, pendências, consumo, limites, bloqueios e resultados incertos
- [ ] 14.2 Garantir que saúde tenant-scoped não revele contrato global, credenciais, custo interno ou incidentes de outros tenants
- [ ] 14.3 Criar métricas de latência, resultado, 429, breaker, fila, consumo e reconciliação sem CNPJ completo ou material fiscal como label
- [ ] 14.4 Criar logs estruturados com correlação e sanitização allowlist para todos os adapters/jobs novos
- [ ] 14.5 Estender auditoria para tenant switch, plano, contrato, Termo, procuração, consulta, guia, transmissão, consumo e conciliação
- [ ] 14.6 Criar alertas para certificado/Termo/token, procuração, fonte, consumo, guia, mensagem, parsing e resultado incerto
- [ ] 14.7 Documentar runbooks de indisponibilidade SERPRO, rate limit, breaker, rotação, divergência de fatura e vazamento suspeito
- [ ] 14.8 Cobrir respostas de API com varredura automática de segredos e testes de `office_id` forjado

## 15. Frontend Nuxt UI tenant-aware

- [ ] 15.1 Usar `/frontend-nuxt-stack` e o template fixado para mapear shell, navegação, dashboard, tabelas, settings, mestre–detalhe e modais antes de editar páginas
- [ ] 15.2 Implementar seletor acessível de escritório somente para memberships autorizadas, invalidando stores/queries após troca
- [ ] 15.3 Criar onboarding Settings de assinatura, Autor do Pedido, Termo, procurações, saúde e ações requeridas sem campos recuperáveis de segredo
- [ ] 15.4 Adicionar grupo Monitoramento e command palette para Dashboard Fiscal, Simples/MEI, DCTFWeb/MIT, Parcelamentos, Situação Fiscal, Caixas Postais, Declarações, Guias e FGTS
- [ ] 15.5 Criar Dashboard Fiscal com KPIs reais, cobertura, alertas, consumo/franquia e horário de atualização
- [ ] 15.6 Criar tabelas server-side por módulo com filtros na URL, paginação, seleção funcional e ações permitidas por papel
- [ ] 15.7 Criar detalhe mestre–cliente com competência, execução, findings, evidências, guias e deep-links de origem
- [ ] 15.8 Implementar vocabulário visual/acessível para `UP_TO_DATE`, `PENDING`, `PROCESSING`, `ATTENTION`, `ERROR`, `NOT_APPLICABLE`, `UNKNOWN`, `UNSUPPORTED` e `BLOCKED`
- [ ] 15.9 Criar painel de consumo do tenant sem fatura global, custo interno ou dados de outro escritório
- [ ] 15.10 Implementar confirmação reforçada de emissão/mutação com preflight, consequência, custo e desafio TOTP
- [ ] 15.11 Rotular FGTS como cobertura parcial e remover qualquer ação que sugira integração com portal humano
- [ ] 15.12 Cobrir responsividade, teclado, acessibilidade, troca de tenant, filtros, permissões, estados vazios/erro e ausência de dados inventados
- [ ] 15.13 Executar lint, typecheck e testes frontend dos novos módulos

## 16. Segurança, piloto produtivo e escala

- [ ] 16.1 Executar suite completa de backend e frontend, incluindo testes negativos de tenancy, segredos, idempotência e operações incertas
- [ ] 16.2 Realizar revisão de ameaça para certificado global, A1 opcional do escritório, Termos, tokens, mensagens, relatórios e guias
- [ ] 16.3 Validar política de retenção, backup, restore, revogação e exclusão para dados globais e por tenant
- [ ] 16.4 Executar trial completo com mocks oficiais e ledger em shadow mode sem alterar estado fiscal produtivo
- [ ] 16.5 Preparar um escritório piloto, poucos contribuintes, procurações por serviço, orçamento baixo e aceite operacional documentado
- [ ] 16.6 Executar smoke produtivo somente leitura para Contratante, Autor e Contribuinte, registrando correlação e evidência sanitizada fora de CI
- [ ] 16.7 Comparar consumo do piloto com relatório/fatura SERPRO e bloquear escala enquanto houver divergência material sem explicação
- [ ] 16.8 Testar suspensão, kill switch, breaker, rotação de token, Termo expirado, procuração revogada e rollback sem perda de evidência
- [ ] 16.9 Liberar módulos somente leitura por coortes e acompanhar custo, latência, erros, fila e vazamentos entre tenants
- [ ] 16.10 Aprovar separadamente guias assistidas e cada operação mutante após piloto somente leitura estável
- [ ] 16.11 Atualizar documentação de operação, suporte, onboarding, limites de cobertura e comunicação comercial antes da disponibilidade geral
