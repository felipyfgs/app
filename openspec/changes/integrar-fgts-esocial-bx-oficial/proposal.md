## Why

O módulo FGTS já modela fechamento e totalização do eSocial, mas permanece bloqueado por um client desabilitado mesmo existindo o eSocial BX oficial para consultar identificadores e baixar eventos por webservice. A integração precisa transformar essa cobertura parcial em automação operacional real, respeitando certificado digital, limites do Ambiente Nacional e a ausência de API pública do portal FGTS Digital.

## What Changes

- Integrar o provider oficial eSocial BX para consultar e baixar os XMLs S-1299 e S-5013 por competência usando SOAP 1.1, XMLDSig e mTLS com o A1 ativo do cliente no vault.
- Aplicar gates fail-closed para ambiente, kill switch, credencial, dias 1 a 7, janela máxima, defasagem mínima de uma hora, limite compartilhado de 10 chamadas/dia e exclusão mútua por empregador.
- Interpretar respostas e códigos oficiais, persistir apenas eventos válidos como evidência protegida e projetar estados FGTS idempotentes sem expor XML, PFX ou senha.
- Expor no manifesto/API/UI a disponibilidade real da fonte, a prontidão de credencial, a cobertura M2M e os bloqueios operacionais; manter sincronização manual e pelo scheduler/Horizon.
- Manter S-5003 como evento aceito pelo domínio, mas fora da busca automática agregada enquanto não houver CPF/identificador do trabalhador fornecido por uma fonte oficial local.
- Manter guia, pagamento, débito e pendências do portal FGTS Digital como `UNSUPPORTED`; não usar scraping, sessão Gov.br, CAPTCHA, cookie ou automação de navegador.
- Adicionar testes unitários e Feature para assinatura/envelope, parser, limites, isolamento de tenant, credenciais, persistência, endpoints e contrato Nuxt.
- Non-goals: emissão/pagamento de guia, mutações no FGTS Digital ou eSocial, integração SERPRO live, parecer jurídico sobre procurações, flags de produção ligadas por padrão, novos canais SEFAZ, serviços `mei`/`mei-worker` no Compose e targets de backup/restore indisponíveis.

## Capabilities

### New Capabilities

- `fgts-esocial-bx-automation`: Automação read-only ponta a ponta do recorte FGTS disponível pelo eSocial BX oficial, incluindo credencial, transporte, limites, evidências, projeção, API, scheduler e UI honesta.

### Modified Capabilities

- Nenhuma.

## Impact

- API Laravel: `Contracts/EsocialEventClient`, `Services/Esocial`, resolução de `ClientCredential`, transporte SOAP/mTLS, configuração `fgts_esocial`, cache/locks Redis, bindings, jobs, controller e testes.
- Web Nuxt: página `/monitoring/fgts`, tipos/composable fiscal e testes unitários de disponibilidade, bloqueio e sincronização.
- Dependências: reutiliza `nfephp-org/sped-common` e `robrichards/xmlseclibs` já instalados; não adiciona SDK de terceiro instável nem serviço ao Compose.
- Sistemas externos: endpoints oficiais de download do eSocial em Produção e Produção Restrita; nenhum acesso ao portal autenticado FGTS Digital.

### Dependências entre changes

- Nível: `C0`.
- Bases estáveis: `declarations-obligation-hub`, `fiscal-monitoring-snapshot-read-auth`, infraestrutura de credenciais de cliente/vault e o módulo FGTS/eSocial já versionado.
- Depende de: nenhuma change ativa; capability/contrato próprio `fgts-esocial-bx-automation`; marco exigido: nenhum; relação: `coordenada` apenas com as main specs e contratos estáveis existentes.
- Desbloqueia: monitoramento FGTS parcial com fonte oficial operacional e futuras changes independentes para S-5003 baseado em identificadores locais ou para APIs do FGTS Digital caso sejam oficialmente publicadas.
- Pode executar em paralelo com changes ativas que não editem `config/fgts_esocial.php`, `Services/Esocial`, `FgtsEsocialController`, `/monitoring/fgts` ou os contratos compartilhados de credenciais; conflitos nesses arquivos exigem serialização, sem criar dependência semântica.
