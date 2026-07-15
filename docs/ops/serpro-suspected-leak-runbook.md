# Runbook: vazamento suspeito de segredo SERPRO / fiscal

## Escopo

Suspeita de exposição de: e-CNPJ A1 contratante, Consumer Secret, tokens OAuth, Termo XML, token de procurador, PFX A1 de escritório, `VAULT_MASTER_KEY`, ou material fiscal (XML, mensagens, guias) em log/API/export/métrica.

## Sinais

- Achado em log/APM com `BEGIN PRIVATE`, `vault_object_id`, PEM, Bearer token, XML de Termo
- Resposta API com campos proibidos (falha em testes de varredura de segredos)
- Métrica/label com CNPJ completo ou chave de 44 dígitos
- Suporte/print compartilhando health **platform** com tenant
- Acesso anômalo a endpoints PLATFORM_ADMIN ou vault

## Contenção imediata (ordem)

1. **Kill switch SERPRO global ON** — interrompe novas chamadas Integra:

```bash
php artisan serpro:contract kill-on --reason="suspeita_vazamento"
# ou POST /api/v1/platform/serpro/kill-switch
```

2. **Revogar/rotacionar** conforme superfície:
   - Certificado contratante / Consumer Secret → `serpro-global-cert-rotation-runbook.md`
   - Token procurador de um office → forçar reupload Termo / refresh; status ACTION_REQUIRED
   - A1 gerenciado do escritório → revogar credential do office (metadados de auditoria permanecem)
   - `VAULT_MASTER_KEY` → incidente de criptografia: isolar backups, rotação de envelope (procedimento offline)

3. **Preservar evidência**: não apagar `audit_logs`, ledger, snapshots. Coletar correlation ids e janela.

4. **Invalidar sessões** de usuários envolvidos se houver comprometimento de conta (Sanctum/session).

## Investigação

| Superfície | O que procurar |
|------------|----------------|
| Logs app | `LogSanitizer` / `audit.*` sem redaction |
| API tenant | `/operations/summary`, `/operations/inbox`, usage, authorization |
| API platform | health global não deve ir para tenant |
| Filas/Horizon | payload de job serializado só com ids |
| Métricas | labels fora da allowlist (`LogSanitizer::METRIC_LABEL_ALLOWLIST`) |
| Exports | zip sem PFX/PEM |

Rodar suite:

```bash
cd backend && php artisan test --filter=OperationsSecretsAndForgedOfficeTest
```

## Comunicação

- Interno: canal de segurança; classificação do dado vazado.
- Tenants afetados: comunicação factual mínima (possível exposição / rotação) **sem** reenviar o segredo.
- Nunca publicar Consumer Secret, PEM ou Termo em ticket.

## Recuperação

1. Rotação completa do material comprometido.
2. Smoke mTLS/OAuth fora de CI.
3. Kill switch OFF só após validação.
4. Revisar PR/config que introduziu o vazamento; reforçar testes de varredura.
5. Atualizar baseline em `docs/ops/autxml-secret-leakage-baseline.md` se aplicável.

## Relacionados

- `serpro-global-cert-rotation-runbook.md`
- `serpro-circuit-breaker-runbook.md`
- `multi-tenant-isolation-checklist.md`
