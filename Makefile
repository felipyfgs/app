.PHONY: help init-env setup up dev down build logs shell-php composer-install migrate horizon-status frontend-prepare-generated frontend-install frontend-generate frontend-dev backup backup-verify restore prod-check prod-config prod-build prod-up prod-down prod-backup prod-backup-verify prod-restore prod-restore-smoke

LOCAL_UID := $(shell id -u)
LOCAL_GID := $(shell id -g)
PROD_ENV ?= .env.prod
PROD_COMPOSE = PROD_ENV_FILE=$(PROD_ENV) docker compose --env-file $(PROD_ENV) -f compose.prod.yml -p fiscal-hub

help:
	@echo "Comandos:"
	@echo "  make init-env           Cria ambientes locais e chaves aleatórias (uma vez)"
	@echo "  make setup              Prepara dependências, banco, SPA e sobe a stack"
	@echo "  make up                 Sobe stack (nginx, php, postgres, redis, horizon, scheduler)"
	@echo "  make dev                Sobe o sistema com Nuxt HMR em localhost:3000"
	@echo "  make down               Derruba stack"
	@echo "  make build              Rebuild imagens da aplicação"
	@echo "  make logs               Segue logs"
	@echo "  make shell-php          Shell no container PHP"
	@echo "  make composer-install   Instala dependências do backend"
	@echo "  make migrate            Roda migrations"
	@echo "  make frontend-install   Instala deps do frontend"
	@echo "  make frontend-generate  Gera SPA estática"
	@echo "  make frontend-dev       Sobe Nuxt dev (perfil dev)"
	@echo "  make backup             Cria backup verificável em BACKUP_DIR (padrão: backups)"
	@echo "  make backup-verify BACKUP=...  Confere manifesto e checksums"
	@echo "  make restore BACKUP=... CONFIRM_RESTORE=SIM  Restauração destrutiva"
	@echo "  make prod-config        Valida a stack de produção e o ambiente"
	@echo "  make prod-build         Constrói as imagens imutáveis de produção"
	@echo "  make prod-up CONFIRM_PROD=SIM  Migra e sobe produção com HTTPS"
	@echo "  make prod-down CONFIRM_PROD_DOWN=SIM  Para produção sem apagar volumes"
	@echo "  make prod-backup        Backup banco + vault + private_storage"
	@echo "  make prod-backup-verify BACKUP=...  Verifica backup de produção"
	@echo "  make prod-restore BACKUP=... CONFIRM_PROD_RESTORE=SIM  Restaura produção"
	@echo "  make prod-restore-smoke Executa restore destrutivo em projeto isolado"

init-env:
	@set -eu; umask 077; \
	command -v openssl >/dev/null 2>&1 || { echo "openssl é obrigatório" >&2; exit 1; }; \
	if [ ! -e .env ]; then install -m 600 .env.example .env; fi; \
	if [ ! -e backend/.env ]; then install -m 600 backend/.env.example backend/.env; fi; \
	chmod 600 .env backend/.env; \
	if grep -q '^APP_KEY=$$' backend/.env; then \
		key=$$(openssl rand -base64 32); sed -i "s|^APP_KEY=$$|APP_KEY=base64:$$key|" backend/.env; \
	fi; \
	if grep -q '^VAULT_MASTER_KEY=$$' backend/.env; then \
		key=$$(openssl rand -base64 32); sed -i "s|^VAULT_MASTER_KEY=$$|VAULT_MASTER_KEY=$$key|" backend/.env; \
	fi

setup: init-env build composer-install frontend-generate
	docker compose up -d postgres redis
	docker compose run --rm php php artisan migrate --force
	docker compose up -d nginx php horizon scheduler

up:
	docker compose up -d nginx php postgres redis horizon scheduler

dev: frontend-prepare-generated
	LOCAL_UID=$(LOCAL_UID) LOCAL_GID=$(LOCAL_GID) docker compose --profile dev up -d nginx php postgres redis horizon scheduler frontend-dev

down:
	docker compose down

build:
	docker compose --profile dev build nginx php frontend-dev

logs:
	docker compose logs -f

shell-php:
	docker compose exec php sh

composer-install:
	docker compose run --rm --no-deps php composer install --no-interaction --prefer-dist

migrate:
	docker compose exec php php artisan migrate

