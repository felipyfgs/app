# Smoke produtivo somente leitura — Contratante, Autor, Contribuinte

**Change:** `build-complete-fiscal-monitoring-hub` · task **16.6**  
**Atualizado:** 2026-07-15  
**Status:** **PENDING_OPS** (sem certificado contratante real provisionado neste ambiente)

Relacionado: `docs/ops/serpro-mtls-oauth-smoke-pending.md`

## Princípios

- **Fora de CI.** Nenhum PFX de produção/homologação no pipeline.
- **Somente leitura.** Mutações e emissão de guias permanecem OFF.
- Evidência: correlação, HTTP status, latência, health sanitizada — **nunca** token, PFX, senha, Consumer Secret ou Termo completo.
- Gate comercial: ver `serpro-integra-contador-commercial-legal-evidence.md` antes de produção faturável.

## Pré-requisitos

1. [ ] Aceite piloto 16.5 assinado (ou trial interno homologação)
2. [ ] Evidência comercial mínima para o ambiente alvo
3. [ ] Contrato cadastrado e ACTIVE (`HOMOLOGATION` preferível na 1ª vez)
4. [ ] PFX e-CNPJ contratante + Consumer Key/Secret em mídia controlada
5. [ ] Autor + Termo + ao menos um poder no office piloto
6. [ ] Contribuinte com procuração válida para o serviço do smoke
7. [ ] `SERPRO_USE_FAKE_CLIENTS=false` **somente** no ambiente de smoke
8. [ ] Kill switch OFF; breaker closed; shadow ledger ON
9. [ ] `SERPRO_SMOKE_ENABLED=true` apenas durante a janela
10. [ ] Backup verificado nas últimas 24h

## Config de janela

```env
SERPRO_USE_FAKE_CLIENTS=false
SERPRO_SMOKE_ENABLED=true
SERPRO_SMOKE_STATUS=IN_PROGRESS
SERPRO_KILL_SWITCH=false
SERPRO_USAGE_SHADOW_MODE=true
FEATURES_MUTATING_ENABLED=false
FISCAL_MUTATIONS_ENABLED=false
# Um módulo RO na allowlist do office piloto
```

## Procedimento

### Passo 1 — Contratante (mTLS + OAuth)

```bash
php artisan serpro:contract list --env=HOMOLOGATION
php artisan serpro:contract health --env=HOMOLOGATION

# Renovação OAuth (exemplo — ajustar ao serviço real)
php artisan tinker --execute="
\$c = app(\\App\\Services\\Serpro\\SerproContractService::class)
  ->activeFor(\\App\\Enums\\SerproEnvironment::Homologation);
\$t = app(\\App\\Contracts\\SerproContractAuthenticator::class)->authenticate(\$c);
echo json_encode(\$t->toSanitizedArray());
"
```

**Sucesso:** HTTP 200 no token endpoint; health `OK`; resposta sanitizada; nada de bearer em log.

### Passo 2 — Autor (cadeia + token de procurador)

1. Na API tenant, GET health de autorização SERPRO do office.
2. Confirmar Termo válido (sha256 conhecido) e status que permita chamada.
3. Se modo A3: completar desafio interativo documentado no produto.
4. Se modo A1 gerenciado: garantir que material não é exposto na response.
5. Refresh de token do Autor; registrar `expires_at` e correlação de audit `serpro.authorization.token_refresh`.

**Sucesso:** cadeia elegível; erros de Termo/signatário **bloqueiam** antes da chamada.

### Passo 3 — Contribuinte (consulta RO de baixo custo)

1. Escolher **uma** operação de catálogo read-only de menor custo (ex.: consulta Sitfis ou serviço acordado no aceite).
2. Preflight de elegibilidade (poder + Termo + contrato + kill switch).
3. Executar **uma** chamada monitorada (job ou ação manual ADMIN).
4. Persistir evidência + snapshot; anotar `correlation_id`.
5. Confirmar ledger shadow com 1 entrada atribuída ao `office_id`.

**Sucesso:** resposta tratada; evidência com hash; sem mutação; tenant isola dados.

### Passo 4 — Encerramento da janela

```env
SERPRO_SMOKE_ENABLED=false
SERPRO_SMOKE_STATUS=PASS   # ou FAIL
# Reavaliar SERPRO_USE_FAKE_CLIENTS conforme ambiente
```

Amostrar `storage/logs` e `audit_logs` por padrões de segredo (token JWT-like, `BEGIN CERTIFICATE`, senha).

## Registro sanitizado (preencher)

| Campo | Valor |
|-------|--------|
| Data/hora (UTC) | |
| Ambiente | HOMOLOGATION / PRODUCTION |
| Operador | |
| Contrato env / id interno | |
| Fingerprint cert (já exposto na health) | |
| Office / contribuintes (máscara) | |
| Serviço/operação RO | |
| Correlação(ões) | |
| HTTP status token | |
| HTTP status chamada RO | |
| Latência p50/p95 (ms) | |
| Ledger shadow entry ids | |
| Segredos em log? | NÃO / SIM (incidente) |
| Resultado | **PENDING_OPS** / PASS / FAIL |

### Estado atual (dev local 2026-07-15)

| Campo | Valor |
|-------|--------|
| Resultado | **PENDING_OPS** |
| Motivo | Sem PFX contratante real; `SERPRO_USE_FAKE_CLIENTS` permanece o default seguro |
| Próximo passo | Provisionar cert em ambiente controlado e executar passos 1–4 |

## Falhas e resposta

| Sintoma | Ação |
|---------|------|
| 401/403 OAuth | Kill switch; revisar cert/CK/CS; runbook rotação |
| Termo rejeitado | Não forçar; corrigir Autor/destinatário/validade |
| Procuração insuficiente | Bloquear serviço; atualizar poderes |
| Timeout / TLS | Cadeia ICP-Brasil; hostname; TLS≥1.2 |
| Resposta inesperada | Manter evidência; **não** mutar; abrir ticket com correlação |
| Suspeita de vazamento | Kill switch; rotacionar; incidente segurança |

## Proibições

- Executar smoke em CI.
- Usar contribuinte real sem mandato.
- Habilitar mutações “só para testar”.
- Anexar response completa com dados fiscais sensíveis em issue pública.
