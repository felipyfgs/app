## Por quê

O workspace operacional já possui APIs e telas iniciais, mas o escritório `demo` usado pela sessão local não recebe processos nem tarefas: o seeder operacional existente aponta para outros tenants e não é chamado pelo `DatabaseSeeder`. Como resultado, `/work` e suas páginas relacionadas abrem vazias e não permitem avaliar corretamente a experiência de fila, calendário, processos e modelos.

Esta change torna o módulo demonstrável e operacionalmente legível usando dados sintéticos persistidos pelo backend e refatora a família `/work` a partir do Nuxt UI Dashboard Template fixado. A Agenda Makro serve somente como referência de densidade, alternância temporal e painel lateral; a identidade, os componentes, a acessibilidade e a estrutura continuam sendo do MonitorHub/Nuxt UI.

## O que muda

- Faz o `DatabaseSeeder` local/testing alimentar o escritório `demo` da sessão com departamentos, responsáveis, clientes, modelos, processos, tarefas, comentários e estados de risco representativos, usando tabelas, models, policies e endpoints reais.
- Torna a massa operacional determinística, idempotente, sanitizada e tenant-scoped, com datas relativas a uma âncora controlável, sem arrays fake no Vue, interceptador de runtime ou fallback sintético em produção.
- Refatora `/work` como mestre–detalhe fiel ao arquétipo `inbox.vue`: fila densa e priorizada, filtros reproduzíveis, contagem, navegação por teclado, detalhe completo e slideover/drawer no mobile.
- Refatora `/work/calendar` com visões `Mês`, `Semana` e `Dia`, navegação temporal, minicalendário `UCalendar`, filtros server-side e painel da data selecionada. A visão semanal usa lanes por dia e prazos reais, sem grade horária ou compromissos inexistentes no domínio.
- Refatora `/work/processes` como lista administrativa server-side e `/work/processes/{id}` como detalhe por seções, com progresso, checklist, riscos, responsáveis, comentários, evidências e histórico autorizado.
- Refatora `/work/templates` como lista administrativa com criação/edição focada e fluxo guiado `Selecionar → Configurar → Pré-visualizar → Confirmar → Acompanhar`, sem simular sucesso no frontend.
- Amplia o resumo de trabalho da Home com carga/progresso por departamento e deep-links que preservam filtros, sem misturar indicadores de trabalho com saúde fiscal ou infraestrutura.
- Padroniza loading inicial, refresh, preenchido, vazio legítimo, erro, conflito, somente leitura e estados móveis em toda a família operacional.
- Adiciona testes de contrato, tenancy, idempotência do seeder, interação, permissões, acessibilidade, responsividade e regressão visual com artefatos sanitizados.

## Capacidades

### Novas capacidades

- `operational-workspace-demo-fixtures`: massa demonstrativa local/testing, persistida e determinística para o workspace operacional, com cobertura realista de estados, riscos, papéis e isolamento multi-escritório, sem dependência no runtime produtivo.

### Capacidades modificadas

- `frontend-dashboard-experience`: completar a experiência das rotas `/work`, calendário, processos e modelos com os arquétipos mestre–detalhe, Home, lista administrativa, Settings e modal/Stepper do template fixado.
- `dashboard-template-fidelity`: tornar explícita a derivação de cada tela operacional dos arquivos exatos do template e exigir aceite visual/funcional por estado e viewport.
- `operations-dashboard`: apresentar progresso e carga operacional por departamento com métricas reais e deep-links coerentes para a fila e os processos.

## Não-objetivos

- Não criar portal ou login para clientes finais/contribuintes.
- Não substituir o Nuxt UI Dashboard Template, copiar marca, cores, tipografia, sidebar ou chrome da Makro.
- Não modelar reuniões, compromissos, recorrências, horários inicial/final, feriados ou agenda externa; prazos de tarefas continuam sendo datas civis.
- Não adicionar mocks Nuxt, arrays fake em componentes, respostas interceptadas no runtime ou dados demonstrativos como fallback de produção.
- Não redesenhar o domínio de processos, criar novos papéis ou alterar as regras de transição, evidência, auditoria e concorrência já definidas em `add-operational-process-management`.
- Não alterar SERPRO, ADN/SEFAZ, cursores NSU/nNF, certificados, cofre, mutações fiscais ou plano de controle global.
- Não permitir seleção livre de `office_id`, misturar dados entre escritórios ou expor PFX, senha, PEM, tokens, XML fiscal real, identificadores de cofre ou conteúdo de evidência em fixtures e artefatos.

## Impacto

- **OpenSpec:** especializa a entrega visual e demonstrativa de `add-operational-process-management` e pode ser aplicada como incremento focado antes da refatoração transversal `refactor-complete-dashboard-ui-ux`, evitando duplicar decisões de domínio.
- **Backend Laravel:** ajuste e registro do seeder operacional, dados sintéticos tenant-scoped, possível enriquecimento read-only dos resources/agregados do calendário e dashboard, sem criar API paralela de demonstração.
- **Frontend Nuxt/Nuxt UI:** refatoração de `frontend/app/pages/work/**`, componentes de trabalho, tipos/composables e bloco operacional da Home; produção permanece SPA estática same-origin com Laravel/Sanctum.
- **Testes:** PHPUnit para fixtures/tenancy/contratos; Vitest para estado e permissões; Playwright funcional e visual em desktop/mobile com varredura de segredos.
- **Dependências:** nenhuma nova biblioteca de calendário ou design system; usar `UDashboard*`, `UCalendar`, `UTabs`, `UTable`, `UProgress`, `USlideover`, `UModal`, `UStepper` e `UFileUpload` já fornecidos pelo Nuxt UI 4.
