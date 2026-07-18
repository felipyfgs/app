## Por quê

`ccmei.emitirccmei` é uma operação oficialmente produtiva, mas ainda possui
somente coordenadas genéricas: não há contrato de domínio, emissão protegida de
PDF, API tenant-scoped nem superfície de negócio. Isso impede que o hub ofereça
o certificado CCMEI de forma segura e verificável.

## O que muda

- Implementar o fluxo de emissão manual de certificado CCMEI, separado das
  consultas `DADOSCCMEI122` e `CCMEISITCADASTRAL123` já existentes.
- Validar a resposta documental de forma fail-closed, guardar os bytes apenas
  no `SecureObjectStore` e expor somente metadados sanitizados.
- Disponibilizar histórico local e download same-origin autorizado no detalhe
  do cliente, com confirmação explícita antes da consulta possivelmente
  bilhetável.
- Cobrir contrato, tenancy, autorização, sanitização, estados de interface e
  evidências locais; manter o canário de produção bloqueado até autorização
  operacional e pré-condição real válida.

## Capacidades

### Novas capacidades

- `ccmei-certificate-issuance`: emissão, armazenamento protegido, consulta de
  histórico e entrega autorizada de certificados CCMEI.

### Capacidades modificadas

Nenhuma.

## Impacto

Backend: catálogo/adapter CCMEI, codec e DTO documental, projeção
tenant-scoped, cofre, controller/rotas e testes Laravel. Frontend: contratos,
cliente API e painel do detalhe do cliente seguindo o arquétipo do painel.
Ledger: linha `ccmei.emitirccmei` passa a registrar a maturidade local sem ser
promovida a produção.

### Dependências entre changes

- Nível: `C0`.
- Bases estáveis: `schema-conventions`, catálogo oficial reconciliado e as
  changes já aplicadas de consulta CCMEI (`cobrir-consulta-dados-ccmei` e
  `cobrir-situacao-cadastral-ccmei-123`).
- Depende de: nenhuma change ativa bloqueante; consome os contratos CCMEI já
  aplicados como relação coordenada.
- Marco exigido: `apply` das duas changes de consulta, já presente no código.
- Desbloqueia: homologação Trial e, quando houver autorização, canário de
  produção de `ccmei.emitirccmei`.
- Paralelismo: pode avançar sem alterar `CurrentOffice`, catálogo canônico,
  `OperationKeyMap`, ledger ou rotas-base compartilhadas; essas integrações
  serão serializadas pelo coordenador.

### Não objetivos

- Não emitir certificado em produção, não habilitar flags/capabilities nem
  alterar allowlists.
- Não expor ou ler PFX, tokens, PDFs brutos, CPF/CNPJ completo ou segredos.
- Não modificar as operações de consulta CCMEI existentes, nem criar mutações
  fiscais ou canais outbound.
