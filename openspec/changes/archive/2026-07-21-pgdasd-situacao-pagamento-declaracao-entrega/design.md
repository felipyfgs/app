## Context

A carteira PGDAS-D em `apps/web` (`pgdasd-table.ts`) hoje mostra Situação = entrega (`declaration_state`), Últ. Declaração = PA colorido pelo mesmo estado, e Pagamento = `payment_state`. Domínio/API permanecem com eixos ortogonais; só a UI reatribui colunas.

## Goals / Non-Goals

**Goals:**

- Situação = pagamento DAS (copy humano: Em dia / Pendências / Sem DAS).
- Declaração = entrega via cor do MM/YYYY; header `Declaração`.
- Remover coluna Pagamento; migrar `PaymentValue` (badge + popover) para Situação.
- Colapsar estado interno sem evidência: Sem procuração ou `—` (nunca “Não verificado” nem flags máquina).
- Atualizar specs/testes de ordem.

**Non-Goals:**

- Redefinir KPIs (`row.situation` continua entrega).
- Mudar enums/resolvers no backend.
- Aplicar o mesmo padrão em DCTFWeb/Declarações.
- SERPRO live, flags ON, mei no Compose.

## Decisions

1. **Rebind UI, não domínio** — Situação renderiza `PaymentValue` + override Sem procuração; Declaração usa `DeclarationIndicator` enriquecido. Alternativa (Situação composta entrega+pagamento) rejeitada: perde diagnóstico.

2. **Coluna Pagamento removida** — conteúdo migra para Situação; evita duas badges de pagamento.

3. **UNVERIFIED colapsado só na UI** — enum permanece fail-closed; exibição: Sem procuração se `procuracao_status=missing`, senão `—` sem badge de negócio / sem popover.

4. **Tooltip no header Situação** — “Pagamento dos DAS do PA esperado” mitiga colisão lexical com KPIs de entrega.

5. **Quatro deltas de capability** — justificado no proposal: redesign atômico da grade PGDAS-D.

## Risks / Trade-offs

- [KPI vs coluna “Em dia”] → tooltip no header; change futura de copy se confundir.
- [Operador procura “Pagamento”] → header Situação + tooltip; popover intacto.
- [Sem evidência vira `—`] → fail-closed visual; consulta pendente já tem skeleton.

## Migration Plan

Deploy só frontend (SPA). Rollback = reverter change. Sem migration de dados.
