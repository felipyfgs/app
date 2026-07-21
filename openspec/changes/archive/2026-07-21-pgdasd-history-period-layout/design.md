## Context

`MonitoringPgdasdHistoryView` em `/monitoring/clients/:id` (aba PGDAS-D) hoje renderiza:

- Resumo: badge de situação, PA esperado, última consulta.
- Desktop: tabela única com `rowspan` no PA e colunas misturadas (declaração + DAS), muitas células `—`.
- Mobile: cards por PA (já mais próximos do modelo oficial).

O portal PGDAS-D oficial trabalha por **período de apuração (PA)** e distingue **declaração transmitida** de **geração de DAS**. Já existe hierarquia semelhante nos modais `PgdasdDasHistoryModal` / `PgdasdDeclarationsHistoryModal` (carteira Declarações), mas a página do cliente permanece na tabela esparsa.

Escopo: só front (`apps/web`), mesmo payload de `usePgdasdMonitoring().fetchHistory`. Sem mudança de API, bilhetagem ou flags.

## Goals / Non-Goals

**Goals:**

- Layout por PA com seções Declarações e DAS, legível e fiel ao mental model oficial.
- Um modelo visual único (desktop e mobile), com densidade adequada ao design system Nuxt UI / shell.
- Preservar coleta confirmada de documentos, downloads autenticados e estados loading/error/empty.
- Manter `data-testid` estáveis onde possível (`pgdasd-history-view`, resumo); atualizar seletores da tabela antiga se necessário.

**Non-Goals:**

- Redesign do modal “Histórico DAS” ou da carteira `/monitoring/declarations`.
- Filtro por ano (pode ficar follow-up; o modal DAS já tem).
- Tema “gov.br” (azul/branco institucional) — permanecer no tema do painel.
- Qualquer chamada SERPRO implícita ao renderizar.

## Decisions

1. **Blocos por PA em vez de tabela com rowspan**  
   Cada período é um `article`/seção com cabeçalho `PA MM/AAAA` + ação “Buscar documentos”.  
   Alternativa rejeitada: manter rowspan e só colorir linhas — continua esparso e pouco oficial.

2. **Duas subtabelas (ou listas) dentro do PA**  
   - Declarações: Operação · Nº · Transmissão · Malha · atalhos de documento da declaração/recibo quando houver.  
   - DAS: Nº DAS · Emissão · Pago · download quando houver artefato.  
   Alternativa rejeitada: master-detail com segundo modal por PA nesta página — a página já é o detalhe; modal duplicaria o fluxo da carteira.

3. **Mesmo markup responsivo**  
   Empilhar seções em todos os breakpoints; subtabelas podem scrollar horizontalmente só se necessário, sem sticky PA.  
   Alternativa rejeitada: manter dois templates (mobile cards vs desktop table) — aumenta drift.

4. **Reutilizar helpers existentes**  
   Labels de operação, malha, pagamento, `artifacts()`, `collectDocuments` e download autenticado permanecem no componente (ou extrair só se o arquivo ficar ilegível). Sem novo endpoint.

5. **Documentos no cabeçalho do PA + por linha quando fizer sentido**  
   Chips/botões de download no rodapé do bloco PA (como hoje no mobile); não reinventar coluna Documentos vazia na tabela cruzada.

## Risks / Trade-offs

- **[Regressão de seletores]** Testes/fidelity que apontam `pgdasd-history-table` quebram → Mitigação: atualizar para `pgdasd-history-periods` / por-PA; manter `pgdasd-history-view`.
- **[Sensação de “mais scroll”]** Vários cards vs uma tabela → Mitigação: densidade compacta, PA mais recentes primeiro (já ordenado), sem cards decorativos extras.
- **[Duplicação visual com modal DAS]** Usuário vê botão “Histórico DAS” + esta view → Mitigação: copy deixa claro que esta tela é o histórico local completo do cliente; não remover o atalho do modal nesta change.
- **[Bilhetagem]** Coleta continua só com confirmação explícita — sem mudança de risco SERPRO.

## Migration Plan

- Deploy só front; rollback = reverter o componente.
- Sem migração de dados.

## Open Questions

- Nenhum bloqueante: filtro por ano e alinhamento pixel-perfect ao CSS do portal RFB ficam fora desta change.
