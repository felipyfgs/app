.PHONY: help init-env setup up dev down build logs shell-php composer-install migrate horizon-status frontend-install frontend-generate frontend-dev backup backup-verify restore

help:
	@echo "Comandos:"
	@echo "  make init-env           Cria ambientes locais e chaves aleatórias (uma vez)"
	@echo "  make setup              Prepara dependências, banco, SPA e sobe a stack"
	@echo "  make up                 Sobe stack (nginx, php, postgres, redis, horizon, scheduler)"
	@echo "  make dev                Sobe o sistema com Nuxt HMR em localhost:3000"
	@echo "  make down               Derruba stack"
	@echo "  make build              Rebuild imagens PHP"
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

dev:
	docker compose --profile dev up -d nginx php postgres redis horizon scheduler frontend-dev

down:
	docker compose down

build:
	docker compose build php horizon scheduler

logs:
	docker compose logs -f

shell-php:
	docker compose exec php sh

composer-install:
	docker compose run --rm --no-deps php composer install --no-interaction --prefer-dist

migrate:
	docker compose exec php php artisan migrate

frontend-install:
	docker compose --profile dev run --rm --no-deps frontend-dev bash -lc "corepack enable && pnpm install --frozen-lockfile"

frontend-generate:
	docker compose --profile dev run --rm --no-deps frontend-dev bash -lc "corepack enable && pnpm install --frozen-lockfile && pnpm run generate"

frontend-dev:
	docker compose --profile dev up -d nginx php postgres redis horizon scheduler frontend-dev

backup:
	./docker/ops/backup.sh "$${BACKUP_DIR:-backups}"

backup-verify:
	@test -n "$(BACKUP)" || { echo "Informe BACKUP=/caminho/nfse-backup-..." >&2; exit 2; }
	./docker/ops/restore.sh --verify-only "$(BACKUP)"

restore:
	@test -n "$(BACKUP)" || { echo "Informe BACKUP=/caminho/nfse-backup-..." >&2; exit 2; }
	@test "$(CONFIRM_RESTORE)" = "SIM" || { echo "Confirme com CONFIRM_RESTORE=SIM" >&2; exit 2; }
	./docker/ops/restore.sh --force "$(BACKUP)"
