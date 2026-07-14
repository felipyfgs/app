# NFS-e ADN Capture

Sistema interno do escritório contábil para captura e organização de XMLs de NFS-e via API oficial do ADN.

## Estrutura

```
backend/     Laravel 13 (PHP 8.4) — API, filas, cofre
frontend/    Nuxt 4 + Nuxt UI 4 — SPA
docker/      Nginx, PHP-FPM
openspec/    Especificações e mudanças
docs/adr/    Decisões de arquitetura
CONTEXT.md   Vocabulário de domínio
```

## Pré-requisitos

- Docker e Docker Compose
- OpenSSL (somente para gerar as chaves do ambiente local)
- Node 24+ / pnpm apenas se optar por executar o frontend fora do Docker
- Não versionar segredos: use `.env.example` → `.env`

## Configuração inicial

```bash
make setup
```

O comando cria `.env` e `backend/.env` com permissão `0600` quando ausentes, gera `APP_KEY` e `VAULT_MASTER_KEY` distintas, instala as dependências, executa as migrations e sobe a stack. Revise as senhas antes de qualquer uso fora do computador local. Em dev, nginx (`APP_PORT`, default 8080) e Nuxt (`FRONTEND_DEV_PORT`, default 3000) publicam em `0.0.0.0` para acesso remoto por IP:porta; Postgres e Redis permanecem em `127.0.0.1`. Ajuste `APP_URL`, `SESSION_DOMAIN` e `SANCTUM_STATEFUL_DOMAINS` no `backend/.env` ao mudar o host/IP.

## Desenvolvimento

```bash
# Desenvolvimento com volumes locais e hot reload
make dev

# ou gere a SPA estática servida pelo Nginx
make frontend-generate
make up
```

Para executar Nuxt diretamente no host, use Node 24 e pnpm 11.9:

```bash
cd frontend
corepack enable
pnpm install --frozen-lockfile
pnpm run dev
```

- Desenvolvimento com HMR: `http://localhost:3000` (proxy same-origin do `nuxt-auth-sanctum`).
- SPA estática: `http://localhost:8080` (Nginx same-origin: SPA + `/api` + `/sanctum`).

Os diretórios `frontend/` e `backend/` são montados nos contêineres. Alterações no Nuxt recarregam o navegador e alterações no Laravel passam a valer na próxima requisição, sem rebuild.

## Segurança

- `VAULT_MASTER_KEY` fora do banco e de backups comuns
- Volumes privados: vault e storage da aplicação
- Certificados A1 nunca são recuperáveis pela API
- `/up` e `/horizon` restritos ao host/rede privada do Compose; o Horizon também depende da autorização Laravel

## Backup e restauração

```bash
make backup
make backup-verify BACKUP=backups/nfse-backup-AAAAMMDDTHHMMSSZ
make restore BACKUP=backups/nfse-backup-AAAAMMDDTHHMMSSZ CONFIRM_RESTORE=SIM
```

O backup usa diretamente os volumes do Compose, pausa escritas, aplica permissões restritivas e gera checksums. A chave mestra nunca é incluída. Consulte o procedimento completo antes de restaurar dados reais.

## Documentação

- [CONTEXT.md](./CONTEXT.md)
- [docs/adr/](./docs/adr/)
- [docs/ops/backup-restore.md](./docs/ops/backup-restore.md)
- OpenSpec: `openspec/changes/build-nfse-adn-capture-system/`
