## MODIFIED Requirements

### Requirement: Spine canônica das carteiras por cliente

As carteiras por cliente (DCTFWeb, MIT, PGDAS-D, PGMEI, SITFIS, FGTS, hub Declarações) SHALL exibir a spine compartilhada com Situação primeiro e Consulta por último. Histórico de busca MUST NOT aparecer na grade; SHALL permanecer apenas no menu ⋮ da coluna Ações.

Carteiras **com** Declaração / Últ. Declaração (PGDAS-D, DCTFWeb, hub Declarações PGDAS) SHALL usar:
`Situação · [Declaração|Últ. Declaração] · [valores/domínio] · Cliente · Ações · Comunicação · Consulta`.

Carteiras **sem** coluna de declaração (PGMEI, SITFIS, FGTS, MIT) SHALL usar:
`Situação · Cliente · [domínio] · Ações · Comunicação · Consulta`.

Na carteira PGDAS-D, o header da coluna de período SHALL ser **Declaração** (não “Últ. Declaração”), e MUST NOT existir coluna Pagamento separada (pagamento vive em Situação).

#### Scenario: Ordem DCTFWeb com declaração

- **WHEN** o operador abre a carteira DCTFWeb
- **THEN** a ordem começa Situação · Últ. Declaração · Cliente
- **AND** não existe coluna Histórico na grade
- **AND** não existe coluna isolada Hist. comunicação na grade

#### Scenario: Exceção PGDAS-D

- **WHEN** o operador abre a carteira PGDAS-D
- **THEN** a ordem é Situação · Declaração · RBT12 · Cliente · Ações · Comunicação · Consulta
- **AND** não existe coluna Pagamento na grade
