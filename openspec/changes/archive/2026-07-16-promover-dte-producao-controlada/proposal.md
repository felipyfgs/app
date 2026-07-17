## Why

Depois de configurar o contrato e comprovar OAuth, ainda falta um caminho mínimo e reversível para confirmar uma operação faturável real sem liberar o catálogo inteiro. O primeiro passo produtivo deve limitar custo, Office, cliente, operação e quantidade, com aprovação humana distinta e resultado fiscal isolado no tenant.

## What Changes

- Fixar o primeiro canário em `dte.consultar` (`DTE` / `CONSULTASITUACAODTE111` / `/Consultar`), somente leitura, sem parâmetros de negócio e com poder de procuração `00050`.
- Permitir ao Proprietário selecionar server-side um Office ativo e um cliente pertencente a ele, sem aceitar `office_id` do body/query; o Office ADMIN confirma a participação pelo contexto tenant.
- Exigir dois usuários distintos, credencial Production ativa, OAuth recente, seis gates aceitos, A1/Termo/procuração válidos, limites positivos, ambiente Production e kill switches abertos.
- Executar exatamente uma tentativa idempotente; expor o resultado fiscal somente ao Office e mostrar no console global apenas status, correlação e consumo sanitizados.
- Após sucesso e reconciliação manual com a Área do Cliente, permitir ao Proprietário promover DTE para `LIMITED`, somente para o mesmo Office e teto inicial de dez consultas.
- Permitir desativação imediata pelo painel; o kill switch externo continuará prevalecendo.
- Non-goals: CI/live smoke automático, qualquer mutação, emissão, declaração, outra operação, outro Office, retry cego, API de saldo SERPRO ou tabela de preços em reais.

## Capabilities

### New Capabilities

Nenhuma.

### Modified Capabilities

- `serpro-go-live-controlado`: troca o primeiro canário genérico pelo fluxo DTE fixo, quantitativo, idempotente, dual e promovível somente ao modo limitado do Office piloto.

## Impact

- **Backend:** seleção global auditada de Office/cliente, aprovação tenant, gates de elegibilidade, executor/ledger DTE e promoção limitada.
- **Frontend:** ações de canário e promoção na configuração global; confirmação do Office ADMIN em `/settings`; resultado fiscal permanece nas telas tenant.
- **Tenancy:** o Proprietário não recebe acesso fiscal implícito; seleção é explícita e server-side, e toda consulta valida o cliente no Office selecionado.
- **Operação:** a chamada live e a reconciliação permanecem ops-gated e desmarcadas até evidência real.
- **Dependências:** requer `configurar-serpro-global-plataforma`, Proprietário único, aprovação SERPRO adaptada, onboarding do Office, A1, Termo e procuração `00050`.