frontend-prepare-generated:
	@set -eu; \
	for path in frontend/.nuxt frontend/.output frontend/test-results frontend/playwright-report; do \
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

frontend-dev: frontend-prepare-generated
	LOCAL_UID=$(LOCAL_UID) LOCAL_GID=$(LOCAL_GID) docker compose --profile dev up -d nginx php postgres redis horizon scheduler frontend-dev

backup:
	./docker/ops/backup.sh "$${BACKUP_DIR:-backups}"

backup-verify:
	@test -n "$(BACKUP)" || { echo "Informe BACKUP=/caminho/nfse-backup-..." >&2; exit 2; }
	./docker/ops/restore.sh --verify-only "$(BACKUP)"

restore:
	@test -n "$(BACKUP)" || { echo "Informe BACKUP=/caminho/nfse-backup-..." >&2; exit 2; }
	@test "$(CONFIRM_RESTORE)" = "SIM" || { echo "Confirme com CONFIRM_RESTORE=SIM" >&2; exit 2; }
	./docker/ops/restore.sh --force "$(BACKUP)"

prod-check:
	@test -f "$(PROD_ENV)" || { echo "Crie $(PROD_ENV) a partir de .env.prod.example" >&2; exit 2; }
	@test "$$(stat -c '%a' "$(PROD_ENV)")" = "600" || { echo "$(PROD_ENV) deve usar permissão 600" >&2; exit 2; }
	@grep -Eq '^ACME_EMAIL=[^[:space:]@]+@[^[:space:]@]+$$' "$(PROD_ENV)" || { echo "Defina ACME_EMAIL válido em $(PROD_ENV)" >&2; exit 2; }
	@grep -Eq '^APP_KEY=base64:.{32,}$$' "$(PROD_ENV)" || { echo "Defina APP_KEY válida em $(PROD_ENV)" >&2; exit 2; }
	@grep -Eq '^VAULT_MASTER_KEY=.{32,}$$' "$(PROD_ENV)" || { echo "Defina VAULT_MASTER_KEY em $(PROD_ENV)" >&2; exit 2; }
	@grep -Eq '^DB_PASSWORD=.{16,}$$' "$(PROD_ENV)" || { echo "DB_PASSWORD deve ter ao menos 16 caracteres" >&2; exit 2; }
	@grep -qx 'LOG_CHANNEL=stderr' "$(PROD_ENV)" || { echo "Produção exige LOG_CHANNEL=stderr" >&2; exit 2; }
	@grep -qx 'MAIL_MAILER=smtp' "$(PROD_ENV)" || { echo "Produção exige MAIL_MAILER=smtp" >&2; exit 2; }
	@grep -Eq '^MAIL_HOST=.+$$' "$(PROD_ENV)" || { echo "Defina MAIL_HOST" >&2; exit 2; }
	@grep -Eq '^MAIL_PORT=[0-9]+$$' "$(PROD_ENV)" || { echo "Defina MAIL_PORT" >&2; exit 2; }
	@grep -Eq '^MAIL_SCHEME=(smtp|smtps)$$' "$(PROD_ENV)" || { echo "Defina MAIL_SCHEME como smtp ou smtps conforme o provedor" >&2; exit 2; }
	@grep -Eq '^MAIL_USERNAME=.+$$' "$(PROD_ENV)" || { echo "Defina MAIL_USERNAME" >&2; exit 2; }
	@grep -Eq '^MAIL_PASSWORD=.+$$' "$(PROD_ENV)" || { echo "Defina MAIL_PASSWORD" >&2; exit 2; }
	@grep -Eq '^MAIL_FROM_ADDRESS=[^[:space:]@]+@[^[:space:]@]+$$' "$(PROD_ENV)" || { echo "Defina MAIL_FROM_ADDRESS válido" >&2; exit 2; }
	@! grep -Eq 'substitua|example\.com|change-me' "$(PROD_ENV)" || { echo "Remova placeholders de $(PROD_ENV)" >&2; exit 2; }
	@# Contenção SERPRO: drivers reais e fake clients não podem estar ON em produção
	@! grep -Eq '^SERPRO_USE_FAKE_CLIENTS=true$$' "$(PROD_ENV)" || { echo "Produção exige SERPRO_USE_FAKE_CLIENTS=false (ou ausente com default fail-closed no app)" >&2; exit 2; }
	@! grep -Eq '^SERPRO_CAPABILITY_[A-Z_]+=real$$' "$(PROD_ENV)" || { echo "Drivers SERPRO_CAPABILITY_*=real bloqueados no prod-check até go-live controlado" >&2; exit 2; }
	@! grep -Eq '^FEATURES_GLOBAL_ENABLED=true$$' "$(PROD_ENV)" || { echo "FEATURES_GLOBAL_ENABLED deve permanecer false até promoção explícita" >&2; exit 2; }
	@! grep -Eq '^FEATURES_MUTATING_ENABLED=true$$' "$(PROD_ENV)" || { echo "FEATURES_MUTATING_ENABLED deve permanecer false" >&2; exit 2; }
	@grep -Eq '^SERPRO_KILL_SWITCH=' "$(PROD_ENV)" || { echo "Defina SERPRO_KILL_SWITCH em $(PROD_ENV) (recomendado true na contenção)" >&2; exit 2; }
	@! grep -Eq '^SERPRO_SMOKE_ENABLED=true$$' "$(PROD_ENV)" || { echo "SERPRO_SMOKE_ENABLED deve permanecer false até smoke controlado" >&2; exit 2; }
	@# Se a stack prod estiver no ar, executa prod-check de credencial exposta (fail se não RETIRED/COMPROMISED)
	@if $(PROD_COMPOSE) ps --status running --services 2>/dev/null | grep -qx 'php'; then \
		echo "==> serpro:prod-check (credenciais expostas / egress faturável)"; \
		$(PROD_COMPOSE) exec -T php php artisan serpro:prod-check --serpro-env=PRODUCTION || exit 2; \
	else \
		echo "Aviso: stack prod php offline — serpro:prod-check de runtime adiado (checks de .env ok)"; \
	fi

