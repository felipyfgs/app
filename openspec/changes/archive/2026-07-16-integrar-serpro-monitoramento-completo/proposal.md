## Why

O painel expõe uma cobertura ampla do Integra Contador, mas o catálogo local contém coordenadas provisórias e a maior parte das famílias ainda usa adapters fake ou o caminho legado. Precisamos de uma fonte documental verificável e de um gateway único, seguro e tenant-aware antes de transformar o monitoramento em integração SERPRO real.

## What Changes

- Substituir o catálogo provisório por um snapshot oficial versionado das 119 operações, mantendo as 21 não produtivas catalogadas e não executáveis.
- Endurecer o gateway Integra Contador para aceitar somente `operation_key`, identidades tipadas e metadados oficiais, com OAuth2/mTLS, Termo/procurações, respostas assíncronas, rate limit e faturamento normalizados.
- Conectar as 98 operações produtivas aos módulos fiscais por adapters tipados e projeções idempotentes isoladas por `Office`.
- Implementar contratos mutantes produtivos, preservando flags default OFF, allowlist, TOTP, confirmação, idempotência, orçamento e kill switch; o scheduler nunca executará mutações.
- Adicionar os módulos “Cadastro e vínculos” e “Processos fiscais”, com APIs tenant-scoped e páginas Nuxt derivadas do template oficial.
- Registrar consumo e proveniência sem expor PFX, OAuth, Termo, tokens, ETags sensíveis, XML bruto ou payload fiscal em logs e APIs.
- Manter FGTS/eSocial, canais SEFAZ, portal de contribuinte e habilitação produtiva automática fora desta mudança.

## Capabilities

### New Capabilities

- `serpro-catalogo-oficial`: snapshot, validação e versionamento das 119 operações e seus metadados oficiais.
- `serpro-gateway-seguro`: protocolo HTTP, autenticação, identidades, autorização, assincronismo, faturamento e sanitização do Integra Contador.
- `serpro-monitoramento-familias`: adapters, projeções, ledger e gates das 98 operações produtivas integradas ao monitoramento.
- `serpro-cadastro-processos-ui`: APIs e telas tenant-aware de Cadastro/Vínculos e e-Processo.

### Modified Capabilities

Nenhuma: o repositório não possui specs principais vigentes nesta base.

## Impact

- Backend Laravel: catálogo global, gateway SERPRO, autorização por escritório, adapters fiscais, jobs Horizon, ledger, projeções e novas rotas `/api/v1/fiscal/*`.
- Frontend Nuxt: navegação de monitoramento, duas páginas de lista e duas seções no detalhe do cliente.
- Banco: novas versões canônicas do catálogo e projeções tenant-scoped sem apagar histórico.
- Operação: drivers por família permanecem `disabled` até validação documental, testes, evidência comercial/legal e smoke mTLS restrito.
