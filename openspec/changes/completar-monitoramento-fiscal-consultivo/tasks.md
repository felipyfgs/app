## 1. N0 — Contrato canônico e harness local

- [x] 1.1 Implementar o registro tipado `surface -> capability -> action`, corrigir rotas canônicas, incorporar as capabilities hoje excepcionais e adicionar testes de integridade contra o manifesto oficial.
- [x] 1.2 Reintroduzir um harness E2E de navegador para Nuxt/Laravel local, com seed determinístico, autenticação por papéis, bloqueio de rede fiscal externa e limpeza de processos/artefatos.

## 2. N1 — Contratos e políticas do backend

- [x] 2.1 Derivar `MonitoringCoverageService`, inventário manual e schemas públicos exclusivamente do registro canônico, mantendo compatibilidade transitória e cobrindo sanitização/tenancy em testes de API.
  Depende de: 1.1
- [x] 2.2 Criar DTOs/projeções comuns para estados `IDLE|QUEUED|PROCESSING|READY|NO_DATA|FAILED|BLOCKED|UNSUPPORTED`, proveniência, frescor e preservação do último snapshot, com testes unitários e feature.
  Depende de: 1.1
- [x] 2.3 Reforçar a elegibilidade `READ` no inventário, dispatcher e job, incluindo papéis, `CurrentOffice`, readiness/procuração e rejeição auditada de geração/transmissão/mutação em chamadas diretas.
  Depende de: 1.1
- [x] 2.4 Unificar descritores de evidência, autorização de download e cobertura parcial/`UNSUPPORTED` para documentos, agregadores e FGTS, com regressões cross-tenant e fail-closed.
  Depende de: 1.1

## 3. N2 — Fundação do workspace Nuxt

- [x] 3.1 Criar tipos e provider/composable do workspace que carregue/cacheie o contrato canônico, filtre cobertura contextual, descarte respostas por `sessionEpoch`/geração e elimine a matriz normativa manual com gate de equivalência.
  Depende de: 2.1, 2.2
- [x] 3.2 Adaptar o painel de cobertura central/contextual e os componentes comuns de estado/documento para todos os papéis, sem coordenadas internas, JSON bruto ou ação derivada de evidência ausente.
  Depende de: 2.1, 2.2, 2.4
- [x] 3.3 Tornar comunicação PGDAS-D/PGMEI/DCTFWeb explicitamente informativa, preservando preferências/histórico e removendo qualquer controle que prometa envio real ou automático.
  Depende de: 2.3

## 4. N3 — Jornadas consultivas por superfície

- [ ] 4.1 Completar Simples/MEI com PGDAS-D, PGMEI, DEFIS, CCMEI e Regime na mesma rota canônica, incluindo parâmetros, snapshots, documentos existentes, cobertura contextual e testes de componente/API.
  Depende de: 3.1, 3.2, 3.3
- [ ] 4.2 Completar DCTFWeb/MIT com projeções distintas, histórico, lista/apuração/situação, documentos já coletados, estados assíncronos e bloqueio visível das ações não consultivas.
  Depende de: 3.1, 3.2, 3.3
- [ ] 4.3 Completar Parcelamentos e SITFIS com modalidades/pedidos/parcelas/pagamentos, documento existente e protocolo→relatório assíncrono, sem gerar nova guia e com regressões de espera/erro.
  Depende de: 3.1, 3.2
- [ ] 4.4 Completar Caixa Postal em lista/detalhe com indicadores, DTE, conteúdo, anexos autorizados, triagem local e estados responsivos, sem tratar triagem como efeito fiscal externo.
  Depende de: 3.1, 3.2
- [ ] 4.5 Completar Declarações e Guias preservando obrigação/fonte, pagamento, apoio Sicalc/PagtoWeb, evidência integral versus parcial e downloads autorizados.
  Depende de: 3.1, 3.2
- [ ] 4.6 Completar Cadastros, Processos Fiscais e detalhe consolidado do cliente, incluindo refresh `READ`, paginação/filtros globais, cobertura por aba e isolamento de tenant.
  Depende de: 3.1, 3.2
- [ ] 4.7 Completar Dashboard e FGTS/eSocial com KPIs derivados, cobertura das capabilities, fechamento/totalização/eventos e guia/pagamento explicitamente `UNSUPPORTED` quando sem provider.
  Depende de: 3.1, 3.2

## 5. N4 — Gates integrados e evidências de prontidão

- [ ] 5.1 Executar suites completas Laravel e Nuxt, lint, typecheck, geração SPA e E2E local cobrindo login, troca de tenant, todos os grupos de superfície, documento, assíncrono, bloqueio, FGTS parcial e `VIEWER` sem ação; registrar evidência sanitizada.
  Depende de: 1.2, 4.1, 4.2, 4.3, 4.4, 4.5, 4.6, 4.7
- [ ] 5.2 Validar catálogo/contrato sem drift, executar scans de segredos e artefatos, provar que nenhuma action não-`READ` ou outbound ficou executável e rodar smoke Trial opcional somente com os quatro cenários oficiais e autorização explícita.
  Depende de: 2.3, 2.4, 4.1, 4.2, 4.3, 4.4, 4.5, 4.6, 4.7
