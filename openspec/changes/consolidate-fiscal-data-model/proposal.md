## Why

A modelagem cresceu por entregas incrementais e hoje mantém autoridades concorrentes para clientes e estabelecimentos, documentos e aquisições, cursores, recuperações de XML, monitoramento, guias e consumo SERPRO. Antes de ampliar o piloto, precisamos consolidar essas fronteiras e levar invariantes críticas ao PostgreSQL sem perder dados, isolamento entre escritórios ou funcionalidades já entregues.

## What Changes

- Consolidar o agregado cadastral em `Office -> Client (raiz) -> Establishment (CNPJ completo)`, com uma raiz única por escritório, vários estabelecimentos por cliente, uma única matriz e um A1 por raiz.
- Tornar o isolamento de tenant estrutural e fail-closed, com referências compostas por `office_id`, seleção de escritório vinculada a membership válida e separação explícita entre planos de controle global e de dados do tenant.
- Consolidar o catálogo fiscal em documento canônico imutável, aquisições que registram cada chegada, interesses semânticos por estabelecimento e projeções tipadas com uma única autoridade.
- Consolidar cursores por fluxo fiscal sem misturar identidades ou credenciais: distribuição do contribuinte, distribuição do autor/escritório e sequenciamento de saídas permanecem streams distintos.
- Separar casos de recuperação de XML, tentativas por fonte e solicitações de pacote; convergir mutações fiscais em uma trilha genérica, sem ampliar o escopo mutante do MVP.
- Consolidar catálogos e ledger SERPRO, separando operação estável, versão oficial e regra de cobrança e separando agregados globais dos agregados por escritório.
- Separar identidade de período, execução operacional e snapshot fiscal; consolidar a autoridade de versão/situação de guias e estados de pagamento.
- Padronizar enums: estados internos fechados usam enum PHP, `varchar` e `CHECK`; valores oficiais evolutivos preservam o valor bruto e são normalizados por mapper; catálogos configuráveis são tabelas versionadas.
- Executar a transição em fases aditivas, com inventário, backfill idempotente, compatibilidade temporária e reconciliação antes de aposentar qualquer estrutura.
- Exigir uma verificação final pós-apply com reconciliação de dados, isolamento multi-tenant, contratos de API, regressão funcional, invariantes fiscais e ensaio de restauração. Estruturas legadas só poderão ser removidas após esse gate ser aprovado.
- **BREAKING (interno):** nomes de tabelas, colunas, modelos, enums e relações redundantes poderão ser substituídos após a fase de compatibilidade; os contratos HTTP e as funcionalidades de usuário deverão permanecer compatíveis ou ser migrados explicitamente.

## Capabilities

### New Capabilities

- `fiscal-data-model-integrity`: invariantes estruturais, política de enums, evolução segura do schema, reconciliação e gate final de integridade/regressão.

### Modified Capabilities

- `client-credential-management`: fixa Cliente como raiz do CNPJ, permite múltiplos estabelecimentos e elimina autoridades cadastrais concorrentes sem alterar a proteção do A1.
- `office-access-control`: torna o contexto de tenant fail-closed e exige coerência referencial por escritório e membership ativa.
- `fiscal-document-catalog`: separa documento canônico, cada aquisição, interesse semântico e projeção, preservando imutabilidade e idempotência.
- `adn-document-sync`: migra o cursor ADN para a autoridade consolidada sem avanço indevido ou perda de histórico.
- `sefaz-distdfe-sync`: migra o cursor DistDFe e sua idempotência para a autoridade consolidada sem misturar canais.
- `outbound-xml-ingestion`: registra proveniência por aquisição e preserva divergências em custódia durante e após a consolidação.

## Impact

- Backend Laravel: migrations PostgreSQL, models, enums, repositories, policies/scopes, jobs, parsers, serviços de cadastro, catálogo documental, monitoramento, guias, outbound e ledger SERPRO.
- Banco e operação: novas constraints e índices, backfills, comandos de auditoria/reconciliação, backup/restore e posterior retirada controlada de estruturas legadas.
- APIs e frontend Nuxt: adaptação interna a DTOs canônicos; respostas e jornadas existentes devem permanecer funcionais, com mudanças contratuais apenas se documentadas e testadas.
- Testes: ampliar cobertura PostgreSQL real, contratos de API, isolamento entre escritórios, idempotência, máquinas de estado, reconciliação financeira/fiscal e regressão ponta a ponta.
- OpenSpec: a aplicação desta change deve ocorrer depois que `build-complete-fiscal-monitoring-hub` estiver estável e suas specs estiverem sincronizadas, evitando duas autoridades concorrentes para o modelo-alvo.

## Não-objetivos

- Criar portal ou login para contribuinte final, mudar o stack, introduzir scraping/Gov.br/CAPTCHA ou substituir canais oficiais.
- Ampliar mutações fiscais, emissão/cancelamento de documentos, gateway de pagamento, precificação comercial completa ou cobertura municipal genérica.
- Expor ou migrar segredos para fora do `SecureObjectStore`, duplicar PFX, ou misturar credenciais globais SERPRO com dados de tenants.
- Alterar regras oficiais de NSU, pular documentos problemáticos, reescrever XML canônico ou remover histórico fiscal/auditoria para simplificar o schema.
- Fazer uma reescrita instantânea: a retirada de estruturas antigas depende de reconciliação e aprovação explícita do gate final.
