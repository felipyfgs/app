# Runbooks — autXML escritório + import em massa

**Change:** `add-office-autxml-and-bulk-xml-import`

## ERP / autXML (NF-e 55)

1. Cadastre **Identidade fiscal do escritório** (Admin) com CNPJ completo do contador.
2. Envie A1 do escritório (ADMIN+2FA); sem rota de download.
3. Habilite flags só após piloto: `SEFAZ_AUTXML_DISTDFE_ENABLED`, allowlist de office.
4. Oriente o ERP a incluir o CNPJ do escritório em `<autXML>` **antes** de autorizar — **sem retroatividade**.
5. Cobertura: **somente NF-e 55**. NFC-e 65: import XML/ZIP ou canal SVRS/MA.

## Novo cliente sem retroatividade

- Enrollment `PENDING` → stream DistDFe precisa `activated_at` (1ª consulta quiet) → só então **Confirmar**.
- Documentos anteriores à inclusão no autXML **não** voltam via DistDFe; use **Importar saídas**.

## Consumidor concorrente (mesma raiz)

- Um capturador DistDFe por A1. Se outro ERP/robô usa o mesmo CNPJ-base, risco de **656**.
- Ver `autxml-external-distnsu-consumers.md`. Gate fechado se ownership não resolvido.

## cStat 656

1. Cursor vai a `BLOCKED`; `next_sync_at` ≥ 1h (`sefaz.autxml.circuit_breaker_hours`).
2. **Não** forçar retry antecipado.
3. Revisar rps/concorrência e consumidor externo.
4. Kill switch: `SEFAZ_AUTXML_KILL_SWITCH=true`.

## Quarentena

- Emitente sem vínculo, tag autXML divergente, bytes divergentes, evento órfão.
- Fora do catálogo/export/download comum.
- Resolução auditada (ADMIN); sem aceitar conflito cegamente.

## Import histórico

1. Documentos → Importar saídas (≤50 arq / 20 MiB).
2. Histórico: `/docs/import-batches?batch=…` · CSV por lote · retry UNMATCHED.
3. Notas antes de eventos no mesmo ZIP; dual ISSUER/TAKER quando ambos clientes do office.

## A1 do escritório

- Alertas 30/7/1 (serviço de credencial).
- Expiração bloqueia só cursores autXML, não A1 de clientes.

## Kill switch / rollback

```
SEFAZ_AUTXML_DISTDFE_ENABLED=false
SEFAZ_AUTXML_KILL_SWITCH=true
IMPORT_ASYNC_BATCHES_ENABLED=false
```

Preserva cursores NSU, documentos, quarentena e spool até retenção.
