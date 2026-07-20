.PHONY: help init-env setup up dev down build logs shell migrate seed \
	composer-install frontend-generate \
	prod-config prod-build prod-up prod-down \
	backup restore prod-backup prod-restore \
	frontend-prepare-generated frontend-install frontend-dev seed-dev seed-pilot \
	prod-check backup-verify prod-backup-verify prod-restore-smoke prod-readiness prod-release-manifest

LOCAL_UID := $(shell id -u)
LOCAL_GID := $(shell id -g)
PROD_ENV ?= .env
RELEASE_SHA ?= $(shell git rev-parse HEAD 2>/dev/null)
RELEASE_TAG ?= sha-$(shell printf '%s' '$(RELEASE_SHA)' | cut -c1-12)
BUILD_DATE ?= $(shell date -u +%Y-%m-%dT%H:%M:%SZ)
PROD_COMPOSE = RELEASE_SHA=$(RELEASE_SHA) RELEASE_TAG=$(RELEASE_TAG) BUILD_DATE=$(BUILD_DATE) PROD_ENV_FILE=$(PROD_ENV) docker compose --env-file $(PROD_ENV) -f docker-compose.prod.yml -p fiscal-hub

OPS_UNAVAILABLE = @echo "Indisponível até a fase de ops." >&2; exit 2

# -----------------------------------------------------------------------------
# Dia a dia — o que você realmente usa
# -----------------------------------------------------------------------------

help:
	@echo "Local"
	@echo "  make setup              Primeira vez: env + build + deps + migrate + up"
	@echo "  make dev                Stack + Nuxt HMR (:3000) e API (:8080)"
	@echo "  make up                 Stack sem HMR (SPA estática no nginx)"
	@echo "  make down               Para a stack local"
	@echo "  make build              Rebuild imagens locais"
	@echo "  make logs               Logs (follow)"
	@echo "  make shell              Shell no PHP"
	@echo "  make migrate            Migrations"
	@echo "  make seed               Seed de desenvolvimento"
	@echo ""
	@echo "Produção"
	@echo "  make prod-config        Valida .env + compose prod"
	@echo "  make prod-build         Build imagens imutáveis (tag SHA)"
	@echo "  make prod-up            Sobe produção (HTTPS)"
	@echo "  make prod-down          Para produção (mantém volumes)"

init-env:
	@set -eu; umask 077; \
	command -v openssl >/dev/null 2>&1 || { echo "openssl é obrigatório" >&2; exit 1; }; \
	if [ ! -e .env ]; then install -m 600 .env.example .env; fi; \
	if [ ! -e apps/api/.env ]; then install -m 600 apps/api/.env.example apps/api/.env; fi; \
	chmod 600 .env apps/api/.env; \
	if grep -q '^APP_KEY=$$' apps/api/.env; then \
		key=$$(openssl rand -base64 32); sed -i "s|^APP_KEY=$$|APP_KEY=base64:$$key|" apps/api/.env; \
	fi; \
	if grep -q '^VAULT_MASTER_KEY=$$' apps/api/.env; then \
		key=$$(openssl rand -base64 32); sed -i "s|^VAULT_MASTER_KEY=$$|VAULT_MASTER_KEY=$$key|" apps/api/.env; \
	fi

setup: init-env build composer-install frontend-generate
	docker compose up -d postgres redis
	docker compose run --rm php php artisan migrate --force
	docker compose up -d nginx php horizon scheduler

up:
	docker compose up -d --remove-orphans nginx php postgres redis horizon scheduler

dev: frontend-prepare-generated
	LOCAL_UID=$(LOCAL_UID) LOCAL_GID=$(LOCAL_GID) docker compose --profile dev up -d --remove-orphans nginx php postgres redis horizon scheduler frontend-dev

down:
	docker compose --profile dev down --remove-orphans

build:
	docker compose --profile dev build nginx php frontend-dev

logs:
	docker compose logs -f

shell shell-php:
	docker compose exec php sh

migrate:
	docker compose exec php php artisan migrate

seed seed-dev:
	docker compose exec php php artisan db:seed --force

# -----------------------------------------------------------------------------
# Produção
# -----------------------------------------------------------------------------

