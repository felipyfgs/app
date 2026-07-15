## ADDED Requirements

### Requirement: CSC e ID CSC protegidos por estabelecimento e ambiente
O sistema SHALL permitir que ADMIN com 2FA recente cadastre ou substitua CSC e ID CSC para um estabelecimento e ambiente de NFC-e modelo 65, MUST armazená-los por envelope no `SecureObjectStore` e MUST NOT expor seu conteúdo por API, log, auditoria, exportação ou rota de recuperação. Essas credenciais SHALL ser opcionais para consulta 562 e recuperação oficial e somente exigidas quando fallback mutante estiver formalmente habilitado.

#### Scenario: Cadastro de CSC
- **WHEN** ADMIN com 2FA recente informa CSC e ID válidos para estabelecimento MA e ambiente explícito
- **THEN** o sistema grava o segredo no cofre e devolve somente estado configurado, ambiente e metadados não sensíveis

#### Scenario: Consulta não mutante sem CSC
- **WHEN** perfil modelo 65 possui A1 e semente válidos, mas não possui CSC
- **THEN** consulta de protocolo 562 e ingestão de pacote oficial permanecem elegíveis

#### Scenario: Modelo 55
- **WHEN** o perfil é NF-e modelo 55
- **THEN** a API e a interface MUST NOT solicitar nem usar CSC/ID CSC

#### Scenario: Substituição falha
- **WHEN** novo CSC/ID não passa na validação do leiaute
- **THEN** o segredo anterior permanece ativo e o valor inválido não é persistido nem logado

#### Scenario: Leitura de credencial
- **WHEN** qualquer usuário consulta o estado do CSC
- **THEN** a resposta indica somente configurado/ausente/inválido e nunca contém CSC, ID em claro ou referência do vault

## MODIFIED Requirements

### Requirement: Uso do A1 da raiz em múltiplos canais oficiais
O sistema SHALL reutilizar o certificado e-CNPJ A1 ativo da raiz do cliente somente para canais fiscais habilitados e pertencentes ao escopo escritural: ADN NFS-e, SEFAZ DistDFe/CT-e e consulta/recuperação de NF-e/NFC-e de saída MA. Operação mutante MA MUST cumprir os gates próprios antes de materializar o A1. O sistema MUST NOT armazenar cópia adicional do PFX fora do vault, expor material criptográfico ou usar o A1 para captura MDF-e.

#### Scenario: Mesmo A1 para ADN e DistDFe
- **WHEN** o estabelecimento tem captura ADN e captura DistDFe habilitadas
- **THEN** ambos os jobs obtêm o PFX do mesmo objeto de vault da raiz e o usam somente em memória

#### Scenario: Mesmo A1 para consulta outbound MA
- **WHEN** o estabelecimento MA está elegível para consulta de protocolo de saída
- **THEN** o job obtém o mesmo A1 ativo da raiz somente em memória, sem criar nova cópia de credencial

#### Scenario: Fallback mutante sem gates
- **WHEN** a flag mutante está desligada ou mandato/allowlist/2FA/aprovação está incompleto
- **THEN** o sistema não materializa o A1 nem o CSC para inutilização, transmissão ou cancelamento

#### Scenario: Canal MDF-e legado
- **WHEN** existe configuração legada para captura MDF-e
- **THEN** o sistema não materializa o A1 nem enfileira job para esse canal

### Requirement: Elegibilidade de captura por canal
O sistema SHALL expor elegibilidade para os canais escriturais suportados ADN, DistDFe, CT-e e saída MA, com motivos específicos. Para saída MA, SHALL considerar UF, modelo, ambiente, A1, semente/série, mandato, allowlist, feature flags, estado do cursor e modo `ASSISTED|AUTOMATIC`; CSC MUST ser critério apenas para fallback mutante de modelo 65. O sistema MUST NOT publicar MDF-e como canal elegível.

#### Scenario: DistDFe inelegível sem A1
- **WHEN** o cliente não possui credencial A1 ativa
- **THEN** a elegibilidade DistDFe é falsa e o job não é enfileirado

#### Scenario: Outbound MA inelegível sem semente
- **WHEN** o estabelecimento MA possui A1, mas não possui semente válida para a série
- **THEN** a consulta daquela série é inelegível com motivo acionável e nenhum job é enfileirado

#### Scenario: Consulta NFC-e sem CSC
- **WHEN** perfil 65 possui A1/semente/mandato válidos e somente a consulta 562 está ligada
- **THEN** o canal de consulta pode ser elegível mesmo sem CSC

#### Scenario: M2M indisponível
- **WHEN** não há contrato máquina-a-máquina aprovado para a plataforma MA
- **THEN** a elegibilidade informa `capture_mode=ASSISTED` e não anuncia recuperação automática

#### Scenario: Resumo de elegibilidade
- **WHEN** a API lista a elegibilidade de captura de um cliente
- **THEN** a resposta não contém o canal `MDFE_DISTDFE`

### Requirement: A1 do cliente só quando for desbloquear ou manifestar
O sistema SHALL usar o A1 ACTIVE da raiz do cliente para DistDFe, ciência/MD-e opcional e, quando habilitado e elegível, consulta/recuperação de NF-e/NFC-e de saída MA; MUST usar sempre o certificado do cliente, nunca o certificado do escritório. Operações mutantes de saída MUST permanecer bloqueadas sem os gates adicionais definidos para o canal.

#### Scenario: Sem A1
- **WHEN** o operador tenta obter XML completo via ciência e não há A1
- **THEN** a ação falha com motivo claro; o catálogo e o download do que já existe continuam funcionando

#### Scenario: Saída MA sem A1
- **WHEN** consulta ou recuperação automática MA é disparada sem A1 ACTIVE da raiz
- **THEN** o job não chama serviço externo, registra motivo sanitizado e mantém pacotes já importados disponíveis

#### Scenario: Certificado do escritório
- **WHEN** um fluxo de saída tenta usar certificado pertencente ao escritório em lugar do cliente emitente
- **THEN** o sistema rejeita a operação antes da chamada externa

