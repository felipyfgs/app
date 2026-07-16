# Hub fiscal multi-escritório

SaaS para **escritórios contábeis**: captura de DF-e (NFS-e ADN, NF-e/CT-e DistDFe, autXML, import, canais regionais) e monitoramento fiscal via Integra Contador / SERPRO — **sem** portal do contribuinte final.

## Estrutura

```
backend/     Laravel 13 (PHP 8.4) — API, filas, cofre, jobs SEFAZ/SERPRO
frontend/    Nuxt 4 + Nuxt UI 4 — SPA do painel
docker/      Nginx, PHP-FPM, Compose
openspec/    Specs e changes (fonte de verdade de produto)
docs/adr/    Decisões de arquitetura
docs/ops/    Runbooks, gates e evidências (archive/ = one-offs)
AGENTS.md    Regras para agentes e contribuidores
CONTEXT.md   Vocabulário de domínio
mvp.md       Cobertura e não-objetivos do produto
```

## Modelo

| Área | Escolha |
|------|---------|
| Tenancy | Multi-escritório (`office_id`); tenant ativo por membership |
| Auth | Fortify + Sanctum cookie; papéis `ADMIN` / `OPERATOR` / `VIEWER` |
| Plataforma | `PLATFORM_ADMIN` separado (não herda conteúdo fiscal) |
| SERPRO | Contrato **global** da software house; tenants não recebem credenciais |
| Edge | Nginx same-origin: SPA + `/api` (sem CORS em prod) |

Detalhe e não-objetivos: [`AGENTS.md`](./AGENTS.md), [`mvp.md`](./mvp.md).

## Pré-requisitos

- Docker e Docker Compose
- OpenSSL (chaves do ambiente local)
- Node 24+ / pnpm apenas se rodar o frontend fora do Docker
- Não versionar segredos: `.env.example` → `.env`

## Configuração inicial

```bash
make setup
```

Cria `.env` e `backend/.env` (`0600` quando ausentes), gera `APP_KEY` e `VAULT_MASTER_KEY`, instala deps, migra e sobe a stack. Revise senhas antes de uso fora do host local.

## Desenvolvimento

```bash
make dev                 # volumes + HMR
make frontend-generate   # SPA estática no Nginx
make up
```

- HMR: `http://localhost:3000` (proxy same-origin Sanctum)
- SPA + API: `http://localhost:8080`

## Segurança

- `VAULT_MASTER_KEY` fora do banco e de backups comuns
- A1/PFX, tokens SERPRO e Termo **nunca** expostos na API/logs/export
- `/up` e `/horizon` restritos à rede privada do Compose

## Backup e restauração

```bash
make backup
make backup-verify BACKUP=backups/nfse-backup-AAAAMMDDTHHMMSSZ
make restore BACKUP=backups/nfse-backup-AAAAMMDDTHHMMSSZ CONFIRM_RESTORE=SIM
```

Procedimento: [`docs/ops/backup-restore.md`](./docs/ops/backup-restore.md).

## Documentação

- [AGENTS.md](./AGENTS.md) — regras de implementação e domínio
- [CONTEXT.md](./CONTEXT.md) — vocabulário
- [mvp.md](./mvp.md) — cobertura de canais
- [docs/adr/](./docs/adr/) — ADRs
- [docs/ops/](./docs/ops/) — runbooks e gates
- OpenSpec: `openspec/specs/` e `openspec/changes/`