prod-check:
	@test -f "$(PROD_ENV)" || { echo "Crie $(PROD_ENV) a partir de .env.example" >&2; exit 2; }
	@test "$$(stat -c '%a' "$(PROD_ENV)")" = "600" || { echo "$(PROD_ENV) deve usar permissão 600" >&2; exit 2; }
	@grep -Eq '^ACME_EMAIL=[^[:space:]@]+@[^[:space:]@]+$$' "$(PROD_ENV)" || { echo "Defina ACME_EMAIL válido em $(PROD_ENV)" >&2; exit 2; }
	@grep -Eq '^APP_KEY=base64:.{32,}$$' "$(PROD_ENV)" || { echo "Defina APP_KEY válida em $(PROD_ENV)" >&2; exit 2; }
	@grep -Eq '^VAULT_MASTER_KEY=.{32,}$$' "$(PROD_ENV)" || { echo "Defina VAULT_MASTER_KEY em $(PROD_ENV)" >&2; exit 2; }
	@grep -Eq '^MEI_AUTOMATION_HMAC_SECRET=.{32,}$$' "$(PROD_ENV)" || { echo "Defina MEI_AUTOMATION_HMAC_SECRET em $(PROD_ENV)" >&2; exit 2; }
	@grep -Eq '^DB_PASSWORD=.{16,}$$' "$(PROD_ENV)" || { echo "DB_PASSWORD deve ter ao menos 16 caracteres" >&2; exit 2; }
	@grep -qx 'LOG_CHANNEL=stderr' "$(PROD_ENV)" || { echo "Produção exige LOG_CHANNEL=stderr" >&2; exit 2; }
	@grep -qx 'MAIL_MAILER=smtp' "$(PROD_ENV)" || { echo "Produção exige MAIL_MAILER=smtp" >&2; exit 2; }
	@grep -Eq '^MAIL_HOST=.+$$' "$(PROD_ENV)" || { echo "Defina MAIL_HOST" >&2; exit 2; }
	@grep -Eq '^MAIL_FROM_ADDRESS=[^[:space:]@]+@[^[:space:]@]+$$' "$(PROD_ENV)" || { echo "Defina MAIL_FROM_ADDRESS válido" >&2; exit 2; }
	@! grep -Eqi 'substitua|example\.com|change-me|changeme|placeholder' "$(PROD_ENV)" || { echo "Remova placeholders de $(PROD_ENV)" >&2; exit 2; }
	@! grep -Eq '^SERPRO_USE_FAKE_CLIENTS=true$$' "$(PROD_ENV)" || { echo "Produção exige SERPRO_USE_FAKE_CLIENTS=false (ou ausente)" >&2; exit 2; }
	@! grep -Eq '^SERPRO_CAPABILITY_[A-Z_]+=real$$' "$(PROD_ENV)" || { echo "SERPRO_CAPABILITY_*=real bloqueado até go-live controlado" >&2; exit 2; }
	@! grep -Eq '^FEATURES_GLOBAL_ENABLED=true$$' "$(PROD_ENV)" || { echo "FEATURES_GLOBAL_ENABLED deve permanecer false até promoção explícita" >&2; exit 2; }
	@! grep -Eq '^FEATURES_MUTATING_ENABLED=true$$' "$(PROD_ENV)" || { echo "FEATURES_MUTATING_ENABLED deve permanecer false" >&2; exit 2; }
	@grep -Eq '^SERPRO_KILL_SWITCH=' "$(PROD_ENV)" || { echo "Defina SERPRO_KILL_SWITCH em $(PROD_ENV)" >&2; exit 2; }
	@! grep -Eq '^SERPRO_KILL_SWITCH=false$$' "$(PROD_ENV)" || { echo "Go-live inicial exige SERPRO_KILL_SWITCH=true" >&2; exit 2; }
	@! grep -Eq '^SERPRO_SMOKE_ENABLED=true$$' "$(PROD_ENV)" || { echo "SERPRO_SMOKE_ENABLED deve permanecer false" >&2; exit 2; }
	@! grep -Eq '^MEI_AUTOMATION_ENABLED=true$$' "$(PROD_ENV)" || { echo "MEI_AUTOMATION_ENABLED deve permanecer false (sem sidecar)" >&2; exit 2; }
	@! grep -Eq '^MEI_AUTOMATION_LIVE_EGRESS_ENABLED=true$$' "$(PROD_ENV)" || { echo "MEI_AUTOMATION_LIVE_EGRESS_ENABLED deve permanecer false" >&2; exit 2; }

