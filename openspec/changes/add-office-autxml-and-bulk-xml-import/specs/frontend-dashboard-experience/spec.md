## MODIFIED Requirements

### Requirement: Import de saídas
O sistema SHALL oferecer a `OPERATOR` e `ADMIN` uma superfície de importação de saídas que aceite, na mesma seleção, múltiplos XML e ZIP de NF-e 55 e NFC-e 65, apresente os limites vigentes antes do envio e crie um lote assíncrono sem exigir manifestação ou credencial SEFAZ. A opção padrão SHALL ser associar automaticamente cada item pelo CNPJ completo do emitente; a interface SHALL permitir restringir a conferência a um cliente ou estabelecimento sem substituir a identidade do XML.

#### Scenario: Upload ZIP de saídas
- **WHEN** o operador envia um ZIP com XML de NF-e 55 e/ou NFC-e 65 emitidas por estabelecimentos do escritório
- **THEN** a UI cria o lote, mostra progresso de envio e processamento e apresenta contagens de importados, duplicados, sem vínculo, divergência de cliente, inválidos, não suportados, quarentenados e falhos sem material de certificado ou XML bruto

#### Scenario: Seleção mista de arquivos
- **WHEN** o usuário seleciona vários XML e ZIP no mesmo envio
- **THEN** a interface mostra quantidade e tamanho total, valida os limites observáveis no navegador e envia todos como um lote, mantendo a validação do backend como autoridade

#### Scenario: ZIP multiempresa com associação automática
- **WHEN** nenhum cliente é usado como restrição e o ZIP contém emitentes distintos
- **THEN** a interface informa que cada item será associado pelo `emit/CNPJ` e exibe o cliente/estabelecimento resolvido no resultado de cada item

#### Scenario: Cliente usado como restrição
- **WHEN** o usuário seleciona um cliente para conferir o lote
- **THEN** a UI deixa claro que XML de emitente divergente será marcado como `CLIENT_MISMATCH`, sem oferecer associação forçada

#### Scenario: Modal fechado durante processamento
- **WHEN** o usuário fecha a superfície de upload depois que a API aceitou o lote
- **THEN** o processamento continua e a interface oferece acesso ao lote pelo histórico sem reenviar os arquivos

#### Scenario: Arquivo excede limite conhecido
- **WHEN** a seleção excede quantidade ou tamanho total informado pela API
- **THEN** a interface impede o envio, identifica o limite excedido e mantém a seleção editável

#### Scenario: VIEWER
- **WHEN** o usuário é `VIEWER`
- **THEN** não vê ação de importar ou repetir lote e uma tentativa por URL/API recebe 403

## ADDED Requirements

### Requirement: Gestão da identidade fiscal e do A1 do escritório
O sistema SHALL oferecer em Configurações uma superfície própria para a identidade fiscal e a credencial A1 do escritório, separada de Clientes. Somente ADMIN com 2FA recente SHALL poder cadastrar, substituir, ativar ou revogar a credencial; OPERATOR e VIEWER SHALL visualizar apenas estado e metadados públicos permitidos. A interface MUST NOT oferecer recuperação, download ou cópia de PFX, senha, chave privada, PEM ou referência de vault.

#### Scenario: ADMIN cadastra o A1 do escritório
- **WHEN** ADMIN com 2FA recente envia PFX e senha na superfície da identidade fiscal
- **THEN** a UI apresenta somente titular, CNPJ, fingerprint, validade e estado devolvidos pelo backend e descarta o segredo do formulário após a resposta

#### Scenario: Falha de validação do A1
- **WHEN** senha, titular, raiz, validade ou certificado não passam na validação
- **THEN** a interface mostra mensagem sanitizada, não ecoa senha/material e mantém a credencial anterior ativa quando existir

#### Scenario: OPERATOR abre a identidade fiscal
- **WHEN** OPERATOR consulta a configuração do escritório
- **THEN** vê CNPJ copiável, estado do canal e validade, mas não possui ação de upload, substituição, revogação ou recuperação do A1

### Requirement: Onboarding autXML por estabelecimento sem promessa retroativa
O sistema SHALL apresentar a OPERATOR/ADMIN um checklist por estabelecimento para inclusão do CNPJ completo do escritório em `autXML` pelo ERP do emitente, com estados `PENDING`, `CONFIRMED` e `INACTIVE`. A interface MUST informar que a tag deve integrar o XML antes da autorização, que novo usuário `distNSU` não recebe NSU retroativo e que NFC-e 65 não é capturada por esse canal. O sistema SHALL indicar XML/ZIP como caminho para histórico, lacunas e NFC-e.

#### Scenario: Copiar CNPJ para o ERP
- **WHEN** o operador inicia o onboarding de um estabelecimento
- **THEN** a interface permite copiar o CNPJ completo normalizado do escritório, explica que a alteração é feita no ERP do cliente e não oferece ação para editar XML autorizado

#### Scenario: Stream ainda não ativado
- **WHEN** a identidade fiscal ainda não executou a primeira `distNSU` e cumpriu o quiet mínimo
- **THEN** a UI impede marcar novos enrollments como ativos e orienta concluir primeiro a ativação do stream

#### Scenario: Primeiro XML observado
- **WHEN** o canal recebe NF-e 55 válida do estabelecimento contendo o CNPJ esperado em `autXML`
- **THEN** a interface mostra `first_seen_at`, permite confirmar o enrollment e distingue evidência observada de simples declaração manual

#### Scenario: Usuário procura NFC-e no autXML
- **WHEN** o usuário consulta a cobertura do onboarding
- **THEN** a UI informa explicitamente “NF-e modelo 55” para o canal automático e direciona NFC-e modelo 65 ao import XML/ZIP ou canal específico habilitado

