## Context

Em `/monitoring/simples-mei`, a consulta rápida (linha/seleção) enfileira run e só faz `refresh()` imediato da carteira — a linha mantém Situação/Última consulta antigas enquanto o Horizon processa. O usuário pediu skeleton na linha solicitada até haver resultado.

Upstream: `simples-mei-minimal-consult` (atalhos Consultar). Situação “Sem procuração” é ownership de outra change nas mesmas células.

Stakeholders: contador no painel; sem API nova.

## Goals / Non-Goals

**Goals:**

- Após enqueue bem-sucedido (PGDAS-D ou PGMEI, linha ou bulk), marcar `client_id`(s) como pendentes e renderizar skeleton nas células de resultado da(s) linha(s).
- Acompanhar run(s) até status terminal via `GET /api/v1/fiscal/runs/{id}`; então `refresh` da carteira e limpar pendência.
- Cliente permanece legível (nome/CNPJ); ações de consulta desabilitadas na linha pendente.

**Non-Goals:**

- Skeleton em outras carteiras ou consultas regime/DEFIS do menu.
- WebSocket / Echo.
- Sucesso visual sem run terminal.
- Alterar contrato de enqueue SERPRO.

## Decisions

1. **Estado pendente no front (Map clientId → runId)**  
   Página `simples-mei` (ou composable `useSimplesMeiConsultPending`) guarda pendências. Builders recebem `pendingClientIds` / `isPending(clientId)`.  
   - Alternativa rejeitada: `loading` da tabela inteira — obscurece outras linhas.

2. **Skeleton só em células de resultado**  
   PGDASD: Situação, Últ. Declaração, RBT12, Consulta (data). PGMEI: Situação, Consulta. Cliente e Ações/tracking permanecem (Consultar disabled + aria busy).  
   - Alternativa rejeitada: row inteira skeleton — perde contexto de qual cliente.

3. **Poll por run id**  
   PGDASD já devolve `FiscalMonitoringRun`. PGMEI `consult` devolve `data` com runs — tipar/usar `id` + `client_id`. Poll ~2–2.5s, teto ~48 tentativas; statuses terminais alinhados ao domínio (`COMPLETED`, `FAILED`, `BLOCKED`, `SKIPPED`, etc.).  
   - Alternativa rejeitada: só poll do portfolio por `last_valid_query_at` — falha de run nunca muda o timestamp.

4. **Bulk emite eventos `pending` + `settled`**  
   `SelectionActions` / `BulkActions` emitem os runs enfileirados para a página registrar skeleton; não acoplar poll dentro do bulk component.

## Risks / Trade-offs

- **[Poll agressivo]** → Mitigação: intervalo ≥2s, teto, `onBeforeUnmount` limpa timers; um poll por run (não N×refresh).
- **[Run sem id no response PGMEI]** → Mitigação: tipar `data` com runs; se faltar id, skeleton com timeout + refresh único (fail-soft).
- **[Concorrência com “Sem procuração”]** → Mitigação: pending tem precedência visual sobre badge de situação enquanto a run não termina.
- **[Bilhetagem]** → Sem chamadas SERPRO extras; só GET run + refresh portfolio.

## Mapa de dependências

- Upstream: `simples-mei-minimal-consult` — não editar artefatos dessa change; consumir atalhos já no código.
- Paralelo: `simples-mei-situacao-sem-procuracao` — coordenar precedência pending > sem procuração na célula Situação.
- Ownership: só `apps/web` nesta change.
- Rollback: remover pending/skeleton; consulta continua funcionando.

## Open Questions

- Nenhum bloqueante: escopo = carteira Simples/MEI consulta rápida PGDASD/PGMEI.
