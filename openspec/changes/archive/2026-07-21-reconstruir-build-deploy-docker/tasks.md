## 1. Scaffold Dockerfiles

- [x] 1.1 Criar `infra/docker/php/` com Dockerfile multi-stage (`base|dev|prod`), `conf.d/opcache.ini`, `conf.d/php.ini` e `entrypoint.sh` (PHP 8.4-fpm, extensões pgsql/redis/pcntl/bcmath/intl/zip/opcache/curl/sodium)
- [x] 1.2 Criar `infra/docker/nginx/` com Dockerfile (`spa-builder|dev|prod`), `conf/dev.conf` e `conf/prod.conf` (SPA `try_files` + fastcgi `/api` `/sanctum` `/up` `/storage`)
- [x] 1.3 Criar `infra/docker/frontend/` com Dockerfile Node/pnpm e `entrypoint.sh` para HMR (`dev|install|generate|prepare`)
- [x] 1.4 Criar `infra/docker/traefik/` mínimo (init de `acme.json` + imagem Traefik oficial em prod)

## 2. Compose desenvolvimento

- [x] 2.1 Reescrever `docker-compose.yml`: builds novos, bind `apps/api`/`apps/web`, services nginx/php/horizon/scheduler/postgres/redis, `frontend-dev` no profile `dev`
- [x] 2.2 Remover serviços `mei`/`mei-worker` e volumes/artefatos MEI do Compose de dev
- [x] 2.3 Validar `docker compose -f docker-compose.yml config`

## 3. Compose produção

- [x] 3.1 Reescrever `docker-compose.prod.yml`: imagens imutáveis tagueadas, Traefik+socket-proxy+acme-init, web/php/horizon/scheduler/postgres/redis, sem bind de código
- [x] 3.2 Remover `mei`/`mei-worker` e `x-mei` do Compose de prod
- [x] 3.3 Validar `docker compose -f docker-compose.prod.yml` config com `.env.example`

## 4. Makefile

- [x] 4.1 Reescrever alvos `init-env`, `build`, `up`, `dev`, `down`, `migrate`, `composer-install`, `frontend-generate`, `logs`, `shell-php`, `help`
- [x] 4.2 Apontar `PROD_COMPOSE` para `-f docker-compose.prod.yml -p fiscal-hub`; implementar `prod-config`, `prod-up`/`prod-down` com `CONFIRM_*=SIM`
- [x] 4.3 Stubar `backup`/`restore`/`prod-backup`/`prod-restore`/etc. com mensagem de indisponibilidade (sem chamar `infra/docker/ops/*`)

## 5. CI

- [x] 5.1 Atualizar `.github/workflows/ci.yml`: remover checks de scripts ops deletados; validar Compose dev+prod; manter OpenSpec validate; tornar build de imagens gate opcional ou alinhado ao que for viável

## 6. Validação local

- [x] 6.1 `docker compose build php nginx frontend-dev` conclui
- [x] 6.2 `make down && make up` → `/up` 200; volumes DB preservados
- [x] 6.3 `make dev` → HMR em `:3000` com Sanctum proxy; containers healthy
