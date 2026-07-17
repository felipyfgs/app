## Por quê

O monitoramento PGDAS-D atual usa campos e payloads que não representam o contrato oficial da SERPRO e apresenta colunas genéricas que não ajudam o escritório a decidir se a declaração mensal exige ação. A página precisa se tornar uma projeção operacional confiável, com histórico, RBT12 e documentos protegidos, sem transformar visualizações em chamadas faturáveis ou em envios ao cliente.

## O que muda

- Especializar somente a tabela PGDAS-D com razão social, última declaração, RBT12, controles de comunicação em modo template, última consulta válida e histórico detalhado.
- Integrar `CONSDECLARACAO13`, `CONSULTIMADECREC14`, `CONSDECREC15` e `CONSEXTRATO16` pelos contratos oficiais, sem chamar operações de emissão de DAS.
- Persistir original e retificadoras, DAS observados, documentos no cofre e uma projeção idempotente de RBT12 extraída do PDF oficial.
- Consolidar os estados `CURRENT`, `DUE_WITHIN_DEADLINE`, `OVERDUE_NOT_FOUND` e `UNVERIFIED`, impedindo vermelho sem consulta produtiva posterior ao prazo e calendário verificado.
- Criar preferências, prévia e rastreamento futuro de e-mail/WhatsApp, sem provider, webhook, fila ou envio real nesta change.
- Impedir que Base64 de documentos, caminhos do cofre ou dados de outro escritório apareçam em banco operacional, logs ou respostas públicas.

Não são objetivos desta change: chamada SERPRO real em testes ou smoke automatizado, emissão de DAS, transmissão de declaração, parecer jurídico sobre consentimento de comunicação, ativação de feature flags em produção e integração com provedores de e-mail/WhatsApp.

## Capacidades

### Novas capacidades

- `pgdasd-monitoring`: consulta, normalização, persistência, estado operacional, RBT12, documentos e interface especializada do monitoramento PGDAS-D.
- `pgdasd-communication-template`: preferências, prévia e rastreamento tenant-scoped de comunicação futura, explicitamente sem execução de envio.

### Capacidades modificadas

Nenhuma.

## Impacto

- Backend Laravel: catálogo e codecs SERPRO, scheduler de monitoramento, projeções fiscais, cofre de documentos, calendário de vencimentos, APIs tenant-scoped e auditoria.
- Banco PostgreSQL/SQLite de testes: migrations aditivas para operações, artefatos, RBT12 e comunicação.
- Frontend Nuxt/Nuxt UI: especialização da página PGDAS-D, tabela fluida derivada do arquétipo de lista e modais de histórico, prévia, preferências e rastreamento.
- Imagem PHP: inclusão de `poppler-utils` para extração controlada de texto do extrato PDF.
- Compatibilidade: demais submódulos de monitoramento mantêm seus contratos e componentes atuais; snapshots legados ou simulados não serão promovidos para as novas projeções.
