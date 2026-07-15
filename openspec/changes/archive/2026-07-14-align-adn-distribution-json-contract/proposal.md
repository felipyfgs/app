## Why

O pipeline de captura (mTLS, cursor NSU, job, cofre, catálogo) já existe, mas o cliente ADN interpreta o envelope de distribuição como XML no estilo NF-e (`retDistDFeInt` / `cStat` / `docZip`). Smoke real com o certificado de desenvolvimento e o cliente piloto (`client_id=8`, CNPJ `34194865000158`) mostrou HTTP 200 e dezenas de documentos em **JSON** (`StatusProcessamento` / `LoteDFe` / `ArquivoXml`), rejeitados por `AdnInvalidResponseException`. Sem alinhar o contrato wire ao ADN oficial, a busca de notas **não funciona em produção**.

## What Changes

- Alinhar `HttpAdnContributorClient` (e DTOs de página) ao **envelope JSON** da API de Distribuição de Contribuintes do ADN, conforme manual oficial e resposta real observada.
- Mapear estados oficiais de processamento (`DOCUMENTOS_LOCALIZADOS`, `NENHUM_DOCUMENTO_LOCALIZADO`, `REJEICAO`) para o modelo interno de cursor/job, preservando as garantias já especificadas (não avançar NSU em falha de decode, idempotência, bloqueio).
- Substituir fixtures XML incorretas por fixtures **JSON sanitizadas** derivadas do contrato real (sem dados fiscais sensíveis de cliente).
- Manter transporte mTLS próprio com PFX em memória (`CURLOPT_SSLCERT_BLOB`); **não** adotar bibliotecas comunitárias de ADN/NFS-e como dependência de runtime (escrevem PEM temporário e/ou desligam verificação TLS).
- Confirmar e documentar a semântica de paginação **sem `maxNSU`/`ultNSU` no JSON**: `ultimoNsu` = maior NSU do lote; `hasMore` derivado do status e do tamanho do lote.
- Usar o cliente piloto **8** / estabelecimento **9** como base controlada de smoke e aceite de desenvolvimento (fora do CI).
- Ajustar a spec `adn-document-sync` para citar o envelope JSON e os status oficiais (hoje a spec já menciona `NENHUM_DOCUMENTO_LOCALIZADO`, mas o código e fixtures ainda falam em `cStat` 137/138).

**BREAKING** (interno ao backend de sync): parsing e fixtures da distribuição deixam de aceitar o envelope XML inventado; respostas de teste e mocks de unit/feature precisam ser reescritos. Não quebra API HTTP do painel nem schema de banco.

## Capabilities

### New Capabilities

Nenhuma. O produto já define `adn-document-sync`; esta change corrige o contrato de integração.

### Modified Capabilities

- `adn-document-sync`: requisitos de envelope de resposta (JSON), status de processamento oficiais, campos de documento no lote, semântica de cursor/hasMore na ausência de `maxNSU`, e rejeição explícita de clientes ADN comunitários que materializem PEM ou desabilitem TLS.

## Impact

| Área | Efeito |
|------|--------|
| `HttpAdnContributorClient`, DTOs `Distribution*`, testes Unit/Feature de sync | Reescrita do parse e fixtures |
| `DistributionPageProcessor`, `DocumentDecoder`, job | Reuso com status/DTO normalizados; decode Base64+GZip do `ArquivoXml` já existe |
| `CurlMtlsTransport` | Mantido (já correto: BLOB, TLS 1.2+, verify host/peer) |
| Dependências Composer | **Sem** adicionar `sped-nfs-nacional`, `sped-nfse-nacional` ou SDKs ADN comunitários; permanece `nfephp-org/sped-common` só para PFX |
| Frontend / APIs de notas/export | Sem mudança de contrato; passam a receber dados reais após sync |
| CI | Continua sem certificado de homologação; smoke real só em ambiente restrito/dev com A1 de teste |
| Operação | Habilita backfill do cliente piloto e, depois, piloto progressivo |

## Não-objetivos

- Emitir, cancelar ou assinar NFS-e; DANFSe/PDF; portal do cliente.
- APIs municipais, scraping do portal, multi-escritório SaaS, KMS em nuvem.
- Substituir o cliente próprio por biblioteca comunitária ADN/NFS-e.
- Desbloqueio de cursor com override de NSU; gestão de usuários.
- Consulta automática de eventos por chave em todo documento (endpoint `events` pode ser alinhado no parse, mas refresh on-demand em massa fica fora se não for necessário para o backfill via distribuição).
- Teste de carga 1000+ estabelecimentos (permanece tarefa de aceite posterior).
