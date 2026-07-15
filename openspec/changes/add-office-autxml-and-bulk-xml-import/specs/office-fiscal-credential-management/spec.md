## ADDED Requirements

### Requirement: Identidade fiscal própria do escritório
O sistema MUST manter uma identidade fiscal própria por escritório para o canal `NFE_AUTXML_DISTDFE`, composta pelo `office_id`, pelo CNPJ completo canônico usado em `autXML` e nas consultas e pela raiz desse CNPJ. O CNPJ completo MUST ser armazenado como texto de 14 caracteres, em maiúsculas, sem máscara e nunca como número, admitindo a composição numérica ou alfanumérica vigente. O ambiente fiscal SHALL pertencer à configuração/cursor de sincronização, sem duplicar a identidade ou o A1. O `office_id` MUST ser derivado da sessão autenticada e nunca aceito como autoridade a partir do cliente.

#### Scenario: Cadastro da identidade fiscal do escritório
- **WHEN** um ADMIN autenticado no escritório informa um CNPJ completo válido para o canal do Ambiente Nacional
- **THEN** o sistema persiste o CNPJ canônico, deriva sua raiz e vincula a identidade exclusivamente ao `office_id` da sessão, permitindo cursores separados por ambiente sem copiar a credencial

#### Scenario: Tentativa de cadastro entre escritórios
- **WHEN** um usuário tenta cadastrar, consultar ou alterar a identidade fiscal de outro `office_id`
- **THEN** o sistema rejeita a operação sem revelar se a outra identidade ou credencial existe

#### Scenario: CNPJ inválido ou não canônico
- **WHEN** o valor informado não pode ser normalizado para um CNPJ completo válido de 14 caracteres
- **THEN** o sistema rejeita o cadastro antes de receber ou persistir qualquer credencial fiscal

### Requirement: A1 do escritório como decisão operacional
O produto SHALL aceitar para automação desassistida somente certificado e-CNPJ A1 em contêiner PFX ou P12, embora o serviço oficial autentique certificado ICP-Brasil e não imponha A1 como único tipo. Antes da ativação, o sistema MUST validar em memória a presença da chave privada, a cadeia ICP-Brasil, o uso para autenticação de cliente, a validade temporal e a correspondência entre a raiz do CNPJ do certificado e a raiz do CNPJ completo canônico consultado, conforme a RV 593.

#### Scenario: A1 válido da mesma raiz
- **WHEN** um ADMIN envia PFX ou P12 A1 válido cuja raiz de CNPJ coincide com a identidade fiscal do escritório
- **THEN** o sistema aceita a credencial para validação e armazenamento seguro, ainda que o estabelecimento completo do certificado seja diferente do CNPJ completo canônico da consulta

#### Scenario: Rejeição preventiva equivalente à RV 593
- **WHEN** a raiz do CNPJ extraída do certificado difere da raiz do CNPJ completo que será consultado
- **THEN** o sistema rejeita a credencial antes da ativação e registra somente o código seguro `CERTIFICATE_BASE_MISMATCH`, sem material criptográfico ou senha

#### Scenario: Certificado sem aptidão para mTLS
- **WHEN** o arquivo não contém chave privada, não pertence à cadeia ICP-Brasil, não admite autenticação de cliente, está vencido ou ainda não é válido
- **THEN** o sistema rejeita ou bloqueia a ativação com motivo operacional sanitizado

#### Scenario: Certificado A3 apresentado
- **WHEN** um ADMIN tenta cadastrar certificado dependente de dispositivo A3
- **THEN** o sistema rejeita o formato por decisão operacional do produto e não afirma que a rejeição decorre de limitação do serviço oficial

### Requirement: Proteção integral do material criptográfico
O sistema MUST guardar PFX ou P12 e senha exclusivamente pelo `SecureObjectStore`, com criptografia de envelope e chave mestra externa ao banco de dados e aos backups comuns. O PFX, a senha, a chave privada e qualquer PEM MUST existir em claro somente em memória durante validação ou conexão mTLS e MUST NOT ser gravado em arquivo, fila, cache compartilhado, resposta de API, log, auditoria ou exportação.

#### Scenario: Armazenamento seguro do A1
- **WHEN** uma credencial passa por todas as validações
- **THEN** o sistema cifra o objeto e sua senha pelo cofre, descarta buffers e temporários em claro e persiste apenas a referência opaca e metadados não sensíveis

#### Scenario: Leitura da configuração pela API
- **WHEN** um usuário autorizado consulta a configuração fiscal do escritório
- **THEN** a API retorna somente estado, finalidade, CNPJ canônico, impressão digital, emissor e datas de validade, sem PFX, senha, chave privada, PEM ou referência utilizável do cofre

#### Scenario: Ausência de recuperação de certificado
- **WHEN** qualquer usuário tenta recuperar ou exportar o certificado ou sua senha
- **THEN** o sistema rejeita a operação porque não existe rota de recuperação de segredo

