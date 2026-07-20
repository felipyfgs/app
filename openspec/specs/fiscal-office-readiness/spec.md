# fiscal-office-readiness Specification

## Purpose
TBD - created by archiving change simplificar-ativacao-fiscal. Update Purpose after archive.
## Requirements
### Requirement: Onboarding único e assíncrono do escritório
O sistema SHALL aceitar A1, senha e consentimento em um único fluxo, armazenar segredos somente no vault e retornar `202` enquanto avança por `CONFIGURANDO`, `VALIDANDO`, `AUTORIZANDO`, `CARREGANDO_PROCURACOES`, `SINCRONIZANDO` e `PRONTO`.

#### Scenario: Certificado válido é cadastrado
- **WHEN** o administrador do escritório envia A1, senha e consentimento válidos
- **THEN** o sistema valida vigência/identidade, gera e assina o Termo, obtém token do procurador, inicia procurações e coleta sem etapa manual adicional

#### Scenario: Segredo é consultado no status
- **WHEN** o frontend consulta o progresso do onboarding
- **THEN** nenhuma senha, PFX, consumer secret, token ou payload fiscal é retornado

### Requirement: Procuração oficial por cliente
O sistema MUST usar `PROCURACOES/OBTERPROCURACAO41` com `outorgante`, `tipoOutorgante`, `outorgado` e `tipoOutorgado` obrigatórios, consultando cada cliente ativo como outorgante e o autor do escritório como outorgado em lotes da fila fiscal.

#### Scenario: Onboarding alcança clientes ativos
- **WHEN** o token do procurador fica disponível
- **THEN** um sync idempotente é agendado para cada cliente ativo pertencente ao escritório

#### Scenario: Novo cliente é cadastrado
- **WHEN** um cliente ativo é adicionado depois do onboarding pronto
- **THEN** a sincronização oficial de sua procuração é agendada automaticamente

### Requirement: Persistência e matriz completa de poderes
O sistema SHALL persistir a evidência em `ClientProcuracaoSnapshot`, os poderes em `TaxProxyPower` e mapear os sistemas oficiais concedidos pela matriz completa de serviços × procurações, preservando evidências desconhecidas sem conceder poder indevido.

#### Scenario: Resposta contém múltiplos sistemas
- **WHEN** a SERPRO retorna sistemas concedidos e `dtexpiracao`
- **THEN** todos os poderes reconhecidos são persistidos com validade e os desconhecidos permanecem na evidência sem liberar operações

### Requirement: Validade, frescor e alertas
O sistema SHALL converter `dtexpiracao` em `America/Sao_Paulo`, expirar localmente procurações e certificados, renovar evidência oficial no máximo a cada sete dias e produzir alertas internos em 30, 7 e 1 dia antes do vencimento.

#### Scenario: Verificação diária sem snapshot antigo
- **WHEN** a manutenção diária encontra evidência recente ainda válida
- **THEN** ela atualiza estados e alertas localmente sem nova chamada SERPRO

#### Scenario: Data de validade passou
- **WHEN** `valid_to` é anterior ao instante atual
- **THEN** a procuração ou certificado é marcado vencido e operações dependentes são bloqueadas

### Requirement: Consulta atualiza procuração quando necessário
O sistema SHALL executar diretamente com snapshot válido e recente, atualizar automaticamente snapshot ausente/antigo e bloquear somente as operações cujo poder esteja inexistente ou vencido.

#### Scenario: Consulta manual encontra snapshot antigo
- **WHEN** uma consulta manual exige poder e a evidência tem mais de sete dias
- **THEN** a API retorna `202`, agenda/acompanha a atualização e continua a operação quando a evidência confirmar elegibilidade

### Requirement: Agenda e coleta inicial independentes da credencial
O sistema SHALL manter agenda mensal por módulo entre os dias 1 e 28, atribuir dia automático determinístico por padrão e separar manutenção de certificado, Termo, token e procurações da coleta fiscal mensal.

#### Scenario: Onboarding concluído
- **WHEN** o escritório alcança readiness e possui clientes elegíveis
- **THEN** a primeira coleta é iniciada imediatamente e as próximas seguem a agenda mensal sem exigir ativação de módulo

### Requirement: Estado de procuração visível por cliente
O sistema SHALL apresentar `Autorizada`, `Vence em breve`, `Vencida`, `Não encontrada`, `Verificando` ou `Falha ao verificar`, com expiração, última verificação e módulos cobertos.

#### Scenario: Procuração vence em sete dias
- **WHEN** o usuário consulta o cliente cuja procuração válida vence em até sete dias
- **THEN** a UI mostra “Vence em breve”, a data de expiração e os módulos cobertos

