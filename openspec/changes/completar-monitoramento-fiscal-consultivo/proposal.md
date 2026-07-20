## Why

O monitor fiscal já possui páginas, catálogo SERPRO e consultas reais, mas mantém contratos paralelos entre o registro Laravel, a matriz TypeScript e o explorador manual, deixando capacidades consultivas como DEFIS, CCMEI e Regime fora da visão central. Precisamos transformar essa cobertura parcial em uma experiência consultiva completa e verificável para todos os usuários autenticados, sem habilitar transmissão ou outra mutação fiscal.

## What Changes

- Estabelecer um contrato canônico, provider-neutral e tenant-safe para todas as superfícies e subcapacidades consultivas do monitor, incluindo rotas canônicas, fonte, disponibilidade, política documental, parâmetros, estado assíncrono e projeções públicas de saída.
- Incorporar ao contrato central as consultas hoje tratadas como exceções no explorador manual, incluindo DEFIS, CCMEI, Regime de Apuração, apoio Sicalc e contagem PagtoWeb quando possuírem handler de leitura.
- Fazer o Nuxt consumir o contrato do backend em vez de manter uma segunda matriz normativa, preservando helpers locais apenas como cache/fallback gerado e validado contra a fonte canônica.
- Completar as jornadas de leitura das páginas de dashboard, Simples/MEI, DCTFWeb/MIT, parcelamentos, SITFIS, Caixa Postal, declarações, guias, cadastros, processos fiscais, FGTS/eSocial e detalhe do cliente, com estados de carregamento, vazio, erro, bloqueio, processamento, resultado e documento coerentes.
- Exibir a cobertura contextual e suas limitações para `ADMIN`, `OPERATOR` e `VIEWER`, sem mostrar coordenadas internas, segredos, payload fiscal bruto ou ações que o perfil não possa executar.
- Manter FGTS/eSocial honestamente parcial quando não existir fonte oficial para guia ou pagamento, sem fabricar sucesso, documento ou cobertura.
- Adicionar testes de contrato, API, componentes e jornadas E2E locais para todas as superfícies; cenários Trial oficiais permanecem smoke tests explícitos e protegidos, nunca requisito de CI nem evidência fiscal produtiva.
- Remover da experiência qualquer promessa de envio de e-mail/WhatsApp, transmissão, encerramento, adesão, geração mutante ou outra ação fiscal não autorizada nesta fase.
- Não inclui SERPRO live em testes automatizados, validação produtiva de contribuintes, parecer jurídico, novo provider FGTS, outbound, mutações fiscais nem ampliação de poderes de procuração.

## Capabilities

### New Capabilities

- `fiscal-monitoring-workspace`: contrato canônico e experiência completa do monitor fiscal consultivo, cobrindo superfícies, subcapacidades, projeções, documentos existentes, visibilidade por perfil e testes ponta a ponta sem mutações.

### Modified Capabilities


## Impact

- Backend Laravel: `MonitoringSurfaceRegistry`, catálogo de consultas manuais, DTOs/projeções públicas, APIs de portfolio/coverage, políticas de disponibilidade, documentos e testes de contrato/feature.
- Frontend Nuxt: páginas em `apps/web/app/pages/monitoring`, componentes/composables/tipos fiscais, remoção da matriz normativa duplicada e testes de componente/jornada.
- Operação: nenhuma nova credencial ou chamada mutante; Trial continua limitado aos cenários oficiais configurados e produção continua sujeita ao resolvedor fiscal e ao kill switch.
- Segurança: tenancy derivada de `CurrentOffice`, `VIEWER` somente leitura, nenhuma coordenada SERPRO sensível, token, PFX, XML integral ou resposta fiscal bruta na SPA.

### Dependências entre changes

- Nível: `C0`.
- Bases estáveis: catálogo SERPRO, governança/readiness fiscal, shell responsivo, listas do painel, orquestrador MEI e contratos atuais de monitoramento implementados pelas changes concluídas.
- Depende de: nenhuma change ativa.
- Capability/contrato consumido: `MonitoringSurfaceRegistry`, `ManualConsultActionCatalog`, `FiscalModuleAvailabilityService`, APIs fiscais tenant-scoped e contratos de identidade `Office`/`CurrentOffice`.
- Marco exigido: nenhum upstream ativo; as bases concluídas são consumidas como estado atual do repositório.
- Relação: coordenada com as bases estáveis, sem dependência bloqueante.
- Desbloqueia: futura change independente de comunicações outbound e futuras changes específicas de operações fiscais mutantes.
- Paralelismo: trabalhos sem sobreposição em contratos do monitor, rotas fiscais, tipos/composables do Nuxt ou testes E2E podem avançar; alterações nessas áreas devem aguardar a estabilização desta capability para evitar duas fontes de verdade.
