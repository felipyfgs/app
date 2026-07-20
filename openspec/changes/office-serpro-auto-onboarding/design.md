## Context

Em `/conta/escritorio` o contador completa perfil, consentimento técnico e A1 canônico. O backend já chama `evaluateAndMaybeEnqueue` / `reactToProfileOrCredentialChange` e o job `ProcessOfficeSerproOnboardingJob` sabe assinar Termo, obter token e carregar procurações. Porém `evaluatePrerequisites` ainda exige `author_pfx` legado em `OfficeSerproAuthorization`, enquanto o painel grava só a credencial canônica (+ vínculo `SERPRO_TERM_SIGNING`). O job de assinatura também lê só `author_pfx`. Resultado: a automação não parte após o aceite+A1.

Separadamente, `SerproLifecycleMonitor` marca token expirado como `ACTION_REQUIRED` e **nunca** renova — com `SERPRO_TERM_REPRESENTATION_*` default `PENDING_VALIDATION`, `refreshProcuradorToken` também bloqueia renovação silenciosa. Isso derruba consultas de toda a carteira do office.

## Goals / Non-Goals

**Goals:**

- Pré-requisitos do onboarding SERPRO do office: perfil institucional + consentimento técnico + A1 canônico (com vínculo `SERPRO_TERM_SIGNING` ou canônica ativa).
- Ao completar pré-requisitos, sincronizar autor (CNPJ do perfil, modo `ManagedA1`, consentimento) e enfileirar o job se Termo/token/procurações ainda não estiverem prontos.
- Assinatura do Termo materializa A1 via `OfficeCredentialResolver::resolveForSerproTermSigning` (fallback quando `author_pfx` ausente).
- Renovação automática do token do **office** quando a estratégia for `REUSE_STORED_TERM` e Termo+credencial forem válidos (skew/expirado).
- TRIAL: default de `term_representation` → `REUSE_STORED_TERM` para operação contínua em trial; PRODUCTION permanece `PENDING_VALIDATION` (fail-closed) até validação explícita.
- Testes unitários/feature da ponte canônica, enqueue e renovação/fail-closed.
- UI: sem campos técnicos SERPRO em `/conta/escritorio`; só status de onboarding já exposto.

**Non-Goals:**

- Autor/token compartilhado da plataforma (FELIPE) para todos os tenants.
- Renovação automática sob `REQUIRE_NEW_SIGNATURE` ou A3 interativo.
- Abrir Consumer Key / console admin ao contador.
- Flags SERPRO ON em produção; mei no Compose; mutações fiscais além do onboarding/token.
- Restaurar UI técnica de Termo/token **ou** stepper de onboarding SERPRO no escritório.
- Seção separada de consentimento em `/conta/escritorio` (aceite só no modal do A1).

## Decisions

### 1. A1 canônico é a fonte de verdade para assinatura

- **Decisão:** `evaluatePrerequisites` considera A1 ok se existir credencial canônica ativa ou vínculo `SERPRO_TERM_SIGNING`. `SignTermoWithManagedA1Job` usa `OfficeCredentialResolver::resolveForSerproTermSigning` quando `author_pfx` for nulo.
- **Por quê:** evita duplicar PFX no vault e alinha com o que `/conta/escritorio` já grava.
- **Alternativa:** copiar PFX canônico para `author_pfx` no cutover — rejeitada (dois segredos, AAD diferente, drift).

### 2. Sincronizar autor a partir do perfil no evaluate/enqueue

- **Decisão:** antes do enqueue (ou no início de `process`), se perfil tiver CNPJ e A1 canônico ok, chamar `configureAuthor` (CNPJ + nome + `ManagedA1`) e marcar `managed_a1_consent` a partir do consentimento técnico vigente — sem exigir tela SERPRO.
- **Por quê:** hoje autor fica `000…0` / `ExternalSignature` e o job de assinatura falha mesmo com A1 canônico.

### 3. Renovação automática só com `REUSE_STORED_TERM`

- **Decisão:** no lifecycle (ou job dedicado enfileirado pelo scan), quando token estiver no skew/expirado e a estratégia do ambiente for `REUSE_STORED_TERM`, chamar `refreshProcuradorToken`. Caso contrário, manter `ACTION_REQUIRED` (comportamento atual).
- **TRIAL default:** `SERPRO_TERM_REPRESENTATION_TRIAL` → `REUSE_STORED_TERM`.
- **PRODUCTION default:** permanece `PENDING_VALIDATION`.

### 4. Escopo de UI

- **Decisão:** não reintroduzir checklist técnico SERPRO em `/conta/escritorio`. O stepper/status de onboarding já consumido pelo painel continua sendo a única superfície.

## Risks / Trade-offs

- **[Assinatura com A1 canônico vs author_pfx legado]** Mitigação: fallback explícito no job; testes cobrem ambos os caminhos.
- **[TRIAL auto-renew vs fail-closed]** Mitigação: só TRIAL muda default; PRODUCTION continua bloqueado até config explícita.
- **[Reonboarding ao trocar A1]** Já existe `reactToProfileOrCredentialChange` → mantido.
- **[Bilhetagem Apoiar]** Renovação usa o mesmo `refreshProcuradorToken`; sem chamadas extras além do necessário no skew.

## Mapa de dependências

- Nível: `C0` — sem dependência de change ativa.
- Capability: `office-serpro-auto-onboarding` (NEW).
- Paralelismo: ok com changes de admin SERPRO nav/console (ownership distinto).
