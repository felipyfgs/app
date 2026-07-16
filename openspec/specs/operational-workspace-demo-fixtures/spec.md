# operational-workspace-demo-fixtures

## Purpose

Especificação `operational-workspace-demo-fixtures` (sync change).

## Requirements

### Requirement: Fixture operacional persistida no office demo
O sistema SHALL popular, em ambiente `local` ou `testing`, o escritório `demo` usado pela sessão padrão com dados operacionais sintéticos persistidos nas tabelas e consumidos pelos endpoints reais de trabalho.

#### Scenario: Recriação do ambiente local
- **WHEN** migrations e `DatabaseSeeder` são executados em ambiente local limpo
- **THEN** os usuários demo abrem `/work` e recebem departamentos, processos e tarefas do office `demo` pela API Laravel, sem mock ou array fake no frontend

#### Scenario: Viewer do office demo
- **WHEN** `viewer@example.com` abre o workspace após o seed
- **THEN** os mesmos dados autorizados do office `demo` são exibidos em modo somente leitura e ações mutantes não são oferecidas

### Requirement: Massa representativa do vocabulário operacional
A fixture SHALL conter variedade suficiente para exercitar departamentos, clientes, modelos, competências, lifecycle, risco, atribuição, comentário, evidência sintética e telas de calendário/processo.

#### Scenario: Estados de tarefa
- **WHEN** a fixture termina de carregar
- **THEN** existem tarefas `A_FAZER`, `EM_PROGRESSO`, `IMPEDIDA`, `CONCLUIDA` e `DISPENSADA`, distribuídas entre departamentos e responsáveis

#### Scenario: Prazos e riscos
- **WHEN** os KPIs, a fila e o calendário consultam a massa demo
- **THEN** existem exemplos vencidos, em multa, vencendo hoje, futuros, sem prazo e sem responsável, todos calculados pelas regras reais do backend

#### Scenario: Conteúdo de detalhe
- **WHEN** uma tarefa ou processo demonstrativo é aberto
- **THEN** há exemplos com descrição, comentário, motivo de impedimento, progresso e evidência sintética sanitizada suficientes para preencher as seções aplicáveis

### Requirement: Âncora temporal determinística
O seeder MUST calcular todas as datas operacionais a partir de uma única âncora no timezone do escritório e SHALL aceitar `DEMO_WORK_ANCHOR_DATE` para testes reproduzíveis.

#### Scenario: Teste visual com data fixa
- **WHEN** o seed executa com `DEMO_WORK_ANCHOR_DATE=2026-07-15`
- **THEN** prazos, competências, buckets, contagens e ordem da fila são reproduzíveis em execuções subsequentes

#### Scenario: Uso local sem variável
- **WHEN** a variável de âncora não está configurada em ambiente local
- **THEN** o seeder usa o hoje civil do timezone do office calculado uma única vez e distribui os cenários relativamente a essa data

### Requirement: Idempotência e reconciliação segura
O seeder MUST ser idempotente, transacional e restrito ao namespace demonstrativo, atualizando datas relativas sem duplicar registros nem alterar dados operacionais não demonstrativos.

#### Scenario: Segunda execução
- **WHEN** o seeder é executado novamente com a mesma âncora
- **THEN** as contagens e chaves lógicas da massa permanecem estáveis e não surgem processos, tarefas, comentários ou evidências duplicadas

#### Scenario: Âncora alterada
- **WHEN** o seeder é reexecutado com outra âncora
- **THEN** somente registros demonstrativos gerenciados são reconciliados para as novas datas e registros manuais fora do manifesto permanecem intactos

#### Scenario: Falha durante a carga
- **WHEN** uma criação viola constraint ou serviço de domínio durante o seed
- **THEN** a transação é revertida, a falha é reportada sem segredo e não resta massa parcialmente carregada

### Requirement: Isolamento multi-escritório demonstrável
A fixture MUST incluir um tenant sentinela separado com pelo menos um CNPJ/rótulo repetido e MUST provar que office, membership, queries, agregados, detalhes e arquivos não atravessam tenant.

#### Scenario: Mesmo CNPJ em dois offices
- **WHEN** o cliente sintético de mesmo CNPJ existe no office `demo` e no sentinela
- **THEN** fila, processos, calendário, KPIs, busca e detalhes da sessão demo retornam somente registros cujo `office_id` pertence ao office ativo

#### Scenario: ID do tenant sentinela na URL
- **WHEN** um usuário do office `demo` tenta abrir processo, tarefa ou evidência do tenant sentinela
- **THEN** o sistema responde como não encontrado ou negado sem revelar identidade, contagem ou conteúdo do recurso

### Requirement: Ausência de dependência demo no runtime produtivo
O build e o runtime de produção MUST funcionar sem carregar seeder, manifesto, fixture, interceptador ou fallback sintético, e a carga demo MUST falhar fechada fora de `local`/`testing`.

#### Scenario: Seeder em produção
- **WHEN** a carga operacional demo é invocada em ambiente de produção
- **THEN** a execução é abortada antes de qualquer escrita e registra apenas uma mensagem sanitizada

#### Scenario: Build SPA de produção
- **WHEN** o frontend é gerado para produção
- **THEN** não existe dataset operacional demo no bundle nem condição que substitua resposta vazia/erro por conteúdo sintético

### Requirement: Fixtures e artefatos não expõem material sensível
Dados, arquivos, logs e artefatos demonstrativos MUST NOT conter PFX, senha, PEM, chave privada, Consumer Secret, token, Termo XML, XML fiscal real, cookie, `vault_object_id` ou resposta externa bruta.

#### Scenario: Evidência sintética
- **WHEN** uma evidência demonstrativa é criada e baixada por usuário autorizado
- **THEN** o arquivo contém somente conteúdo sintético com aviso de ausência de validade fiscal e o resource omite caminho e identificador de cofre

#### Scenario: Scanner de artefatos
- **WHEN** screenshots, traces, relatórios e logs dos testes são gerados
- **THEN** o scanner confirma a ausência de material sensível e dados fiscais reais antes de aceitar a suíte
