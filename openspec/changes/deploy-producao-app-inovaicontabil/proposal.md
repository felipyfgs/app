## Why

O sistema precisa deixar de ser apenas uma stack local/preparada e passar a operar em produção, com HTTPS, domínio oficial e evidências objetivas de prontidão em `https://app.inovaicontabil.com.br`. O repositório já possui `compose.prod.yml`, targets `make prod-*`, Traefik/ACME, backup e readiness; falta uma change que amarre o caminho de go-live até o aceite final.

## What Changes

- Criar um contrato de go-live para publicar a aplicação em `https://app.inovaicontabil.com.br` com Docker Compose de produção, Traefik, TLS automático, Laravel em `APP_ENV=production`, Nuxt SPA estática e serviços `php`, `web`, `postgres`, `redis`, `horizon` e `scheduler`.
- Definir gates verificáveis para fonte, pré-deploy e pós-deploy usando os comandos existentes (`prod-check`, `prod-config`, `prod-build`, `prod-readiness`, `prod-up`, `prod-backup`, `prod-release-manifest`) e evidências preservadas fora de segredos.
- Exigir `.env.prod` e `/etc/fiscal-hub/backup.env` protegidos com permissão `600`, sem placeholders, com chaves fortes, SMTP configurado, backup cifrado e política inicial de RPO/RTO.
- Garantir primeiro go-live em contenção fiscal: SERPRO, SEFAZ, mutações e feature flags globais permanecem fail-closed até autorização explícita posterior.
- Carregar a massa piloto já existente em `dados/` por `PilotSeeder`, deixando `felipe@example.com` ativo como login principal com senha inicial `password`, sem importar PFX/PDF para o cofre automaticamente.
- Registrar critérios de rollback operacional: backup pré-deploy para instâncias existentes, restauração validada, tag SHA anterior quando aplicável e proibição de rollback automático de schema.
- Non-goals: habilitar SERPRO live, executar smoke faturável, ativar mutações fiscais, ativar canais SEFAZ outbound, alterar regras de tenancy/RBAC, emitir parecer jurídico, importar PFX/PDF para o cofre automaticamente ou mudar o domínio público.

## Capabilities

### New Capabilities
- `operacao-producao-dominio`: cobre os requisitos para disponibilizar, verificar e manter a stack produtiva no domínio `app.inovaicontabil.com.br` com HTTPS, contenção fiscal, backup, evidências de readiness e rollback operacional.

### Modified Capabilities
- Nenhuma capability existente terá requisitos alterados; esta change adiciona um contrato operacional novo sobre a stack de produção.

## Impact

- Infra e deploy: `compose.prod.yml`, `Makefile`, `docker/traefik/`, `docker/nginx/prod.conf`, `docker/ops/deploy.sh`, `docker/ops/prod-readiness.sh`, `docker/ops/release-manifest.sh` e scripts de backup/restore existentes.
- Ambiente produtivo: host Linux com Docker/Compose, DNS público de `app.inovaicontabil.com.br` apontando para o servidor, portas 80/443 liberadas, ACME e e-mail operacional em `ACME_EMAIL`.
- Segredos e dados: `.env.prod`, `VAULT_MASTER_KEY`, credenciais SMTP, `DB_PASSWORD`, `/etc/fiscal-hub/backup.env`, `BACKUP_PACKAGE_KEY`, volumes Docker de Postgres/Redis/vault/private storage e backups cifrados.
- Aplicação: Laravel 13/PHP 8.4, Horizon, scheduler, Sanctum same-origin, Nuxt SPA gerada no build de imagem, logs em `stderr`, healthchecks de serviços e seed piloto explícito.
- Operação: registro de evidências sanitizadas de source/predeploy/postdeploy, manifesto de release por SHA, política inicial de backup diário e restauração testável.

### Dependências entre changes

- Nível: C0.
- Bases estáveis: specs principais atuais (`das-emissao-real-only`, `fgts-esocial-runtime-real-only`, `list-filters-ux`, `pagtoweb-arrecadacao-receipt`, `schema-conventions`) e artefatos produtivos já presentes no repo.
- Depende de: nenhuma change ativa.
- Capability/contrato: `operacao-producao-dominio`.
- Marco exigido: nenhum upstream; a implementação deve partir do estado versionado atual e só consumir outras changes se elas forem arquivadas antes do deploy.
- Relação: coordenada com changes ativas que também mexam em `Makefile`, `compose.prod.yml`, `docker/ops/*`, configuração de produção, autenticação ou flags fiscais; bloqueante apenas se houver conflito direto nesses arquivos antes da execução.
- Desbloqueia: aplicação acessível em `https://app.inovaicontabil.com.br` com readiness pós-deploy aprovado, backup inicial verificável e evidências de go-live.
- Condições de paralelismo: pode avançar em paralelo com workstreams funcionais que não alterem contrato operacional de produção, não habilitem drivers fiscais reais e não exijam mudanças no mesmo domínio público.
