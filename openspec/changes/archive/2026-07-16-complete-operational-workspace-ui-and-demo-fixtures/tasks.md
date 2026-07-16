## 1. Baseline, coordenação e matriz do template

- [x] 1.1 Registrar contagens atuais do office `demo`, payloads de `/api/v1/work/*` e screenshots de `/work`, calendário, processos e modelos antes da mudança.
- [x] 1.2 Revisar o worktree e mapear arquivos sobrepostos com `add-operational-process-management` e `refactor-complete-dashboard-ui-ux`, preservando todas as mudanças locais existentes.
- [x] 1.3 Criar a matriz `/work` → arquivo exato do template fixado para fila, detalhe, calendário, processos, detalhe de processo, modelos e Home.
- [x] 1.4 Comparar `frontend/app/pages/work/index.vue` com `pages/inbox.vue`, `InboxList.vue` e `InboxMail.vue` e registrar divergências estruturais.
- [x] 1.5 Comparar processos/modelos com `pages/customers.vue`, detalhe com `pages/settings.vue` e criação com `AddModal.vue`.
- [x] 1.6 Registrar quais elementos da Agenda Makro serão aproveitados e quais serão recusados, incluindo a proibição explícita de grade horária.
- [x] 1.7 Confirmar pelo MCP Nuxt UI as props/slots efetivamente usados de `UDashboardPanel`, `UCalendar`, `UTabs`, `USlideover`, `UProgress`, `UStepper` e `UFileUpload`.
- [x] 1.8 Definir contratos de URL para `task`, `tab`, `view`, `date`, filtros, paginação e seções de processo antes de alterar componentes.

## 2. Contrato e infraestrutura da fixture operacional

- [x] 2.1 Definir configuração local/testing para `DEMO_WORK_ANCHOR_DATE`, office alvo `demo` e namespace/manifesto dos registros operacionais demonstrativos.
- [x] 2.2 Implementar parser da âncora com validação `Y-m-d`, timezone do office e fallback único para o hoje civil local.
- [x] 2.3 Refatorar `OperationalWorkDemoSeeder` para abortar antes de qualquer escrita fora de `local`/`testing`.
- [x] 2.4 Refatorar o seeder para localizar e reutilizar o office `demo`, a assinatura e os usuários/memberships criados pelo `DatabaseSeeder`.
- [x] 2.5 Envolver a reconciliação da massa operacional em transação e emitir resumo sanitizado de contagens após commit.
- [x] 2.6 Definir chaves lógicas estáveis para departamentos, clientes, modelos, processos, tarefas, comentários e evidências demonstrativas.
- [x] 2.7 Implementar upsert/reconciliação restrita ao manifesto demo sem modificar registros operacionais manuais fora do namespace.
- [x] 2.8 Registrar `OperationalWorkDemoSeeder` no `DatabaseSeeder` depois do catálogo e dos usuários demo.

## 3. Massa operacional representativa

- [x] 3.1 Criar/reconciliar departamentos demo Fiscal, Pessoal, Contábil e Societário com cores/tokens sanitizados.
- [x] 3.2 Criar/reconciliar memberships operacionais por departamento reutilizando papéis `ADMIN`, `OPERATOR` e `VIEWER`, sem inventar novos papéis.
- [x] 3.3 Criar/reconciliar um conjunto determinístico de clientes sintéticos do office `demo` com CNPJs válidos como texto e nomes explicitamente demonstrativos.
- [x] 3.4 Criar/reconciliar modelos de processo para rotinas Fiscal, Pessoal, Contábil e Societária com regras e tarefas variadas.
- [x] 3.5 Criar/reconciliar processos em competências anterior, atual e seguinte, com responsáveis/departamentos e prazos relativos à âncora.
- [x] 3.6 Criar/reconciliar tarefas cobrindo `A_FAZER`, `EM_PROGRESSO`, `IMPEDIDA`, `CONCLUIDA` e `DISPENSADA`.
- [x] 3.7 Distribuir tarefas entre vencidas, em multa, vencendo hoje, próximas, sem prazo, críticas e sem responsável usando calculadores reais.
- [x] 3.8 Criar comentários e motivos de impedimento sintéticos por serviços/modelos permitidos, sem payload externo bruto.
- [x] 3.9 Criar ao menos uma evidência textual sintética via `SecureObjectStore` ou adapter de testing, com aviso “SEM VALIDADE FISCAL”.
- [x] 3.10 Criar office sentinela sem membership dos usuários demo e repetir ao menos um CNPJ/rótulo para testes de isolamento.
- [x] 3.11 Garantir que processos/tarefas do sentinela tenham estados e datas que seriam visíveis caso uma query esquecesse `office_id`.

## 4. Testes e segurança da fixture

