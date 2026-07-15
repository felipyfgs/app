## ADDED Requirements

### Requirement: Configuração de captura de saídas MA no arquétipo Settings
O sistema SHALL apresentar no detalhe do cliente uma seção reproduzível de captura de saídas, organizada por estabelecimento, ambiente, modelo e série, mostrando modo `ASSISTED|AUTOMATIC`, estado do A1/CSC sem valores, semente, posição `nNF`, última execução, próxima tentativa, lacunas e bloqueios. A estrutura visual MUST seguir o template Nuxt UI fixado no repositório.

#### Scenario: Série NF-e configurada
- **WHEN** o usuário abre uma série modelo 55
- **THEN** vê semente, número inicial/posição atual, captura e pendências sem campo ou estado de CSC

#### Scenario: Série NFC-e configurada
- **WHEN** o usuário abre uma série modelo 65
- **THEN** vê estado do CSC somente como configurado/ausente quando aplicável, nunca seu valor

#### Scenario: Modo assistido
- **WHEN** não existe contrato M2M aprovado para a plataforma MA
- **THEN** a UI rotula claramente `Assistido`, orienta obtenção/upload do pacote oficial e não usa texto de sincronização automática

#### Scenario: Modo automático
- **WHEN** contrato M2M, flag, perfil e allowlist estão válidos
- **THEN** a UI mostra `Automático`, competência coberta, próxima execução e última recuperação concluída

#### Scenario: Lacuna esgotada
- **WHEN** um número chega a `EXHAUSTED_VISIBLE`
- **THEN** a série exibe o `nNF`, dez tentativas, último resultado e ação de revisão permitida sem ocultar a lacuna

### Requirement: Ações da captura MA respeitam papel e 2FA
O sistema SHALL permitir upload de semente/pacote e consulta somente leitura a OPERATOR/ADMIN quando elegíveis, e MUST restringir cadastro de CSC, ativação de produção, mandato, allowlist, reset, fallback mutante e kill switch a ADMIN com 2FA recente e confirmação explícita.

#### Scenario: Operador envia pacote oficial
- **WHEN** OPERATOR autorizado seleciona estabelecimento/competência e envia ZIP oficial MA
- **THEN** a UI mostra progresso e resultado por XML sem material de certificado ou payload bruto

#### Scenario: Operador tenta ativar perfil
- **WHEN** OPERATOR tenta habilitar produção, alterar mandato ou resetar sequência
- **THEN** a ação não é oferecida ou retorna 403 sem alteração parcial

#### Scenario: Admin reseta sequência
- **WHEN** ADMIN com 2FA recente confirma posição e motivo do reset
- **THEN** a UI envia a ação protegida, mantém histórico visível e informa que resultados fiscais anteriores não serão refeitos

#### Scenario: Confirmação mutante
- **WHEN** ADMIN autorizado abre ação de inutilização/sonda experimental
- **THEN** a UI apresenta série/período, riscos fiscais, gates e efeito irreversível, exigindo confirmação específica; confirmação genérica de modal não basta

### Requirement: Operação e catálogo distinguem chave de XML
O sistema SHALL diferenciar visualmente lacuna, chave descoberta, XML pendente, XML capturado e incidente fiscal. Documento técnico autorizado MUST aparecer com finalidade e situação reais; chave sem XML MUST NOT oferecer download.

#### Scenario: Chave descoberta
- **WHEN** o número está `KEY_DISCOVERED` ou `XML_PENDING`
- **THEN** a tela operacional mostra a chave e pendência de recuperação, enquanto o catálogo não apresenta download inexistente

#### Scenario: Documento técnico cancelado
- **WHEN** documento técnico possui autorização e evento de cancelamento
- **THEN** Documentos mostra `Saída`, finalidade `Validação técnica` e situação `Cancelada`, sem ocultá-lo

#### Scenario: Cancelamento falho
- **WHEN** existe `FISCAL_INCIDENT`
- **THEN** a interface destaca alerta crítico persistente e kill switch ativo até resolução humana

## MODIFIED Requirements

