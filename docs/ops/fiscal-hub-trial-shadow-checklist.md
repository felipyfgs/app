# Checklist — trial com mocks oficiais e ledger em shadow mode

**Change:** `build-complete-fiscal-monitoring-hub` · task **16.4**  
**Atualizado:** 2026-07-15

## Objetivo

Validar o fluxo completo **sem** alterar estado fiscal produtivo e **sem** gerar cobrança real de tenant por consumo SERPRO: clientes fake/mock, contrato em `TRIAL`, ledger registrando uso em **shadow**.

## Defaults obrigatórios

```env
SERPRO_USE_FAKE_CLIENTS=true
SERPRO_SMOKE_ENABLED=false
SERPRO_SMOKE_STATUS=PENDING_OPS
SERPRO_KILL_SWITCH=false
SERPRO_USAGE_SHADOW_MODE=true
SERPRO_USAGE_COMMERCIAL_BLOCKING=false
FEATURES_GLOBAL_ENABLED=true          # só no ambiente de trial controlado
FEATURES_MUTATING_ENABLED=false
FISCAL_MUTATIONS_ENABLED=false
FISCAL_MONITORING_MUTATING_ENABLED=false
# Módulos somente leitura sob teste — ex.:
# FEATURE_SITFIS_ENABLED=true
# FEATURE_SITFIS_OFFICE_ALLOWLIST=<office_id>
```

> Shadow vence bloqueio comercial: mesmo com `COMMERCIAL_BLOCKING=true`, shadow efetivamente não bloqueia por franquia (`config/serpro_usage.php`).

## Pré-condições

- [ ] Suite filtrada 16.1 PASS no ambiente
- [ ] Migrations do hub aplicadas no Postgres do trial
- [ ] `ops:preflight-tenant-isolation` sem bloqueios
- [ ] Backup recente (`docker/ops/backup.sh`)
- [ ] Evidência comercial SERPRO: **não** exigida para mock-only; **exigida** se sair de fake clients
- [ ] Office de trial criado com assinatura `TRIAL` e orçamento/franquia baixos (mesmo em shadow, para validar alertas)

## Roteiro

### A. Plano de controle

1. [ ] Provisionar `PLATFORM_ADMIN` de teste
2. [ ] Registrar contrato ambiente `TRIAL` (material fake ou fixture) via API platform / `serpro:contract`
3. [ ] Confirmar health sanitizada sem segredos
4. [ ] Listar catálogo/preços seed presentes
5. [ ] Confirmar kill switch off; simular on/off sem perda de metadados (ver 16.8)

### B. Onboarding Autor (tenant)

1. [ ] `ADMIN` do office configura Autor (CPF/CNPJ + modo cert)
2. [ ] Upload de Termo de **fixture** válida (assinatura de teste)
3. [ ] Rejeitar Termo com signatário divergente (teste negativo)
4. [ ] Refresh de token **simulado** (`simulated=true` no audit)
5. [ ] Importar poder de procuração mínimo (ex.: PGDASD ou SITFIS conforme módulo)
6. [ ] Health tenant: status de cadeia sem detalhe de fatura global

### C. Monitoramento somente leitura

1. [ ] Habilitar **um** módulo RO na allowlist do office trial
2. [ ] Disparar execução manual ou `fiscal:dispatch-due-monitoring` em janela controlada
3. [ ] Verificar snapshot + findings + evidência com hash
4. [ ] Confirmar que status **não** vira `UP_TO_DATE` sem evidência adequada
5. [ ] Cross-tenant: segundo office **não** vê dados do trial (404)

### D. Ledger shadow

1. [ ] Após chamadas mock, listar consumo do office (`/api/v1/...` usage tenant)
2. [ ] Confirmar entradas/reservas gravadas com correlação
3. [ ] Confirmar que estouro de franquia **não** bloqueia (shadow)
4. [ ] Platform: agregado global recompute sem expor PII fiscal desnecessária
5. [ ] Registrar reconciliação **sintética** (fixture de fatura) e provar que ledger original não é reescrito

### E. Negativos obrigatórios

- [ ] `VIEWER` não configura Autor
- [ ] Tenant admin **não** acessa contrato global SERPRO
- [ ] `PLATFORM_ADMIN` **não** lista documentos/mensagens do office
- [ ] Mutação/guia retorna bloqueado (feature off)
- [ ] Logs sem bearer, PFX, senha, XML completo de Termo

## Critérios de sucesso

| Critério | Evidência |
|----------|-----------|
| Zero chamada rede real SERPRO | `SERPRO_USE_FAKE_CLIENTS=true` + ausência de egress nas métricas |
| Ledger populado | Entradas no período com `office_id` correto |
| Sem cobrança tenant | Shadow on; sem fatura interna emitida |
| Isolamento | Testes negativos manuais ou suite Platform |
| Estado fiscal produtivo intacto | Nenhuma operação mutante; ambiente ≠ produção de clientes reais |

## Registro de execução (preencher)

| Campo | Valor |
|-------|--------|
| Data | *pendente* |
| Ambiente | *ex.: local / staging* |
| Office ID | |
| Módulos exercitados | |
| Resultado | PENDING / PASS / FAIL |
| Correlação(ões) | *UUIDs sanitizados* |
| Operador | |

## Saída

Com checklist PASS, autorizar preparação do **piloto 1 office** (16.5) ainda com orçamento baixo. **Não** desligar fake clients sem 16.6 + evidência comercial.
