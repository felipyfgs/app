## MODIFIED Requirements

### Requirement: Custódia exclusiva de segredos no vault
O sistema MUST armazenar Consumer Key, Consumer Secret, senha e material PFX, Bearer, JWT e quaisquer tokens derivados somente no `SecureObjectStore`, cifrados com AAD que identifique propósito, ambiente e versão. Banco, Redis, logs, auditoria, filas, exceções, linha de comando e respostas HTTP SHALL conter apenas metadados sanitizados e hashes não reversíveis.

#### Scenario: Consulta administrativa sanitizada
- **WHEN** um `PLATFORM_ADMIN` autorizado e com senha recentemente confirmada consulta a credencial ou o estado do contrato
- **THEN** a API retorna versão, estado, fingerprint, titular e datas, sem segredo, senha, token, XML bruto ou identificador interno do vault

#### Scenario: Varredura de superfícies persistentes
- **WHEN** a suíte de segurança inspeciona banco, Redis, payloads de jobs, logs e respostas de API após autenticação e autorização
- **THEN** nenhum segredo ou material canônico recuperável é encontrado fora do vault

### Requirement: Ativação global com quatro olhos
Ativar, substituir ou desbloquear um contrato produtivo e retirar o kill switch global MUST exigir dois `PLATFORM_ADMIN` distintos, ambos com reconfirmação da própria senha válida por no máximo quinze minutos, motivo e janela de mudança. TOTP MUST NOT ser exigido. A ativação MUST validar leitura do vault, horizonte mínimo do certificado e OAuth real com a versão pendente antes do cutover, sem chamada de negócio.

#### Scenario: Um único aprovador
- **WHEN** somente um administrador aprova a ativação ou a retirada do kill switch
- **THEN** a mudança permanece pendente e a versão anterior não é alterada

#### Scenario: Confirmação de um aprovador expira
- **WHEN** um dos dois administradores não possui reconfirmação de senha válida no instante de sua aprovação
- **THEN** essa aprovação SHALL ser rejeitada sem aproveitar a confirmação do outro ator

#### Scenario: OAuth da versão pendente falha
- **WHEN** o teste mTLS/OAuth pré-cutover não retorna o par de tokens válido
- **THEN** a versão pendente não pode ser marcada ativa

