# 1.7 Fixtures sintéticas determinísticas (congeladas)

**Registrado em:** 2026-07-15  
**Fontes canônicas (não duplicar builders):**

| Arquivo | Escopo |
|---------|--------|
| `frontend/tests/e2e/support/api-fixtures.ts` | Auth, clients, notes, exports, syncs, offices A/B, papéis |
| `frontend/tests/e2e/support/fiscal-fixtures.ts` | Builders fiscais sanitizados |
| `frontend/tests/e2e/support/monitoring-fixtures.ts` | Router HTTP `/api/v1/fiscal/*` |
| `frontend/tests/e2e/support/work-fixtures.ts` | Fila, processos, templates, departamentos |

## Constantes estáveis

| Conceito | Valor / origem |
|----------|----------------|
| `FIXED_NOW` | `2026-07-14T15:00:00.000Z` |
| Office A | id `1`, nome `Escritório Contábil Modelo` (`FISCAL_OFFICE_A_NAME`) |
| Office B | id ≠ 1, nome `Escritório Sentinela` (`FISCAL_OFFICE_B_NAME`) |
| Cliente A | `Cliente Demonstração Segura` |
| Cliente B | `Cliente Tenant Sentinela` |
| Papéis | `ADMIN`, `OPERATOR`, `VIEWER` via `OfficeRole` / helpers de login e2e |
| Cenários de lista | `ready` \| `empty` \| `error` \| `slow` (`ListScenario` / `FiscalListScenario`) |
| Temas | claro/escuro via `colorMode` nos specs visuais (`visual.spec.ts`, `monitoring-visual.spec.ts`) |

## Estados obrigatórios cobertos pelos fixtures

- **Preenchido:** `listScenario = 'ready'` / builders com 1+ linhas.
- **Vazio:** `empty` → listas `data: []`, meta total 0.
- **Erro:** `error` → HTTP 500/503 sanitizado sem stack/segredo.
- **Dois escritórios:** troca via memberships; nomes e ids distintos; asserts de isolamento.
- **Papéis:** mesma URL, ações ocultas para `VIEWER`; mutações só ADMIN/OPERATOR conforme permissão.

## Regras de sanitização (fixtures)

Fixtures **MUST NOT** conter:

- PFX, senha, PEM, chave privada, Consumer Secret, token real, Termo XML, XML fiscal real, cookie de sessão, `vault_object_id`, bytes de evidência, resposta SERPRO bruta.

Marcação demo: textos como `DEMONSTRAÇÃO — SEM VALIDADE FISCAL` / `DEMO_FIXTURE` onde aplicável.

## Política desta change

- Estender fixtures existentes em vez de criar um segundo universo paralelo.
- Qualquer fixture nova (contexto operacional, calendário Mês/Semana/Dia, stepper) entra nos arquivos canônicos acima.
- Screenshots usam somente estes payloads; atualizar baseline visual só após revisão por zonas (tarefa 12.7).
