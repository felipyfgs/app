## ADDED Requirements

### Requirement: Download via descriptor fiscal usa cliente Sanctum

A SPA SHALL baixar o documento oficial exposto em `FiscalDocumentDescriptor.href` (hub do cliente, central de guias e demais superfícies que usam `FiscalDocumentAction`) mediante requisição autenticada do cliente Sanctum, e MUST NOT navegar o path `/api/v1/...` como rota do Vue Router nem abrir `target=_blank` top-level como único mecanismo em modo HMR/proxy.

#### Scenario: Usuário autenticado baixa documento do descriptor no hub

- **WHEN** o usuário autenticado aciona o botão de documento disponível (`available=true` com `href` do servidor) no detalhe do cliente ou superfície equivalente que renderiza `FiscalDocumentAction`
- **THEN** a SPA MUST solicitar os bytes via cliente Sanctum
- **AND** MUST iniciar o save local do arquivo
- **AND** MUST NOT exibir a página Nuxt «Page not found» para o path da API

#### Scenario: Central de guias baixa documento disponível

- **WHEN** o usuário autenticado aciona «Documento» em uma linha de guia cujo `document.available` e `document.href` estão presentes
- **THEN** a SPA MUST baixar via cliente Sanctum (mesmo padrão do descriptor)
- **AND** MUST NOT usar `to`/`href` do Vue Router apontando para `/api/v1/...`

#### Scenario: Falha de autenticação ou artefato ausente no descriptor

- **WHEN** a API responde erro (401/403/404) ou corpo JSON de erro no download do descriptor
- **THEN** a SPA MUST informar o usuário (toast/mensagem) sem salvar um arquivo JSON como PDF
