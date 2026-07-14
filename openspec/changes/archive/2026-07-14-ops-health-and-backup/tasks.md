## 1. Configuração e modelo de backup

- [x] 1.1 Adicionar config `backup.php` e variáveis em `.env.example` / `backend/.env.example` (`BACKUP_DISK_ROOT`, retenção, `BACKUP_SCHEDULE_ENABLED` default false)
- [x] 1.2 Criar migração `instance_backup_runs` (kind, status, started_at, finished_at, byte_size, manifest_path relativo, checksum, message sanitizada, timestamps)
- [x] 1.3 Criar model `InstanceBackupRun` e factory mínima para testes
- [x] 1.4 Documentar no change `ops-notes.md` o runbook de backup diário, custódia offline da `VAULT_MASTER_KEY` e restore drill

## 2. Comandos de backup e restore drill

- [x] 2.1 Implementar serviço de backup com lock anti-concorrência e kinds `database`, `vault`, `full`
- [x] 2.2 Implementar `php artisan ops:backup-run` gravando `SUCCESS`/`FAILED` e manifesto JSON (sem chave mestra)
- [x] 2.3 Implementar `php artisan ops:backup-restore-drill` validando manifesto/checksums e registrando drill
- [x] 2.4 Opcional: agendar backup diário em `routes/console.php` somente se `BACKUP_SCHEDULE_ENABLED=true`
- [x] 2.5 Testes feature/unit: full sucesso, falha parcial = FAILED, concorrência rejeitada, manifesto sem secrets

## 3. Inbox e resumo operacional (API)

- [x] 3.1 Implementar `OperationsInboxBuilder` com whitelist de types/severidades e queries indexadas por escritório
- [x] 3.2 Expor `GET /api/v1/operations/inbox` com filtros `severity`/`type`, limit/cursor e DTO sem segredos
- [x] 3.3 Ampliar `OperationsSummaryController` com contagens da inbox e bloco `backup` (stale/never/last_success/drill)
- [x] 3.4 Incluir `actions` por papel reutilizando policy de sync e `CaptureEligibilityService` (sem avançar NSU)
- [x] 3.5 Testes feature: isolamento entre escritórios, cursor_blocked, credential_expiring, backup_never/stale, VIEWER sem trigger_sync, scan de payload proibido

## 4. Frontend — tipos, home e alertas

- [x] 4.1 Atualizar `types/api.ts` e `useApi` para inbox, summary ampliado e backup
- [x] 4.2 Ampliar home com alerta de backup e bloco “Atenção operacional” (top itens + link para lista)
- [x] 4.3 Refatorar `NotificationsSlideover` para consumir inbox (fallback sanitizado se falhar)
- [x] 4.4 Criar rota `/health` com tabela server-side, filtros na URL e empty state positivo
- [x] 4.5 Adicionar item de navegação/command palette “Saúde” respeitando permissões
- [x] 4.6 Card somente leitura de backup em `admin/index.vue` (ADMIN+2FA)

## 5. Fidelidade visual e UX

- [x] 5.1 Derivir lista `/health` de arquétipo de tabela do template (`0f30c09`) e registrar origem na matriz se o projeto exigir
- [x] 5.2 Garantir deep-links para `/clients/{id}/sincronizacao` e `/clients/{id}/certificado` sem rolagem horizontal em 390px
- [x] 5.3 Não exibir botão de restore, seleção em massa ou controles ornamentais sem função

## 6. Validação e qualidade

- [x] 6.1 Rodar Pint e suíte PHPUnit (filtros Operations/Backup/Inbox)
- [x] 6.2 Rodar typecheck e vitest do frontend (tipos e helpers da inbox se houver)
- [x] 6.3 Smoke manual local: seed → forçar cursor blocked / A1 expiring → inbox e home; `ops:backup-run` + drill
- [x] 6.4 Executar `openspec validate ops-health-and-backup --json` e corrigir divergências

## 7. Fora desta change (não implementar)

- [ ] 7.1 (adiado) Desbloqueio de cursor com motivo e auditoria sem avançar NSU
- [ ] 7.2 (adiado) Gestão de usuários do escritório e e-mail de alertas
- [ ] 7.3 (adiado) Smoke ADN real e restore self-service pela UI
