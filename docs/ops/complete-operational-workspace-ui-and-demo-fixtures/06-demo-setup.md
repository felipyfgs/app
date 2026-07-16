# Setup e reseed da demonstração operacional

## Pré-requisitos

- Docker Compose com serviços `php`, `postgres`, `redis`, `nginx`, `frontend-dev`
- Ambiente `APP_ENV=local` (ou `testing` em CI)
- Office `demo` e usuários criados pelo `DatabaseSeeder`

## Variáveis

| Variável | Default | Descrição |
|----------|---------|-----------|
| `DEMO_WORK_ANCHOR_DATE` | (hoje civil do office) | Data âncora `Y-m-d` para prazos/competências |
| `WORK_DEMO_ENABLED` | `true` | Switch opcional; ainda exige local/testing |
| `WORK_DEMO_OFFICE_SLUG` | `demo` | Tenant da sessão |
| `WORK_DEMO_SENTINEL_SLUG` | `demo-work-sentinel` | Office de isolamento |

## Contas demo

| E-mail | Papel | Senha |
|--------|-------|-------|
| `admin@example.com` | ADMIN (+ 2FA em seed) | `password` |
| `operador@example.com` | OPERATOR | `password` |
| `viewer@example.com` | VIEWER (somente leitura) | `password` |

## Comandos

```bash
# Recriação limpa (destrutivo no volume local)
docker compose exec php php artisan migrate:fresh --seed

# Apenas massa operacional (idempotente)
docker compose exec php php artisan db:seed --class=OperationalWorkDemoSeeder

# Âncora fixa para screenshots/testes
DEMO_WORK_ANCHOR_DATE=2026-06-15 docker compose exec -e DEMO_WORK_ANCHOR_DATE=2026-06-15 php \
  php artisan db:seed --class=OperationalWorkDemoSeeder
```

## O que o seeder cria

- 4 departamentos (Fiscal, Pessoal, Contábil, Societário)
- 6 clientes sintéticos `[DEMO]…` + cliente compartilhado com o sentinela
- 4 modelos de processo
- 7 processos com tarefas em todos os estados e riscos
- Comentários e 1 evidência textual no cofre com aviso **SEM VALIDADE FISCAL**
- Office sentinela **sem** membership dos usuários demo

## Limites e avisos

- Fail-closed fora de `local`/`testing`
- Não há validade fiscal; nomes e conteúdos são demonstrativos
- Produção **não** carrega este seeder nem fallback de dataset no SPA
- Reexecução reconcilia apenas títulos/chaves do manifesto `DEMO · …` / marker `[demo-work-fixture]`
