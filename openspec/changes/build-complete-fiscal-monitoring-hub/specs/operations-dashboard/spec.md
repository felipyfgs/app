## MODIFIED Requirements

### Requirement: Resumo operacional
O sistema SHALL apresentar totais do escritório ativo para clientes, estabelecimentos, documentos, trabalhos, falhas, credenciais, autorizações fiscais, procurações, pendências, consumo SERPRO e franquia, com horário da última atualização e indicação de cobertura.

#### Scenario: Abertura do painel
- **WHEN** um usuário autenticado acessa o painel
- **THEN** o sistema mostra somente métricas agregadas do escritório ativo com horário da última atualização

#### Scenario: Integração parcialmente configurada
- **WHEN** o contrato global está saudável, mas o Termo do escritório ou procurações estão ausentes
- **THEN** o resumo mostra bloqueio do tenant e a próxima ação, sem expor credenciais globais

#### Scenario: Consumo próximo do limite
- **WHEN** o uso mensal do escritório alcança o limiar configurado
- **THEN** o resumo mostra consumo, franquia/saldo e deep-link para o detalhamento do próprio tenant

## ADDED Requirements

### Requirement: Inbox fiscal tipada
O sistema SHALL incluir itens sanitizados para Termo expirado, procuração ausente, consulta bloqueada, fonte indisponível, pendência fiscal, mensagem nova, guia vencendo, consumo elevado e resultado externo incerto.

#### Scenario: Procuração expirada
- **WHEN** poder necessário expira para um cliente vinculado
- **THEN** a inbox cria item acionável com módulo, cliente interno e serviço, sem conteúdo do instrumento ou token

#### Scenario: Resultado incerto de emissão
- **WHEN** uma operação mutante termina como `UNKNOWN_RESULT`
- **THEN** a inbox cria item crítico de reconciliação e não oferece retry imediato

### Requirement: Saúde SERPRO sanitizada por tenant
O sistema SHALL apresentar ao escritório somente disponibilidade, última execução, estado da sua autorização e bloqueios aplicáveis; detalhes do contrato global e incidentes de outros tenants MUST permanecer ocultos.

#### Scenario: Circuit breaker global aberto
- **WHEN** falha global impede chamadas de todos os tenants
- **THEN** o escritório vê indisponibilidade geral sanitizada sem métricas, consumo ou identidade de outros escritórios

### Requirement: Métricas fiscais não afirmam cobertura absoluta
O dashboard MUST separar pendência confirmada, atenção, desconhecido, não aplicável e não suportado e MUST NOT somar `UNKNOWN`/`UNSUPPORTED` como “em dia”.

#### Scenario: FGTS com cobertura parcial
- **WHEN** há fechamento eSocial conhecido, mas guia/pagamento não são consultáveis
- **THEN** o indicador explicita cobertura parcial e não apresenta o cliente como integralmente regular no FGTS Digital