prod-config: prod-check
	$(PROD_COMPOSE) config --quiet

prod-build: prod-check
	@test -n "$(RELEASE_SHA)" || { echo "RELEASE_SHA vazio" >&2; exit 2; }
	@echo "==> build RELEASE_SHA=$(RELEASE_SHA) RELEASE_TAG=$(RELEASE_TAG)"
	$(PROD_COMPOSE) build acme-init traefik web php
	$(PROD_COMPOSE) pull socket-proxy
	docker tag fiscal-hub-php:$(RELEASE_TAG) fiscal-hub-php:prod
	docker tag fiscal-hub-web:$(RELEASE_TAG) fiscal-hub-web:prod
	@php_rev=$$(docker image inspect fiscal-hub-php:$(RELEASE_TAG) --format '{{index .Config.Labels "org.opencontainers.image.revision"}}'); \
	web_rev=$$(docker image inspect fiscal-hub-web:$(RELEASE_TAG) --format '{{index .Config.Labels "org.opencontainers.image.revision"}}'); \
	test "$$php_rev" = "$(RELEASE_SHA)" || { echo "OCI revision PHP=$$php_rev != $(RELEASE_SHA)" >&2; exit 2; }; \
	test "$$web_rev" = "$(RELEASE_SHA)" || { echo "OCI revision web=$$web_rev != $(RELEASE_SHA)" >&2; exit 2; }; \
	echo "OCI revision ok em php e web"

# Ordem fail-closed: build → dados/php → migrate (sem web/workers) → edge + app.
prod-up: prod-check
	@test -n "$(RELEASE_SHA)" || { echo "RELEASE_SHA vazio" >&2; exit 2; }
	$(PROD_COMPOSE) build acme-init traefik web php
	$(PROD_COMPOSE) pull socket-proxy
	docker tag fiscal-hub-php:$(RELEASE_TAG) fiscal-hub-php:prod
	docker tag fiscal-hub-web:$(RELEASE_TAG) fiscal-hub-web:prod
	$(PROD_COMPOSE) up -d acme-init socket-proxy traefik postgres redis php
	$(PROD_COMPOSE) stop web horizon scheduler 2>/dev/null || true
	$(PROD_COMPOSE) run --rm --no-deps php php artisan migrate --force
	$(PROD_COMPOSE) up -d web horizon scheduler

prod-down:
	@test -f "$(PROD_ENV)" || { echo "Arquivo $(PROD_ENV) ausente" >&2; exit 2; }
	$(PROD_COMPOSE) down

# -----------------------------------------------------------------------------
# Internos / raros (não aparecem no help)
# -----------------------------------------------------------------------------

composer-install:
	docker compose run --rm --no-deps php composer install --no-interaction --prefer-dist

frontend-prepare-generated:
	@set -eu; \
	for path in apps/web/.nuxt apps/web/.output apps/web/test-results apps/web/playwright-report; do \
		if [ -e "$$path" ] && ! git check-ignore -q "$$path"; then \
			echo "Recusando ajustar artefato não ignorado: $$path" >&2; exit 1; \
		fi; \
	done
	@LOCAL_UID=$(LOCAL_UID) LOCAL_GID=$(LOCAL_GID) \
		docker compose --profile dev run --rm --no-deps frontend-dev prepare

frontend-install: frontend-prepare-generated
	LOCAL_UID=$(LOCAL_UID) LOCAL_GID=$(LOCAL_GID) \
		docker compose --profile dev run --rm --no-deps frontend-dev install

frontend-generate: frontend-prepare-generated
	LOCAL_UID=$(LOCAL_UID) LOCAL_GID=$(LOCAL_GID) \
		docker compose --profile dev run --rm --no-deps frontend-dev generate

frontend-dev: dev

seed-pilot:
	docker compose exec php php artisan db:seed --class=PilotSeeder --force

backup backup-verify restore \
prod-backup prod-backup-verify prod-restore prod-restore-smoke \
prod-readiness prod-release-manifest:
	$(OPS_UNAVAILABLE)
