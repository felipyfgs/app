# serpro-catalogo-oficial

## Purpose

Catálogo versionado e rastreável das 119 operações da API Integra Contador, controlando o que pode ser executado em produção.

## Requirements

### Requirement: Catálogo oficial integral e rastreável
O sistema SHALL manter exatamente 119 operações do Integra Contador: 98 produtivas, 19 em prospecção, 1 em construção e 1 cancelada, cada uma com coordenadas, rota, versão, estado, schemas, autenticação, poder, mutabilidade, faturamento, política assíncrona, módulo e fonte oficial.

#### Scenario: Validação do snapshot
- **WHEN** o comando de validação processar o snapshot versionado
- **THEN** ele SHALL falhar se contagens, campos obrigatórios, hashes ou coordenadas divergirem do contrato documental

### Requirement: Ausência de placeholders executáveis
O catálogo MUST rejeitar coordenadas provisórias, códigos de inventário ou operações executáveis sem fonte e schema oficiais.

#### Scenario: Entrada provisória encontrada
- **WHEN** uma entrada contiver placeholder ou não possuir evidência oficial suficiente
- **THEN** a importação SHALL falhar e nenhum driver real SHALL ser habilitado

### Requirement: Estado oficial controla execução
Somente operações com estado oficial produtivo e suporte de plataforma implementado SHALL ser resolvidas como executáveis.

#### Scenario: Operação não produtiva solicitada
- **WHEN** uma operação em prospecção, construção ou cancelada for solicitada
- **THEN** o resolver SHALL retornar erro fail-closed sem chamada externa

### Requirement: Histórico canônico imutável
Mudanças de coordenadas ou metadados SHALL criar uma nova versão efetiva e encerrar a anterior sem apagar histórico.

#### Scenario: Nova versão documental
- **WHEN** um snapshot posterior alterar uma operação existente
- **THEN** o importador SHALL preservar a versão anterior e ativar somente a nova versão validada
