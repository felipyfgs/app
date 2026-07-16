## 1. Linha de base, domínio e gates

- [x] 1.1 Executar e registrar a linha de base das suítes de auth, tenant switch, clientes, auditoria, exports, operações e frontend antes de alterar o módulo.
- [x] 1.2 Congelar em testes a matriz `ADMIN` / `OPERATOR` / `VIEWER` definida nas specs para departamentos, modelos, processos, tarefas, lote, evidências e consultas.
- [x] 1.3 Definir enums e objetos de valor de competência, origem/estado do processo, estado da tarefa, regra de prazo, risco, bucket da fila e estado de batch.
- [x] 1.4 Criar teste arquitetural que impeça controllers/jobs operacionais de chamar clientes SERPRO, ADN ou SEFAZ e de escrever cursores NSU/nNF.
- [x] 1.5 Documentar no código que gestor/executor são personas mapeadas para papéis existentes e que não haverá login de cliente final.

## 2. Fronteira entre plano de controle e plano de dados

- [x] 2.1 Confirmar que a change não cria tabela global nem concede acesso por `platform_memberships`, mantendo todo conteúdo operacional no plano de dados.
- [x] 2.2 Adicionar teste negativo que negue conteúdo operacional a `PLATFORM_ADMIN` sem membership tenant ativa e escritório selecionado.
- [x] 2.3 Definir escopos/repositórios tenant-scoped para todas as novas entidades usando `CurrentOffice`/`BelongsToOffice`.
- [x] 2.4 Adicionar guard comum que rejeite ou ignore `office_id` de payload/filtro e valide todos os relacionamentos no escritório da sessão.
- [x] 2.5 Criar teste arquitetural/integração que cubra queries, jobs, locks, agregações, downloads e exports sem `office_id` livre.

## 3. Schema relacional tenant-scoped

- [x] 3.1 Criar migration de timezone do escritório com backfill válido para `America/Sao_Paulo` e validação de timezone IANA.
- [x] 3.2 Criar `work_departments` com `office_id`, unicidades tenant-scoped, estado e índices de listagem.
- [x] 3.3 Adicionar `work_department_id` opcional a `office_user` e constraints que permitam apenas departamento do mesmo escritório.
- [x] 3.4 Criar `process_templates` e `process_template_tasks` com regras de prazo, ordem, defaults, `lock_version`, desativação e índices por tenant.
- [x] 3.5 Criar `process_generation_batches` e `process_generation_items` com preview, versão, hash/idempotência, expiração, estados e resultado por cliente.
- [x] 3.6 Criar `operational_processes` com cliente, competência, origem, snapshot, prazos, departamento, membership responsável, estado derivado e `lock_version`.
- [x] 3.7 Implementar unicidade PostgreSQL de geração por `office_id + template_id + client_id + competence` apenas para origem `TEMPLATE`.
- [x] 3.8 Criar `operational_tasks` com ordem, lifecycle, prazos, departamento, membership responsável, flags, impedimento, timestamps e `lock_version`.
- [x] 3.9 Criar `operational_comments` com alvo exclusivo de processo ou tarefa, autor, `office_id` e sem edição retroativa.
- [x] 3.10 Criar `operational_task_evidences` com metadados sanitizados, hash, objeto opaco, autor, tarefa e índices tenant-scoped.
- [x] 3.11 Criar estrutura de export operacional com filtros congelados, estado, path interno oculto, expiração e solicitante tenant-scoped sem misturar ZIP/XML fiscal.
- [x] 3.12 Implementar models, casts, relações, factories e seed builders de todas as novas tabelas.
- [x] 3.13 Testar migrations/constraints em PostgreSQL com referências cruzadas, duplicidade concorrente e mesmo CNPJ/competência em dois escritórios.
- [x] 3.14 Verificar rollback integral em banco sem dados e documentar estratégia forward-only após o piloto.

## 4. Serviços de competência, prazo, estado e risco

