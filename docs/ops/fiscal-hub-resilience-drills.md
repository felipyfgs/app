# Runbook — drills de resiliência (suspensão, kill switch, breaker, rotação, Termo, procuração, rollback)

**Change:** `build-complete-fiscal-monitoring-hub` · task **16.8**  
**Atualizado:** 2026-07-15

## Objetivo

Provar que controles de emergência **interrompem novas chamadas e mutações** sem apagar evidências, ledger, cursores ou audit. Executar em staging ou janela controlada **antes** de GA.

## Pré-drill

- [ ] Backup + verify-only  
- [ ] Office de teste com cadeia mock **ou** homologação  
- [ ] Observador de filas Horizon e `audit_logs`  
- [ ] `correlation_id` de uma execução saudável de baseline  
- [ ] Dois operadores (executor + verificador)

## Matriz de drills

| ID | Cenário | Esperado | Evidência a capturar |
|----|---------|----------|----------------------|
| D1 | Suspensão de assinatura do office | Novas chamadas Integra/jobs do office bloqueados; leitura histórica ok | Estado `SUSPENDED`; tentativas BLOCKED |
| D2 | Kill switch SERPRO global | Nenhuma nova chamada contrato; metadados intactos | `serpro:contract kill-on`; health bloqueada |
| D3 | Kill switch features hub | Módulos RO e mutantes off | `FEATURES_KILL_SWITCH=true` |
| D4 | Kill switch mutações | Consultas RO ok; mutações bloqueadas | `FISCAL_MUTATIONS_KILL_SWITCH` / `FEATURES_MUTATING_KILL_SWITCH` |
| D5 | Circuit breaker por solução | Após N falhas, open; half-open com probe limitado | Config `SERPRO_BREAKER_*`; métricas open_seconds |
| D6 | Rotação token OAuth contratante | Novo token; antigo inválido; sem vazamento | Audit `serpro.oauth` sanitizado |
| D7 | Rotação/replace cert global | Um ACTIVE; antigo SUPERSEDED/BLOCKED | `serpro:contract replace` |
| D8 | Termo expirado / inválido | Cadeia inelegível; sem chamada externa | Upload rejeitado ou elegibilidade false |
| D9 | Procuração revogada | Serviço específico bloqueado; outros intactos se poderes distintos | Poder inactive; preflight |
| D10 | Rollback de release app | Imagem anterior; dados preservados | Flags off; filas drenadas |
| D11 | Office kill / remoção allowlist | Office some da fila de dispatch | Allowlist sem id; jobs não enfileiram |

---

## D1 — Suspensão de office

1. Marcar subscription do office como `SUSPENDED` (API platform / fluxo lifecycle).  
2. Tentar dispatch de monitoramento e ação tenant que dispare Integra.  
3. Confirmar bloqueio com motivo de assinatura/suspensão.  
4. GET de snapshots/evidências antigas ainda autorizado para papéis do office.  
5. Reativar `ACTIVE` e confirmar retomada.

**Não apagar:** ledger, evidências, Termo.

## D2 — Kill switch SERPRO global

```bash
php artisan serpro:contract kill-on --reason="drill_resiliencia_16_8"
# ou POST /api/v1/platform/serpro/kill-switch { "active": true, "reason": "..." }
# ou SERPRO_KILL_SWITCH=true + restart workers
```

Verificar:

- Novas autenticações/chamadas falham fechado  
- `serpro:contract health` reflete bloqueio  
- Ledger e contratos permanecem  

Desligar:

```bash
php artisan serpro:contract kill-off --reason="drill_ok"
```

## D3 / D4 — Kill switches de features e mutações

```env
FEATURES_KILL_SWITCH=true
# ou seletivo:
FEATURES_MUTATING_KILL_SWITCH=true
FISCAL_MUTATIONS_KILL_SWITCH=true
```

Reiniciar `php`/`horizon` se config cacheada. Provar UI/API: mutação 403/422 bloqueada; RO conforme D3.

## D5 — Circuit breaker

1. Config temporária: `SERPRO_BREAKER_FAILURE_THRESHOLD=3`, `OPEN_SECONDS=60`.  
2. Forçar falhas (fake client em modo erro ou endpoint inválido em homolog).  
3. Observar abertura; chamadas curtas-circuitadas sem martelar SERPRO.  
4. Aguardar half-open; um probe; fechar em sucesso.  
5. Restaurar config de produção.

## D6 — Rotação de token OAuth

1. Forçar re-auth do contrato ACTIVE.  
2. Confirmar que responses de health **não** contêm access_token.  
3. Confirmar lock de renovação sob concorrência (dois workers).  

## D7 — Rotação de certificado global

Seguir `docs/ops/serpro-global-cert-rotation-runbook.md` em **homologação**.  
Em drill sem cert novo: validar caminho `block` + recusa de segundo ACTIVE sem `replace` (coberto por testes Feature).

## D8 — Termo expirado

1. Submeter Termo com validade passada (fixture).  
2. Esperar rejeição (`termo expirado` / elegibilidade).  
3. Confirmar que jobs não chamam SERPRO.  
4. Audit de falha sem XML completo.

## D9 — Procuração revogada

1. Importar poder; executar preflight OK.  
2. Revogar/desativar poder.  
3. Preflight do mesmo serviço falha; outros poderes permanecem se independentes.  

## D10 — Rollback de aplicação

1. Parar Scheduler e Horizon.  
2. Preservar DB + volumes vault.  
3. Reverter imagem/tag da aplicação (sem migration destrutiva).  
4. `php artisan up` se maintenance.  
5. Validar leitura de evidência e ledger do período.  
6. **Não** reprocessar mutações em estado incerto automaticamente.

## D11 — Allowlist / coorte

1. Remover `office_id` de `FEATURE_*_OFFICE_ALLOWLIST`.  
2. `fiscal:dispatch-due-monitoring` não deve enfileirar o office.  
3. Reincluir e confirmar 1 job.

---

## Critérios de sucesso do conjunto

| Critério | OK? |
|----------|-----|
| Nenhum drill apagou ledger/evidência/audit | |
| Kill switches efetivos em &lt; 5 min (com restart se necessário) | |
| Restore de serviço após drill sem re-seed de dados | |
| Zero segredo em tickets de evidência do drill | |
| Post-mortem se algum controle falhou | |

## Registro (template)

| Drill | Data | Ambiente | Resultado | Notas |
|-------|------|----------|-----------|-------|
| D1 Suspensão | | | PENDING / PASS / FAIL | |
| D2 Kill SERPRO | | | | |
| D3 Kill features | | | | |
| D4 Kill mutações | | | | |
| D5 Breaker | | | | |
| D6 Token | | | | |
| D7 Cert | | | PENDING_OPS sem cert | |
| D8 Termo | | | | |
| D9 Procuração | | | | |
| D10 Rollback app | | | | |
| D11 Allowlist | | | | |

**Nota:** testes automatizados já cobrem parte de elegibilidade, shadow, kill paths e isolamento; este runbook é o **ensaio operacional** integrado.
