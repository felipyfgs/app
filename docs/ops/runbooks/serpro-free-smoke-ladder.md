# Runbook — escada de smoke gratuito (FREE_SMOKE_OK)

Ordem obrigatória. **Não pular etapas.** **Não usar `/Consultar` como smoke.**

```
CONFIGURED → CREDENTIALS_ROTATED → TLS_OK → OAUTH_OK
  → TERM_LOCAL_VALID → TERM_SERPRO_ACCEPTED → POWERS_VERIFIED
  → FREE_SMOKE_OK
  ⛔ CANARY_READY (aprovação separada + teto unitário)
```

## 12.6 — Office real + Termo local

1. Selecionar **na aplicação** um Office real não-demo (nunca gravar id/CNPJ no OpenSpec).
2. Confirmar autor/consentimento e papéis (`ADMIN` do office).
3. Gerar/assinar/validar Termo pelo fluxo aprovado (A1 gerenciado ou baseline externo).
4. Evidência offline: `php artisan serpro:readiness --office=<ID> --json`.

## 12.7 — `/Apoiar` somente

1. Enviar Termo **apenas** via Autentica Procurador (`/Apoiar` / `ENVIOXMLASSINADO81`).
2. Aceitar 200 (novo) ou 304 (cache/ETag).
3. Confirmar token do procurador + Expires + ETag no vault (metadados na API sanitizada).
4. Zero segredo em logs/Redis/resposta JSON (`has_token`, nunca o valor).
5. Gate: `TERM_SERPRO_ACCEPTED`.

## 12.8 — Poderes + `/Monitorar`

1. Confirmar poderes/autorização por evidência **offline/aprovada** (não `OBTERPROCURACAO41` faturável no free smoke).
2. Canário `/Monitorar` (Eventos de Atualização) dentro dos limites oficiais versionados (PF/PJ/dia, lote ≤ 1000).
3. Respeitar 429 (sem retry agressivo).
4. **Proibido:** `/Consultar`, `/Emitir`, `/Declarar`.

## Promover FREE_SMOKE_OK

Após a escada e revisão de ledger (somente rotas não bilhetadas):

```bash
php artisan serpro:go-live free-smoke-promote \
  --serpro-env=PRODUCTION \
  --office=<ID_NA_APP> \
  --confirm-ladder \
  --termo-local \
  --apoiar-ok \
  --powers-verified \
  --monitorar-ok \
  --zero-billable \
  --kill-switch-tested \
  --notes='free-smoke-window-complete'
```

Isso **não** promove `CANARY_READY`.

## Evidência

Template: `evidence/12-free-smoke-ladder.md` — marcar checkboxes sem identidades.
