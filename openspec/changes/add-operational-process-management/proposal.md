## Por quê

Os escritórios já concentram no MonitorHub dados fiscais e cadastrais dos seus clientes, mas ainda não dispõem de uma camada operacional para transformar rotinas recorrentes em trabalho priorizado, atribuível e auditável. Esta change cria esse núcleo para reduzir esquecimentos e atrasos, dar ao executor uma fila diária objetiva e permitir ao gestor enxergar risco, carga e gargalos sem recorrer a controles paralelos.

## O que muda

- Adiciona departamentos do escritório e modelos reutilizáveis de processo, compostos por tarefas ordenadas, responsáveis opcionais, exigência de evidência e regras relativas de prazo.
- Permite criar processos manualmente ou gerar, por modelo e competência, processos para um ou vários clientes após um preview validado de tarefas, prazos, responsáveis, duplicidades e conflitos.
- Introduz processos e tarefas tenant-scoped com responsáveis, departamentos, prazos, prazo-meta, competência, indicador de multa, estados controlados e progresso derivado.
- Entrega a fila “Minha fila”, ordenada por risco e prazo, com ações rápidas de iniciar, impedir, concluir, dispensar, comentar e anexar evidência conforme papel e regras da tarefa.
- Adiciona listagens por cliente, processo e tarefa, calendário mensal/semanal, filtros server-side, edição em lote e exportação CSV, sempre restritos ao escritório ativo.
- Amplia o dashboard existente com KPIs de execução, riscos, carga por departamento/responsável e deep-links para as listas correspondentes, preservando os indicadores fiscais e de infraestrutura já existentes.
- Registra em auditoria as mudanças relevantes de status, prazo, responsável, departamento, conclusão, dispensa, comentários, evidências e operações em lote.
- Reutiliza autenticação, memberships, papéis `ADMIN` / `OPERATOR` / `VIEWER`, clientes e seleção explícita de escritório já existentes; “gestor” e “executor” são personas operacionais, não novos papéis de segurança.
- Estende o painel Nuxt existente a partir dos arquétipos do template fixado em `.reference/nuxt-dashboard-template` no commit `0f30c09`, sem criar outro starter ou design system.

## Capacidades

### Novas capacidades

- `operational-process-modeling`: departamentos, modelos de processo, tarefas padrão, regras relativas de prazo e preview/geração idempotente por cliente e competência.
- `operational-process-execution`: instâncias de processo e tarefa, fila priorizada, transições de estado, atribuição, comentários, impedimentos, evidências e operações em lote.
- `operational-process-oversight`: tabelas, calendário, indicadores de risco, métricas de carga/produtividade, filtros, deep-links gerenciais e exportação CSV tenant-scoped.

### Capacidades modificadas

- `frontend-dashboard-experience`: acrescentar as rotas, a navegação e os arquétipos de interface do módulo operacional ao shell autenticado existente, incluindo a fila como destino primário do executor.
- `operations-dashboard`: incorporar indicadores e listas de atenção de processos/tarefas ao resumo operacional sem remover nem misturar os sinais fiscais e de infraestrutura atuais.

## Não-objetivos

- Não criar portal, login ou qualquer acesso para os clientes finais/contribuintes do escritório.
- Não criar novos papéis além de `ADMIN`, `OPERATOR` e `VIEWER`, nem conceder acesso tenant a `PLATFORM_ADMIN` por essa condição.
- Não incluir IA generativa autônoma, sugestão automática de responsável, balanceamento automático de carga ou criação sem revisão humana no MVP.
- Não implementar chat completo, aplicativo móvel nativo, notificações por WhatsApp/e-mail, BI avançado, controle financeiro ou automações externas.
- Não vincular automaticamente eventos fiscais a tarefas nem alterar canais SERPRO, ADN/SEFAZ, cursores NSU/nNF, certificados ou mutações fiscais nesta change.
- Não expor evidências, comentários, exportações ou métricas entre escritórios, nem material fiscal/criptográfico sensível por API, UI, log ou arquivo.

## Impacto

- **Backend Laravel:** novos modelos, enums, migrations, policies, serviços de geração/priorização, endpoints REST tenant-scoped, exportação assíncrona, armazenamento privado de evidências e integração com a auditoria existente.
- **Frontend Nuxt/Nuxt UI:** novas páginas e componentes para fila, processos, calendário, modelos e configurações de departamentos, além de extensões no dashboard e na navegação; a forma visual seguirá os arquétipos home, lista administrativa, mestre–detalhe, settings e modal do template fixado.
- **Dados:** novas tabelas do plano de dados com `office_id` obrigatório, referências aos `clients` e usuários/memberships existentes, índices para filtros e unicidade idempotente por escritório/modelo/cliente/competência.
- **Filas e arquivos:** Redis/Horizon para geração em lote e exportações; evidências em disco privado abstraído pelo Laravel, com autorização por tenant e sem URL pública persistente.
- **APIs e segurança:** novas superfícies protegidas por sessão Sanctum/CSRF, papel do escritório e tenant ativo derivado da sessão; nenhum `office_id` fornecido pelo cliente será aceito como autoridade.

