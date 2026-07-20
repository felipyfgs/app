## 1. N0 — Pré-requisitos canônicos + sync do autor

- [x] 1.1 Atualizar `OfficeSerproOnboardingService::evaluatePrerequisites` para aceitar A1 canônico / vínculo `SERPRO_TERM_SIGNING` (sem exigir `author_pfx`)
- [x] 1.2 Ao avaliar/enfileirar com pré-requisitos ok, sincronizar autor a partir do perfil (CNPJ + `ManagedA1` + consentimento técnico)
  - Depende de: 1.1
- [x] 1.3 Teste unitário/feature: pré-requisitos completos com A1 canônico enfileiram; sem A1 permanecem `CONFIGURING`/`A1_REQUIRED`
  - Depende de: 1.2
  - Evidência: `php artisan test --filter=OfficeSerproAutoOnboarding`

## 2. N1 — Assinatura do Termo via credencial canônica

- [x] 2.1 Em `SignTermoWithManagedA1Job`, materializar PFX via `OfficeCredentialResolver::resolveForSerproTermSigning` quando `author_pfx` ausente
  - Depende de: 1.1
- [x] 2.2 Teste: assinatura/onboarding não falha só por ausência de `author_pfx` se canônica existir
  - Depende de: 2.1
  - Evidência: `php artisan test --filter=SignTermo|OfficeSerproAutoOnboarding`

## 3. N1 — Renovação automática do token do office

- [x] 3.1 Default TRIAL `term_representation` → `REUSE_STORED_TERM`; PRODUCTION permanece `PENDING_VALIDATION`
- [x] 3.2 No lifecycle (ou job dedicado), renovar via `refreshProcuradorToken` no skew/expirado quando estratégia for `REUSE_STORED_TERM`; senão manter `ACTION_REQUIRED`
  - Depende de: 3.1
- [x] 3.3 Testes: auto-renew em TRIAL/ReuseStoredTerm; bloqueio em PendingValidation/RequireNewSignature
  - Depende de: 3.2
  - Evidência: `php artisan test --filter=SerproLifecycle|ProcuradorTokenRenew`

## 5. N1 — UX mínima do escritório (só cert + aceite)

- [x] 5.1 Remover card/stepper de onboarding SERPRO e seção separada de consentimento em `/conta/escritorio`
- [x] 5.2 Manter aceite apenas no modal do certificado; copy deixa claro que a ativação é automática
  - Depende de: 5.1
- [x] 5.3 Atualizar gate de fidelity/painel que exigia `UStepper` no escritório
  - Depende de: 5.1
  - Evidência: `pnpm run test -- tests/unit/painel-responsivo-mobile-gate.test.ts`
- [x] 5.4 Botão "Atualizar integração" no certificado regenera token sem reenviar PFX
  - Depende de: 1.2, 3.2
  - Evidência: `php artisan test --filter=refresh_integration`

## 4. N2 — Gates integrados

- [x] 4.1 Gates API: pint --test + testes da área
  - Depende de: 1.3, 2.2, 3.3
- [x] 4.2 `openspec validate --changes --strict` e gate web da área settings
  - Depende de: 4.1, 5.3