### Requirement: Sincronização autXML central distinguível dos clientes
O sistema SHALL apresentar o cursor `NFE_AUTXML_DISTDFE` como sincronização central do escritório por identidade fiscal/CNPJ-base e ambiente, separado das sincronizações por cliente. A UI SHALL mostrar estado, último/maior NSU, último sucesso, próximo agendamento, heartbeat e cStat/motivo sanitizado, e MUST NOT oferecer edição ou reset direto do NSU.

#### Scenario: Primeira consulta sem documentos
- **WHEN** a ativação retorna `cStat=137`
- **THEN** a interface registra a primeira consulta, mostra a espera mínima de uma hora e não descreve o resultado como falha nem como backfill concluído

#### Scenario: Consumo indevido
- **WHEN** o cursor registra `cStat=656`
- **THEN** a UI mostra circuito aberto e horário mínimo da próxima tentativa, sem botão de retry antecipado ou envelope SOAP bruto

#### Scenario: Cursor autXML bloqueado
- **WHEN** o canal do escritório está `BLOCKED`, mas sincronizações de clientes estão saudáveis
- **THEN** a interface atribui o problema somente ao stream autXML e mantém as ações dos canais de clientes independentes

### Requirement: Acompanhamento durável dos lotes de importação
O sistema SHALL apresentar histórico tenant-aware dos lotes com estado, autor, horário, quantidade de arquivos, totais por resultado e progresso persistido. A tela SHALL permitir reabrir um lote após navegação ou recarga e MUST distinguir upload concluído de processamento fiscal concluído.

#### Scenario: Lote ainda em processamento
- **WHEN** o usuário abre um lote `QUEUED` ou `PROCESSING`
- **THEN** a interface mostra progresso indeterminado ou contagens reais processadas, atualiza com polling controlado e não anuncia sucesso antes do estado terminal

#### Scenario: Retorno após recarga
- **WHEN** o usuário recarrega a página ou retorna ao painel depois de fechar o upload
- **THEN** o lote continua acessível pelo identificador/URL reproduzível e seu estado vem novamente da API

#### Scenario: Falha do polling
- **WHEN** a atualização de progresso falha depois de um estado válido ter sido carregado
- **THEN** a UI preserva o último estado conhecido, informa falha sanitizada e permite tentar atualizar sem recriar o lote

### Requirement: Resultado item a item e exportação do relatório
O sistema SHALL apresentar itens paginados e filtráveis por resultado, com arquivo/entrada, tipo, chave validada, emitente, cliente/estabelecimento associado e motivo sanitizado. O sistema SHALL permitir exportar CSV do relatório sem incluir XML, assinatura, referência de vault, caminho interno ou material criptográfico.

#### Scenario: Lote concluído parcialmente
- **WHEN** um lote chega a `COMPLETED_WITH_ERRORS`
- **THEN** a interface mostra resumo por estado e permite filtrar os itens que exigem ação sem ocultar os documentos importados com sucesso

#### Scenario: Consulta de lote volumoso
- **WHEN** o lote possui milhares de entradas
- **THEN** a interface pagina e filtra no servidor, sem carregar todos os itens ou qualquer XML bruto no navegador

#### Scenario: Exportar relatório
- **WHEN** usuário autorizado solicita o CSV do lote
- **THEN** recebe somente metadados e resultados sanitizados do escritório ativo

### Requirement: Retentativa orientada e resolução sem associação forçada
O sistema SHALL oferecer retentativa somente para itens `UNMATCHED` e falhas transitórias elegíveis, explicando a ação necessária antes do reprocessamento. Itens `CLIENT_MISMATCH`, `INVALID`, `UNSUPPORTED` ou `QUARANTINED` por conflito de chave MUST NOT possuir ação de aceitar cegamente ou trocar o cliente indicado pelo XML.

#### Scenario: Reprocessar após cadastrar estabelecimento
- **WHEN** o operador cadastra o estabelecimento correspondente ao emitente de itens `UNMATCHED` e solicita retentativa dentro do prazo de retenção
- **THEN** a interface acompanha nova tentativa dos itens elegíveis sem exigir novo upload e atualiza cliente/estabelecimento somente após confirmação do backend

#### Scenario: Conflito de chave
- **WHEN** um item está `QUARANTINED` porque a chave já possui bytes canônicos diferentes
- **THEN** a UI mostra alerta e encaminha à revisão operacional, sem botão de substituir o XML canônico

#### Scenario: Erro inválido não retentável
- **WHEN** assinatura, protocolo, formato ou modelo tornou o item `INVALID` ou `UNSUPPORTED`
- **THEN** a interface explica que o arquivo precisa ser corrigido na origem e não oferece retry que repetiria o mesmo erro

### Requirement: Importação acessível e sem conteúdo fiscal bruto
O sistema SHALL oferecer seleção por controle de arquivo e drag-and-drop acessíveis por teclado, anunciar mudanças relevantes de progresso e resultado a tecnologias assistivas e MUST NOT renderizar XML bruto, stack trace, caminho temporário, A1, senha, CSC, chave privada ou PEM em modal, histórico, toast, tabela, CSV ou log do navegador.

#### Scenario: Uso por teclado
- **WHEN** o usuário opera a importação sem mouse
- **THEN** consegue selecionar arquivos, remover itens, enviar o lote, acompanhar estado e abrir o resultado com foco visível e nomes acessíveis

#### Scenario: Erro interno com trecho de XML
- **WHEN** a API sanitiza uma falha interna que originalmente continha payload fiscal
- **THEN** a interface exibe somente código, mensagem segura e correlação, sem inserir o conteúdo original no DOM ou console
