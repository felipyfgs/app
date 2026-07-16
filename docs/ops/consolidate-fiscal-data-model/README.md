# Baseline e inventário — `consolidate-fiscal-data-model`

Artefatos gerados na fase 1 (pré-condições) da change OpenSpec `consolidate-fiscal-data-model`.

| Arquivo | Tarefa | Conteúdo |
|---------|--------|----------|
| `01-preconditions-and-physical-map.md` | 1.1 | Estabilidade do hub, sync de specs, mapa físico atualizado |
| `02-migrations-inventory.md` | 1.2 | Migrations aplicadas/pendentes e condicionais |
| `schema-dictionary.md` | 1.3 | Dicionário PostgreSQL efetivo (resumo + tabelas prioritárias) |
| `raw/` | 1.3 | Dumps TSV brutos (colunas, FKs, índices, contagens) |
| `04-origin-destination-matrix.md` | 1.4 | Matriz origem → destino por agregado |
| `05-functionality-matrix.md` | 1.5 | Matriz de funcionalidades e contratos |
| `06-data-baseline.md` | 1.6 | Contagens, hashes, NSUs, órfãos (sanitizado) |
| `http-routes-snapshot.md` | 1.7 | Snapshot de rotas HTTP (sem payloads sensíveis) |
| `07-http-journey-baseline.md` | 1.7 | Jornadas críticas e evidências de contrato |
| `08-backup-restore-gate.md` | 1.8 | Procedimento e evidência de backup/restore |
| `09-shadow-and-final-gate.md` | 1.9 | Responsáveis, janela shadow, tolerâncias, formato do relatório |

**Ambiente de captura:** local Docker (`app-postgres-1` / DB `nfse`), 2026-07-15.

**Não incluir nestes artefatos:** PFX, senhas, PEM, tokens SERPRO, Consumer Secret, Termo XML, `VAULT_MASTER_KEY`, vault object contents.
