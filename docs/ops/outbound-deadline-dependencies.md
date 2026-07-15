# Dependências — agendamento gradual por prazo (saídas XML)

**Change:** `schedule-gradual-outbound-xml-capture-by-deadline`  
**Data:** 2026-07-15  
**Status:** preparação / flags off

## 1.1 Governador SVRS (egress)

| Item | Estado no monorepo (2026-07-15) |
|------|----------------------------------|
| `SvrsPortalEgressGovernor` | **Implementado** (`RedisSvrsPortalEgressGovernor` + `SvrsPortalEgressConfig`) |
| Budgets | exchanges/hora, exchanges/dia, inflight, intervalo global/raiz |
| Breaker coorte | aberto/half-open/canário via governador |
| Rate limiter NFC-e legado | `SvrsNfceRateLimiter` ainda usado no orquestrador NFC-e (convergência gradual) |

**Gate de dispatch:** auto-queue desta change permanece **desligado** até:

1. ciclo em modo sombra com planner lendo o governador; e  
2. allowlist de transporte aprovada; e  
3. `OUTBOUND_DEADLINE_DISPATCH_ENABLED=true` + `SHADOW_MODE=false` explícitos.

## 1.2 Inventário de scheduler / orquestração

| Componente | Papel | Retry atual |
|------------|-------|-------------|
| `OutboundXmlRecoveryOrchestrator` | KEY_DISCOVERED/XML_PENDING → SVRS NFC-e | backoff 15m/1h/6h/12h, até 5 tentativas |
| `RecoverSvrsNfceXmlJob` | job único por recovery | Horizon tries=1; re-dispatch com delay |
| `DispatchSvrsNfceXmlRecoveriesCommand` | enfileira due recoveries | respeita `auto_queue_enabled` |
| `QueryOutboundSequenceJob` / MA outbound | descoberta nNF | retry por horas de perfil |
| `SyncOfficeAutXmlDistDfeJob` | autXML DistDFe | quiet 1h / 656 / decode block |
| Import batches | XML/ZIP | assíncrono opcional |

## 1.3 SLA operacional (dia 1)

O escritório informou que o XML de saída pode ser disponibilizado **até o dia 1 do mês seguinte**.

- **Natureza:** SLA operacional interno do produto.  
- **Não é** declaração de prazo legal nacional.  
- Config default: `due_at` = 23:59:59 do dia 1 no timezone do escritório (`America/Sao_Paulo`).  
- Meta interna `target_at` = `due_at` − buffer (default 48h, mínimo 24h).

## 1.4 Ordem de aplicação das changes ativas

1. `build-ma-outbound-nfe-nfce-capture` / schema MA (`document_acquisitions`, number states) — já no código  
2. `add-svrs-nfce-outbound-xml-retrieval` — recovery tables (já no código)  
3. `add-resilient-svrs-nfe55-outbound-xml-retrieval` — governador compartilhado (planejado)  
4. **`schedule-gradual-outbound-xml-capture-by-deadline`** — prazo/capacidade (esta change)  
5. `add-office-autxml-and-bulk-xml-import` — fonte preferencial sem egress  

**Política de retry SVRS única:** a partir desta change, com flag `OUTBOUND_DEADLINE_RETRY_POLICY=true`, o orquestrador usa no máximo **2** transações e intervalo ≥24h (não coexistir com 5 tentativas rápidas).

## 1.5 Flags default off

```
OUTBOUND_DEADLINE_SCHEDULING_ENABLED=false
OUTBOUND_DEADLINE_PLANNER_ENABLED=false
OUTBOUND_DEADLINE_DISPATCH_ENABLED=false
OUTBOUND_DEADLINE_SHADOW_MODE=true
OUTBOUND_DEADLINE_RETRY_POLICY=false   # liga política 2 tentativas
SEFAZ_SVRS_NFCE_XML_AUTO_QUEUE=false   # existente
```

## 1.6 Backup

Antes de migrations em ambiente com dados fiscais reais: `docs/ops/backup-restore.md`.  
Drill local pré-schema autXML: `docs/ops/autxml-backup-drill-2026-07-15.md`.  
Novo drill opcional: `docs/ops/outbound-deadline-backup-drill-2026-07-15.md` (verify-only se stack local).
