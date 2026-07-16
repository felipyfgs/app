# 1.7 Baseline de jornadas e contratos HTTP

**Data:** 2026-07-15  
**Inventário de rotas:** `http-routes-snapshot.md` (254 rotas de aplicação, Horizon omitido)

## Política de captura

- Registrar **método + URI + middleware + shape sanitizado** de resposta (chaves, não valores sensíveis).
- **Proibido** em artefatos: PFX, senha, PEM, private key, tokens SERPRO, Consumer Secret, Termo XML, vault ciphertext, Authorization headers.
- Payloads de fixture usam CNPJs de teste / dados demo.

## Jornadas críticas (checklist de evidência)

| # | Jornada | Rotas âncora (grupo) | Evidência baseline | Status captura |
|---|---------|----------------------|--------------------|----------------|
| J1 | Auth + CSRF + sessão | sanctum/csrf-cookie, login, logout | Feature tests Auth | Inventário OK; snapshot body → suite 9.3 |
| J2 | TOTP confirm | `POST api/v1/auth/confirm-totp` | FiscalMutation + Fortify | Inventário OK |
| J3 | Troca de escritório | `api/v1/tenants` | CurrentOffice | Inventário OK |
| J4 | Clientes CRUD + detalhe | `api/v1/clients` | Clients feature tests | Inventário OK |
| J5 | Credencial A1 (upload/status) | credentials sob clients/office | **sem** recovery route | Inventário OK |
| J6 | Catálogo documentos + download | `api/v1/documents` | documents tests | Inventário OK |
| J7 | Export | `api/v1/exports` | BuildExportZipJob | Inventário OK |
| J8 | Sync runs / capturas | `api/v1/sync-runs`, jobs | Sync tests | Inventário OK |
| J9 | Outbound recovery | `api/v1/outbound/*` | Outbound tests | Inventário OK |
| J10 | Monitoramento fiscal módulos | `api/v1/fiscal/*` | FiscalMonitoring tests | Inventário OK |
| J11 | Guias | fiscal guides | Tax guide tests | Inventário OK |
| J12 | Platform admin | `api/v1/platform/*` | Platform tests | Inventário OK |
| J13 | Consumo SERPRO tenant | office/fiscal consumption | ledger shadow | Inventário OK |
| J14 | Settings / ops health | office + ops | Backup/ops | Inventário OK |

## Como reexecutar snapshots de body (fase 9.2)

```bash
# Exemplo — apenas ambiente local/demo, cookie session
# Comparar chaves JSON e status codes; redigir headers
docker exec app-php-1 php artisan test --filter=Api
```

Contratos formais vivem nos testes Feature sob `backend/tests/Feature/{Auth,Clients,Fiscal,Outbound,Platform,Sync,...}`.  
Esta tarefa **congela o inventário de superfície**; o diff baseline×refatorado é a task 9.2.

## Achados relevantes para consolidação

1. Grande superfície `api/v1/fiscal` (73 rotas) depende de runs/snapshots — fase 7 crítica.
2. Outbound (37) e documents (14) dependem de acquisitions/cursors — fases 4–5.
3. Platform (17) deve permanecer sem vazar catálogo de custo global indevido ao tenant.
4. Nenhuma rota de “certificate recovery” deve existir antes ou depois (scan task 9.9).
