## ADDED Requirements

### Requirement: Domínio produtivo com HTTPS
O sistema MUST disponibilizar a aplicação em `https://app.inovaicontabil.com.br` usando a stack produtiva, com Traefik como borda pública, certificado TLS válido e redirecionamento permanente de HTTP para HTTPS.

#### Scenario: Acesso público seguro ao domínio
- **WHEN** `app.inovaicontabil.com.br` aponta para o host de produção e a stack produtiva está em execução
- **THEN** uma requisição HTTPS para `https://app.inovaicontabil.com.br` retorna a aplicação sem erro TLS e uma requisição HTTP para o mesmo domínio é redirecionada para HTTPS

#### Scenario: Domínio diferente não é aceito como origem produtiva
- **WHEN** uma validação de produção encontra `APP_URL`, `SESSION_DOMAIN`, `SANCTUM_STATEFUL_DOMAINS` ou regra Traefik apontando para domínio diferente de `app.inovaicontabil.com.br`
- **THEN** o gate de produção falha antes do deploy público ser aceito

### Requirement: Ambiente produtivo validado antes do deploy
O sistema MUST validar `.env.prod`, `/etc/fiscal-hub/backup.env`, configuração Compose e pré-condições de host antes de construir ou subir a produção.

#### Scenario: Ambiente com segredo ausente ou placeholder
- **WHEN** `.env.prod` ou `/etc/fiscal-hub/backup.env` contém placeholder, segredo fraco, permissão diferente de `600`, SMTP ausente ou `BACKUP_PACKAGE_KEY` inválida
- **THEN** o gate de produção falha sem imprimir valores secretos

#### Scenario: Configuração produtiva consistente
- **WHEN** `.env.prod`, backup env, Docker Compose e pré-condições de host estão válidos
- **THEN** `prod-check`, `prod-config` e readiness de pré-deploy concluem com evidências sanitizadas

### Requirement: Contenção fiscal no primeiro go-live
O sistema MUST manter SERPRO, SEFAZ, mutações fiscais e feature flags globais em modo fail-closed durante o primeiro go-live do domínio.

#### Scenario: Driver fiscal real habilitado indevidamente
- **WHEN** a configuração produtiva define `SERPRO_CAPABILITY_*=real`, `SERPRO_KILL_SWITCH=false`, `SERPRO_SMOKE_ENABLED=true`, `FEATURES_GLOBAL_ENABLED=true`, `FEATURES_MUTATING_ENABLED=true` ou canal SEFAZ habilitado
- **THEN** o gate de produção bloqueia o deploy até a configuração voltar para contenção

#### Scenario: Domínio publicado sem egress fiscal real
- **WHEN** o deploy público é aceito no primeiro go-live
- **THEN** as capacidades fiscais reais permanecem desabilitadas e nenhuma operação fiscal mutável ou faturável é executada como parte do aceite

### Requirement: Release imutável e serviços saudáveis
O sistema MUST construir e executar imagens produtivas rastreáveis por `RELEASE_SHA`, aplicar migrations de forma controlada e manter os serviços essenciais saudáveis.

#### Scenario: Build rastreável por SHA
- **WHEN** `prod-build` constrói as imagens PHP e web para um `RELEASE_SHA`
- **THEN** as labels OCI das imagens correspondem ao SHA informado e a tag mutável `prod` aponta para essas imagens sem remover tags anteriores

#### Scenario: Stack produtiva sobe com serviços essenciais
- **WHEN** `prod-up` conclui com confirmação explícita
- **THEN** `web`, `php`, `postgres`, `redis`, `horizon`, `scheduler`, `traefik` e `socket-proxy` ficam em execução saudável ou o deploy falha com orientação operacional

### Requirement: Readiness e evidências de go-live
O sistema MUST produzir evidências sanitizadas de readiness nas fases source, predeploy e postdeploy, cobrindo fonte, configuração, domínio público, TLS, serviços internos e release.

#### Scenario: Evidências de readiness completas
- **WHEN** os gates `source`, `predeploy` e `postdeploy` são executados para o go-live
- **THEN** arquivos de evidência sanitizados são gravados no diretório operacional configurado e podem ser revisados sem revelar segredos

#### Scenario: Pós-deploy público falha
- **WHEN** o domínio, TLS, redirecionamento, aplicação, serviço interno ou release SHA não passa no readiness postdeploy
- **THEN** o go-live permanece não aceito e a execução aponta a etapa que deve ser corrigida

### Requirement: Massa piloto autorizada
O sistema MUST permitir carregar a massa piloto de `dados/` em produção somente por autorização explícita, mantendo PFX, senhas de PFX e PDFs fora do cofre e fora dos logs.

#### Scenario: Seed piloto em produção sem autorização explícita
- **WHEN** `PilotSeeder` é executado em produção sem `PILOT_SEED_ALLOW_PRODUCTION=true`
- **THEN** o seed falha antes de criar ou alterar usuários, offices, clientes ou estabelecimentos

#### Scenario: Seed piloto autorizado no go-live
- **WHEN** `PilotSeeder` é executado em produção com `PILOT_SEED_ALLOW_PRODUCTION=true`
- **THEN** o usuário `felipe@example.com` fica ativo como `PLATFORM_ADMIN`, a senha inicial documentada é `password`, o office `plataforma` representa Felipe MEI e o office `contador` contém o cliente AUTO CENTER

#### Scenario: Materiais sensíveis da pasta dados
- **WHEN** a massa piloto é carregada
- **THEN** PFX, senhas de PFX e PDFs permanecem em `dados/` e não são importados automaticamente para banco, cofre, logs ou manifesto

### Requirement: Backup e rollback operacional
O sistema MUST exigir caminho de backup e restauração verificável para instâncias existentes e registrar backup inicial cifrado após o deploy aceito.

#### Scenario: Instância existente sem backup pré-deploy
- **WHEN** o deploy detecta volumes produtivos existentes e `PRE_DEPLOY_BACKUP` não aponta para um backup verificado
- **THEN** o deploy é bloqueado antes de migrations ou troca de tráfego

#### Scenario: Backup inicial pós-go-live
- **WHEN** o postdeploy é aceito
- **THEN** um backup produtivo cifrado é criado, verificado e registrado como evidência operacional sem incluir `VAULT_MASTER_KEY` nem `BACKUP_PACKAGE_KEY`

#### Scenario: Rollback exige restauração controlada
- **WHEN** uma falha pós-deploy exige retorno operacional
- **THEN** o operador usa backup verificado e tag SHA anterior quando aplicável, sem executar rollback automático destrutivo de schema
