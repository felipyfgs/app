## 1. Backend — labels e grupos operacionais

- [x] 1.1 Em `NfseNoteStatus`, adicionar mapa de grupo operacional (`AUTHORIZED` / `CANCELLED` / `REVIEW`) a partir do enum granular
- [x] 1.2 Alterar `label()` para retornar Autorizada / Cancelada / Em revisão conforme o grupo
- [x] 1.3 Adicionar método de descrição oficial curta (cStat ou status granular: Gerada, Substituição gerada, Substituída, Decisão judicial, etc.) sem substituir o enum
- [x] 1.4 Atualizar `NfseNoteStatusTest` (labels operacionais + grupos; cStat/fromEvent inalterados)

## 2. Backend — filtro e insights

- [x] 2.1 Aceitar filtro de situação por grupo operacional na listagem de notas (expandir para `whereIn` de enums) mantendo filtro por enum único se já existir
- [x] 2.2 Aplicar o mesmo critério de grupo no filtro de exportação de notas, se compartilhado
- [x] 2.3 Ajustar agregações/insights de triagem para contar Autorizadas, Canceladas e Em revisão pelos grupos do design
- [x] 2.4 Garantir que respostas de listagem/detalhe ainda devolvem `status` e `official_status_code` (e descrição oficial se exposta)

## 3. Frontend — labels e badges

- [x] 3.1 Atualizar `format.ts` / `statusLabel` para Autorizada, Cancelada, Em revisão (mapa espelhando o backend)
- [x] 3.2 Ajustar `AppStatusBadge` para tons: success (Autorizada), error (Cancelada), warning (Em revisão); `SUBSTITUTE`/`JUDICIAL` como success
- [x] 3.3 Remover da UI principal opções “Gerada”, “Substituta”, “Substituída”, “Decisão judicial” como chips de grade

## 4. Frontend — filtros, insights e export

- [x] 4.1 `NotesFilters`: itens de situação = Autorizada / Cancelada / Em revisão (valores de grupo ou enums expandidos conforme API)
- [x] 4.2 Tela de exportações: mesmo vocabulário e valores de filtro
- [x] 4.3 `NotesInsightsBar`: cards e contagens alinhados aos grupos operacionais
- [x] 4.4 Conferir query string / URL dos filtros com os novos valores

## 5. Frontend — detalhe da nota

- [x] 5.1 Modal/painel de detalhe: badge operacional + linha de situação oficial (cStat + descrição)
- [x] 5.2 Exibir nuance de substituição (cStat 101 / SUBSTITUTE) e de supersedida (SUPERSEDED / evento) no detalhe, não na grade
- [x] 5.3 Manter eventos de cancelamento legíveis no detalhe quando a API já os expuser

## 6. Verificação

- [x] 6.1 Rodar testes unitários backend relacionados a status
- [x] 6.2 Smoke manual: lista, filtro Autorizada/Cancelada, modal com cStat, export se aplicável
- [x] 6.3 Confirmar que parse cStat 100/101 e eventos de cancelamento não regrediram (sem mudança de enum no banco)
