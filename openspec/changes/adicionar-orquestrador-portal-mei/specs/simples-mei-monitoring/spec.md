## ADDED Requirements

### Requirement: Roteamento híbrido por operação
O backend SHALL selecionar uma cadeia ordenada de providers para cada operação `INTEGRA_MEI`, com defaults desabilitados e compatibilidade SERPRO quando a automação portal estiver OFF.

#### Scenario: Feature desligada
- **WHEN** a automação MEI não está habilitada para o escritório
- **THEN** a operação usa exatamente o provider SERPRO já existente

#### Scenario: Provider portal bem-sucedido
- **WHEN** a política prioriza portal e a execução retorna sucesso
- **THEN** nenhum request SERPRO é feito para aquela operação

### Requirement: Fallback classificado
O router SHALL avançar para o próximo provider somente em falha recuperável anterior à submissão e SHALL bloquear retry cego em validação fiscal ou resultado incerto.

#### Scenario: Drift antes de submissão
- **WHEN** o provider portal retorna `PORTAL_DRIFT` sem submeter efeito remoto
- **THEN** o router pode executar o próximo provider configurado e registra o motivo

#### Scenario: Resultado incerto
- **WHEN** o provider informa que a submissão pode ter ocorrido
- **THEN** a operação termina `UNCERTAIN` e nenhum provider seguinte é chamado

### Requirement: Tentativas tenant-scoped e auditáveis
O Laravel SHALL persistir tentativas ligadas a uma run ou mutação, sem aceitar `office_id` do cliente e sem armazenar CNPJ completo, segredo, captcha ou HTML bruto em campos públicos/logs.

#### Scenario: Consulta entre escritórios
- **WHEN** um usuário tenta acessar tentativa de cliente de outro escritório
- **THEN** o backend responde como recurso inexistente e não revela metadados

### Requirement: Proveniência do portal
Resultados live de portal oficial SHALL usar `source_provenance=RECEITA_PORTAL` e `verification_kind=PORTAL_ARTIFACT`, sem serem rotulados como `SERPRO_REAL`.

#### Scenario: Persistência de resultado portal
- **WHEN** um provider portal retorna artefato válido
- **THEN** snapshot, tentativa e resposta pública preservam a proveniência distinta