- [x] 4.1 Implementar parser/normalizador de competência `YYYY-MM` com casos inválidos e limites.
- [x] 4.2 Implementar calculador puro de dia fixo com clamp para último dia do mês no timezone do escritório.
- [x] 4.3 Implementar regras de dias corridos após competência e antes do prazo do processo.
- [x] 4.4 Testar cálculo em fevereiro bissexto, mês curto, virada de ano e servidor em timezone distinto.
- [x] 4.5 Implementar recalculador transacional de estado do processo a partir das tarefas obrigatórias/críticas.
- [x] 4.6 Implementar calculador de prazo efetivo e riscos combináveis `ATRASADA`, `EM_MULTA`, `SEM_PRAZO` e `SEM_RESPONSAVEL`.
- [x] 4.7 Implementar buckets determinísticos da fila e desempate por prazo, criticidade, criação e ID.
- [x] 4.8 Congelar em testes as definições compartilhadas por fila, KPIs, calendário e export.

## 5. Policies, concorrência e auditoria

- [x] 5.1 Estender `OfficeRole`/permissões com capacidades operacionais explícitas sem criar novos papéis.
- [x] 5.2 Implementar policies tenant-scoped de departamento, modelo, batch, processo, tarefa, comentário, evidência e export.
- [x] 5.3 Implementar resolução de membership responsável e validação de atividade/departamento no mesmo escritório.
- [x] 5.4 Implementar helper de concorrência otimista para `lock_version` com resposta 409 sanitizada.
- [x] 5.5 Definir ações de auditoria tipadas e contexto allowlisted para todas as mutações operacionais.
- [x] 5.6 Implementar resource de timeline que combine auditoria, comentários e evidências sem devolver payload bruto.
- [x] 5.7 Testar policies por papel, ID externo, membership inativa, office forjado e versão desatualizada.

## 6. Departamentos e modelos de processo

- [x] 6.1 Implementar CRUD/listagem de departamentos para `ADMIN`, incluindo desativação segura e associação à membership.
- [x] 6.2 Implementar requests/resources de departamento com unicidade tenant-scoped, sigla/cor válidas e paginação.
- [x] 6.3 Implementar CRUD/listagem de modelos e tarefas padrão com ordenação atômica e `lock_version`.
- [x] 6.4 Validar responsáveis/departamentos padrão ativos e pertencentes ao mesmo escritório.
- [x] 6.5 Implementar desativação de modelo usado sem apagar snapshots ou processos existentes.
- [x] 6.6 Criar testes de API para departamentos/modelos por papel, tenant, validação, ordenação e auditoria.

## 7. Preview e geração idempotente por modelo

- [x] 7.1 Implementar DTO canônico de pedido de preview com modelo, competência, clientes e overrides permitidos.
- [x] 7.2 Implementar `OperationalProcessGenerationService` para calcular processos/tarefas, defaults, prazos, responsáveis, alertas e conflitos no backend.
- [x] 7.3 Persistir batch/item `PREVIEWED` com hash, versão do modelo, expiração e nenhuma instância de processo criada.
- [x] 7.4 Implementar endpoint de preview que bloqueie cliente/modelo inativo, referência externa, regra incompleta e duplicidade.
- [x] 7.5 Implementar confirmação que revalide tenant, membership, clientes, versão, expiração e idempotency key antes de enfileirar.
- [x] 7.6 Implementar job Horizon em chunks que crie cada processo/tarefas/snapshot em uma transação e registre resultado por item.
- [x] 7.7 Tratar corrida da constraint única como `SKIPPED_DUPLICATE` sem duplicação nem batch inconsistente.
- [x] 7.8 Implementar consulta de progresso/resultado do batch e retry seguro apenas de itens elegíveis.
- [x] 7.9 Testar preview sem escrita, modelo alterado, preview expirado, retry HTTP, retry de job, falha parcial por item e confirmação concorrente.

## 8. Processos, tarefas e transições

- [x] 8.1 Implementar criação manual de processo/tarefas para `ADMIN` e `OPERATOR` com validação tenant/competência.
- [x] 8.2 Implementar leitura, alteração permitida, arquivamento e listagem base de processos sem hard delete de histórico.
- [x] 8.3 Implementar criação, edição e reordenação de tarefas antes do início e bloqueio estrutural para `OPERATOR` após execução.
- [x] 8.4 Implementar `OperationalTaskTransitionService` para iniciar, impedir, retomar, concluir, dispensar e reabrir.
- [x] 8.5 Exigir motivo em impedimento e justificativa de `ADMIN` em dispensa/reabertura.
- [x] 8.6 Exigir evidência existente antes de concluir tarefa marcada como obrigatória de evidência.
- [x] 8.7 Recalcular estado/progresso do processo e timestamps na mesma transação da mudança de tarefa.
- [x] 8.8 Implementar atribuição administrativa e ação de `OPERATOR` assumir tarefa livre do próprio departamento.
- [x] 8.9 Implementar comentários append-only em processo/tarefa e consulta paginada da timeline.
- [x] 8.10 Implementar endpoints/resources sem campos internos, `office_id` autoritativo ou dados de outro tenant.
- [x] 8.11 Testar todas as transições válidas/inválidas, evidência obrigatória, criticidade, progresso, concorrência e ausência de efeito fiscal.

