## ADDED Requirements

### Requirement: Ativação produtiva exige confirmação reforçada do proprietário
Ativar, substituir ou desbloquear um contrato produtivo, executar cutover de credencial e retirar kill switch global ou de solução MUST exigir autorização do Proprietário `PLATFORM_ADMIN` único. A autorização MUST conter reconfirmação da própria senha válida por no máximo quinze minutos, frase exata específica da operação, motivo, janela de mudança vigente e vínculo ao mesmo recurso, ambiente e ação. TOTP MUST NOT ser exigido. O sistema MUST persistir auditoria sanitizada e consumir a autorização no máximo uma vez.

A ativação e o cutover MUST continuar validando leitura do vault, horizonte mínimo do certificado e OAuth mTLS real com a versão pendente antes da troca, sem chamada fiscal de negócio. Essa autorização singular MUST NOT satisfazer ações cuja capability exige duas pessoas com papéis distintos, incluindo o canário faturável.

#### Scenario: Proprietário confirma uma ativação válida
- **WHEN** o Proprietário possui senha recentemente confirmada, digita a frase exata e informa motivo e janela vigentes para o recurso correto
- **THEN** a autorização SHALL ser registrada e a operação SHALL prosseguir somente após todos os demais gates produtivos

#### Scenario: Confirmação de senha ausente ou expirada
- **WHEN** a sessão não possui reconfirmação válida no instante da autorização
- **THEN** a operação MUST ser bloqueada antes de alterar contrato, credencial ou kill switch

#### Scenario: Frase, motivo ou janela inválidos
- **WHEN** a frase diverge da operação ou o motivo está vazio ou a janela não está vigente
- **THEN** a autorização MUST ser rejeitada sem produzir aprovação parcial ou efeito operacional

#### Scenario: Autorização é reutilizada ou pertence a outro recurso
- **WHEN** um serviço tenta consumir autorização já usada, expirada ou vinculada a outra ação, versão, contrato ou ambiente
- **THEN** a operação MUST permanecer bloqueada sem alterar a versão ativa

#### Scenario: OAuth da versão pendente falha
- **WHEN** o teste mTLS/OAuth pré-cutover não retorna o par de tokens válido
- **THEN** a versão pendente MUST permanecer `VERIFIED`, a versão anterior MUST permanecer ativa e nenhum segredo SHALL ser exposto

#### Scenario: Kill switch é retirado
- **WHEN** o Proprietário confirma a retirada global ou de solução com todos os campos e gates válidos
- **THEN** o switch SHALL ser desativado e a resposta MUST NOT aguardar um segundo `PLATFORM_ADMIN`

#### Scenario: CLI ou job tenta fabricar aprovação
- **WHEN** uma CLI ou job tenta criar confirmação humana ou executar sem autorização HTTP persistida e vigente
- **THEN** o sistema MUST bloquear a ação e MUST NOT fabricar ator, senha confirmada ou timestamp de aprovação

#### Scenario: Proprietário tenta aprovar sozinho um canário faturável
- **WHEN** a confirmação singular é apresentada para uma ação que exige Proprietário e `Office ADMIN` distintos
- **THEN** ela MUST satisfazer no máximo o papel global e a ação SHALL continuar bloqueada até a aprovação separada do Office

## REMOVED Requirements

### Requirement: Ativação global com quatro olhos
**Reason**: Uma instalação passa a possuir apenas um `PLATFORM_ADMIN`, tornando impossível exigir dois titulares globais sem recriar a complexidade removida pelo modelo de Proprietário único.

**Migration**: Ações globais de contrato, credencial e kill switch passam a usar confirmação reforçada e auditada do Proprietário. Aprovações pendentes do modelo anterior devem expirar e ser refeitas; aprovações dual-role de canário permanecem separadas.
