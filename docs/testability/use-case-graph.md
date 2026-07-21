# Grafo de testabilidade dos casos de uso

Snapshot: `ff66d72fab0bdfedd12e0b7580d8df6fa3ee4f1992a2c5662ea9c734cda56b79`

O levantamento classifica **456 rotas API**, **97 páginas Nuxt** e **14 clientes HTTP** em **10 jornadas**. 4 jornadas são críticas e exigem evidência L1–L3.

| Jornada | Crítica | Rotas | Páginas | Clientes HTTP | L0 | L1 | L2 | L3 | Lacunas |
|---|:---:|---:|---:|---:|:---:|:---:|:---:|:---:|---|
| Ativação e onboarding público (`public-access`) | não | 5 | 1 | 2 | ✓ | — | — | — | L1, L2, L3 |
| Identidade, conta e troca de escritório (`identity-tenancy`) | sim | 15 | 12 | 1 | ✓ | ✓ | ✓ | ✓ | nenhuma |
| Governança global da plataforma (`platform-governance`) | não | 19 | 15 | 1 | ✓ | ✓ | ✓ | — | L3 |
| Configuração e onboarding do escritório (`office-operations`) | não | 33 | 8 | 1 | ✓ | ✓ | ✓ | — | L3 |
| Catálogo e ciclo de vida de clientes (`client-lifecycle`) | sim | 21 | 23 | 1 | ✓ | ✓ | ✓ | ✓ | nenhuma |
| Documentos, notas e exportações (`documents-notes`) | não | 24 | 9 | 1 | ✓ | — | — | — | L1, L2, L3 |
| Monitoramento fiscal e consultas (`fiscal-monitoring`) | sim | 181 | 21 | 3 | ✓ | ✓ | ✓ | ✓ | nenhuma |
| Fila e processos operacionais (`operational-work`) | sim | 50 | 7 | 3 | ✓ | ✓ | ✓ | ✓ | nenhuma |
| Captura, integrações e documentos de saída (`outbound-capture`) | não | 43 | 1 | 1 | ✓ | — | — | — | L1, L2, L3 |
| Autorização e consumo SERPRO (`serpro-governance`) | não | 65 | 0 | 2 | ✓ | — | — | — | L1, L2, L3 |

## Leitura dos níveis

- `L0`: superfície inventariada e classificada.
- `L1`: contrato HTTP com autenticação, tenant e papel.
- `L2`: regra de domínio ou comportamento do cliente web.
- `L3`: jornada executada no navegador pelo Playwright local.

## Limites e segurança

- Lacunas não críticas permanecem explícitas; referência textual não conta como cobertura behavioral.
- Playwright permanece fora do CI e bloqueia hosts externos.
- SERPRO, Integra, SEFAZ, portal MEI e comunicação permanecem fail-closed nos testes.
- Endpoints de clientes HTTP sem correspondência estrutural: **0**.
