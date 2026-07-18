## ADDED Requirements

### Requirement: Lista de clientes deve projetar a validade oficial de procuração

O sistema MUST retornar para cada cliente do `CurrentOffice` o estado
sanitizado da procuração, sua validade e a data da última verificação, sem
consultar a SERPRO durante a leitura e sem expor evidência, poderes, autor,
NI, protocolo ou identificadores internos de tenant.

#### Scenario: Procuração ativa e vigente

- **WHEN** a projeção oficial local estiver `authorized` e a validade ainda
  não tiver passado
- **THEN** a API MUST retornar `authorized`, a validade e a última verificação

#### Scenario: Procuração expirada desde a última sincronização

- **WHEN** a projeção armazenada estiver `authorized` mas `valid_to` estiver
  no passado
- **THEN** a API MUST retornar `expired` sem alterar a evidência nem chamar a
  SERPRO

#### Scenario: Cliente sem evidência oficial

- **WHEN** não existir projeção oficial de procuração para o cliente
- **THEN** a API MUST retornar `unverified` e MUST NOT inferir autorização por
  dados manuais ou poderes isolados

### Requirement: Coluna de Procuração deve orientar sem iniciar sincronização

A tela `/clients` SHALL usar o contrato sanitizado da lista para mostrar uma
coluna de Procuração com estado e validade comparáveis ao resumo de
certificado, sem iniciar chamada externa ou aceitar `office_id` no navegador.

#### Scenario: Procuração a vencer

- **WHEN** a validade oficial estiver dentro de 30 dias e ainda não tiver
  expirado
- **THEN** a interface MUST mostrar estado de atenção, data de vencimento e
  orientação para renovar no e-CAC

#### Scenario: Abertura da lista

- **WHEN** o usuário abre `/clients`
- **THEN** a interface MUST realizar apenas a leitura local da lista e MUST NOT
  acionar sincronização de procurações
