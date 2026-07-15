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