## 9. Evidências no cofre

- [x] 9.1 Adicionar `SecureObjectPurpose::OperationalTaskEvidence` e helper de AAD canônico com purpose, office, tarefa, evidência e SHA-256.
- [x] 9.2 Configurar limite padrão de 20 MiB, allowlist PDF/PNG/JPEG/texto e detecção MIME server-side.
- [x] 9.3 Implementar upload que valide antes de gravar, persista metadados após o cofre e compense falhas sem objeto órfão.
- [x] 9.4 Implementar download autenticado por streaming/attachment com AAD exata e sem URL pública persistente.
- [x] 9.5 Implementar remoção autorizada com justificativa, auditoria e proteção da única evidência exigida por tarefa concluída.
- [x] 9.6 Implementar limpeza segura de objetos órfãos/expirados sem varrer ou apagar objetos de outras finalidades.
- [x] 9.7 Testar adulteração de AAD, MIME falso, excesso de tamanho, upload interrompido, download cruzado e remoção inconsistente.
- [x] 9.8 Estender scanners de API/log/export para barrar `vault_object_id`, path, bytes, PFX, PEM, tokens, Termo XML e material sensível.

## 10. Fila, lote e consultas operacionais

- [x] 10.1 Implementar `OperationalQueueQuery` com abas Hoje, Atrasadas, Esta semana, Impedidas e Concluídas.
- [x] 10.2 Aplicar escopo padrão de `OPERATOR` à própria membership e tarefas livres do departamento; permitir visão ampla autorizada a `ADMIN`.
- [x] 10.3 Implementar listagens por cliente/processo/tarefa com paginação, ordenação e todos os filtros das specs.
- [x] 10.4 Implementar `OperationalWorkBulkService` limitado e atômico para responsável, departamento, prazo e estado permitido.
- [x] 10.5 Validar todos os IDs/versões do lote antes de escrever e correlacionar eventos de auditoria.
- [x] 10.6 Implementar agregados mensais/semanais do calendário e endpoint paginado de detalhe do dia.
- [x] 10.7 Testar ordenação estável, timezone, filtros combinados, lote válido, lote externo/desatualizado e paginação sem vazamento.

## 11. Métricas, dashboard e export CSV

- [x] 11.1 Implementar query de KPIs de trabalho com total, atrasadas, em multa, vencem hoje, em progresso, concluídas e sem responsável.
- [x] 11.2 Implementar agrupamentos por departamento/responsável e listas de maiores riscos, processos sem dono e clientes com pendências.
- [x] 11.3 Acrescentar bloco operacional ao resource/controller do dashboard sem alterar definições da inbox fiscal, canais ou backup.
- [x] 11.4 Implementar deep-links/filtros equivalentes entre KPIs, riscos, fila e listagens.
- [x] 11.5 Implementar criação, consulta, job, expiração e download de export CSV operacional com snapshot de filtros.
- [x] 11.6 Definir colunas CSV allowlisted e excluir comentários, evidências, identificadores internos e conteúdo fiscal bruto.
- [x] 11.7 Medir queries representativas, adicionar índices faltantes e congelar limites/paginação sem introduzir agregação cross-tenant.
- [x] 11.8 Testar reconciliação KPI-lista, riscos combinados, dashboard misto, export por papel e payload sanitizado.

## 12. Fundação frontend no template fixado

