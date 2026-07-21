## Context

O fluxo SITFIS é assíncrono (`solicitar_protocolo` → espera mínima → `emitir_relatorio`) orquestrado por `SitfisFlowService`, com estado em `fiscal_monitoring_runs.progress` e cursor em `progress_cursor`. A SERPRO devolve `protocoloRelatorio` como token longo (base64-like, frequentemente > 120 caracteres). Hoje o cursor é `protocol:{token_completo}`, incompatível com `varchar(120)`, o que aborta a persistência após HTTP bem-sucedido.

Em paralelo, `SerproOperationAttemptStore` trata strings longas base64-like como blob documental e omite o protocolo no ACK; replay sticky devolve sucesso sem protocolo correlacionável. Snapshots `ERROR` ainda entram no TTL de 24h, então “Buscar pendências” retorna `WITHIN_TTL` sem novo enqueue. Schedules SITFIS estão ausentes para a carteira, então o scheduler não cobre Desconhecido.

## Goals / Non-Goals

**Goals:**

- Persistência estável do protocolo (progress completo + cursor curto).
- Attempt store preserva protocolo SITFIS; replay omitido força reclaim.
- Refresh pós-erro / force honesto; UI não mente sobre enqueue.
- Refresh cria/garante schedule SITFIS quando a categoria existir.

**Non-Goals:**

- Mudar contrato SERPRO (`SOLICITARPROTOCOLO91` / `RELATORIOSITFIS92`).
- Alterar bilhetagem ou composição da chave de idempotência além do reclaim.
- Redesenhar a carteira Nuxt ou o parser de relatório.
- Abrir kill switches ou adicionar `mei` ao Compose.

## Decisions

1. **Cursor = hash curto do protocolo**  
   `progress_cursor = protocol:{substr(sha256(protocol), 0, 16)}` (≤ ~25 chars). Protocolo integral só em `progress.protocol`.  
   **Alternativa rejeitada:** gravar o token inteiro e só ampliar a coluna — risco de estourar índices/logs e repetir o bug se o token crescer.

2. **Migration `progress_cursor` → `string(64)`**  
   Folga para prefixo + hash; não precisa de `text`.

3. **Allowlist de campos de protocolo no sanitizer**  
   Para `sitfis.solicitar_protocolo` e `sitfis.emitir_relatorio`, campos `protocoloRelatorio` / `protocolo` / aliases oficiais NÃO passam por `looksLikeEncodedBlob`; persistem truncados a 512 se necessário.  
   **Alternativa rejeitada:** desligar blob detection global — vazaria PDFs PGDAS/Pagtoweb.

4. **Reclaim em sucesso sticky com protocolo omitido**  
   Se `toResponse` de solicit tiver descritor `omitted_from_attempt_store` no lugar do protocolo, reclaim + dispatch (mesmo espírito de falhas não sticky). Detectar no fluxo SITFIS ou no store ao montar replay.

5. **TTL só para snapshots “úteis”**  
   `WITHIN_TTL` aplica-se a `UP_TO_DATE` / `PENDING` / `ATTENTION` / `PROCESSING`. `ERROR` / `BLOCKED` / `UNKNOWN` sempre enfileiram. `force=true` também bypassa. Controller passa `force` do body.

6. **`ensureSchedule` no refresh**  
   Após enfileirar (ou antes), se categoria SITFIS existir no catálogo/link do cliente, chamar `FiscalCategoryService::ensureSchedule` para o scheduler cobrir Desconhecido.

7. **UI conta só `enqueued === true`**  
   Toasts diferenciam `WITHIN_TTL` / `ALREADY_RUNNING` / sucesso.

## Risks / Trade-offs

- **[Hash no cursor]** Continuação depende de `progress.protocol`, não do cursor textual → Mitigação: `SitfisProtocolState::fromProgress` já é a fonte da verdade; testes cobrem requeue.
- **[Protocolo no attempt store]** Token sensível em DB → Mitigação: truncamento 512; já há sanitização de tokens OAuth; não logar body bruto.
- **[Force bypass TTL]** Custo SERPRO → Mitigação: só ADMIN/OPERATOR; UI confirma refresh recente.
- **[ensureSchedule]** Pode criar schedules inesperados → Mitigação: só se categoria/link SITFIS já existir; intervalo do config (1440 min).

## Migration Plan

1. Deploy migration `progress_cursor` 120 → 64 + código.
2. Runs antigas com cursor longo inválido não são reescritas; próximas consultas usam hash.
3. Attempts sticky com protocolo omitido reclaim na próxima solicit.
4. Rollback: reverter código + migration down (64 → 120) só se nenhum cursor > 120 existir (hash cabe).

## Open Questions

- Nenhum bloqueante.
