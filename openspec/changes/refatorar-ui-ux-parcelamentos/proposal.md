## Why

A carteira de Parcelamentos já expõe os dados e modalidades oficiais, porém sua hierarquia visual ocupa o primeiro viewport com um alerta permanente e uma grade redundante, deixando filtros, KPIs e tabela menos escaneáveis que as carteiras de Simples Nacional e DCTFWeb. A refatoração deve alinhar a experiência operacional ao padrão dessas páginas sem esconder modalidades, alterar dados fiscais ou redesenhar o shell.

## What Changes

- Reorganizar o filtro de modalidade em uma faixa compacta e rolável com uma tab para cada tipo oficial do catálogo, além de Todos.
- Fazer cada tab produtiva aplicar seu código no filtro server-side já existente; manter PAEX e SIPADE visíveis em tabs próprias, identificadas como “em prospecção” e desabilitadas.
- Alinhar a ordem e a densidade do conteúdo ao fluxo das carteiras Simples Nacional e DCTFWeb: contexto, KPIs, filtros e tabela no primeiro viewport.
- Melhorar a leitura da tabela de Parcelamentos com situação como eixo inicial, identidade do cliente, resumos fiscais compactos e ações no final, preservando o detalhe local em slideover.
- Garantir comportamento responsivo, acessível e baseado em cores semânticas do Nuxt UI, com testes Vitest/fidelity da estrutura, modalidades individuais e estado indisponível.

Non-goals: alterar contratos ou payloads da API; habilitar egress SERPRO live ou flags de produção; executar PAEX/SIPADE; implementar adesão, reparcelamento, desistência ou emissão fiscal nova; emitir parecer jurídico; criar `mei`/`mei-worker` no Compose; usar targets de backup/restore indisponíveis.

## Capabilities

### New Capabilities

- `installments-portfolio-ux`: hierarquia visual, seleção contextual de modalidade, densidade operacional, responsividade e acessibilidade da carteira `/monitoring/installments`.

### Modified Capabilities

- Nenhuma. A change complementa, sem substituir, o contrato ativo `serpro-installments-monitoring` e preserva os contratos permanentes de shell, URL canônica, ordenação e carteira por cliente.

## Impact

- Web Nuxt: `apps/web/app/pages/monitoring/installments.vue` e, somente se necessário para composição reutilizável, componentes/utilitários do módulo de monitoramento.
- Testes: Vitest unitário/fidelity da superfície de Parcelamentos e gates completos do frontend.
- APIs, persistência, integração SERPRO, Compose e dependências externas não mudam.

### Dependências entre changes

- Nível: `C1`.
- Bases estáveis: arquétipo `.local/reference/nuxt-dashboard-template`, `MonitoringModuleTable`, main specs `monitoring-url-canonical`, `monitoring-client-column-fit` e `panel-scrollable-tabs-overflow`.
- Depende de: `monitorar-todos-parcelamentos-serpro`; capability/contrato `serpro-installments-monitoring`; marco exigido `apply`; relação `coordenada`.
- Desbloqueia: uma carteira de Parcelamentos visualmente consistente e pronta para verificação integrada da change upstream.
- Paralelismo: design e testes podem avançar em paralelo a gates backend da change upstream; alterações concorrentes em `installments.vue` ou nos testes da carteira exigem preservação e reconciliação explícita. Não há dependência ou conflito esperado com changes de Caixa Postal e SITFIS.
