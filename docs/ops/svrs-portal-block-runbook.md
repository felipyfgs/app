# Runbook — bloqueio portal SVRS (egress compartilhado)

**Change:** `add-resilient-svrs-nfe55-outbound-xml-retrieval` · task 12.10  
**Atualizado:** 2026-07-15

## Sintomas

- `SVRS_EGRESS_BLOCKED_MULTIPLE_QUERIES` em tentativas (mesmo HTTP 200)
- Saúde da coorte: `state=open`, `cause=SVRS_EGRESS_BLOCKED_MULTIPLE_QUERIES`
- Backlog NF-e/NFC-e em retry/fallback assistido

## Ações imediatas

1. **Não** repetir chamadas, trocar IP, proxy, A1 ou raiz para “testar”.
2. Confirmar kill switch se necessário:
   - `SEFAZ_SVRS_NFCE_XML_KILL_SWITCH=true`
   - `SEFAZ_SVRS_NFE55_XML_KILL_SWITCH=true` (quando existir tráfego 55)
3. Master e auto-queue devem permanecer **off** fora de piloto formal.
4. Encaminhar pendências para **XML/ZIP** ou **pacote oficial MA**.

## Cooldown e canário

| Patamar | Cooldown padrão |
|---------|-----------------|
| 0 (primeiro bloqueio) | 24 h |
| 1 | 48 h |
| 2 | 96 h |
| 3+ | 168 h (máx.) |

- Após `next_probe_at`: **um** canário allowlisted.
- ADMIN pode **estender** cooldown e escolher canário; **não** antecipar prova.
- `Retry-After` HTTP maior que o local prevalece.

## Contato

Registrar pedido formal: `docs/ops/svrs-nfessl-formal-inquiry.md`.

## Rollback

Desligar retrieval/auto-queue; kill switch; breaker administrativo; filas drenam sem nova rede. Preserva hashes, tentativas, documentos.
