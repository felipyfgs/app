# Rollback — agendamento gradual por prazo

**Change:** `schedule-gradual-outbound-xml-capture-by-deadline` · task 14.10

## Desligar sem apagar estado

```bash
# backend/.env
OUTBOUND_DEADLINE_SCHEDULING_ENABLED=false
OUTBOUND_DEADLINE_PLANNER_ENABLED=false
OUTBOUND_DEADLINE_DISPATCH_ENABLED=false
OUTBOUND_DEADLINE_SHADOW_MODE=true
OUTBOUND_DEADLINE_RETRY_POLICY=false
```

Efeitos:

- Planner/dispatcher paramam de alterar agenda e de enfileirar.
- Política de 2 tentativas volta ao backoff legado da NFC-e (se flag off).
- `due_at`, `target_at`, faixas, snapshots, tentativas, aquisições e XMLs **permanecem**.
- Contingência assistida (import XML/ZIP, pacote, autXML) continua disponível.

## Não fazer

- Não restaurar automaticamente 5 retries rápidos como padrão de produto sem decisão explícita.
- Não apagar `ma_outbound_retrieval_requests` nem snapshots para “limpar” o piloto.
- Não aumentar budgets do governador SVRS como compensação de backlog.
