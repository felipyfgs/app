## 1. N0 — UI unificada

- [x] 1.1 Unificar card Credenciais para TRIAL e PRODUCTION (consent + CTA onboarding em PRODUCTION)
- [x] 1.2 Remover card Ativação com stepper; status compacto no card Credenciais
  - Depende de: 1.1
- [x] 1.3 Manter Liberações externas e Limites no fluxo alinhado
  - Depende de: 1.2

## 2. N1 — Testes e gates

- [x] 2.1 Atualizar testes que assertam stepper/`serpro-production-onboarding` denso; vitest da área
  - Depende de: 1.3
  - Evidência: `pnpm exec vitest run tests/unit/navigation.test.ts`
- [x] 2.2 `openspec validate --changes --strict` + eslint do arquivo
  - Depende de: 2.1
