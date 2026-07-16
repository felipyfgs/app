## 1. Congelamento, evidência e compatibilidade

- [x] 1.1 Confirmar master e auto-queue SVRS NF-e/NFC-e desligados em todos os ambientes antes de alterar código.
- [x] 1.2 Inventariar adapters, jobs, configs, migrations e UI já implementados por `add-svrs-nfce-outbound-xml-retrieval` para definir reuso sem duplicação.
- [x] 1.3 Registrar ADR que distingue os limites oficiais do `NFeDistribuicaoDFe` da ausência de limite publicado para `NFESSL`.
- [x] 1.4 Registrar o bloqueio HTTP 200 observado como fixture sanitizada, sem chave, CNPJ, cookie, HTML integral ou material do A1.
- [x] 1.5 Remover dos gates de rollout a premissa de 5 s globais, 30 s por raiz e 20 chaves por job antes de qualquer nova chamada real.
- [x] 1.6 Documentar que esta change é pré-condição do piloto real da recuperação NFC-e existente.
- [x] 1.7 Solicitar formalmente à SVRS/SEFAZ esclarecimento sobre autorização, limiar, escopo, cooldown e retenção do `NFESSL`.

## 2. Infraestrutura, configuração e dados

- [x] 2.1 Criar configuração tipada para host/path allowlisted, coorte de egress, timeouts e budgets defensivos, sem override por request.
- [x] 2.2 Validar no bootstrap que `SVRS_EGRESS_COHORT_ID` existe quando o canal está habilitado.
- [x] 2.3 Impedir habilitação simultânea de implantações que compartilham NAT sem coordenador comum documentado.
- [x] 2.4 Criar migration para estado durável do breaker/coorte com causa, patamar, `opened_at`, `next_probe_at` e canário.
- [x] 2.5 Estender solicitações/tentativas para modelo 55, origem, exchanges reservados/consumidos e resultados tipados.
- [x] 2.6 Adicionar proveniência `SVRS_NFE55_DOWNLOAD_XML_DFE` às aquisições documentais.
- [x] 2.7 Criar índices e uniques idempotentes incluindo `office_id`, ambiente, chave e origem onde aplicável.
- [x] 2.8 Garantir que chaves e CNPJs permaneçam texto maiúsculo, sem máscara e nunca tipos numéricos.
- [x] 2.9 Adicionar rollback não destrutivo das migrations sem remover documentos/aquisições já válidos.
- [x] 2.10 Executar backup e restore drill antes de aplicar migrations em ambiente com dados fiscais reais.

## 3. Governador compartilhado de egress

- [x] 3.1 Definir interface `SvrsPortalEgressGovernor` independente dos adapters NF-e/NFC-e.
- [x] 3.2 Implementar lock Redis global por coorte com uma transação lógica em voo.
- [x] 3.3 Implementar reserva atômica do GET+POST antes de qualquer exchange ou leitura do A1.
- [x] 3.4 Contabilizar GET, POST e redirect manual individualmente nas janelas de hora/dia.
- [x] 3.5 Aplicar intervalo mínimo global de 120 segundos entre transações.
- [x] 3.6 Aplicar intervalo mínimo de 15 minutos e limite de 6 chaves/dia por raiz.
- [x] 3.7 Aplicar limites globais de 10 exchanges/hora e 50 exchanges/dia por coorte.
- [x] 3.8 Implementar jitter determinístico e reagendamento sem retry na mesma execução.
- [x] 3.9 Falhar fechado quando Redis, banco, relógio ou reserva ficarem indisponíveis/inconsistentes.
- [x] 3.10 Persistir abertura/fechamento do breaker e publicar invalidação imediata aos workers.
- [x] 3.11 Migrar o adapter NFC-e existente para usar exclusivamente o governador compartilhado.
- [x] 3.12 Remover caminhos internos que chamem o host SVRS sem reserva prévia.
- [x] 3.13 Criar teste arquitetural que falhe ao detectar cliente SVRS fora do governador.

## 4. Bloqueio, cooldown e canário

