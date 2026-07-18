## ADDED Requirements

### Requirement: TLC-01 — Onboarding cria tenant principal atomicamente
O onboarding de uma instalação vazia SHALL criar em uma única transação o primeiro usuário, sua membership global `platform_admin`, um tenant principal ativo, sua membership `tenant_admin`, assinatura/configuração inicial, referência de tenant principal e seleção operacional padrão. O resultado SHALL permitir cadastrar o primeiro cliente sem etapa manual de criação de escritório.

#### Scenario: Primeiro onboarding concluído
- **WHEN** credenciais válidas concluem o onboarding em banco vazio
- **THEN** o usuário autenticado entra no tenant principal como `tenant_admin` e pode abrir a carteira sem visitar criação de tenant

#### Scenario: Falha intermediária
- **WHEN** qualquer criação do usuário, tenant, memberships, assinatura ou configuração falha
- **THEN** a transação reverte integralmente e não deixa identidade, tenant ou vínculo órfão

#### Scenario: Repetição concorrente
- **WHEN** duas requisições tentam concluir o primeiro onboarding simultaneamente
- **THEN** somente uma conclui e a outra recebe erro de indisponibilidade sem duplicar registros

### Requirement: TLC-02 — Referência única de tenant principal
A instalação SHALL possuir exatamente uma referência de tenant principal após onboarding. Criar administradores adicionais da plataforma MUST NOT criar novos tenants implicitamente. Alterar a referência SHALL exigir `platform_admin`, tenant-alvo ativo, confirmação explícita e auditoria.

#### Scenario: Segundo platform admin
- **WHEN** outro `platform_admin` é criado
- **THEN** ele recebe somente a autoridade global e nenhum tenant novo é criado automaticamente

#### Scenario: Troca do tenant principal
- **WHEN** um `platform_admin` confirma a escolha de outro tenant ativo como principal
- **THEN** a referência muda atomicamente, sem mover clientes, credenciais ou dados entre tenants

#### Scenario: Referência inválida
- **WHEN** o tenant indicado está suspenso, desprovisionado ou inexistente
- **THEN** a alteração é rejeitada e o tenant principal anterior permanece

### Requirement: TLC-03 — Carteira direta sempre pertence a tenant
Clientes diretos do administrador inicial SHALL pertencer ao tenant principal e SHALL seguir o mesmo isolamento de qualquer cliente tenant-scoped. Usuário, membership global ou configuração da plataforma MUST NOT possuir cliente, credencial, documento ou consulta fiscal diretamente.

#### Scenario: Cadastro do primeiro cliente
- **WHEN** o administrador inicial cadastra cliente após onboarding
- **THEN** o cliente recebe o tenant resolvido por `CurrentOffice` e não recebe vínculo direto com `platform_memberships`

#### Scenario: Cadastro sem contexto
- **WHEN** um `platform_admin` tenta cadastrar cliente no plano global sem tenant selecionado
- **THEN** o sistema bloqueia a operação e não aceita `office_id` enviado como substituto de contexto

### Requirement: TLC-04 — SERPRO, cofre e consumo usam o tenant ativo
Toda autorização, credencial, termo, procuração, consulta, histórico, bilhetagem e auditoria SERPRO SHALL ser resolvida pelo tenant ativo e pelo cliente tenant-scoped. Trocar de tenant SHALL trocar o conjunto de credenciais e atribuição de consumo. Ausência de contexto, cliente cruzado ou tenant não operacional MUST bloquear antes do transporte faturável.

#### Scenario: Consulta da carteira principal
- **WHEN** o administrador consulta a SERPRO para cliente direto no tenant principal
- **THEN** credencial, contrato, usage entry e auditoria são atribuídos ao tenant principal e ao cliente correto

#### Scenario: Troca para outro tenant
- **WHEN** o `platform_admin` seleciona outro tenant e consulta cliente desse tenant
- **THEN** nenhuma credencial, cache, termo ou orçamento do tenant principal é reutilizado

#### Scenario: Tenant suspenso antes do transporte
- **WHEN** o tenant é suspenso entre o enqueue e a tentativa de chamada externa
- **THEN** o job revalida lifecycle e encerra fail-closed sem chamada SERPRO faturável

### Requirement: TLC-05 — Criação de tenant é ação exclusiva da plataforma
Somente `platform_admin` SHALL criar tenant. A criação SHALL ser idempotente, SHALL validar identidade e slug, SHALL criar a assinatura/configuração inicial coerente e SHALL iniciar o fluxo de primeiro `tenant_admin` quando informado. Criar tenant MUST NOT habilitar SERPRO live, mutações fiscais, módulos ou canais outbound automaticamente.

#### Scenario: Tenant pendente com primeiro administrador
- **WHEN** um `platform_admin` cria tenant e informa destinatário válido para primeiro `tenant_admin`
- **THEN** o tenant nasce em `PENDING_ACTIVATION`, a membership pendente e ativação são criadas uma vez e nenhum segredo é retornado além do contrato seguro do método escolhido

