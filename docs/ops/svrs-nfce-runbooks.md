# Runbooks — canal SVRS NFC-e XML retrieval

**Change:** `add-svrs-nfce-outbound-xml-retrieval`

## Ativação (gates)

1. Backup/restore drill recente (`docs/ops/svrs-nfce-backup-drill-*.md`).
2. Flags master ainda **off**; deploy com defaults seguros.
3. Smoke mTLS restrito allowlisted (sem persistência automática).
4. Habilitar `SEFAZ_SVRS_NFCE_XML_RETRIEVAL_ENABLED=true` só na instância piloto.
5. Manter `SEFAZ_SVRS_NFCE_XML_AUTO_QUEUE_ENABLED=false` até critérios de piloto.
6. Opcional: `SEFAZ_SVRS_NFCE_XML_PILOT_ALLOWLIST_ONLY=true` + perfil allowlisted.

## Diagnóstico

| Sintoma | Ação |
|---------|------|
| Backlog sobe, latência alta | Ver rate limit (5s global / 30s raiz); não ampliar sem evidência |
| Muitos `REMOTE_NOT_FOUND` | Confirmar chave/ambiente; fallback upload |
| `RESPONSE_CONTRACT_CHANGED` | Breaker global abre; comparar fixture; não reabrir sem smoke |
| `AUTH_FORBIDDEN` | A1 relacionado? Validade? Kill switch se sistêmico |
| `IDENTITY_MISMATCH` / `INVALID_SIGNATURE` | Breaker por raiz; revisar XML; sem retry cego |
| Kill switch ativo | Apenas consulta/fallback; sem GET/POST |

## Circuit breaker

- Global: contrato/auth sistêmica.
- Por raiz: A1/identidade/assinatura.
- Half-open: uma chave allowlisted de prova.
- Reset: ADMIN+2FA + motivo (`POST /api/v1/outbound/svrs-nfce/breaker/reset`).

## Kill switch

- `POST /api/v1/outbound/svrs-nfce/kill-switch` com `active` + `reason`.
- **Não apaga** tentativas, objetos vault, aquisições, posições `nNF` nem documentos.

## Fallback assistido

1. Upload XML/ZIP na captura de saídas MA (pacote oficial / import).
2. Sistema marca recovery SVRS como `RESOLVED_BY_OTHER_SOURCE`.
3. UI continua oferecendo upload quando canal off/bloqueado.

## Rollback

1. `SEFAZ_SVRS_NFCE_XML_AUTO_QUEUE_ENABLED=false`
2. `SEFAZ_SVRS_NFCE_XML_RETRIEVAL_ENABLED=false`
3. Kill switch on
4. Drenar jobs Horizon não iniciados
5. Preservar estado; não apagar fiscal

## Alertas sugeridos

- Backlog > limiar ou idade do item mais antigo > 24h
- Breaker global open
- Queda abrupta da taxa de captura vs baseline piloto

## Política de tentativas orientada a prazo (2026-07-15)

Com `OUTBOUND_DEADLINE_RETRY_POLICY=true`, o orquestrador limita a **2** transações SVRS por chave com intervalo ≥**24h**, substituindo o backoff 15m/1h/6h/12h e a 5ª tentativa. Default permanece `false` (legado). Ver `docs/ops/outbound-deadline-dependencies.md`.