- [x] 4.1 Implementar normalização limitada de texto visível sem executar JavaScript.
- [x] 4.2 Detectar `IP não autorizado devido múltiplas consultas` e variantes por fingerprint versionado.
- [x] 4.3 Fazer o detector de bloqueio prevalecer sobre status HTTP 200 e scripts genéricos de download.
- [x] 4.4 Abrir breaker global da coorte e cancelar reservas não iniciadas ao primeiro bloqueio confirmado.
- [x] 4.5 Aplicar cooldown inicial de 24 horas sem override antecipado.
- [x] 4.6 Escalonar reincidências do canário para 48, 96 e 168 horas.
- [x] 4.7 Honrar `Retry-After` válido quando maior que o cooldown local.
- [x] 4.8 Permitir apenas um canário allowlisted depois de `next_probe_at`.
- [x] 4.9 Impedir uso de outra raiz, A1, escritório, proxy ou worker como evasão do breaker.
- [x] 4.10 Permitir a ADMIN apenas desligar, estender cooldown e selecionar canário elegível.
- [x] 4.11 Testar concorrência entre workers no instante de half-open garantindo um único canário.

## 5. Fake portal e fixtures antes do mTLS real

- [x] 5.1 Criar fake server local com GET do formulário e POST de download sem acesso externo.
- [x] 5.2 Criar fixture sanitizada de sucesso NF-e 55 com literal e escapes aceitos.
- [x] 5.3 Criar fixtures de bloqueio HTTP 200, 403, 429, 503, redirect e timeout.
- [x] 5.4 Criar fixtures de não encontrado, autenticação negada e contrato alterado.
- [x] 5.5 Criar fixtures maliciosas com expressão, concatenação, template string e múltiplos candidatos.
- [x] 5.6 Criar XMLs de teste para chave divergente, modelo 65, ambiente divergente, protocolo inválido e assinatura quebrada.
- [x] 5.7 Testar limites de tamanho de HTML, literal e XML antes de alocação/persistência excessiva.
- [x] 5.8 Garantir que CI use somente fake/fixtures e não exija certificado de homologação/produção.

## 6. Cofre e transporte mTLS NF-e 55

- [x] 6.1 Definir interface `SvrsNfe55DownloadClient` com entrada e resultados fechados.
- [x] 6.2 Validar localmente chave, DV, modelo 55, ambiente, direção e raiz antes de reservar rede.
- [x] 6.3 Resolver credencial por `office_id` e raiz derivada, sem confiar em office/credencial do payload.
- [x] 6.4 Reservar orçamento antes de materializar o PFX no `SecureObjectStore`.
- [x] 6.5 Configurar libcurl BLOB com PFX/senha somente em memória, TLS 1.2+, CA e hostname verify.
- [x] 6.6 Implementar GET autenticado único e sessão/cookies somente em memória durante a transação.
- [x] 6.7 Implementar POST único com campos allowlisted do `NFESSL`.
- [x] 6.8 Desabilitar redirect automático e rejeitar host/path fora da allowlist.
- [x] 6.9 Limitar DNS/connect/TLS/TTFB/total timeout e tamanho de corpo.
- [x] 6.10 Limpar buffers, handles e sessão efêmera em sucesso e falha.
- [x] 6.11 Auditar logs/exceptions para provar ausência de PFX, senha, PEM, cookie, chave completa e corpo remoto.

## 7. Parser e validação do nfeProc

- [x] 7.1 Reusar ou generalizar o parser NFC-e sem misturar contratos de paths/modelos.
- [x] 7.2 Localizar somente o literal associado ao download oficial por assinatura de wrapper versionada.
- [x] 7.3 Implementar decoder de gramática mínima de escapes sem `eval`, engine JS ou `stripcslashes` genérico.
- [x] 7.4 Classificar contrato ausente/ambíguo/alterado antes de tentar parsear XML.
- [x] 7.5 Parsear XML sem rede, DTD ou entidades externas.
- [x] 7.6 Validar `nfeProc`, modelo 55, chave derivada, ambiente e raiz emitente.
- [x] 7.7 Validar protocolo de autorização e vínculo com a chave.
- [x] 7.8 Validar referências/digests e assinatura XMLDSig.
- [x] 7.9 Calcular SHA-256 sobre os bytes originais sem normalização.
- [x] 7.10 Preservar XSD futuro bem-formado com alerta somente após identidade/protocolo/assinatura válidos.
- [x] 7.11 Classificar resultados sem retornar HTML, JavaScript, XML ou chave completa em erros.

