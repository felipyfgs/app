## Why

Ao solicitar consulta PGDAS-D/PGMEI pela UI em `/monitoring/simples-mei`, a linha continua com dados antigos até um refresh genérico — o usuário não vê que aquela linha está em processamento. É preciso feedback visual imediato e localizado até o resultado da run.

## What Changes

- Após confirmar consulta (linha ou seleção), as linhas afetadas entram em estado pendente com **skeleton** nas células de resultado (não a tabela inteira).
- A UI acompanha a run enfileirada até status terminal e então atualiza a carteira, removendo o skeleton.
- Falha de enqueue não entra em skeleton; falha de run limpa o skeleton e mantém feedback honesto (toast/erro já existente).

## Capabilities

### New Capabilities

- `simples-mei-consult-pending-row`: skeleton por linha enquanto consulta Simples/MEI solicitada pela UI não retorna resultado.

### Modified Capabilities

- (nenhuma)

## Impact

- Web: `simples-mei/index.vue`, builders `pgdasd-table.ts` / `pgmei-table.ts`, ações de seleção/bulk, possível composable de poll de run; testes unitários.
- API: sem contrato novo — reutiliza `POST` enqueue + `GET /api/v1/fiscal/runs/{id}` já existentes.
- Non-goals: skeleton em outras carteiras; WebSocket; inventar sucesso visual sem run terminal.

### Dependências entre changes

- Nível: `C1`
- Depende de: `simples-mei-minimal-consult` (capability `simples-mei-portfolio-ux` — atalho Consultar linha/seleção)
- Capability/contrato: consulta rápida já implantável na carteira
- Marco: atalho `pgdasd-row-consult` / `pgmei-row-consult` e bulk Consultar presentes
- Relação: estende UX da consulta rápida com feedback de pendência
- Desbloqueia: apply desta change
- Paralelismo: coordenada com `simples-mei-situacao-sem-procuracao` (ownership distinto nas células de Situação)
