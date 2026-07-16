# serpro-credenciais-produtivas Specification

## Purpose

Custódia, rotação e ativação controlada de credenciais SERPRO (PFX/OAuth) no vault, com autenticação canônica e four-eyes em cutover produtivo.

## Requirements


### Requirement: Custódia exclusiva de segredos no vault
O sistema MUST armazenar Consumer Key, Consumer Secret, senha e material PFX, Bearer, JWT e quaisquer tokens derivados somente no `SecureObjectStore`, cifrados com AAD que identifique propósito, ambiente e versão. Banco, Redis, logs, auditoria, filas, exceções, linha de comando e respostas HTTP SHALL conter apenas metadados sanitizados e hashes não reversíveis.

#### Scenario: Consulta administrativa sanitizada
- **WHEN** um `PLATFORM_ADMIN` com TOTP consulta a credencial ou o estado do contrato
- **THEN** a API retorna versão, estado, fingerprint, titular e datas, sem segredo, senha, token, XML bruto ou identificador interno do vault

#### Scenario: Varredura de superfícies persistentes
- **WHEN** a suíte de segurança inspeciona banco, Redis, payloads de jobs, logs e respostas de API após autenticação e autorização
- **THEN** nenhum segredo ou material canônico recuperável é encontrado fora do vault

### Requirement: Rotação obrigatória das credenciais expostas
O sistema SHALL considerar comprometida qualquer versão marcada como exposta e MUST impedir que ela satisfaça o gate produtivo. A rotação MUST criar uma nova versão, validar mTLS/OAuth com ela, invalidar caches do par Bearer/JWT e retirar a versão anterior em uma transição auditada, considerando que uma Consumer Key/Secret renovada pode afetar outras APIs SERPRO do mesmo contratante.

#### Scenario: Versão exposta ainda ativa
- **WHEN** o preflight encontra a versão de Consumer Key/Secret marcada como exposta ou sem evidência de rotação posterior
- **THEN** o estado permanece `CREDENTIALS_BLOCKED` e nenhuma chamada produtiva de negócio é elegível

#### Scenario: Rotação coordenada bem-sucedida
- **WHEN** a nova versão autentica com o e-CNPJ correto e o operador confirma o impacto nas demais APIs compartilhadas
- **THEN** o sistema ativa atomicamente a nova versão, invalida tokens antigos e registra somente evidência sanitizada da transição

### Requirement: Autenticação canônica mTLS e OAuth2
O autenticador produtivo MUST usar `POST https://autenticacao.sapi.serpro.gov.br/authenticate`, mTLS com o e-CNPJ válido do contratante, `Authorization: Basic`, `role-type: TERCEIROS`, `Content-Type: application/x-www-form-urlencoded` e `grant_type=client_credentials`. O cliente MUST validar hostname e cadeia TLS e MUST NOT oferecer opção produtiva equivalente a `curl -k`.

#### Scenario: Emissão válida do par de tokens
- **WHEN** o certificado corresponde ao contratante ativo e a nova Key/Secret é válida
- **THEN** o sistema aceita somente uma resposta que contenha Bearer e JWT com expiração válida e armazena ambos cifrados

#### Scenario: Certificado ou protocolo divergente
- **WHEN** o titular do PFX, o endpoint, o `role-type`, o content type ou a validação TLS diverge do contrato oficial
- **THEN** a autenticação falha fechada, o contrato não é marcado saudável e o erro é sanitizado

### Requirement: Renovação atômica de Bearer e JWT
O sistema MUST tratar Bearer e JWT como um único par versionado, renová-los antes da expiração com lock distribuído e, após um `401`, invalidar e renovar o par apenas uma vez antes de devolver erro. Um token de uma versão de credencial MUST NOT ser reutilizado por outra versão ou ambiente.

#### Scenario: Concorrência na expiração
- **WHEN** múltiplos workers encontram o par próximo da expiração
- **THEN** apenas um worker solicita novos tokens e os demais usam o mesmo par validado após o lock