## 8. Roteamento, ingestão e idempotência

- [x] 8.1 Implementar `OutboundXmlRecoveryRouter` com a ordem vault/importação/autXML → SVRS → fallback.
- [x] 8.2 Consultar o catálogo/vault por chave antes de criar tentativa remota.
- [x] 8.3 Cancelar idempotentemente job não iniciado quando XML válido chegar por outra fonte.
- [x] 8.4 Recusar busca SVRS por período, série, numeração ou chave sem vínculo interno.
- [x] 8.5 Encaminhar backlog em massa para `autXML`, XML/ZIP ou pacote oficial em vez de rajada no portal.
- [x] 8.6 Integrar origem SVRS NF-e 55 ao pipeline canônico existente.
- [x] 8.7 Persistir bytes no vault e aquisição antes de transicionar `XML_PENDING` para `XML_CAPTURED`.
- [x] 8.8 Reusar bytes seguros do vault quando a persistência falhar após download, evitando nova chamada externa.
- [x] 8.9 Registrar aquisições duplicadas de mesmo hash sem duplicar documento.
- [x] 8.10 Preservar canônico e abrir divergência crítica quando hashes válidos diferirem para a mesma chave.
- [x] 8.11 Reconciliar upload XML/ZIP/pacote oficial com tentativas SVRS pendentes.
- [x] 8.12 Cobrir isolamento entre escritórios em todas as queries, policies, jobs e uniques.

## 9. Orquestração Horizon e scheduler

- [x] 9.1 Criar job de uma única chave com payload mínimo e contexto de tenant derivado.
- [x] 9.2 Verificar master, auto-queue, allowlist, breaker e orçamento antes de resolver certificado.
- [x] 9.3 Adquirir lock idempotente da chave para impedir recuperação concorrente por fontes.
- [x] 9.4 Mapear resultados tipados para sucesso, reagendamento, bloqueio da raiz, breaker global e fallback.
- [x] 9.5 Impedir retry automático imediato do Horizon após qualquer exchange consumido.
- [x] 9.6 Preservar jobs/backlog quando kill switch for ativado durante execução.
- [x] 9.7 Distribuir o auto-queue elegível deterministicamente sem ultrapassar budgets.
- [x] 9.8 Manter DistDFe e demais canais fiscais independentes do breaker do portal SVRS.
- [x] 9.9 Testar crash entre reserva, GET, POST, vault e commit sem duplicar chamadas indevidamente.

## 10. API, autorização e auditoria

- [x] 10.1 Expor DTO sanitizado da recuperação por chave, fonte escolhida e próximo passo.
- [x] 10.2 Expor saúde da coorte sem vazar chaves, CNPJs ou contagens privadas entre escritórios.
- [x] 10.3 Restringir gestão de master/allowlist/kill switch/cooldown/canário a ADMIN com 2FA recente.
- [x] 10.4 Permitir a OPERATOR enfileirar somente item elegível e iniciar fallback assistido.
- [x] 10.5 Manter VIEWER somente leitura.
- [x] 10.6 Recusar por API aumento de limites, antecipação de `next_probe_at`, URL, host, cabeçalho, cookie, proxy ou certificado arbitrários.
- [x] 10.7 Auditar ações administrativas, transições do breaker, seleção do canário e roteamento sem segredos.
- [x] 10.8 Aplicar CSRF, sessão Sanctum, policies e `office_id` derivado em todos os endpoints.

## 11. Dashboard Nuxt e contingência

