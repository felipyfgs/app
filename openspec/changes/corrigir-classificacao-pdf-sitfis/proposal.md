## Why

O parser atual trata todo relatório SITFIS em PDF como `ATTENTION` sem ler seu conteúdo, fazendo a carteira divergir da fonte oficial: relatórios com ausência explícita de pendências não ficam `UP_TO_DATE` e relatórios com pendências reais não ficam `PENDING`. Além disso, o contador de achados da carteira soma registros históricos/de outros snapshots em vez de representar o snapshot SITFIS corrente.

## What Changes

- Extrair texto de PDFs SITFIS oficiais com limites de tamanho/tempo e fallback conservador quando o documento não puder ser interpretado.
- Classificar como `UP_TO_DATE` somente o relatório que contenha a declaração oficial explícita de ausência de pendências; manter `is_negative_certificate=false` e copy que não confunda relatório de apoio com certidão negativa.
- Classificar como `PENDING` relatórios que contenham seções oficiais de pendência e projetar achados por seção reconhecida; layout ambíguo ou falha de extração permanece `ATTENTION`.
- Restringir `findings_count` da carteira aos achados ativos do snapshot SITFIS corrente; `pending_count` continua representando itens operacionais abertos, com semântica explícita distinta.
- Disponibilizar reprocessamento local, idempotente e auditável dos snapshots/evidências SITFIS existentes, sem consulta SERPRO, para corrigir os 11 relatórios já armazenados.
- Adicionar testes unitários/Feature com PDFs sanitizados e regressão do contador corrente.
- Garantir que o download do relatório na carteira use o cliente Sanctum/proxy, sem navegar o path da API como rota Nuxt.

Non-goals:
- Fazer nova consulta SERPRO live, alterar polling/protocolo/bilhetagem, ligar flags ou providers.
- Tratar o relatório SITFIS como certidão negativa ou emitir parecer jurídico.
- Alterar canais SEFAZ, mutações fiscais, serviços `mei`/`mei-worker` no Compose ou targets ops indisponíveis.
- Implementar histórico de busca/download, que permanece na change `sitfis-historico-busca`.

## Capabilities

### New Capabilities

- (nenhuma)

### Modified Capabilities

- `sitfis-monitoring-surface`: a situação e os achados exibidos deverão refletir marcadores explícitos do PDF oficial, com fallback conservador, contagem restrita ao snapshot corrente e reprocessamento somente local.

## Impact

- API: parser SITFIS, dependência PHP de extração de texto PDF, projeção de achados/snapshots, query da carteira e comando de reprocessamento local.
- Dados: snapshots/findings atuais poderão ser reprojetados a partir das evidências imutáveis existentes; nenhum novo egress SERPRO.
- Web: sem redesenho; badges/contadores existentes passam a receber situações e números corretos da API, e ações de relatório baixam o artefato via sessão Sanctum.
- Segurança: PDFs continuam no `SecureObjectStore`; texto integral não será logado nem exposto em JSON; falhas permanecem `ATTENTION`.
- Testes: Unit do parser/classificador, Feature da carteira e do reprocessamento local; gates API e OpenSpec.

### Dependências entre changes

- Nível: **C1**
- Bases estáveis: `sitfis-monitoring-surface`, `sitfis-protocol-persist`, `fiscal-authenticated-artifact-download`
- Depende de: `sitfis-historico-busca` — capability `sitfis-monitoring-surface`, marco `specs`, relação `coordenada` (a change de histórico já delimita parser/classificação como non-goal e não bloqueia a implementação desta change)
- Desbloqueia: carteira SITFIS semanticamente alinhada aos relatórios oficiais já armazenados
- Paralelismo: pode ser aplicada em paralelo ao histórico apenas com ownership separado; esta change não edita endpoint/modal de history e a change de histórico não edita parser/classificação/contadores
