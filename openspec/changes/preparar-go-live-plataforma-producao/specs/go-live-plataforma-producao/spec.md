## ADDED Requirements

### Requirement: Release produtiva é imutável e rastreável
Toda release produtiva SHALL ser construída a partir de worktree limpo e commit identificado por SHA completo. As imagens PHP e web MUST carregar tag derivada do SHA e metadados OCI de revisão, e o deploy MUST registrar manifesto sanitizado com SHA, imagens, horário e lote de migrations sem depender somente de tag mutável.

#### Scenario: Release aprovada é construída
- **WHEN** o gate de source recebe o SHA igual ao `HEAD` limpo
- **THEN** as imagens e o manifesto SHALL referenciar esse SHA de forma verificável

#### Scenario: Árvore ou SHA não corresponde
- **WHEN** há arquivo alterado/não rastreado ou o SHA informado difere do commit corrente
- **THEN** o gate MUST falhar antes do build/deploy

### Requirement: Readiness possui fases fail-closed e evidência sanitizada
O sistema SHALL oferecer fases `source`, `predeploy` e `postdeploy` com saída não zero para todo requisito obrigatório ausente. Cada execução MUST produzir evidência JSON fora do repositório, com permissão restrita e allowlist de estados, hashes e referências opacas; segredos, contatos, payloads fiscais e valores completos de ambiente MUST NOT aparecer em stdout, log ou evidência.

#### Scenario: Todos os checks obrigatórios passam
- **WHEN** uma fase conclui com configuração e runtime válidos
- **THEN** o comando SHALL retornar sucesso e gravar os checks aprovados com release e timestamp

#### Scenario: Placeholder ou segredo é encontrado
- **WHEN** um campo obrigatório usa placeholder ou a saída contém valor classificado como sensível
- **THEN** o gate MUST falhar e MUST NOT persistir o valor detectado

### Requirement: Configuração e perímetro produtivos são validados antes do deploy
O predeploy MUST exigir `.env.prod` e configuração host de backup com modo `600`, chaves distintas e fortes, SMTP/ACME válidos, referências operacionais, RPO/RTO, capacidade mínima e Compose válido. A stack dev MUST estar desligada, somente SSH/HTTP/HTTPS poderão permanecer publicamente expostos pelo produto e o DNS do domínio MUST resolver para o host que solicitará o certificado.

#### Scenario: Host está pronto
- **WHEN** arquivos, modos, recursos, DNS, portas e contenção atendem ao contrato
- **THEN** o predeploy SHALL autorizar a etapa de build/deploy sem imprimir configuração sensível

#### Scenario: Stack dev está exposta
- **WHEN** serviços do projeto escutam publicamente em `3000`, `8080`, PostgreSQL ou Redis
- **THEN** o predeploy MUST bloquear o go-live

### Requirement: Deploy protege schema e estado persistente
Uma instância com schema ou dados existentes MUST receber backup v3 cifrado e verificado antes de qualquer migration. Instalação realmente vazia MAY pular esse backup somente com confirmação fresh distinta e prova de banco/vault/storage vazios. Falha de migration, health ou smoke MUST manter web, PHP, Horizon e scheduler indisponíveis até rollback ou correção explícita.

#### Scenario: Upgrade possui dados
- **WHEN** o deploy detecta estado persistente
- **THEN** ele MUST registrar backup pré-deploy verificado antes de parar processos e migrar

#### Scenario: Volume existe mas estado não pode ser classificado
- **WHEN** o deploy não consegue provar instalação vazia nem produzir backup compatível
- **THEN** ele MUST falhar fechado e encaminhar ao procedimento de backup/recuperação offline

#### Scenario: Migration falha
- **WHEN** a nova imagem falha ao aplicar schema ou atingir health
- **THEN** o tráfego MUST permanecer fechado e a imagem anterior MUST NOT ser iniciada automaticamente contra schema incerto

### Requirement: Backup produtivo é cifrado, externo e restaurável
Produção MUST executar backup periódico pelo host incluindo PostgreSQL, vault e storage privado em pacote cifrado autenticado por chave separada de `VAULT_MASTER_KEY`. O aceite MUST comprovar pacote recente, verificação, replicação off-site e restore drill real coerentes com RPO/RTO; o restore smoke de CI MUST NOT substituir essa evidência operacional.

#### Scenario: Backup periódico conclui
- **WHEN** o job host executa com os três componentes disponíveis
- **THEN** ele SHALL produzir somente o pacote cifrado v3, manifesto/checksum sanitizados e referência de replicação