#### Scenario: Segundo 401 consecutivo
- **WHEN** a repetição após uma única renovação também retorna `401`
- **THEN** o cliente interrompe tentativas, registra indisponibilidade sanitizada e não cria loop de autenticação

### Requirement: Validação de certificado e cadeia de confiança
O preflight MUST validar titular, CNPJ, finalidade, chave privada, validade temporal, cadeia confiável, hostname, TLS 1.2 ou superior e handshake nos endpoints oficiais. A cadeia vigente do SERPRO e sua transição MUST ser testadas no endpoint de validação publicado antes da promoção produtiva.

#### Scenario: Cadeia nova não confiável
- **WHEN** o ambiente de execução não valida a cadeia vigente no endpoint de validação SERPRO
- **THEN** o gate `TLS_OK` falha e os drivers reais permanecem desligados

#### Scenario: Certificado próximo do vencimento
- **WHEN** um certificado entra nas janelas configuradas de 90, 60, 30, 15 ou 7 dias
- **THEN** o sistema alerta os responsáveis sem expor o arquivo e impede promoção se a janela mínima operacional não for atendida

### Requirement: Preflight produtivo sem doubles
Em `APP_ENV=production`, o sistema MUST rejeitar `SERPRO_USE_FAKE_CLIENTS=true`, driver `simulated`, binding Fake/Disabled que produza sucesso sintético e qualquer base URL não aprovada para uma capacidade marcada `real`. Defaults de flags e drivers SHALL permanecer desligados.

#### Scenario: Fake client configurado em produção
- **WHEN** o preflight detecta cliente fake, resposta sintética ou fallback de driver para uma capacidade real
- **THEN** ele encerra com código não zero e nenhuma chamada remota é iniciada

#### Scenario: Capacidade ainda desligada
- **WHEN** uma capacidade não foi promovida explicitamente
- **THEN** o sistema a mantém `disabled` sem interpretar isso como falha das demais capacidades

### Requirement: Ativação global com quatro olhos
Ativar, substituir ou desbloquear um contrato produtivo e retirar o kill switch global MUST exigir dois `PLATFORM_ADMIN` distintos, ambos com TOTP recente, motivo e janela de mudança. A ativação MUST validar leitura do vault, horizonte mínimo do certificado e OAuth real com a versão pendente antes do cutover, sem chamada de negócio.

#### Scenario: Um único aprovador
- **WHEN** somente um administrador aprova a ativação ou a retirada do kill switch
- **THEN** a mudança permanece pendente e a versão anterior não é alterada

#### Scenario: OAuth da versão pendente falha
- **WHEN** o teste mTLS/OAuth pré-cutover não retorna o par de tokens válido
- **THEN** a versão pendente não pode ser marcada ativa

### Requirement: Material produtivo fora do workspace
PFX, PEM, senha, export de token, PDF contratual contendo dados sensíveis e dumps MUST NOT permanecer no repositório ou imagem de aplicação. Após importação verificada no vault, o fluxo SHALL confirmar permissões seguras e orientar a remoção controlada da cópia transitória, preservando somente referência documental protegida conforme retenção aprovada.

#### Scenario: Preflight encontra artefato local
- **WHEN** a varredura de produção detecta certificado, chave privada, token ou contrato sensível no workspace/imagem
- **THEN** o readiness falha e reporta apenas tipo e caminho sanitizado, sem imprimir conteúdo

### Requirement: Ciclo de vida da chave mestra e recuperação
O vault MUST suportar versionamento de chave, recriptografia controlada e leitura durante a janela de migração sem confundir versão de AAD com versão criptográfica. Backups MUST excluir a chave mestra, cifrar artefatos sensíveis e ter restauração ensaiada que comprove a descriptografia com a chave externa correta.

#### Scenario: Rotação da chave mestra
- **WHEN** uma nova chave mestra é promovida
- **THEN** objetos existentes são recriptografados de forma retomável, auditada e testada antes da retirada da chave anterior

#### Scenario: Restore sem chave correta
- **WHEN** um exercício de restauração não recebe a chave externa compatível
- **THEN** a recuperação falha de forma explícita e nenhum dado é considerado restaurado com sucesso