#### Scenario: Repetição idempotente
- **WHEN** a mesma chave idempotente e o mesmo payload são reenviados
- **THEN** o sistema retorna o tenant original sem duplicar tenant, usuário, membership ou ativação

#### Scenario: Defaults seguros
- **WHEN** o tenant é criado ou ativado
- **THEN** feature flags, capabilities SERPRO, allowlists, kill switches e outbound permanecem em seus defaults seguros

### Requirement: TLC-06 — Administração estrutural e configuração interna são distintas
Somente `platform_admin` SHALL editar identidade estrutural, ciclo de vida, assinatura e limites globais de um tenant. `tenant_admin` SHALL editar configurações internas permitidas do tenant atual, mas MUST NOT executar ações de ciclo de vida ou acessar metadados globais de outros tenants.

#### Scenario: Tenant admin altera configuração interna
- **WHEN** um `tenant_admin` altera configuração permitida do próprio tenant
- **THEN** a operação usa `CurrentOffice`, respeita policies e não altera lifecycle ou assinatura global

#### Scenario: Tenant admin tenta suspender tenant
- **WHEN** um `tenant_admin` chama ação de suspensão, reativação ou desprovisionamento
- **THEN** o sistema responde `403` e mantém lifecycle inalterado

### Requirement: TLC-07 — Máquina de estados explícita
O lifecycle SHALL reconhecer `PENDING_ACTIVATION`, `ACTIVE`, `SUSPENDED` e `DEPROVISIONED`. As transições válidas SHALL ser `PENDING_ACTIVATION→ACTIVE`, `PENDING_ACTIVATION→DEPROVISIONED`, `ACTIVE→SUSPENDED`, `SUSPENDED→ACTIVE` e `SUSPENDED→DEPROVISIONED`. `DEPROVISIONED` SHALL ser terminal para a aplicação comum; qualquer outra transição MUST ser rejeitada sem efeito parcial.

#### Scenario: Transição inválida
- **WHEN** uma requisição tenta `ACTIVE→DEPROVISIONED` sem suspensão prévia ou `DEPROVISIONED→ACTIVE`
- **THEN** o sistema responde `409`, registra a tentativa e não altera o tenant

#### Scenario: Estado desconhecido
- **WHEN** código, migration ou payload encontra valor de lifecycle não reconhecido
- **THEN** o tenant é tratado como não operacional e o preflight bloqueia o cutover

### Requirement: TLC-08 — Suspensão interrompe operação imediatamente
Ao entrar em `SUSPENDED`, o tenant SHALL deixar de ser operacional imediatamente. O sistema SHALL limpar seleções privilegiadas e comuns incompatíveis, bloquear novas requisições tenant-scoped, impedir novos jobs e fazer jobs existentes revalidarem o estado antes de mutação ou chamada externa. Scheduler, Horizon, sincronizações, SERPRO e outbound MUST falhar de modo restritivo.

#### Scenario: Sessão no tenant suspenso
- **WHEN** o tenant corrente de uma sessão é suspenso
- **THEN** a próxima requisição limpa ou invalida o contexto e redireciona para seleção segura sem retornar dados do tenant

#### Scenario: Job já enfileirado
- **WHEN** um job de tenant suspenso é consumido após a suspensão
- **THEN** o job registra bloqueio idempotente e termina antes de ler segredo ou chamar serviço externo

#### Scenario: Acesso global preservado
- **WHEN** o tenant principal de um `platform_admin` é suspenso
- **THEN** sua autoridade global continua disponível, mas sua carteira e operações tenant permanecem bloqueadas

### Requirement: TLC-09 — Reativação é explícita e não dispara efeitos retroativos
Somente `platform_admin` SHALL reativar tenant suspenso. A reativação SHALL restaurar elegibilidade de contexto sem recriar memberships, clientes ou credenciais e MUST NOT reenfileirar automaticamente jobs, consultas ou envios perdidos durante a suspensão.

#### Scenario: Reativação válida
- **WHEN** um `platform_admin` reativa tenant suspenso com confirmação e motivo
- **THEN** o lifecycle volta a `ACTIVE`, vínculos preservados voltam a ser elegíveis e nenhum transporte externo é disparado automaticamente

#### Scenario: Assinatura ainda bloqueada
- **WHEN** o lifecycle volta a `ACTIVE`, mas a assinatura está `SUSPENDED` ou `CANCELED`
- **THEN** os guards comerciais continuam bloqueando mutações e chamadas externas

### Requirement: TLC-10 — Desprovisionamento preserva retenção
“Excluir tenant” SHALL significar transição lógica e auditada para `DEPROVISIONED`, nunca hard delete, cascade ou soft delete genérico nesta change. O tenant MUST estar suspenso; a ação SHALL exigir confirmação sensível, motivo e verificação de que ele não é a referência principal. Clientes, evidências fiscais, auditorias e referências opacas ao cofre SHALL permanecer preservados conforme políticas existentes.