#### Scenario: Cópia existe mas não foi restaurada
- **WHEN** há pacote/checksum sem restore drill real dentro da política
- **THEN** o readiness pós-deploy MUST permanecer bloqueado

### Requirement: Runtime interno comprova serviços sem expor endpoint operacional
Um comando global SHALL verificar ambiente production, debug false, banco, migrations, Redis, Horizon, heartbeat do scheduler, storage/vault, mailer, onboarding e contenção fiscal. Ele MUST executar sem `Office`, `office_id` ou chamada externa fiscal, e Nginx MUST continuar negando acesso público a `/up`, Horizon e qualquer readiness interno.

#### Scenario: Serviços internos estão saudáveis
- **WHEN** o orquestrador executa o comando dentro do container PHP
- **THEN** recebe somente estados sanitizados de todos os componentes obrigatórios

#### Scenario: Scheduler parou
- **WHEN** o heartbeat excede a janela aceita
- **THEN** o comando MUST falhar mesmo que PHP e Redis respondam

#### Scenario: Visitante tenta ler readiness
- **WHEN** uma requisição pública acessa `/up`, `/horizon` ou rota operacional reservada
- **THEN** Nginx MUST negar sem revelar o estado interno

### Requirement: Bootstrap inicial abre e fecha uma janela controlada
Uma instalação nova MAY habilitar temporariamente o onboarding somente com confirmação explícita, base vazia, HTTPS e token forte. Após criar o primeiro administrador e Office, o aceite MUST exigir flag desabilitada, token ausente e status público indisponível; excluir usuários posteriormente MUST NOT reabrir a janela.

#### Scenario: Primeiro bootstrap é autorizado
- **WHEN** operador confirma instalação fresh e onboarding em base estruturalmente vazia
- **THEN** o deploy SHALL permitir a janela temporária sem habilitar integração fiscal

#### Scenario: Bootstrap foi concluído
- **WHEN** o primeiro administrador e Office já existem
- **THEN** o postdeploy MUST falhar enquanto flag/token permanecerem configurados ou o onboarding estiver disponível

### Requirement: Primeiro go-live mantém contenção fiscal
O aceite inicial MUST exigir flags globais e mutantes desligadas, contexto privilegiado desligado, fake clients desligados, kill switch SERPRO ligado, nenhum driver SERPRO real e canais SEFAZ/autXML desligados. Readiness, deploy, health, smoke HTTP e smoke SMTP MUST NOT chamar NFS-e, SEFAZ ou qualquer rota `/Apoiar`, `/Monitorar`, `/Consultar`, `/Emitir` ou `/Declarar`.

#### Scenario: Configuração contida é publicada
- **WHEN** a plataforma passa pelo primeiro go-live
- **THEN** painel, autenticação e administração SHALL funcionar sem promover capability fiscal

#### Scenario: Driver real ou canal fiscal está ativo
- **WHEN** o pre/postdeploy encontra qualquer capacidade externa fora da allowlist vazia do primeiro go-live
- **THEN** o aceite MUST ser bloqueado antes de qualquer smoke

### Requirement: HTTPS, e-mail e observabilidade possuem aceite operacional
O postdeploy MUST comprovar certificado/hostname, redirect HTTP, HSTS, SPA/API, bloqueio de superfícies internas, envio e recebimento SMTP sem dado fiscal, coleta consultável de logs, uptime e alertas para recursos, containers, Horizon, scheduler e backup. O aceite SHALL registrar on-call, escalonamento, RPO/RTO e referências opacas das provas sem versionar contato ou credencial real.

#### Scenario: Smoke automático é seguro
- **WHEN** o postdeploy verifica o domínio e o runtime
- **THEN** somente checks HTTP locais/gratuitos e comandos internos SHALL ser executados

#### Scenario: Provedor externo não foi comprovado
- **WHEN** SMTP, coleta de logs, alerta ou on-call possui apenas configuração sem evidência de funcionamento
- **THEN** a aplicação MAY permanecer implantada, mas o aceite produtivo MUST continuar pendente

### Requirement: Rollback preserva evidência e exige restauração compatível
O runbook SHALL manter tags anteriores, backup pré-deploy e evidências até o fim da janela de rollback. Quando schema puder ter mudado, retornar à release anterior MUST exigir restauração conjunta de PostgreSQL, vault e storage privado e nova execução completa de readiness; volumes, ACME e chaves MUST NOT ser apagados como mecanismo de recuperação.

#### Scenario: Deploy novo precisa ser revertido
- **WHEN** health ou verificação funcional falha após migration
- **THEN** o operador SHALL manter tráfego fechado, restaurar o conjunto compatível e validar a release anterior antes de reabrir
