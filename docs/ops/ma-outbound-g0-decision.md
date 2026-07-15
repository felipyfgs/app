# Decisão G0 — segurança e não-ativação do canal MA outbound

**Change:** `build-ma-outbound-nfe-nfce-capture` · task 1.6  
**Data:** 2026-07-15  
**Status:** **APROVADO para implementação com flags desligadas**

## Evidências G0

| Item | Evidência |
|------|-----------|
| Backup + verify | `docs/ops/ma-outbound-backup-drill-2026-07-15.md` |
| M2M | `NO_GO_M2M` — `docs/ops/ma-outbound-sefaz-ma-status.md` |
| Parecer/mandato mutante | Pendente — mutação desligada — `docs/ops/ma-outbound-legal-mandate.md` |
| Endpoints versionados | `backend/config/sefaz.php` → chave `ma_outbound` |
| Fixtures sanitizadas | `backend/tests/fixtures/ma-outbound/` |

## Feature flags (todas `false` por padrão)

```
SEFAZ_MA_OUTBOUND_ENABLED=false
SEFAZ_MA_PROTOCOL_QUERY_ENABLED=false
SEFAZ_MA_M2M_RETRIEVAL_ENABLED=false
SEFAZ_MA_MUTATING_PROBE_ENABLED=false
```

## Kill switch

- Global: `config('sefaz.ma_outbound.kill_switch')` / cache operacional.
- Por raiz: flag no perfil / serviço de kill switch.
- Desligar **não** apaga cursores, XML, aquisições nem auditoria.
- Com kill switch ativo, apenas reconciliação de incidente fiscal já aberto é permitida (sem novas consultas/sondas).

## Allowlist

Vazia por padrão. Nenhum CNPJ é consultado na SEFAZ até inclusão explícita por ADMIN+2FA.

## Proibições explícitas (não negociáveis nesta change)

- RPA / scraping de portal humano
- Automação de Gov.br, SEFAZNET, CAPTCHA ou MFA
- Armazenamento de cookie/token de sessão de portal
- Certificado real ou CSC real no repositório/CI
- Avanço de `nNF` com base em `last_nsu`
- Uso de CSC em consulta 562 ou download oficial

## Resultado

**Nenhum acesso fiscal externo** é iniciado pelo deploy desta change com defaults. Jobs e scheduler respeitam flags e kill switch.
