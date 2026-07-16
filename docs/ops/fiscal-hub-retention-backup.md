# Retenção, backup, restore, revogação e exclusão — hub fiscal

**Change:** `build-complete-fiscal-monitoring-hub` · task **16.3**  
**Atualizado:** 2026-07-15  
**Relacionados:** `docs/ops/backup-restore.md`, `docs/ops/archive/2026-07-15/fiscal-hub-control-plane-backup-drill-2026-07-15.md`, ADR 003/005

## Princípios

1. **PostgreSQL** é a verdade transacional; **cofre** guarda blobs cifrados (PFX, Termo, tokens, evidências grandes).
2. **`VAULT_MASTER_KEY` nunca** entra no artefato de backup comum nem em dumps versionados.
3. Plano de **controle** (contrato SERPRO, flags, fatura consolidada) e plano de **dados** (tenant) compartilham instância no MVP, mas **políticas de exclusão diferem**.
4. Ledger de consumo e evidências fiscais são **imutáveis** ou append-only: “apagar” é soft-delete/anonimização conforme LGPD + obrigação fiscal, não reescrita silenciosa.

## Matriz de retenção (defaults de produto)

| Classe de dado | Plano | Retenção sugerida MVP | Base config / nota |
|----------------|-------|----------------------|--------------------|
| Snapshots / findings de monitoramento | Tenant | ≥ ciclo comercial + auditoria (alinhado a evidência) | Domínio fiscal |
| Evidências (`fiscal_evidence_*`) | Tenant | **~7 anos** default (`FISCAL_EVIDENCE_RETENTION_DAYS=2555`) | `config/fiscal_monitoring.php` |
| Ledger `serpro_api_usage_*` | Tenant (+ agregados globais) | **Indefinida no MVP** — não purgar enquanto houver conciliação/fatura aberta | Imutável |
| Reconciliações / fatura consolidada | Controle | ≥ prazo fiscal/contábil da software house (definir com jurídico; default **7 anos**) | Não reescrever ledger original |
| Contrato SERPRO metadados | Controle | Enquanto ACTIVE + histórico SUPERSEDED | Soft states; sem hard delete de audit |
| PFX/Termo/tokens no vault | Global ou tenant | Enquanto credencial ACTIVE; após revogação: metadados + hash, blob conforme política de purge | Purpose no SecureObjectStore |
| Audit logs | Instância | ≥ 1 ano operacional; preferir 5–7 se contiver trilha fiscal | Já redigidos |
| Mensagens caixa postal | Tenant | Conforme necessidade do escritório; mínimo alinhado a evidência se originarem findings | |
| Guias / mutações (quando liberadas) | Tenant | Permanente no MVP (prova de operação) | Não apagar após timeout incerto |
| Sessões / cache Redis | — | Efêmero | Tokens OAuth com TTL + skew |
| Backups de instância | Ops | Política da software house (ex.: 30–90 dias rolling + offline) | `docker/ops/backup.sh` |

> Valores são **operacionais**. Ajuste legal/contábil deve ser validado por jurídico da software house antes de GA.

## Backup

### O que entra

- Dump PostgreSQL completo (controle + dados de todos os offices).
- Tarball do cofre **já cifrado** (`vault.tar.gz`).
- Manifesto + `SHA256SUMS` com declaração explícita: **sem master key**.

### O que **não** entra

- `VAULT_MASTER_KEY` / versão em plain no artefato.
- Material PFX em clear.
- Segredos de `.env` (procedimento separado de custódia de config).

### Comandos

```bash
# Preferido no Compose local
bash ./docker/ops/backup.sh backups
bash ./docker/ops/restore.sh --verify-only backups/nfse-backup-<timestamp>

# Make
make backup
make backup-verify BACKUP=backups/nfse-backup-...

# Artisan (requer pg_dump no runtime — no image PHP atual pode falhar)
docker compose exec -T php php artisan ops:backup-run --kind=full
docker compose exec -T php php artisan ops:backup-restore-drill --run=latest
```

