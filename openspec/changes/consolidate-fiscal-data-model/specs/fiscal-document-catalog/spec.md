## ADDED Requirements

### Requirement: Separação entre documento, aquisição e interesse
O sistema MUST representar separadamente o documento fiscal canônico imutável, cada aquisição do artefato e o interesse semântico de cada Estabelecimento, sem usar uma dessas entidades como autoridade implícita das demais.

#### Scenario: Mesmo XML recebido por duas fontes
- **WHEN** bytes com o mesmo SHA-256 chegam por importação e por canal oficial
- **THEN** o sistema mantém um documento canônico, registra duas aquisições e preserva a origem de ambas

#### Scenario: Documento interessa a mais de um estabelecimento
- **WHEN** um documento canônico possui papéis fiscais válidos para dois Estabelecimentos autorizados
- **THEN** o sistema mantém interesses semânticos distintos sem duplicar os bytes nem misturar escritórios

### Requirement: Cada chegada possui idempotência específica da fonte
Toda chegada documental SHALL registrar fonte, método, instante, correlação de execução/importação, identificador oficial de transporte quando houver e resultado de validação; a chave idempotente MUST respeitar a semântica da fonte e MUST NOT colapsar chegadas legítimas apenas por `(documento, fonte, hash)`.

#### Scenario: Reprocessamento da mesma página e NSU
- **WHEN** o mesmo item da mesma página oficial é processado novamente
- **THEN** o sistema reconhece a mesma aquisição sem criar duplicata

#### Scenario: Nova captura posterior do mesmo documento
- **WHEN** o mesmo documento é legitimamente recebido em outra execução ou por outro método
- **THEN** uma nova aquisição é registrada e o documento canônico permanece o mesmo

### Requirement: Projeções tipadas com vínculo único ao canônico
Cada projeção tipada SHALL possuir vínculo inequívoco ao documento canônico e MUST ser reconstruível a partir dos bytes e eventos preservados; campos escalares legados MUST NOT concorrer como segunda autoridade após o corte.

#### Scenario: Reprojeção por parser atualizado
- **WHEN** uma nova versão de parser reprocessa XML bem-formado
- **THEN** o documento e seu SHA-256 não mudam, a projeção registra sua versão e nenhuma evidência anterior é perdida

#### Scenario: Versão oficial desconhecida
- **WHEN** o XML é bem-formado mas o XSD ou versão ainda não é reconhecido
- **THEN** o sistema preserva documento e aquisição, registra alerta de parse e não inventa projeção válida

### Requirement: Backfill documental reconciliado
A migração do catálogo MUST preservar todos os bytes, SHA-256, chaves, eventos, NSUs, papéis, direções, fontes e datas existentes e SHALL produzir mapa de correspondência e relatório de divergências.

#### Scenario: Unicidade legada ocultou proveniência
- **WHEN** uma linha legada não permite reconstruir com certeza quantas chegadas ocorreram
- **THEN** o backfill cria somente fatos comprováveis, marca a limitação de proveniência e não fabrica aquisições

#### Scenario: Mesma chave com hashes diferentes
- **WHEN** a base contém dois artefatos com a mesma identidade oficial e bytes divergentes
- **THEN** o canônico escolhido não sobrescreve o outro artefato, e a divergência permanece em custódia para revisão

