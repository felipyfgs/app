# simples-mei-monitoring Specification

## Purpose
TBD - created by archiving change automatizar-servicos-publicos-mei. Update Purpose after archive.
## Requirements
### Requirement: Sucesso no portal evita consumo SERPRO
O backend SHALL encerrar a cadeia de providers quando o portal retorna resultado válido e SHALL registrar que nenhuma chamada ou consumo SERPRO ocorreu naquela operação.

#### Scenario: Consulta de dívida ativa concluída no portal
- **WHEN** `pgmei.dividaativa` retorna resultado portal válido
- **THEN** a tentativa usa proveniência `RECEITA_PORTAL`, o provider SERPRO não é chamado e nenhum consumo SERPRO é lançado

### Requirement: Emissão de DAS protegida e idempotente
As operações `pgmei.gerardaspdf` e `pgmei.gerardascodbarra` SHALL exigir preflight, autorização, confirmação e chave de idempotência, preservando resultado incerto sem novo envio.

#### Scenario: Repetição da mesma competência
- **WHEN** o cliente repete uma emissão com a mesma chave e fingerprint
- **THEN** o backend reutiliza a operação/tentativa existente e não cria segundo job portal

#### Scenario: Timeout após submissão
- **WHEN** o portal pode ter gerado a guia antes de um timeout
- **THEN** a operação termina `UNCERTAIN`, não chama SERPRO e exige reconciliação

### Requirement: Histórico DASN não promove cobertura parcial
O backend SHALL persistir e apresentar a cobertura retornada por `dasnsimei.consultimadecrec` sem preencher campos integrais a partir de resumo.

#### Scenario: Resumo público DASN
- **WHEN** o provider retorna `coverage=SUMMARY`
- **THEN** API e Nuxt identificam o resultado como resumo e não oferecem recibo integral inexistente

### Requirement: Artefatos e proveniência autorizados
O Laravel SHALL ingerir artefatos portal no `SecureObjectStore`, expor somente downloads autorizados do escritório atual e preservar `PORTAL_ARTIFACT` na resposta pública.

#### Scenario: Download por outro escritório
- **WHEN** usuário autenticado tenta baixar DAS ou evidência pertencente a outro escritório
- **THEN** o backend responde como recurso inexistente sem revelar descriptor interno

### Requirement: Interface assíncrona dos serviços públicos
O Nuxt SHALL oferecer emissão de DAS por competência, histórico DASN-SIMEI, estado de processamento, ação pendente, artefatos e badges de provider usando exclusivamente rotas Laravel.

#### Scenario: Job portal em processamento
- **WHEN** uma tentativa ainda está `QUEUED` ou `RUNNING`
- **THEN** a interface mostra progresso estável e atualiza o estado sem chamar o microserviço diretamente

#### Scenario: Contingência SERPRO
- **WHEN** o portal falha de forma recuperável antes da submissão e SERPRO conclui a operação
- **THEN** a interface mostra proveniência SERPRO e indicação de contingência sem apresentar sucesso portal

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

