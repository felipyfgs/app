# mei-stack-boundaries Specification

## Purpose
TBD - created by archiving change alinhar-fronteiras-responsabilidades-stack. Update Purpose after archive.
## Requirements
### Requirement: Responsabilidades fixas por camada do stack
O sistema SHALL manter a divisão de responsabilidades: `apps/web` (Nuxt) como única UI de usuário; `apps/api` (Laravel) como dono de autenticação, tenancy, SERPRO, vault, auditoria e fonte de verdade durável; `services/mei` como executor interno efêmero de automação de portal via browser; Horizon como fila de jobs de domínio; Celery como fila exclusiva de execução de browser do sidecar MEI.

#### Scenario: SPA não chama o sidecar MEI
- **WHEN** uma tela do painel precisa de status ou artefato de automação MEI
- **THEN** o cliente Nuxt MUST chamar apenas a API Laravel e MUST NOT emitir requisições HTTP diretas ao serviço MEI

#### Scenario: Browser fora do Horizon
- **WHEN** uma operação exige automação de portal com Playwright
- **THEN** a execução MUST ocorrer no worker Celery do sidecar MEI e MUST NOT rodar dentro de um worker Horizon

#### Scenario: Sidecar sem ownership de negócio
- **WHEN** o serviço MEI processa um job
- **THEN** ele MUST NOT persistir tenancy, ledger comercial, vault de credenciais ou autenticação Sanctum/Fortify

### Requirement: Postgres é a fonte de verdade durável
O sistema SHALL tratar `mei_automation_attempts` (e demais projeções Laravel) como única fonte de verdade durável do ciclo de automação MEI. O Redis usado pelo sidecar SHALL armazenar apenas estado efêmero de job, idempotência de transporte e anti-replay, sujeitos a TTL.

#### Scenario: Sync antes do TTL
- **WHEN** o Laravel cria ou acompanha um job no sidecar MEI
- **THEN** ele MUST persistir e atualizar a tentativa em Postgres em intervalo de poll menor que o TTL do estado no Redis

#### Scenario: Estado efêmero perdido
- **WHEN** o job desaparece do Redis antes da sincronização completa e não houve submissão portal confirmada
- **THEN** o Laravel MUST registrar a tentativa em estado de perda de sync e MAY reenfileirar; o Redis MUST NOT ser tratado como SoT para reconstruir o histórico

### Requirement: Allowlist e redaction na fronteira Laravel→MEI
Antes de assinar e enviar o POST HMAC `/v1/jobs`, o Laravel SHALL aplicar allowlist de campos do `input` por operação e redaction de metadados, de modo que CNPJ completo, PII desnecessária, HTML de portal e segredos NÃO sejam expostos em logs, respostas públicas ou campos de metadata do sidecar.

#### Scenario: Campo fora da allowlist
- **WHEN** o orquestrador Laravel recebe um payload com campo não permitido para a operação
- **THEN** o sistema MUST rejeitar ou remover o campo antes do envio HMAC e MUST NOT encaminhar o valor ao MEI

#### Scenario: Metadata pública sem PII
- **WHEN** status de tentativa é exposto à API/UI
- **THEN** a resposta MUST omitir CNPJ cru, HTML e segredos, preservando apenas metadados sanitizados e `client_ref` opaco quando aplicável

### Requirement: Artefatos atravessam a fronteira apenas para o vault
O sistema SHALL ingerir artefatos do sidecar somente via download autenticado HMAC iniciado pelo Laravel, validar tipo/tamanho/digest e persistir o conteúdo no vault (`SecureObjectStore` ou store de evidência fiscal equivalente). O Nuxt MUST NOT baixar artefatos diretamente do MEI.

#### Scenario: Ingestão bem-sucedida
- **WHEN** um job MEI conclui com artefato disponível
- **THEN** o Laravel MUST baixar o artefato com HMAC, validar digest/tipo/tamanho, gravar no vault e atualizar a tentativa em Postgres

#### Scenario: Artefato expirado no sidecar
- **WHEN** o download retorna artefato ausente/expirado e a tentativa ainda não tem evidência no vault
- **THEN** o Laravel MUST marcar a tentativa com falha de ingestão e MUST NOT inventar conteúdo a partir do Redis

### Requirement: Filas e rede internas sem ambiguidade de ownership
O sistema SHALL documentar e preservar Redis DB lógico separado para o MEI (`/4`) versus filas/cache Laravel (`/0`, `/1`). O serviço MEI MUST permanecer apenas na rede interna Docker, sem port mapping público, e sua API HTTP MUST ser tratada exclusivamente como contrato interno Laravel↔MEI, nunca como API de produto.

#### Scenario: Exposição de rede
- **WHEN** a stack sobe em Compose local ou produção
- **THEN** o serviço MEI MUST NOT publicar porta no host e MUST aceitar tráfego somente da rede interna da aplicação

#### Scenario: Papel das filas
- **WHEN** um operador inspeciona a configuração de filas
- **THEN** a documentação operacional MUST declarar Horizon para domínio fiscal/SERPRO/SEFAZ e Celery somente para execução de browser MEI