- [x] 11.1 Usar `/frontend-nuxt-stack` e o arquétipo fixado do dashboard ao implementar as telas.
- [x] 11.2 Exibir estados disponível, aguardando fonte, aguardando budget, recuperando, capturado, cooldown e fallback.
- [x] 11.3 Exibir breaker, `next_probe_at`, exchanges consumidos/restantes e backlog por modelo.
- [x] 11.4 Explicar na UI que os budgets são preventivos e não limites oficiais publicados do `NFESSL`.
- [x] 11.5 Ocultar e bloquear ações administrativas sem papel/2FA recente.
- [x] 11.6 Não oferecer retry remoto durante cooldown; priorizar XML/ZIP ou pacote oficial.
- [x] 11.7 Permitir extensão de cooldown e seleção de canário sem permitir prova antecipada.
- [x] 11.8 Preservar dados válidos durante polling e estados transitórios do governador.
- [x] 11.9 Testar responsividade, teclado, foco, contraste, loading, vazio, erro e bloqueio.

## 12. Observabilidade, segurança e testes

- [x] 12.1 Criar métricas por coorte para exchanges, reservas negadas, breaker, cooldown, canário e resultados tipados.
- [x] 12.2 Criar métricas por escritório somente para backlog e aquisições autorizadas daquele tenant.
- [x] 12.3 Criar itens de inbox para bloqueio múltiplas consultas, contrato, A1, assinatura, divergência e budget.
- [x] 12.4 Garantir cardinalidade limitada e mascaramento de chave/CNPJ em logs e tracing.
- [x] 12.5 Executar testes unitários do governador, janelas, relógio, escalonamento e fail-closed.
- [x] 12.6 Executar testes de integração do fake portal, mTLS simulado, parser e ingestão.
- [x] 12.7 Executar testes de concorrência NF-e/NFC-e em múltiplos workers e tenants.
- [x] 12.8 Executar testes de segurança para SSRF, XXE, redirect, template malicioso e exposição de segredo.
- [x] 12.9 Executar suíte backend/frontend, análise estática e testes de migrations/rollback.
- [x] 12.10 Produzir runbook de bloqueio com kill switch, cooldown, canário, fallback e contato SVRS.
- [x] 12.11 Executar drill de kill switch e rollback sem apagar backlog, hashes ou documentos.

## 13. Smoke restrito e piloto MA

- [ ] 13.1 Confirmar que o cooldown do bloqueio observado expirou sem realizar sondagens intermediárias.
- [x] 13.2 Selecionar uma única NF-e 55 de saída MA allowlisted com A1 relacionado e XML original disponível offline.
- [x] 13.3 Registrar autorização operacional, janela, responsável e plano de parada do smoke.
- [x] 13.4 Executar uma única transação real GET+POST pelo governador e interromper diante de qualquer bloqueio.
- [ ] 13.5 Comparar chave, protocolo, digest, assinatura e SHA-256 com o XML original sem publicar dados fiscais.
- [x] 13.6 Se bloqueado, abrir cooldown correspondente e usar fallback sem repetir a prova.
- [ ] 13.7 Se bem-sucedido, documentar escopo exato comprovado sem generalizar cobertura nacional ou retenção.
- [ ] 13.8 Pilotar uma raiz/uma série com auto-queue ainda desligado e budgets inalterados.
- [ ] 13.9 Observar taxa de captura, exchanges por captura, falhas, bloqueios, latência e fallback pelo período aprovado.
- [ ] 13.10 Aprovar ou rejeitar formalmente o gate de auto-queue com base nas métricas e resposta institucional disponível.

## 14. Liberação e escala controlada

- [ ] 14.1 Habilitar auto-queue somente para allowlist aprovada e com kill switch testado.
- [ ] 14.2 Ampliar raízes em lotes pequenos sem aumentar budgets automaticamente.
- [ ] 14.3 Revisar capacidade de `autXML` e importação em massa antes de encaminhar novas lacunas à SVRS.
- [ ] 14.4 Monitorar tráfego externo à aplicação que possa compartilhar a mesma coorte/NAT.
- [ ] 14.5 Reabrir a decisão de limites somente com evidência formal ou piloto prolongado sem bloqueios.
- [ ] 14.6 Validar critérios de rollback após cada ampliação e manter contingência assistida operacional.
- [ ] 14.7 Atualizar documentação e specs se a SVRS publicar contrato, limites ou política incompatíveis com estes defaults.
