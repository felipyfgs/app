## MODIFIED Requirements

### Requirement: Detalhe de Cliente organizado por seções
O sistema SHALL apresentar o detalhe de Cliente em página dedicada no arquétipo Settings com subnavegação para `Resumo`, `Cadastro`, `Estabelecimentos`, `Certificado A1` e `Sincronização`, condicionando conteúdo e ações às permissões e mantendo a seção reproduzível na URL.

#### Scenario: Abertura do cliente
- **WHEN** o usuário abre `/clients/:id` sem seção especificada
- **THEN** o sistema apresenta Resumo com identidade da raiz, estado e progresso do onboarding

#### Scenario: Seção Cadastro
- **WHEN** o usuário autorizado abre a seção Cadastro
- **THEN** o sistema apresenta dados da raiz, estado, origem/atualização e contatos em cards Settings, editáveis somente para administrador ou operador

#### Scenario: Operador sem gestão de credencial
- **WHEN** um usuário que não pode gerir A1 visualiza o onboarding
- **THEN** a etapa informa que o certificado é gerenciado por `ADMIN` sem expor formulário sensível nem representar falta de permissão como falha operacional

#### Scenario: Seção reproduzível
- **WHEN** o usuário abre uma URL válida da seção Cadastro, Estabelecimentos, Certificado A1 ou Sincronização
- **THEN** a toolbar destaca a seção e o corpo renderiza somente o conteúdo correspondente

## ADDED Requirements

### Requirement: Criação assistida permanece focada e transacional
O sistema SHALL apresentar a criação de Cliente em modal derivado de `customers/AddModal.vue`, solicitar o CNPJ completo e oferecer os dados básicos do onboarding, contato responsável, notas, A1 autorizado e campos adicionais sem reproduzir a ficha cadastral pública completa.

#### Scenario: Prévia encontrada
- **WHEN** a consulta de CNPJ retorna dados sanitizados
- **THEN** o modal preenche razão social e nome fantasia editáveis e não exige redigitar o CNPJ

#### Scenario: Consulta falha sem perder trabalho
- **WHEN** a consulta externa falha depois de o usuário preencher campos
- **THEN** o modal mantém valores não sensíveis, informa o fallback e permite continuar manualmente

#### Scenario: CNPJ já pertence a cliente do escritório
- **WHEN** a API informa que a raiz já está cadastrada no escritório ativo
- **THEN** o modal não cria duplicata e oferece abrir a seção Estabelecimentos do Cliente existente

#### Scenario: Criação concluída
- **WHEN** a API cria Cliente e primeiro Estabelecimento
- **THEN** a interface fecha e limpa o modal, informa sucesso e navega ao detalhe do novo Cliente

#### Scenario: Contato responsável opcional
- **WHEN** o usuário informa nome e ao menos um canal do contato interno responsável
- **THEN** a interface envia o contato separado do e-mail e telefone públicos e a API o cria na mesma transação do cadastro inicial

#### Scenario: Certificado opcional autorizado
- **WHEN** um administrador com 2FA informa PFX e senha válidos no modal
- **THEN** a interface cria o cadastro básico, ativa o A1 pelo endpoint protegido, limpa o material sensível e navega ao Cliente sem expor senha ou PFX

#### Scenario: Campo adicional secreto
- **WHEN** um administrador autorizado adiciona um campo do tipo Segredo
- **THEN** a interface envia o valor somente na gravação e, depois, apresenta apenas rótulo e estado configurado sem recuperar o conteúdo

### Requirement: Manutenção cadastral completa segue o arquétipo Settings
O sistema SHALL oferecer formulários completos de Cliente, contatos, campos adicionais e estabelecimentos usando `UForm`, cards e overlays reconhecíveis do template fixado, sem transformar overlays focados em fichas públicas extensas.

#### Scenario: Edição da raiz
- **WHEN** um administrador ou operador edita dados na seção Cadastro
- **THEN** os campos são agrupados semanticamente, erros locais e 422 aparecem junto ao campo e valores válidos permanecem após falha

#### Scenario: Edição de estabelecimento
- **WHEN** um administrador ou operador abre um estabelecimento
- **THEN** a interface apresenta identidade imutável, dados cadastrais, endereço, contato público e habilitação de captura sem permitir alterar raiz ou NSU

#### Scenario: Viewer consulta cadastro
- **WHEN** um `VIEWER` abre Cadastro ou Estabelecimentos
- **THEN** a mesma informação autorizada aparece em modo somente leitura sem botões de salvar, criar, inativar ou habilitar captura

#### Scenario: Contato interno e contato público
- **WHEN** contatos aparecem no detalhe
- **THEN** a interface distingue visual e semanticamente contatos internos editáveis de telefone/e-mail públicos do estabelecimento

### Requirement: Proveniência e elegibilidade são visíveis sem depender de cor
O sistema SHALL apresentar origem e data da última consulta cadastral, situação externa, estado interno e habilitação da captura como conceitos distintos, por texto e ícone além da cor.

#### Scenario: Dados externos possivelmente defasados
- **WHEN** o cadastro possui origem externa e data de atualização
- **THEN** a interface mostra a fonte e a data sem afirmar atualização em tempo real

#### Scenario: Captura desabilitada
- **WHEN** um estabelecimento está cadastrado mas não elegível para captura
- **THEN** a interface explica qual condição falhou, remove o disparo disponível e preserva acesso ao cadastro e histórico

#### Scenario: Situação desconhecida
- **WHEN** o cadastro manual não possui situação externa confirmada
- **THEN** a interface apresenta “Não consultada” ou equivalente e não a confunde com baixa, inaptidão ou falha operacional
