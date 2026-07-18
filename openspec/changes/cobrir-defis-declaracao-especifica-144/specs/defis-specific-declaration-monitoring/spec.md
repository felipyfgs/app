## ADDED Requirements

### Requirement: Referência opaca para declaração DEFIS
O sistema SHALL guardar o identificador retornado pela DEFIS 142 apenas no
cofre, associado a uma referência local tenant-scoped, sem devolvê-lo em
evidência, projeção, API ou logs.

#### Scenario: Lista DEFIS com identificador válido
- **WHEN** a consulta 142 retorna uma declaração válida com `idDefis`
- **THEN** o sistema cria ou reutiliza uma referência opaca local para uso da 144

### Requirement: Consulta específica confirmada
O sistema SHALL aceitar a consulta DEFIS 144 somente por referência opaca do
cliente no escritório atual, com confirmação explícita e autorização de
sincronização fiscal.

#### Scenario: Referência de outro escritório
- **WHEN** um usuário tenta consultar uma referência que não pertence ao cliente
  do escritório atual
- **THEN** o sistema responde sem revelar o identificador SERPRO e não enfileira chamada externa

### Requirement: Evidência documental protegida
O sistema SHALL validar recibo e declaração da 144 de forma fail-closed,
armazenar os bytes somente no cofre e disponibilizar downloads autenticados por
descritores sem conteúdo sensível.

#### Scenario: Retorno documental válido
- **WHEN** a 144 retorna PDFs Base64 válidos para uma referência autorizada
- **THEN** o sistema cria descritores locais e permite download privado sem expor `idDefis` ou Base64
