## Why

Após o alinhamento cStat/eventos, a UI expõe **muitos chips de situação** (Gerada, Substituta, Cancelada, Substituída, Decisão judicial, Em revisão) que o contador lê como redundantes: na prática a triagem de captura pergunta se a nota **vale ou não**. Sistemas renomados de captura e APIs de emissão consolidam a vida do documento em **Autorizada** vs **Cancelada**, deixando cStat e eventos no detalhe. Precisamos dessa camada operacional sem perder cobertura dos casos nacionais já modelados no backend.

## What Changes

- Definir **status operacional de apresentação** com labels: **Autorizada**, **Cancelada**, **Em revisão**.
- Agrupar enums internos sem apagá-los:
  - **Autorizada** ← `ACTIVE`, `SUBSTITUTE` (e `JUDICIAL` quando a nota ainda é válida na projeção)
  - **Cancelada** ← `CANCELLED`, `SUPERSEDED`
  - **Em revisão** ← `UNKNOWN` (e critérios de parse/triagem já definidos)
- Atualizar **lista, chips, filtros, insights e export** para o vocabulário operacional.
- Manter no **modal/detalhe** (e onde a API já expõe): `status` granular, `official_status_code` (cStat), descrição oficial curta, eventos e vínculo de substituição (“substituída por…”).
- Ajustar labels de backend (`NfseNoteStatus::label` ou equivalente de apresentação) e testes de UI/contrato de label.
- Documentar que **não** é breaking de enum: valores de `status` na API permanecem; muda o **rótulo** e o agrupamento de filtro/insight.

## Capabilities

### New Capabilities

Nenhuma capability de domínio nova.

### Modified Capabilities

- `frontend-dashboard-experience`: chips, filtros, insights, export e modal de notas MUST usar labels operacionais Autorizada/Cancelada/Em revisão e MUST exibir situação oficial (cStat/eventos) no detalhe.
- `fiscal-document-catalog`: requisitos de listagem/filtro/export MUST permitir filtrar e apresentar a vida do documento no eixo operacional, sem exigir um chip por enum interno; detalhe continua expondo status granular + cStat.

## Impact

| Área | Efeito |
|------|--------|
| `NfseNoteStatus` (labels / grupos) | Labels operacionais; enums e `fromCStat`/eventos **inalterados** |
| Frontend `format.ts`, `AppStatusBadge`, filtros Notes/Export | Vocabulário Autorizada/Cancelada/Em revisão |
| Insights (cards de triagem) | Contagens por grupo operacional |
| Modal de detalhe da nota | Badge operacional + bloco situação oficial |
| Filtro de status na API | Aceitar grupo ou múltiplos status equivalentes (sem quebrar filtro por enum) |
| Testes unitários de label + feature de listagem | Cobertura de mapeamento e filtros |

## Não-objetivos

- Alterar mapeamento cStat → enum (`100→ACTIVE`, `101→SUBSTITUTE`, etc.) já corrigido.
- Emitir, cancelar ou substituir NFS-e pelo painel.
- Copiar pipeline de emissor (Rascunho, Aguardando retorno, Falha na emissão).
- Remover enums `SUBSTITUTE` / `SUPERSEDED` / `JUDICIAL` do domínio.
- DANFSe/PDF, portal do contribuinte, scraping.
- Novo status de cadastro CNPJ ou credencial A1.