- [x] 4.1 Testar que uma recriação local limpa deixa o office `demo` com departamentos, modelos, processos e tarefas consumíveis pela API.
- [x] 4.2 Testar idempotência executando o seeder duas vezes com a mesma âncora e comparando chaves/contagens.
- [x] 4.3 Testar reconciliação com âncora diferente sem duplicar entidades nem alterar registro manual fora do manifesto.
- [x] 4.4 Testar rollback integral quando uma etapa intermediária da carga falha.
- [x] 4.5 Testar abort fail-closed do seeder em ambiente diferente de `local`/`testing`.
- [x] 4.6 Testar que admin, operador e viewer demo recebem o mesmo dataset permitido com ações distintas por policy.
- [x] 4.7 Testar fila, processos, calendário, KPIs, detalhes e busca contra o office sentinela para provar isolamento.
- [x] 4.8 Testar que ID de processo, tarefa ou evidência do sentinela não revela existência ou conteúdo ao usuário demo.
- [x] 4.9 Testar resource/download da evidência sintética sem `vault_object_id`, caminho interno ou conteúdo fiscal real.
- [x] 4.10 Adicionar scanner para PFX, PEM, senha, token, cookie, XML, Termo e identificadores de cofre nos logs/artefatos da fixture.

## 5. Contratos backend da fila, calendário e KPIs

- [x] 5.1 Inventariar parâmetros e DTOs atuais de queue, task detail, process list/detail, templates, calendar/day e KPIs.
- [x] 5.2 Tipar e validar filtros de queue para tab, busca, departamento, responsável, cliente, escopo, página e tamanho, rejeitando `office_id` do cliente.
- [x] 5.3 Garantir que paginação/ordenação da fila permaneçam server-side e reutilizem `WorkRiskCalculator` e `QueueBucketResolver`.
- [x] 5.4 Enriquecer o DTO detalhado de tarefa com departamento, responsável, riscos/bucket e timeline allowlisted necessários à UI.
- [x] 5.5 Definir endpoint/DTO de intervalo do calendário com `from`, `to`, timezone, agregados diários e itens tipados necessários às visões Mês/Semana.
- [x] 5.6 Estender a consulta diária para filtros server-side, risco/bucket, departamento, responsável, cliente e paginação.
- [x] 5.7 Testar limites máximos de intervalo e paginação para impedir carga completa da base pelo calendário.
- [x] 5.8 Enriquecer lista/detalhe de processos com progresso, risco, departamento, responsável e contagens allowlisted sem N+1.
- [x] 5.9 Evoluir KPIs por departamento para abertas, concluídas no período, atrasadas, em multa, sem responsável e denominador/percentual explícitos.
- [x] 5.10 Garantir que agregados, fila e deep-links compartilhem timezone, corte temporal e filtros normalizados.
- [x] 5.11 Adicionar testes de contrato, autorização, 404 cross-tenant e ausência de campos sensíveis para todos os DTOs alterados.

## 6. Fundação frontend operacional

- [x] 6.1 Atualizar `frontend/app/types/work.ts` com DTOs discriminados da fila, detalhe, calendário, processos, modelos e progresso departamental, removendo `Record<string, unknown>` das superfícies alvo.
- [x] 6.2 Atualizar `useApi().work` para os contratos e filtros tipados, preservando Sanctum same-origin e sem aceitar `office_id` livre.
- [x] 6.3 Criar utilitários únicos para labels/cores/ícones de `TaskStatus`, `ProcessStatus`, `WorkRisk` e buckets sem depender somente de cor.
- [x] 6.4 Criar composable de filtros/URL da fila que normalize query, remova valores vazios, reinicie paginação e descarte resposta de tenant anterior.
- [x] 6.5 Criar composable temporal do calendário para `view`, `date`, limites Mês/Semana/Dia e navegação anterior/hoje/próximo.
- [x] 6.6 Extrair componente de linha da fila copiando estrutura e atalhos de `InboxList.vue`, adaptando apenas dados e estados.
- [x] 6.7 Extrair painel de detalhe copiando `InboxMail.vue`, substituindo ações demo por transições, comentários, evidências e timeline reais.
- [x] 6.8 Criar filtros operacionais reutilizáveis com `UInput`, `USelect`/`USelectMenu` e overlay de filtros avançados conforme o breakpoint.
- [x] 6.9 Implementar empty/error/loading/refresh/403/409/422 compartilhados sem encapsular o `UDashboardPanel` canônico.
- [x] 6.10 Invalidar seleções, cache e requests pendentes ao trocar explicitamente de membership/office autorizado.

## 7. Refatoração de `/work`