- [x] 12.1 Abrir e registrar os arquivos-fonte exatos do template `0f30c09` para home, customers, inbox, settings, members e AddModal antes de editar cada rota.
- [x] 12.2 Adicionar tipos API, enums, schemas Zod e composables do módulo usando `useApi` e respostas paginadas server-side.
- [x] 12.3 Estender utilitários de permissão para a matriz operacional sem tratar ocultação frontend como autorização.
- [x] 12.4 Adicionar grupo de navegação, command palette e atalhos de “Minha fila”, Processos, Calendário e Modelos no shell existente.
- [x] 12.5 Implementar redirect pós-login de `OPERATOR` para `/work` e reset de estado ao trocar explicitamente de escritório.
- [x] 12.6 Criar helpers de filtros em URL e estados comuns loading/vazio/erro/403/409/422.

## 13. Telas de execução e gestão

- [x] 13.1 Copiar o arquétipo `inbox.vue`/`components/inbox/*` para `/work` e adaptar lista, tabs, seleção e detalhe à fila real.
- [x] 13.2 Implementar painel/slideover de tarefa com contexto, ações rápidas, comentários, evidências e timeline por permissão.
- [x] 13.3 Copiar `customers.vue` para `/work/processes` com níveis cliente/processo/tarefa, filtros, seleção autorizada e paginação server-side.
- [x] 13.4 Copiar `settings.vue`/subpáginas para `/work/processes/[id]` com resumo, checklist, comentários, evidências e histórico.
- [x] 13.5 Copiar o arquétipo de lista/modal para `/work/templates` e implementar editor ordenável de tarefas padrão.
- [x] 13.6 Implementar fluxo de geração por modelo com seleção, preview, conflitos, confirmação idempotente e progresso do batch.
- [x] 13.7 Copiar o padrão de settings/members para `/admin/departments` e associação de departamento primário às memberships.
- [x] 13.8 Implementar `/work/calendar` preservando navbar/toolbar do home e adicionando somente corpo mensal/semanal e painel do dia.
- [x] 13.9 Adaptar o home template para o bloco de KPIs/riscos operacionais sem remover backup, inbox e saúde atuais.
- [x] 13.10 Remover mocks/dados demo e confirmar que nenhuma tela copia `TeamsMenu`, `server/api/*`, paginação client-side ou URL pública de evidência.

## 14. Verificação frontend e contratos ponta a ponta

- [x] 14.1 Criar testes Vitest dos composables de filtro, prioridade apresentada, permissões, conflito 409 e erros 422.
- [x] 14.2 Criar testes de componentes para fila/detalhe, preview, tabela, calendário, estados vazios e ações por papel.
- [x] 14.3 Criar Playwright do fluxo `ADMIN`: departamento → modelo → preview → geração → lote → dashboard/export.
- [x] 14.4 Criar Playwright do fluxo `OPERATOR`: login em Minha fila → assumir/iniciar/impedir/comentar/anexar/concluir.
- [x] 14.5 Criar Playwright de `VIEWER` somente leitura e de troca entre dois escritórios com o mesmo CNPJ/competência.
- [x] 14.6 Validar mestre–detalhe/slideover, tabela, settings, modal e calendário em desktop e mobile contra o checklist do template.
- [x] 14.7 Executar lint, typecheck, Vitest, Playwright, geração SPA e scanner de artefatos sem introduzir runtime Node em produção.

## 15. Trial local, piloto e operação

- [x] 15.1 Criar seed local idempotente com dois escritórios, mesmos CNPJs/competências, departamentos, modelos, riscos e todos os estados de tarefa.
- [x] 15.2 Exercitar geração concorrente e retries com workers Horizon reais, confirmando locks, idempotência e resultados por item.
- [x] 15.3 Definir métricas/logs sanitizados para batches, duração da fila, falhas, conflitos, exports, uploads e uso de storage por escritório.
- [x] 15.4 Configurar retenção/limpeza inicial de previews, exports e objetos órfãos e documentar quota configurável de evidências para o piloto.
- [x] 15.5 Executar backup e restore drill contendo schema operacional, metadados e objetos cifrados antes de dados reais.
- [x] 15.6 Executar smoke test restrito com dois tenants e varredura cross-tenant de API, arquivos, jobs, locks, métricas e exports.
- [x] 15.7 Confirmar que nenhum fluxo operacional exige credencial SERPRO, certificado, Termo, chamada fiscal ou gate comercial externo.
- [x] 15.8 Validar todos os cenários das delta specs, registrar limitações de dias úteis/departamentos múltiplos e preparar rollout do piloto.
- [x] 15.9 Executar a suíte completa backend/frontend e o `openspec validate`, corrigindo regressões antes de marcar a change como implementada.

