# Runbook: revogação e rotação do certificado contratante SERPRO (global)

## Escopo

O e-CNPJ A1 do **contratante** (software house) autentica mTLS/OAuth2 do Integra Contador para **todos** os tenants. Comprometimento ou expiração afeta a plataforma inteira.

`VAULT_MASTER_KEY` **nunca** entra no backup comum do banco/cofre cifrado — custódia offline separada.

## Sinais

- `health_status=BLOCKED` no contrato ACTIVE
- Falhas OAuth 401/403 recorrentes
- `cert_valid_to` em menos de 30 dias (alerta sanitizado)
- Kill switch global SERPRO ativo em incidente

## Kill switch imediato

```bash
# Runtime (API PLATFORM_ADMIN)
POST /api/v1/platform/serpro/kill-switch
{ "active": true, "reason": "suspeita de comprometimento do e-CNPJ contratante" }

# CLI
php artisan serpro:contract kill-on --reason="comprometimento"

# Config (persistente até restart/override)
# SERPRO_KILL_SWITCH=true
```

Efeito: novas chamadas Integra bloqueadas; contratos, Termos, tokens cifrados e ledger **não** são apagados.

## Rotação do certificado (substituição transacional)

1. Obter novo PFX A1 do e-CNPJ contratante e Consumer Key/Secret vigentes.
2. Backup completo + custódia da master key offline (`make backup` / `ops:backup-run`).
3. Cadastrar e ativar com **replace** (nunca dois ACTIVE no mesmo ambiente):

```bash
php artisan serpro:contract replace \
  --env=PRODUCTION \
  --pfx=/secure/path/contratante.pfx \
  --password \
  --consumer-key=... \
  --consumer-secret=... \
  --name="Software House LTDA"
```

Ou API PLATFORM_ADMIN: `POST /api/v1/platform/serpro/contracts` com `activate=true` e `replace=true`.

4. Invalidar tokens em cache (automático na rotação de OAuth; se necessário, `block` + reativação).
5. Smoke mTLS/OAuth **fora de CI** — ver `docs/ops/serpro-mtls-oauth-smoke-pending.md`.
6. Desativar kill switch após validação:

```bash
php artisan serpro:contract kill-off --reason="rotacao_concluida"
```

## Comprometimento

1. Kill switch ON.
2. Revogar certificado na AC / SERPRO conforme processo da software house.
3. Rotacionar Consumer Secret se houver indício de vazamento.
4. `serpro:contract block --id=... --reason=compromised` no contrato antigo.
5. Substituir por novo material (passo de rotação).
6. Auditar `audit_logs` (`serpro.contract.*`, `serpro.kill_switch.*`, `serpro.oauth.token`) — payloads já redigidos.
7. Comunicar tenants apenas sobre indisponibilidade da integração (sem detalhes de credencial).

## Inspeção sanitizada (sem segredos)

```bash
php artisan serpro:contract list --env=PRODUCTION
php artisan serpro:contract health --env=PRODUCTION
GET /api/v1/platform/serpro/health
```

Respostas contêm fingerprint, vigência, máscaras de CNPJ e flags `has_pfx`/`has_oauth` — **nunca** material recuperável.

## Restore drill de objetos cifrados

1. Restaurar vault + DB a partir de backup **sem** master key no artefato.
2. Aplicar `VAULT_MASTER_KEY` correta da custódia offline.
3. `php artisan serpro:contract show --id=...` deve listar metadados.
4. Com master key errada, `SecureObjectStore::get` falha (ver teste unitário de open).

## Contatos

- Ops plataforma / PLATFORM_ADMIN
- Comercial/jurídico SERPRO (contrato software house)
- Evidência comercial: `docs/ops/serpro-integra-contador-commercial-legal-evidence.md`
