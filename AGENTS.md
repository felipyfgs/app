# Repository Guidelines

## Comunicação

Comunique-se sempre com o usuário em português do Brasil (pt-BR). Preserve identificadores, paths, comandos, APIs e logs no idioma original quando isso evitar ambiguidade.

## Project Structure & Module Organization

This is a Docker-orchestrated monorepo with no root `package.json`. The Laravel backend lives in `backend/` (`app/`, `config/`, `database/`, `tests/`). The Nuxt 4 SPA lives in `frontend/` (`app/components`, `app/pages`, `app/composables`, `tests/unit`) and uses Nuxt UI plus pnpm. Docker and ops scripts are under `docker/`; production compose is `compose.prod.yml`. OpenSpec artifacts live in `openspec/`, and reference docs belong in `docs/`.

## Build, Test, and Development Commands

- `make init-env`: create local `.env` files and generate required keys.
- `make setup`: build containers, install dependencies, generate the SPA, migrate, and start the stack.
- `make dev`: run the full stack with Nuxt HMR on port 3000.
- `make up` / `make down`: start or stop the local stack.
- `make migrate`, `make seed`, `make seed-pilot`: manage database state.
- `cd backend && php artisan test`: run Laravel PHPUnit suites.
- `cd backend && vendor/bin/pint --test`: check PHP formatting.
- `cd frontend && pnpm run test:gate`: run ESLint, Nuxt typecheck, and Vitest.
- `cd frontend && pnpm run generate`: build the static SPA.

## Coding Style & Naming Conventions

Follow Laravel PSR-4 structure: `app/Models`, `app/Http`, `app/Providers`, services in `app/Services`, contracts in `app/Contracts`, jobs in `app/Jobs`, and tests named `*Test.php`. Use Pint for PHP. Frontend follows the local Nuxt dashboard template: components `PascalCase.vue`, pages `kebab-case.vue` or `[param].vue`, composables `useXxx.ts`, API factories `createXxxApi.ts`, and utils `kebab-case.ts`. Run `make audit-names` before broad renames.

## Testing Guidelines

Backend tests are organized into `tests/Unit`, `tests/Feature`, and `tests/Architecture`; PHPUnit uses sqlite `:memory:` and fail-closed integration defaults. Frontend tests live in `frontend/tests/unit` and are named `*.test.ts` or `*.nuxt.test.ts`. Add regression tests for tenancy, fiscal integrations, feature flags, and UI contracts when touching those areas.

## Commit & Pull Request Guidelines

Git history uses Conventional Commits, often with scopes: `fix(frontend): ...`, `feat(fiscal): ...`, `docs(openspec): ...`. Keep commits small and focused. PRs should include a summary, linked issue or OpenSpec change when relevant, test evidence, screenshots for UI changes, and notes for migrations, env changes, or production-impacting flags.

## Security & Configuration Tips

Never commit `.env`, PFX files, tokens, `VAULT_MASTER_KEY`, SERPRO secrets, or full fiscal XML payloads. Use `.env.example`, `backend/.env.example`, and `frontend/.env.example` as templates. Treat SERPRO, SEFAZ, mutating fiscal flows, and production deploy/restore targets as guarded operations requiring explicit flags, allowlists, or `CONFIRM_*=SIM`.
