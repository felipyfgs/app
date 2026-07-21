## ADDED Requirements

### Requirement: Download PGDAS-D usa cliente Sanctum autenticado

A SPA SHALL baixar artefatos PGDAS-D (declaração, recibo, DAS, MAED, extrato e equivalentes na superfície de histórico/comunicação) mediante requisição autenticada do cliente Sanctum da aplicação, e MUST NOT depender de navegação top-level/`target=_blank` para `/api/sanctum/...` como único mecanismo de download em modo proxy.

#### Scenario: Usuário autenticado baixa PDF do histórico PGDAS-D

- **WHEN** o usuário autenticado aciona «Baixar» em um artefato disponível no histórico PGDAS-D
- **THEN** a SPA MUST solicitar o bytes via cliente Sanctum (mesma base/proxy das demais APIs)
- **AND** MUST iniciar o save local do arquivo (PDF ou tipo retornado)
- **AND** MUST NOT exibir apenas `{"message":"Unauthenticated."}` em nova aba como resultado do fluxo principal

#### Scenario: Falha de autenticação ou artefato ausente

- **WHEN** a API responde erro (401/403/404) ou corpo JSON de erro no download
- **THEN** a SPA MUST informar o usuário (toast/mensagem) sem salvar um arquivo JSON como PDF