- [x] 7.1 Reestruturar `/work` em painel de lista resizable com `default-size`, `min-size` e `max-size` alinhados a `pages/inbox.vue`.
- [x] 7.2 Mover contagem e tabs da fila para navbar/toolbar conforme a forma do Inbox, com nomes acessíveis e overflow controlado.
- [x] 7.3 Ligar busca, departamento, responsável, cliente e escopo ao composable de URL e à API server-side.
- [x] 7.4 Renderizar linhas densas com título, cliente/processo, prazo, risco, departamento e responsável, mantendo foco e estado selecionado.
- [x] 7.5 Implementar seleção inicial válida, query `task`, restauração após reload e remoção da seleção quando o item sai do filtro.
- [x] 7.6 Renderizar painel adjacente completo com metadados, descrição, badges semânticos, ações, comentários, evidências e timeline.
- [x] 7.7 Corrigir fluxo de upload para usar `UFileUpload` e estados de validação/erro, mantendo o cofre e download protegido.
- [x] 7.8 Tratar 409 em transições preservando entrada não sensível e oferecendo recarga do detalhe.
- [x] 7.9 Implementar estado neutro desktop com ícone e orientação quando não houver seleção.
- [x] 7.10 Implementar slideover/drawer abaixo de `lg`, fechamento por Escape/controle, foco retornado e mesmas ações do desktop.
- [x] 7.11 Implementar `ArrowUp`/`ArrowDown` na lista sem interferir em inputs, textareas ou overlays.
- [x] 7.12 Validar modo somente leitura para `VIEWER` e escopo padrão/ações elegíveis para `OPERATOR`.

## 8. Refatoração de `/work/calendar`

- [x] 8.1 Copiar a anatomia Home/Dashboard para navbar, toolbar e corpo do calendário, preservando sidebar collapse e ação/contexto corretos.
- [x] 8.2 Implementar `UTabs` Mês/Semana/Dia com `view` e `date` reproduzíveis na URL.
- [x] 8.3 Implementar navegação anterior/hoje/próximo e rótulo de intervalo localizados em pt-BR/timezone do office.
- [x] 8.4 Implementar visão Mês com dias civis, contagens e severidade, incluindo células fora do mês quando necessárias à grade estável.
- [x] 8.5 Implementar visão Semana com sete lanes por data e cards ordenados por bucket/prazo, sem eixo de horas.
- [x] 8.6 Implementar visão Dia como fila detalhada paginada que reutiliza linha/detalhe e policies de `/work`.
- [x] 8.7 Implementar rail desktop redimensionável com `UCalendar`, data selecionada e tabs Tarefas/Atrasadas/Concluídas.
- [x] 8.8 Ligar filtros de departamento, responsável, cliente, status e risco a todas as visões sem filtragem apenas client-side.
- [x] 8.9 Migrar o rail para drawer/slideover no mobile e garantir ausência de overflow em 360 px.
- [x] 8.10 Tratar intervalos vazios, loading por visão, falha inicial e refresh mantendo o último calendário válido.
- [x] 8.11 Testar teclado, foco, nomes acessíveis de dias/contagens e preferência por movimento reduzido.

## 9. Processos e detalhe

- [x] 9.1 Realinhar `/work/processes` ao markup, slots, tabela e rodapé de `pages/customers.vue`.
- [x] 9.2 Implementar busca, competência, status, risco, departamento, responsável, cliente e paginação server-side com URL normalizada.
- [x] 9.3 Renderizar colunas/slots tipados para cliente, competência, status, progresso, risco, prazo e responsável, com versão mobile prioritária.
- [x] 9.4 Adicionar ações de linha reais e condicionadas à policy, sem controles decorativos de seleção/colunas.
- [x] 9.5 Realinhar `/work/processes/{id}` ao shell Settings com seções Resumo, Tarefas, Comentários/Evidências e Histórico.
- [x] 9.6 Implementar resumo do processo com cliente, competência, origem, prazos, lifecycle, risco, responsável, departamento e `UProgress` acessível.
- [x] 9.7 Implementar checklist de tarefas ordenado com estados, prazos, criticidade, evidência, responsáveis e ações reais.
- [x] 9.8 Implementar comentários/evidências e timeline allowlisted sem apresentar payload bruto de auditoria.
- [x] 9.9 Tratar loading, vazio parcial, 403, 404 cross-tenant, 409 e viewer somente leitura por seção.
- [x] 9.10 Validar navegação voltar/lista preservando filtros e seção reproduzível em reload/deep-link.

## 10. Modelos e geração guiada

