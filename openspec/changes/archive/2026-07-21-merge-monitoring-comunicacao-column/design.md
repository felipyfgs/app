## Context

A spine de carteiras (`monitoring-table-columns`) hoje separa **Envio** (Send + Switch) e **Hist. comunicação** (só ícone de rastreio). A célula de rastreio já é compacta (`w-16`), mas o header longo consome largura e compete com Cliente. O filtro popover “Envio” / `send_status` já agrega pelo status de tracking — o rótulo da grade e o eixo de filtro não precisam ser idênticos.

Changes ativas `pgdasd-pagamento-e-cnpj-cliente` e correlatas podem tocar `pgdasd-table.ts`; esta change ownershipa o contrato de coluna Comunicação e os builders compartilhados. Em conflito de merge, preservar a coluna Pagamento/CNPJ da outra change e a célula casada desta.

## Goals / Non-Goals

**Goals:**

- Uma coluna **Comunicação** na grade: Send · Switch · ícone de rastreio.
- Liberar largura sem perder affordances nem a11y (tooltips / `aria-label`).
- Atualizar specs e testes source da spine.

**Non-Goals:**

- Renomear filtro “Envio” ou parâmetro `send_status`.
- Mudar API de preferência/preview/send/tracking.
- Mover rastreio para o ⋮ de Ações.
- Abrir kill-switch de provider ou canais externos.

## Decisions

1. **Nome do header = Comunicação**  
   Cobre send + automático + histórico. Alternativa “Envio” subrepresenta o rastreio; “Hist. comunicação” subrepresenta o send.

2. **Um builder, um `id` de coluna**  
   Unificar em `buildMonitoringComunicacaoColumn` (ou evoluir `buildMonitoringEnvioAndTrackingColumns` para retornar uma coluna). Preferir `id: 'comunicacao'` (ou reusar `envio` só se quebrar menos testes mobile — decisão: novo id `comunicacao` + label Comunicação; labels mobile atualizam junto).  
   Alternativa rejeitada: manter dois ids com `colSpan` visual — complexidade sem benefício.

3. **Largura**  
   Meta ~`w-36` / `min-w-32` para caber trio de controles sem scroll horizontal forçado; Cliente continua flexível.

4. **Filtro “Envio” permanece**  
   Copy do popover e query string não mudam nesta change (menos churn de URL/bookmark).

5. **Exceção transversal (2 capabilities)**  
   Justificativa: `monitoring-portfolio-columns` é o contrato da spine; `simples-mei-portfolio-ux` cita explicitamente “Hist. comunicação” como coluna canônica — ambos precisam do mesmo delta para não divergir.

## Risks / Trade-offs

- [Coluna um pouco mais larga que Envio sozinho] → Mitigação: ganho líquido ao eliminar Hist. comunicação; meta de largura explícita.
- [Conflito com change PGDAS ativa em `pgdasd-table.ts`] → Mitigação: editar só o trecho de colunas de comunicação; rebasar se necessário.
- [Operador procura “Hist. comunicação”] → Mitigação: tooltip do ícone de rastreio mantém “Histórico local de comunicação…”.

## Migration Plan

- Deploy só frontend (SPA). Sem migração de dados.
- Rollback: reverter builders/specs para o par Envio · Hist. comunicação.

## Open Questions

- Nenhuma bloqueante.
