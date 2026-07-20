## ADDED Requirements

### Requirement: Compose de desenvolvimento com hot reload

O sistema MUST fornecer um `docker-compose.yml` de desenvolvimento que orquestre nginx, php, horizon, scheduler, postgres e redis, com bind mounts de `apps/api` e (no profile `dev`) `apps/web`, de modo que alterações de código no host sejam visíveis sem rebuild de imagem.

#### Scenario: make dev sobe HMR

- **WHEN** o operador executa `make dev`
- **THEN** sobem nginx, php, horizon, scheduler, postgres, redis e frontend-dev
- **AND** o Nuxt HMR fica disponível na porta configurada (padrão 3000)
- **AND** a API Laravel responde via nginx na porta configurada (padrão 8080)

#### Scenario: edição PHP sem recreate

- **WHEN** um arquivo em `apps/api` é alterado no host com o stack `dev` no ar
- **THEN** a próxima requisição HTTP ao PHP-FPM reflete a alteração
- **AND** o container `php` NÃO precisa ser recriado para isso

#### Scenario: make up sem HMR

- **WHEN** o operador executa `make up`
- **THEN** sobem os serviços de backend sem o profile `dev` (sem frontend-dev)
- **AND** o nginx pode servir a SPA estática gerada quando presente

### Requirement: Compose de produção imutável com Traefik

O sistema MUST fornecer `docker-compose.prod.yml` com imagens imutáveis (sem bind de código da aplicação), Traefik com TLS, e projeto Compose distinto do desenvolvimento.

#### Scenario: prod-up migra sem tráfego de app

- **WHEN** o operador executa `make prod-up`
- **THEN** a migration roda com `postgres`, `redis` e `php` no ar
- **AND** `web`, `horizon` e `scheduler` só sobem depois da migration

#### Scenario: prod-check bloqueia flags fiscais perigosas

- **WHEN** o `.env` de produção tem `SERPRO_KILL_SWITCH=false` ou `FEATURES_MUTATING_ENABLED=true`
- **THEN** `make prod-config` / `make prod-up` falha antes de subir a stack

#### Scenario: imagens sem código montado

- **WHEN** a stack de produção está no ar
- **THEN** os containers `php` e `web` NÃO montam `./apps/api` nem `./apps/web` como código da aplicação
- **AND** apenas volumes de dados (postgres, redis, vault, private_storage, acme) são persistentes

#### Scenario: same-origin SPA e API

- **WHEN** um cliente acessa o host de produção via HTTPS
- **THEN** a SPA estática e as rotas `/api`, `/sanctum` e `/up` são servidas no mesmo origin através do nginx atrás do Traefik

### Requirement: Ausência do sidecar MEI Python

O Compose de desenvolvimento e o de produção MUST NOT definir serviços `mei` ou `mei-worker`. O monitoramento MEI MUST permanecer responsabilidade da API Laravel via SERPRO/Integra Contador.

#### Scenario: compose config sem mei

- **WHEN** se valida `docker compose config` (dev ou prod)
- **THEN** a configuração resultante NÃO lista serviços nomeados `mei` ou `mei-worker`

### Requirement: Makefile com mundos separados

O `Makefile` MUST expor alvos distintos para desenvolvimento e produção, e MUST stubar alvos que dependiam de scripts de ops removidos com mensagem clara de indisponibilidade.

#### Scenario: help lista alvos essenciais

- **WHEN** o operador executa `make help`
- **THEN** aparecem alvos locais `dev`, `up`, `down`, `build`, `migrate` e alvos `prod-up` / `prod-down` / `prod-config`

#### Scenario: ops stub

- **WHEN** o operador executa um alvo de backup/restore/deploy que dependia de scripts removidos
- **THEN** o comando falha com mensagem indicando indisponibilidade até a fase de ops
- **AND** NÃO invoca paths sob `infra/docker/ops/` inexistentes

### Requirement: CI valida Compose sem scripts ops

O workflow de CI MUST validar a sintaxe/config dos Compose files de desenvolvimento e produção e MUST NOT exigir scripts de ops deletados.

#### Scenario: CI compose config

- **WHEN** o job de infrastructure do CI roda
- **THEN** `docker compose -f docker-compose.yml config` sucede
- **AND** `docker compose -f docker-compose.prod.yml` com env de exemplo sucede em `config`
- **AND** o job NÃO falha por ausência de `infra/docker/ops/*`
