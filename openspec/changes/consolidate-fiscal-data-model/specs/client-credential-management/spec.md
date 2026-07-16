## ADDED Requirements

### Requirement: Cliente canônico por raiz do CNPJ
O sistema MUST manter exatamente um Cliente não excluído por raiz de CNPJ e escritório e SHALL associar a esse Cliente todos os Estabelecimentos da mesma raiz, preservando o CNPJ completo normalizado e imutável em cada Estabelecimento.

#### Scenario: Segunda filial da mesma raiz
- **WHEN** um usuário autorizado cadastra um CNPJ completo cuja raiz já pertence a Cliente do escritório ativo
- **THEN** o sistema cria somente o novo Estabelecimento sob o Cliente existente e não cria outro Cliente

#### Scenario: CNPJ de raiz diferente
- **WHEN** uma associação tenta ligar Estabelecimento a Cliente de outra raiz
- **THEN** a aplicação e a integridade persistente rejeitam a operação sem alterar o cadastro vigente

#### Scenario: Mesma raiz em outro escritório
- **WHEN** a mesma raiz já existe em outro escritório
- **THEN** o sistema permite o Cliente independente no escritório ativo e não revela nem reutiliza o registro externo

### Requirement: Autoridade única de matriz e certificado da raiz
O sistema MUST garantir no máximo uma matriz ativa por Cliente e MUST manter o A1 ativo somente na raiz do Cliente, sem `matrix_client_id`, cópia de PFX ou credencial concorrente por filial.

#### Scenario: Promoção de nova matriz
- **WHEN** uma operação válida altera qual Estabelecimento é a matriz
- **THEN** a troca ocorre atomicamente e nunca deixa duas matrizes ativas

#### Scenario: Canal de uma filial
- **WHEN** uma filial elegível inicia captura ADN ou SEFAZ
- **THEN** o job resolve o A1 ativo do Cliente raiz e o materializa somente em memória

### Requirement: Consolidação cadastral sem perda
A migração MUST agrupar cadastros legados por `office_id` e raiz, preservar identificadores por mapa de correspondência e bloquear registros ambíguos antes do corte.

#### Scenario: Cliente legado por filial
- **WHEN** mais de um Cliente legado do mesmo escritório representa CNPJs da mesma raiz
- **THEN** o backfill cria ou seleciona uma raiz canônica, reassocia os Estabelecimentos e preserva contatos, atributos, credenciais e histórico sem duplicar o A1

#### Scenario: Conflito de duas credenciais ativas
- **WHEN** a consolidação encontra mais de um A1 reivindicado como ativo para a mesma raiz
- **THEN** nenhum segredo é exposto, a ativação canônica fica bloqueada e a divergência é encaminhada para revisão administrativa

