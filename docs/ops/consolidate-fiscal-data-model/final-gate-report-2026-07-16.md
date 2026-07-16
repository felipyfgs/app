# Relatório final — consolidate-fiscal-data-model

- **Versão / commit:** working tree apply 2026-07-16  
- **Migrations consolidação:** `2026_07_16_400000` … `400900`  
- **Período shadow prod:** **não iniciado** (default 7 dias)  
- **Ambiente:** local Docker `nfse`

## Resultados por agregado

| Agregado | Reconciliação local | Testes | Shadow prod | Decisão |
|----------|---------------------|--------|-------------|---------|
| tenancy-cadastro | OK | collapse, fail-closed, multi-estab | N/A | **APROVADO local** |
| documentos-cursores | OK (0 sem acquisition) | ADN/DistDFe + recorder | N/A | **APROVADO local** |
| outbound | 0 requests baseline | recovery case/attempt | N/A | **APROVADO estrutura** |
| serpro | ledger/idempotency OK; 125 ops | unit Usage + seed | N/A | **APROVADO local** |
| monitoramento-guias | snapshots dedupe; stubs mapped 5/11 | CHECK+índice current | N/A | **APROVADO local c/ stubs residual** |

## Divergências

Nenhuma no reconciliador estrutural após dedupe de snapshots.

## Exceções formais

Ver `12-functionality-evidence-matrix.md` (EX-FE-1, EX-PG-1, EX-SHADOW-1).

## Restore / backup pós-apply

| Campo | Valor |
|-------|-------|
| BACKUP_DIR | `backups/nfse-backup-20260716T011702Z` |
| postgres.sql.gz | OK (`sha256sum -c`, `gzip -t`) |
| vault.tar.gz | OK (`tar tzf` lista objetos) |
| includes_VAULT_MASTER_KEY | **NO** (MANIFEST) |
| Restore full em instância isolada | **Parcial** — integridade do pacote comprovada; restore destrutivo em DB isolado fica para ops (script `restore.sh` exige CONFIRM_RESTORE=SIM) |
| Smoke jornadas mínimas | Suite filtrada Auth/Clients/Sync/Sefaz/… + FiscalModel (32+ testes sessão) |

## openspec validate

```
valid: true, issues: [], passed: 1
```

## Comandos de gate reexecutados

```bash
php artisan fiscal-model:shadow-verify --json   # passed
php artisan fiscal-model:reconcile --json       # passed
php artisan fiscal-model:reconcile-serpro --json
php artisan fiscal-model:secret-scan --json     # 0 findings
php artisan fiscal-model:backfill tenancy_cadastro|documentos_cursores|serpro|outbound|monitoramento_guias
```

## Cutover (8.x)

| Flag | Valor default | Nota |
|------|---------------|------|
| `*_READ_CANONICAL` | false | **não** cortar leitura prod sem shadow 7d |
| `*_WRITE_CANONICAL` | true | escritas canônicas ativas onde implementadas |
| `FISCAL_MODEL_KILL_SWITCH` | false | rollback lógico disponível |
| `FISCAL_MODEL_FAIL_CLOSED_SCOPES` | true (prod) / false (phpunit) | |

## Decisão final

### Harness + apply local: **APROVADO**

Base de schema, backfills, fail-closed, acquisitions, SERPRO canônico, guias/snapshots, backup pack e testes de domínio **aprovados no ambiente local**.

### Corte de produção / remoção de legado: **REPROVADO** (gate controlado)

Motivos bloqueantes remanescentes:
1. Shadow verification de 7 dias em tráfego real **não** executada  
2. Restore destrutivo em instância isolada **não** reexecutado com `CONFIRM_RESTORE=SIM` (apenas integridade do pacote)  
3. Typecheck/E2E frontend com EACCES neste host  

**Efeito 10.5:**  
- `read_canonical` permanece **false**  
- Proibida migration de drop de legado  
- Kill switch e adapters de leitura legada permanecem  

### 10.7 Change de retirada

**Não criada** até decisão `APROVADO` de corte. Nome sugerido: `retire-legacy-fiscal-data-model`.

## Assinaturas

- Engenharia (apply): 2026-07-16  
- Ops: _pendente shadow + restore isolado_  
- Product: _pendente cutover_