- [x] 10.1 Realinhar `/work/templates` ao arquétipo Customers com busca/status, tabela, ações e paginação server-side.
- [x] 10.2 Copiar `AddModal.vue` para criar/editar modelo com `UForm`, schema tipado, tarefas ordenadas e erros 422 por campo.
- [x] 10.3 Implementar editor de tarefas do modelo com ordem, regra de prazo, departamento/responsável, criticidade e exigência de evidência.
- [x] 10.4 Implementar bloqueio somente leitura para `OPERATOR`/`VIEWER` conforme policies e proteção administrativa/2FA aplicável.
- [x] 10.5 Implementar `UStepper` Selecionar → Configurar → Pré-visualizar → Confirmar → Acompanhar.
- [x] 10.6 Ligar seleção de clientes/competência/overrides ao preview persistido real e apresentar alertas, bloqueios e duplicidades.
- [x] 10.7 Impedir confirmação de preview expirado, alterado ou bloqueado e preservar dados não sensíveis para correção.
- [x] 10.8 Acompanhar batch real com estados, progresso, erros sanitizados e deep-links para processos criados.
- [x] 10.9 Cobrir reload/retorno do fluxo sem simular sucesso nem reenviar confirmação equivalente.

## 11. Home, navegação e estados integrados

- [x] 11.1 Atualizar `WorkKpisBlock.vue` para consumir o novo agregado departamental tipado e manter horário da última atualização válida.
- [x] 11.2 Renderizar cards compactos por departamento com abertas, concluídas, atrasadas, em multa, sem responsável e `UProgress` acessível.
- [x] 11.3 Implementar deep-links de cada métrica para `/work` ou processos com filtros equivalentes e reproduzíveis.
- [x] 11.4 Manter blocos Work, Fiscal, Sincronização, Backup e Infraestrutura semanticamente separados na Home.
- [x] 11.5 Tratar falha parcial do endpoint Work sem desmontar os demais blocos válidos.
- [x] 11.6 Revisar sidebar, command palette e atalhos para fila, calendário, processos e modelos usando as mesmas permissões tipadas.
- [x] 11.7 Remover controles sem backend real e confirmar uma única ação primária por navbar.
- [x] 11.8 Revisar textos/labels pt-BR, datas, competências, pluralização e máscaras sem transformar CNPJ em número.

## 12. Testes frontend, visual e acessibilidade

- [x] 12.1 Atualizar fixtures E2E tipadas para consumir o seed persistido/âncora fixa e separar testes de contrato de interceptações isoladas.
- [x] 12.2 Cobrir `/work` preenchido, filtros, teclado, seleção, transições, comentário, upload/download e viewer.
- [x] 12.3 Cobrir calendário Mês/Semana/Dia, navegação temporal, filtros, minicalendário, rail e overlay mobile.
- [x] 12.4 Cobrir lista/detalhe de processos, seções, checklist, 404 cross-tenant e retorno com filtros.
- [x] 12.5 Cobrir lista/editor de modelos, preview, confirmação, conflito, expiração e acompanhamento de batch.
- [x] 12.6 Adicionar testes unitários para labels/cores/ícones, normalização de URL, data/intervalo e descarte de resposta após troca de office.
- [x] 12.7 Capturar baselines `1440×900` e `390×844` preenchidos para todas as rotas `/work` com âncora fixa.
- [x] 12.8 Capturar estados loading, vazio, erro, refresh, viewer e overlays críticos; validar 360 px sem overflow.
- [x] 12.9 Executar axe/checagens equivalentes para foco, contraste, nomes acessíveis, tabs, tabelas, calendar e overlays.
- [x] 12.10 Executar scanner de screenshots, traces, HTML e logs contra segredos, XML fiscal real e dados do tenant sentinela.

## 13. Gates finais e documentação

- [x] 13.1 Executar migrations e seed do zero no Docker, autenticar com as três contas demo e confirmar dados reais no office `demo`.
- [x] 13.2 Executar novamente o seed e provar idempotência por contagem/hash lógico.
- [x] 13.3 Executar PHPUnit do módulo Work, fixtures e cross-tenant sem regressões.
- [x] 13.4 Executar lint, typecheck, testes unitários frontend e suíte E2E/visual relevante.
- [x] 13.5 Executar build SPA de produção e provar ausência de dataset/fallback demo no bundle/runtime.
- [x] 13.6 Preencher o checklist `nuxt-dashboard-template/references/checklist.md` para cada rota da família `/work`.
- [x] 13.7 Documentar setup/reseed da demonstração, âncora temporal, contas, limites e aviso de ausência de validade fiscal.
- [x] 13.8 Registrar evidências de arquétipos copiados e MCPs consultados por rota, incluindo divergências justificadas.
- [x] 13.9 Revisar tarefas sobrepostas nas changes ativas e referenciar a evidência única sem marcar trabalho não executado como concluído.
- [x] 13.10 Executar `openspec validate complete-operational-workspace-ui-and-demo-fixtures --json` e corrigir todas as violações antes do handoff.