Drill já registrado: `docs/ops/archive/2026-07-15/fiscal-hub-control-plane-backup-drill-2026-07-15.md`.

### Multi-tenant e backup

- Backup de **instância** = todos os tenants. Custódia e restore são de **plataforma**, não “export LGPD de um office”.
- Export por tenant (produto) é caminho distinto (`/api/v1/exports` etc.) e **não** substitui DR.

## Restore

1. Obter master key **offline** com a mesma `VAULT_MASTER_KEY_VERSION`.
2. `backup-verify` / `restore.sh --verify-only`.
3. Restore destrutivo só com confirmação:

```bash
make restore BACKUP=backups/nfse-backup-... CONFIRM_RESTORE=SIM
```

4. Validar: auth, `php artisan about`, health SERPRO sanitizada, leitura de objeto de teste do vault, preflight tenant.
5. **Não** ligar workers de Integra/mutações até smoke RO e kill switches revisados.

Sem master key: DB sobe, blobs do cofre são **irrecuperáveis**.

## Revogação (credenciais e acessos)

| Alvo | Ação | Preserva |
|------|------|----------|
| Cert contratante comprometido | Kill switch SERPRO → revogar AC → `serpro:contract block` → replace | Ledger, audit, Termos de tenants |
| Consumer Secret vazado | Rotacionar no portal SERPRO + replace OAuth no contrato | Metadados |
| A1 Autor do office | Desativar credencial do office; invalidar token procurador | Histórico de autorização |
| Termo expirado/revogado | Marcar autorização inválida; bloquear cadeia | XML hash + audit de upload |
| Procuração revogada | Invalidar `tax_proxy_powers` / poder específico | Histórico de poderes |
| Usuário tenant | Desativar membership (`is_active=false`) | Audit de ações passadas |
| `PLATFORM_ADMIN` | Remover platform membership | Audit de ações de controle |
| Office `SUSPENDED` / `CANCELED` | Bloquear novas chamadas externas e mutações | Leitura autorizada + evidências conforme retenção |

## Exclusão e LGPD (tenant / titular)

| Pedido | Tratamento MVP |
|--------|----------------|
| Exclusão de conta de usuário do escritório | Soft-disable membership; pseudonimizar PII mínima se exigido; **não** apagar audit fiscal |
| Offboarding do office | Estado `CANCELED`; kill de integrações; retenção de evidências/ledger pelo prazo fiscal; depois purge seletivo com checklist jurídico |
| “Apagar CNPJ do cliente” | Soft-delete cadastro operacional; documentos/evidências/ledger **não** somem no prazo de retenção |
| Purge de evidência após retenção | Job futuro controlado; hash pode permanecer em audit; registrar purge |
| Hard delete de ledger | **Proibido** no MVP (conciliação e disputa de fatura) |

### Checklist antes de purge de office

- [ ] Estado `CANCELED` há N dias (SLA interno)
- [ ] Nenhuma fatura/conciliação aberta que cite o office
- [ ] Backup de instância recente verificado
- [ ] Kill switches / allowlists limpos do `office_id`
- [ ] Export entregue ao office se contratualmente devido
- [ ] Aprovação jurídico + platform admin
- [ ] Registro em ticket ops (sem colar segredos)

## Validação 16.3 (estado)

| Item | Status |
|------|--------|
| Procedimento backup/restore documentado | OK (este doc + backup-restore.md) |
| Drill de artefato pré plano de controle | OK (2026-07-15) |
| Restore destrutivo em ambiente de ensaio | **PENDING_OPS** (não em instância viva de dev com dados úteis) |
| Job automático de purge por retenção | Não no MVP — política manual/agenda |
| Política jurídica formal de prazos | **PENDING** jurídico software house |

## Gates

- **Antes de dados fiscais reais de piloto:** backup + verify-only + (recomendado) restore em clone.
- **Antes de GA:** ensaio de restore completo e tabela de retenção assinada por ops+jurídico.