### Requirement: Detalhe de Cliente organizado por seções
O sistema SHALL apresentar o detalhe de Cliente em página dedicada no arquétipo Settings com subnavegação para `Resumo`, `Cadastro`, `Estabelecimentos`, `Certificado A1`, `Captura de saídas` e `Sincronização`, condicionando conteúdo e ações às permissões e mantendo a seção reproduzível na URL.

#### Scenario: Abertura do cliente
- **WHEN** o usuário abre `/clients/:id` sem seção especificada
- **THEN** o sistema apresenta Resumo com identidade da raiz, estado e progresso do onboarding

#### Scenario: Seção Cadastro
- **WHEN** o usuário autorizado abre a seção Cadastro
- **THEN** o sistema apresenta dados da raiz, estado, origem/atualização e contatos em cards Settings, editáveis somente para administrador ou operador

#### Scenario: Operador sem gestão de credencial
- **WHEN** um usuário que não pode gerir A1 visualiza o onboarding
- **THEN** a etapa informa que o certificado é gerenciado por `ADMIN` sem expor formulário sensível nem representar falta de permissão como falha operacional

#### Scenario: Seção Captura de saídas
- **WHEN** o usuário abre a seção de captura de saídas
- **THEN** a página lista somente estabelecimentos e séries do escritório ativo, com modo/estado e ações permitidas ao papel

#### Scenario: Seção reproduzível
- **WHEN** o usuário abre uma URL válida da seção Cadastro, Estabelecimentos, Certificado A1, Captura de saídas ou Sincronização
- **THEN** a toolbar destaca a seção e o corpo renderiza somente o conteúdo correspondente

### Requirement: Filtro e indicação de tipo DF-e
O sistema SHALL permitir filtrar o catálogo por tipo e exibir o tipo de cada linha somente para `NFSE`, `NFE`, `NFCE` e `CTE`. Tipos com captura habilitada MUST listar dados reais; tipos sem captura MUST manter empty state informativo. NFC-e MA MUST distinguir canal assistido de automático, e MDF-e MUST NOT aparecer como opção operacional.

#### Scenario: Tipo sem captura
- **WHEN** o operador filtra por kind sem captura habilitada ou sem dados
- **THEN** a UI explica a indisponibilidade sem erro

#### Scenario: NFS-e com dados
- **WHEN** o operador filtra por NFS-e (ou Todos, com apenas NFS-e populado)
- **THEN** a lista mostra documentos NFS-e com coluna/badge de tipo

#### Scenario: NF-e com captura
- **WHEN** a captura DistDFe ou saída MA está habilitada e há documentos
- **THEN** o filtro NF-e mostra linhas com badge NFE e não exibe “em breve” como único estado

#### Scenario: NFC-e MA com dados
- **WHEN** há XML modelo 65 de saída persistido para estabelecimento MA
- **THEN** o filtro NFC-e mostra linhas NFCE com direção Saída e proveniência legível

#### Scenario: MDF-e ausente
- **WHEN** o operador abre os filtros e estados do catálogo
- **THEN** MDF-e não é apresentado como opção disponível ou futura

### Requirement: Sincronizações multi-canal
O sistema SHALL apresentar status de cursors SEFAZ DistDFe e CT-e e de séries outbound MA nas telas de sincronização e saúde de forma distinguível dos cursors ADN, sem apresentar canal MDF-e. Canal baseado em NSU SHALL mostrar NSU; série outbound SHALL mostrar modelo, série e posição `nNF`, nunca rotulada como NSU.

#### Scenario: Cursor DistDFe bloqueado
- **WHEN** o cursor DistDFe está BLOCKED
- **THEN** a UI de sync ou health mostra o canal e severidade sem dump SOAP bruto

#### Scenario: Série outbound bloqueada
- **WHEN** série NF-e/NFC-e MA está BLOCKED
- **THEN** a UI mostra estabelecimento, modelo, série, `nNF`, motivo sanitizado e ação permitida sem editar a posição diretamente

#### Scenario: Recuperação pendente
- **WHEN** há chaves descobertas sem XML
- **THEN** sincronização mostra contagem `XML_PENDING` separada da quantidade capturada

#### Scenario: Superfície operacional sem MDF-e
- **WHEN** o usuário abre sincronização ou saúde
- **THEN** não existe filtro, status ou ação para MDF-e

