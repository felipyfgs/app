## ADDED Requirements

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
