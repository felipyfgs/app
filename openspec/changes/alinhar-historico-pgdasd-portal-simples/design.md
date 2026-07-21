## Context

`MonitoringPgdasdHistoryView` em `/monitoring/clients/:id/pgdasd` hoje renderiza blocos por PA com duas subtabelas (Declarações e Geração de DAS). O portal oficial “Consultar Declarações” usa uma grade única por PA com:

- faixa verde `PA MM/AAAA`
- cabeçalhos agrupados Declaração / DAS
- linhas `Declaração Original` / `Declaração Retificadora` / `Geração de DAS`
- ícones de download por coluna (Recibo, Declaração, Extrato)

A validação visual na página confirmou que a grade deve ser a superfície canônica do histórico de declarações. O modal aninhado `PgdasdDeclarationsHistoryModal` duplica esse fluxo e será removido. Artefatos no payload já trazem `declaration_number` e `das_number` (`PgdasdArtifact::toPublicArray`).

Escopo: só front (`apps/web`). Sem mudança de API, bilhetagem ou flags.

## Goals / Non-Goals

**Goals:**

- Grade desktop fiel à hierarquia do portal (por PA, linhas por operação, cabeçalhos agrupados).
- Associação correta de artefatos à linha; fallback “Outros documentos”.
- Cards compactos no mobile com o mesmo modelo de linhas.
- Grade oficial concentrada na página de detalhe, sem modal aninhado concorrente.
- Preservar resumo, ano, coleta confirmada, downloads autenticados.

**Non-Goals:**

- Tema “gov.br” pixel-perfect.
- Mudança de API ou enriquecimento SERPRO.
- Redesign da carteira `/monitoring/declarations`; apenas o atalho/modal aninhado de declarações será removido do histórico DAS.

## Decisions

1. **Grade oficial no desktop, cards no mobile**  
   Desktop largo: `<table>` com `colspan` nos grupos Declaração/DAS. Abaixo de `xl`, quando a navegação lateral reduz a largura útil, usar cards por operação. O card e seus wrappers mantêm `min-w-0` / `max-w-full` para não crescer com o min-content da tabela.  
   Alternativa rejeitada: ativar a tabela pelo breakpoint médio da viewport — a largura útil do painel pode ser bem menor e causar overflow.

2. **Modelo de linhas puro (helper)**  
   `buildPgdasdHistoryOperationRows(period)` monta linhas tipadas a partir de `declarations[]` + `das[]` + artefatos filtrados por número.  
   Alternativa rejeitada: juntar declaração+DAS na mesma linha via rowspan — o portal e o operador tratam como operações distintas.

3. **Associação de artefatos**  
   - RECIBO / DECLARACAO / NOTIFICACAO_MAED / DARF_MAED → match `declaration_number`  
   - EXTRATO / DAS → match `das_number`  
   Sem match → “Outros documentos” no rodapé do PA (não repetir em todas as linhas).

4. **Tokens semânticos Nuxt UI**  
   Faixa do PA usa `bg-success/15` (ou equivalente semântico), não hex do portal RFB. Na grade desktop, Malha/Pago permanecem texto simples (`Sim`, `Não`, `—`) como na referência; cards mobile podem usar badges semânticos.

5. **Superfície única na página**  
   Extrair `PgdasdHistoryPeriodGrid.vue` para organizar a página e remover `PgdasdDeclarationsHistoryModal.vue` e seu acionamento no histórico DAS.

## Risks / Trade-offs

- [Regressão de seletores] → Mitigação: manter `pgdasd-history-view` / `pgdasd-history-periods` / por-PA; atualizar asserts para a grade e remover os contratos do modal aninhado.
- [Artefatos só no nível do PA sem número] → Mitigação: “Outros documentos”; não inventar vínculo.
- [Tabela larga] → Mitigação: `overflow-x-auto` no desktop; cards no mobile.

## Migration Plan

- Deploy só front; rollback = reverter componentes/helpers.
- Sem migração de dados.