#### Scenario: Tenant principal ainda referenciado
- **WHEN** alguém tenta desprovisionar o tenant principal
- **THEN** o sistema responde `409` até que outro tenant ativo seja designado como principal

#### Scenario: Desprovisionamento válido
- **WHEN** um tenant suspenso e não principal é confirmado para desprovisionamento
- **THEN** ele se torna terminal, sai de seletores operacionais e preserva seus dados sem cascade

#### Scenario: Purge solicitado
- **WHEN** uma chamada tenta apagar fisicamente tenant ou evidências como parte desta capability
- **THEN** a operação é rejeitada por estar fora de escopo e não executa deleção destrutiva

### Requirement: TLC-11 — Listagem global e seleção respeitam lifecycle
`platform_admin` SHALL listar tenants de todos os estados por filtros explícitos e receber apenas metadados globais sanitizados fora de contexto privilegiado. Seletores operacionais SHALL incluir somente tenants `ACTIVE`. Identificador usado em rota de administração global MUST NOT alterar implicitamente `CurrentOffice` nem autorizar leitura fiscal.

#### Scenario: Tenant suspenso na administração
- **WHEN** o administrador filtra tenants suspensos no plano global
- **THEN** o tenant aparece com metadados de lifecycle e assinatura, sem conteúdo fiscal, clientes, mensagens ou documentos

#### Scenario: Tenant não operacional no seletor
- **WHEN** um tenant está `PENDING_ACTIVATION`, `SUSPENDED` ou `DEPROVISIONED`
- **THEN** ele não pode ser selecionado para operação tenant e uma seleção anterior é invalidada

#### Scenario: ID administrativo não vira contexto
- **WHEN** o administrador abre `/platform/.../{tenant}` para editar metadados
- **THEN** esse identificador é somente alvo da policy global e não se torna autoridade fiscal

### Requirement: TLC-12 — Lifecycle e assinatura permanecem ortogonais
Lifecycle do tenant e status de assinatura SHALL permanecer conceitos separados. `ACTIVE` indica que o tenant pode participar da resolução de contexto; assinatura e demais guards determinam se leitura, mutação ou chamada externa específica é permitida. Mudança em um eixo MUST NOT alterar silenciosamente o outro.

#### Scenario: Tenant ativo com assinatura suspensa
- **WHEN** lifecycle está `ACTIVE` e assinatura está `SUSPENDED`
- **THEN** a identidade do tenant continua administrável, mas mutações e chamadas externas permanecem bloqueadas pelo guard comercial

#### Scenario: Tenant suspenso com assinatura ativa
- **WHEN** lifecycle está `SUSPENDED` e assinatura ainda está `ACTIVE`
- **THEN** a suspensão operacional vence e bloqueia toda operação tenant

### Requirement: TLC-13 — Transições concorrentes, idempotentes e auditadas
Criação, ativação, suspensão, reativação, troca de principal e desprovisionamento SHALL ocorrer em transação com controle de concorrência, idempotência e auditoria. Eventos e side effects SHALL ser emitidos uma única vez após commit; repetição da mesma intenção MUST retornar o estado resultante sem duplicar revogações ou jobs.

#### Scenario: Duas suspensões simultâneas
- **WHEN** duas requisições suspendem o mesmo tenant ativo
- **THEN** uma realiza a transição e a outra observa resultado idempotente ou conflito controlado, sem duplicar eventos

#### Scenario: Suspensão contra reativação
- **WHEN** suspensão e reativação concorrentes partem da mesma versão
- **THEN** somente a transição que obtém o lock válido conclui e a outra recebe conflito com estado atual

### Requirement: TLC-14 — Reconciliação de instalações existentes e rollback seguro
Antes do cutover, a migração SHALL inventariar plataforma, tenants, memberships, principal candidato, lifecycle e jobs pendentes. Instalação sem tenant SHALL criar o principal; instalação com exatamente um tenant ativo MAY designá-lo; instalação com mais de um tenant e sem referência SHALL exigir escolha explícita. Rollback durante a janela compatível SHALL retornar ao resolver legado por flag, revogar sessões e manter estruturas aditivas inertes, sem apagar dados canônicos já escritos.

#### Scenario: Vários tenants sem principal
- **WHEN** o preflight encontra mais de um tenant elegível e nenhuma referência principal
- **THEN** o cutover é bloqueado até operador autorizado escolher explicitamente o principal

#### Scenario: Nenhum platform admin ativo
- **WHEN** uma instalação existente não possui `platform_admin` ativo
- **THEN** o cutover é bloqueado e exige recuperação administrativa antes de prosseguir

#### Scenario: Rollback antes da contração
- **WHEN** métricas de divergência exigem rollback durante dual-read/dual-write
- **THEN** a flag retorna ao caminho legado, sessões são revogadas e nenhum `down()` destrutivo remove perfis, auditorias ou dados fiscais
