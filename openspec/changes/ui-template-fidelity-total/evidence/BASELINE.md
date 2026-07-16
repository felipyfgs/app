# Baseline de fidelidade — ui-template-fidelity-total

Data: 2026-07-16  
Template: `.reference/nuxt-dashboard-template` @ `0f30c09d697160ef5dd0aaaec27fae8d7195d930`

## Comandos

```bash
# Gate lexical histórico (não é o gate estrutural final)
pnpm --dir frontend test:fidelity
node frontend/scripts/template-fidelity-gate.mjs --json

# Teste unitário do gate
pnpm --dir frontend exec vitest run tests/unit/template-fidelity-gate.test.ts

# Suite unitária (amostra / full)
pnpm --dir frontend test

# Visual / e2e (quando ambiente e fixtures disponíveis)
pnpm --dir frontend test:e2e
pnpm --dir frontend test:artifacts
```

## Resultado do baseline lexical

- **PASS lexical histórico; aceite global PENDING**
- 51 páginas ↔ 51 entradas em `parity-matrix.md`
- O gate aceitou cascas (`ShellListShell`, `MonitoringModuleTable`, `DocsWorkspace`), presets e sinais textuais que agora são proibidos
- Nenhuma página recebe `PASS` pela execução deste baseline após a decisão de migração integral

Este baseline não valida bundle único, ausência de wrapper, DOM renderizado, arquivo-fonte exato por linha, fidelidade visual, estados, papéis, acessibilidade, responsividade ou segurança. Consulte `TEMPLATE-SOURCES.md`, `SUPERSESSION.md`, `parity-matrix.md` e `ACCEPTANCE.md`.

## Shell

| Peça | Status |
|------|--------|
| `layouts/default.vue` | Revalidar por DOM/visual sob a regra nova |
| `OfficeIdentity` | Revalidar geometria de TeamsMenu e memberships autorizadas |
| `UserMenu` | Revalidar forma literal, sem demos |
| `useDashboard` | Revalidar atalhos e slideover |

## Próximos gates

- [ ] Remoção da change incremental ativa e alinhamento das skills versionadas
- [ ] Manifesto semântico + contratos renderizados dos bundles diretos
- [ ] Gate que reprova wrappers, híbridos, infinite/sticky/virtualização e footer ausente
- [ ] Lint, typecheck, Vitest/component e generate sem regressão
- [ ] Playwright funcional/estados/papéis para as 51 linhas
- [ ] Visual integral 1440/390 e overflow 360 aplicável
- [ ] A11y e scan de segredos em artefatos
- [ ] `evidence/ACCEPTANCE.md` em `FINAL: PASS`
