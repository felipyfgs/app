# ADR 001 — Cliente próprio da API ADN

## Status

Aceito

## Contexto

A captura de NFS-e depende da API oficial de distribuição para contribuintes do ADN, autenticada por mTLS com certificado e-CNPJ A1. Existem clientes comunitários, mas o domínio exige controle fino de TLS, PFX apenas em memória, DTOs internos e testes de contrato sem materializar PEM em disco.

## Decisão

- Definir a interface de domínio `AdnContributorClient` com operações tipadas (`distribution`, `events`).
- Implementar transporte HTTP próprio com cURL/libcurl, PFX via BLOB em memória, TLS ≥ 1.2 e verificação de hostname/cadeia.
- Usar `nfephp-org/sped-common` **somente** para metadados/leitura de PFX, não como cliente ADN de runtime.
- Versionar fixtures sanitizadas dos estados oficiais e impedir, via testes, a desativação de verificação TLS ou a criação de PEM temporário.

## Consequências

- Mais código de transporte para manter, porém auditável e alinhado às regras de segurança.
- Respostas externas entram no domínio apenas após conversão para DTOs/enums internos.
- Evolução do contrato oficial exige atualização de fixtures e parsers versionados, sem acoplar o job layer ao XML bruto da rede.

## Addendum — Envelope JSON da distribuição (2026-07-14)

Smoke real com A1 de desenvolvimento mostrou que a API de contribuintes responde **JSON**, não o envelope XML `retDistDFeInt` (inspirado em NF-e) usado nas fixtures iniciais.

Campos observados / oficiais:

- `StatusProcessamento`: `DOCUMENTOS_LOCALIZADOS` | `NENHUM_DOCUMENTO_LOCALIZADO` | `REJEICAO`
- `LoteDFe[]`: `NSU`, `ChaveAcesso`, `TipoDocumento` (`NFSE`|`EVENTO`), `ArquivoXml` (Base64+GZip do XML), `DataHoraGeracao`
- Ausência de `maxNSU`/`ultNSU` no JSON; paginação por NSU informado + status

Bibliotecas comunitárias que conhecem esse shape (ex.: forks “sped-nfs-nacional”) **não** entram no runtime: gravam PEM temporário e/ou desabilitam verificação TLS. O cliente próprio permanece obrigatório; `nfephp-org/sped-common` continua restrito à leitura de metadados do PFX.
