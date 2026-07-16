# Runbook — rotação de credencial SERPRO (Consumer Key/Secret + PFX)

**Objetivo:** marcar material exposto como `COMPROMISED`, registrar versão `PENDING`, verificar OAuth, dual-approval cutover e limpar cópias transitórias.

**Proibições:**

- Nunca colar Consumer Secret, senha PFX, PFX base64 ou tokens em issue, chat, OpenSpec, log ou commit.
- Nunca passar segredo por argv (`--consumer-secret=…`); usar arquivo temporário + `shred` ou prompt.
- Nunca selecionar Office/cliente canário em artefato versionado.

## Pré-condições

- Dois `PLATFORM_ADMIN` com TOTP.
- Acesso console ao container PHP (`make shell-php` ou equivalente prod).
- Canal seguro com SERPRO para reemissão de Key/Secret e eventual reexportação do PFX.

## 12.1 — Key/Secret expostos

1. Coordenar com SERPRO/APIs compartilhadas a invalidação da Key/Secret antigas.
2. Listar versões (somente metadados):

   ```bash
   php artisan serpro:credential-version list --serpro-env=PRODUCTION
   php artisan serpro:credential-version show --id=<VERSION_ID>
   ```

3. Marcar versão comprometida:

   ```bash
   php artisan serpro:credential-version compromise \
     --id=<VERSION_ID> \
     --reason='exposed-in-channel-rotation'
   ```

4. Ou marcar contratos legados expostos (sem apagar histórico):

   ```bash
   php artisan serpro:prod-check --serpro-env=PRODUCTION --mark-exposed
   ```

5. Registrar evidência sanitizada em ticket externo (ids de versão, fingerprint prefix, timestamp) — **sem segredos**.

## 12.2 — PFX + vault + cutover quatro olhos

1. Avaliar reexportação/revogação do PFX exposto com o emitente.
2. Colocar Key/Secret/PFX novos em caminhos temporários fora do repo (ex.: `/tmp/serpro-rot-XXXX/` com `umask 077`).
3. Registrar versão pendente:

   ```bash
   php artisan serpro:credential-version register-pending \
     --serpro-env=PRODUCTION \
     --contract-id=<CONTRACT_ID> \
     --pfx-file=/caminho/seguro/novo.pfx \
     --consumer-key-file=/caminho/seguro/ck.txt \
     --consumer-secret-file=/caminho/seguro/cs.txt \
     --notes='rotation-after-exposure'
   # senha PFX via prompt interativo
   ```

4. Verificar (OAuth de verificação — sem rota fiscal):

   ```bash
   php artisan serpro:credential-version verify --id=<NEW_VERSION_ID>
   ```

5. Dual approval cutover (dois aprovadores distintos):

   ```bash
   php artisan serpro:credential-version approve-cutover \
     --id=<NEW_VERSION_ID> --approver-user-id=<ADMIN_A> --reason='cutover-eye-1'
   php artisan serpro:credential-version approve-cutover \
     --id=<NEW_VERSION_ID> --approver-user-id=<ADMIN_B> --reason='cutover-eye-2'
   php artisan serpro:credential-version cutover \
     --id=<NEW_VERSION_ID> --contract-id=<CONTRACT_ID> --approver-user-id=<ADMIN_B>
   ```

6. Remover cópias transitórias: ver `docs/ops/serpro-transient-secret-removal.md`.
7. Confirmar prod-check:

   ```bash
   php artisan serpro:prod-check --serpro-env=PRODUCTION --json
   ```

## Evidência (template)

Usar `openspec/changes/archive/2026-07-16-operacionalizar-integra-contador-producao/evidence/12-credential-rotation.md` (sem segredos/identidades).

## Rollback

- Kill switch global: `php artisan serpro:contract kill-on --reason='credential-incident'`.
- Não reativar versão `COMPROMISED`.
- Cutover só para versão `VERIFIED` com dois olhos e OAuth prévio.