#### Scenario: Falha durante validação ou uso
- **WHEN** a biblioteca criptográfica ou a conexão mTLS produz uma exceção
- **THEN** o sistema sanitiza a exceção e MUST NOT registrar conteúdo do PFX, senha, chave, PEM, cabeçalhos TLS sensíveis ou XML fiscal

### Requirement: Administração protegida por papel e 2FA recente
O sistema MUST permitir criação, substituição, ativação, desativação e exclusão lógica da credencial fiscal do escritório somente a ADMIN com TOTP validado recentemente. OPERATOR e VIEWER MUST NOT alterar a identidade, a credencial, a finalidade ou o CNPJ completo canônico de consulta.

#### Scenario: Mutação autorizada
- **WHEN** um ADMIN com sessão válida e comprovação TOTP recente solicita uma mutação da credencial
- **THEN** o sistema executa a operação no `office_id` da sessão e registra auditoria sanitizada com ator, instante, finalidade e identificadores não secretos

#### Scenario: 2FA ausente ou vencida
- **WHEN** um ADMIN tenta modificar a credencial sem comprovação TOTP recente
- **THEN** o sistema exige nova verificação antes de receber o arquivo ou aplicar a mudança

#### Scenario: Papel sem privilégio
- **WHEN** OPERATOR ou VIEWER tenta cadastrar, substituir, ativar, desativar ou excluir a credencial
- **THEN** o sistema nega a operação e não inicia leitura ou decodificação do arquivo enviado

### Requirement: Credencial vinculada à finalidade
O sistema MUST vincular o A1 do escritório exclusivamente à finalidade `NFE_AUTXML_DISTDFE`. A credencial do escritório MUST NOT ser selecionável para manifestação do destinatário, emissão, cancelamento, inutilização, canais estaduais, captura do Maranhão ou fluxos DistDFe autenticados como cliente; credenciais de clientes MUST NOT ser selecionáveis para o stream `autXML` do escritório.

#### Scenario: Uso no canal autorizado
- **WHEN** o consumidor do stream `NFE_AUTXML_DISTDFE` solicita a credencial ativa do escritório
- **THEN** o cofre libera o objeto somente em memória para a conexão mTLS desse canal e desse `office_id`

#### Scenario: Tentativa de uso fiscal fora da finalidade
- **WHEN** um componente de emissão, manifestação, cancelamento, inutilização ou de captura autenticada como cliente solicita o A1 do escritório
- **THEN** o sistema rejeita a solicitação por incompatibilidade de finalidade antes de abrir o objeto seguro

#### Scenario: A1 de cliente no stream do escritório
- **WHEN** uma configuração tenta associar credencial pertencente a um cliente ao canal `NFE_AUTXML_DISTDFE`
- **THEN** o sistema rejeita a associação e mantém separados credencial, cursor, auditoria e saúde dos dois canais

### Requirement: Rotação e bloqueio seguro
O sistema MUST validar completamente uma nova versão antes de substituir atomicamente a credencial ativa. Credencial vencida, revogada, desativada ou incompatível MUST bloquear novas conexões do canal sem apagar cursor, documentos, quarentena ou evidências operacionais, e a rotação MUST NOT expor ou permitir baixar a versão anterior.

#### Scenario: Rotação bem-sucedida
- **WHEN** um ADMIN com 2FA recente envia uma nova credencial válida para a mesma finalidade e raiz de CNPJ
- **THEN** o sistema ativa a nova versão atomicamente, aposenta a anterior no cofre e preserva o mesmo stream e cursor

#### Scenario: Rotação inválida
- **WHEN** a nova credencial falha em qualquer validação
- **THEN** o sistema mantém a credencial anterior inalterada e registra a tentativa sem conteúdo sensível

#### Scenario: Expiração ou revogação detectada
- **WHEN** o sistema detecta expiração local ou rejeição de validade ou revogação pelo serviço oficial
- **THEN** o canal entra em estado bloqueado, gera alerta sanitizado e não descarta nem avança o cursor

### Requirement: Alertas de validade da credencial do escritório
O sistema SHALL classificar e alertar a credencial fiscal do escritório a 30, 7 e 1 dia do vencimento e MUST impedir novas chamadas mTLS após a expiração, sem bloquear credenciais ou cursores pertencentes aos clientes.

#### Scenario: Credencial vence em trinta dias
- **WHEN** a credencial ACTIVE do escritório alcança o marco de 30 dias para o vencimento
- **THEN** o sistema registra o alerta uma única vez por marco e o apresenta na inbox do escritório sem conteúdo secreto

#### Scenario: Credencial vencida no Scheduler
- **WHEN** o Scheduler encontra o A1 do escritório expirado antes de disparar `NFE_AUTXML_DISTDFE`
- **THEN** não materializa o PFX, bloqueia somente o cursor do escritório e mantém intactos os canais dos clientes