prod-config: prod-check
	$(PROD_COMPOSE) config --quiet

prod-build: prod-check
	$(PROD_COMPOSE) build acme-init traefik web php
	$(PROD_COMPOSE) pull socket-proxy

prod-up: prod-check
	@test "$(CONFIRM_PROD)" = "SIM" || { echo "Confirme com CONFIRM_PROD=SIM" >&2; exit 2; }
	$(PROD_COMPOSE) build acme-init traefik web php
	$(PROD_COMPOSE) pull socket-proxy
	STACK_ENV=$(PROD_ENV) ./docker/ops/deploy.sh

prod-down:
	@test -f "$(PROD_ENV)" || { echo "Arquivo $(PROD_ENV) ausente" >&2; exit 2; }
	@test "$(CONFIRM_PROD_DOWN)" = "SIM" || { echo "Confirme com CONFIRM_PROD_DOWN=SIM" >&2; exit 2; }
	$(PROD_COMPOSE) down

prod-backup: prod-check
	@test -n "$$BACKUP_PACKAGE_KEY" || { echo "Defina BACKUP_PACKAGE_KEY (base64 de 32 bytes) para backup de produção cifrado (v3)" >&2; exit 2; }
	STACK_FILE=compose.prod.yml STACK_ENV=$(PROD_ENV) STACK_PROJECT=fiscal-hub \
		BACKUP_PACKAGE_KEY="$$BACKUP_PACKAGE_KEY" \
		./docker/ops/backup.sh "$${BACKUP_DIR:-backups}"

prod-backup-verify:
	@test -n "$(BACKUP)" || { echo "Informe BACKUP=/caminho/nfse-backup-..." >&2; exit 2; }
	./docker/ops/restore.sh --verify-only "$(BACKUP)"

prod-restore:
	@test -f "$(PROD_ENV)" || { echo "Arquivo $(PROD_ENV) ausente" >&2; exit 2; }
	@test -n "$(BACKUP)" || { echo "Informe BACKUP=/caminho/nfse-backup-..." >&2; exit 2; }
	@test "$(CONFIRM_PROD_RESTORE)" = "SIM" || { echo "Confirme com CONFIRM_PROD_RESTORE=SIM" >&2; exit 2; }
	STACK_FILE=compose.prod.yml STACK_ENV=$(PROD_ENV) STACK_PROJECT=fiscal-hub STACK_WEB=web \
		./docker/ops/restore.sh --force "$(BACKUP)"

prod-restore-smoke:
	./docker/ops/restore-smoke.sh
