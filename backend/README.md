# Backend (Laravel)

API, filas, cofre e integrações fiscais do monorepo.

- **Stack:** Laravel 13 / PHP 8.4, PostgreSQL, Redis/Horizon
- **Domínio e regras:** ver [`../AGENTS.md`](../AGENTS.md) e [`../README.md`](../README.md)
- **Setup:** `make setup` / `make dev` na raiz do monorepo
- **Testes:** `docker compose exec php php artisan test`

Não use este diretório como app Laravel isolado: o edge (Nginx), SPA e Compose ficam na raiz.
