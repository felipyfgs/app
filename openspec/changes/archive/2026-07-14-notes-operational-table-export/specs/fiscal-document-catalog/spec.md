## ADDED Requirements

### Requirement: Projeção legível na listagem e no detalhe
O sistema SHALL expor na API de catálogo e na interface os campos de projeção necessários à triagem sem download do XML: número da NFS-e, nomes de emitente e tomador quando parseados, valor do serviço, competência, papel fiscal, situação, locais de emissão/prestação quando disponíveis e código de situação oficial quando existir.

#### Scenario: Listagem com projeção enriquecida
- **WHEN** o cliente autentica e lista notas do escritório
- **THEN** cada item inclui os campos de projeção persistidos (incluindo `number`, `issuer_name`, `taker_name`, `service_amount`, `competence`, `fiscal_role`, `status`) sem o corpo XML

#### Scenario: Detalhe sem XML embutido
- **WHEN** o usuário abre o detalhe por chave de acesso
- **THEN** a resposta inclui a projeção completa da nota, eventos e metadados do documento, e MUST NOT incluir bytes XML no JSON

#### Scenario: Fallback quando nome ausente
- **WHEN** o parse não obteve razão social de uma parte
- **THEN** a interface e a API ainda expõem o CNPJ da parte e não inventam nome

### Requirement: Agregação por cliente do escritório
O sistema SHALL oferecer consulta agregada de notas por cliente do escritório ativo (identidade do cliente e contagem de notas no escopo filtrado), aplicando o mesmo isolamento por `office_id` e os filtros de catálogo aplicáveis, sem exigir que o cliente baixe todas as páginas do cursor para montar a aba Por empresa.

#### Scenario: Resumo por cliente
- **WHEN** o usuário autorizado solicita a visão por empresa com filtros de competência ou status
- **THEN** a API devolve uma linha por cliente do escritório que possua notas no escopo, com contagem coerente e sem dados de outro office

#### Scenario: Cliente sem notas no filtro
- **WHEN** um cliente do escritório não possui notas no escopo filtrado
- **THEN** ele não aparece na agregação (ou aparece com contagem zero somente se o contrato da API documentar inclusão explícita — padrão: omitir)

#### Scenario: Sem vazamento
- **WHEN** a resposta de agregação é inspecionada
- **THEN** não há XML, vault_object_id, PFX ou material sensível

## MODIFIED Requirements

### Requirement: Consulta paginada e filtrável
O sistema SHALL listar notas com paginação por cursor e filtros combináveis por texto de triagem (`q`), cliente, estabelecimento, papel, situação, competência, data de emissão e identificadores de partes quando aplicável.

#### Scenario: Competência diferente da emissão
- **WHEN** o usuário filtra por competência sem informar data de emissão
- **THEN** o sistema aplica somente o período de competência e não confunde os dois campos

#### Scenario: Busca textual de triagem
- **WHEN** o usuário informa `q` com trecho de número, nome de parte, CNPJ ou chave
- **THEN** a listagem restringe aos registros do office que casam com o critério sem retornar XML
