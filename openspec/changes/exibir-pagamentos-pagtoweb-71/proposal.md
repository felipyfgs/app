## Why

A contagem agregada de pagamentos do PAGTOWEB 7.3 confirma que há dados, mas não permite que a equipe veja quais documentos foram localizados no período consultado. O monitor precisa exibir uma lista segura e paginada dos pagamentos retornados pela operação oficial PAGAMENTOS71, mantendo evidências e limites de segurança para consultas potencialmente bilhetáveis.

## What Changes

- Criar a consulta de leitura PAGTOWEB 7.1 no monitor, com filtros de período, paginação e registros de pagamento mascarados.
- Persistir somente projeções sanitizadas e evidências mínimas necessárias para auditoria, sem documento fiscal completo, CPF, CNPJ, token, certificado ou payload bruto em logs e APIs.
- Disponibilizar no detalhe do cliente uma tela que apresente o período efetivamente consultado, o resultado da execução e os documentos mascarados retornados.
- Cobrir autenticação, tenancy, procuração exigida, tipagem, erros, telemetria segura e testes automatizados do fluxo.

Não inclui uma chamada externa automática, emissão/compensação de pagamento, alteração fiscal, habilitação de credenciais ou alteração das flags de produção.

## Capabilities

### New Capabilities

- `monitor-listagem-pagamentos-pagtoweb`: consulta paginada, auditável e segura de documentos de pagamento por período no monitor fiscal.

### Modified Capabilities

- Nenhuma.

## Impact

Afeta o catálogo SERPRO e o adaptador de guias no backend Laravel, filas de consulta, modelos/projeções sanitizadas, rotas autenticadas do monitor, tipos/composables/página Nuxt, documentação operacional e testes de backend/frontend.

### Dependências entre changes

- Nível: C1.
- Bases estáveis: `schema-conventions` e o catálogo oficial local versionado.
- Depende de: `cobrir-contagem-pagamentos-pagtoweb-73` — contrato de monitoramento de pagamentos e integração com a operação central; marco exigido: `apply`; relação: coordenada.
- Desbloqueia: a comprovação visual de quais documentos correspondem à quantidade agregada do PAGTOWEB 7.3.
- Paralelismo: não deve alterar o mesmo contrato 7.3; pode avançar após o artefato aplicado, preservando rotas e projeções próprias do 7.1.
