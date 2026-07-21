## ADDED Requirements

### Requirement: Monitoramento E0601 diário por escritório
O sistema SHALL permitir habilitar, de forma fail-closed e por escritório, um monitoramento diário da Caixa Postal no modo `ECONOMICO`. Quando devido, o sistema MUST agrupar os CNPJs completos dos estabelecimentos ativos e elegíveis em lotes de no máximo 1.000 contribuintes, solicitar e obter o evento E0601 por `/Monitorar` e MUST NOT criar uma solicitação por cliente quando todos couberem no mesmo lote. O CNPJ MUST ser tratado como texto alfanumérico em maiúsculas em todas as camadas.

#### Scenario: Escritório com quatorze clientes elegíveis
- **WHEN** o monitoramento econômico diário do escritório fica devido e há quatorze clientes elegíveis
- **THEN** o sistema cria um único lote PJ com os quatorze CNPJs completos e executa somente o par assíncrono SOLICITAR/OBTER do E0601

#### Scenario: Monitoramento desabilitado
- **WHEN** o monitoramento do escritório ou um kill switch SERPRO necessário está desabilitado
- **THEN** o scheduler não realiza egress nem cria consultas pagas

#### Scenario: Portfólio maior que o lote oficial
- **WHEN** o escritório possui mais de 1.000 contribuintes elegíveis
- **THEN** o sistema divide deterministicamente o portfólio em lotes de até 1.000, sem duplicar contribuintes, e respeita os limites diários oficiais

### Requirement: Contrato PJ versionado e sem retry faturável automático
O sistema SHALL codificar `SOLICEVENTOSPJ132` e `OBTEREVENTOSPJ134` por um codec PJ versionado. O campo de evento usado no egress MUST ser explícito e testado, e a divergência documental entre `evento` e `eventValue` MUST NOT causar tentativa automática com o segundo formato após uma resposta remota, pois uma segunda chamada pode gerar consumo ou duplicidade. Flags e ambiente de produção MUST permanecer OFF por padrão.

#### Scenario: Envelope PJ configurado
- **WHEN** o codec constrói uma solicitação PJ E0601
- **THEN** ele envia tipo de contribuinte `4`, CNPJs completos separados por vírgula e exatamente um campo de evento conforme a versão de contrato configurada

#### Scenario: Contrato não reconciliado
- **WHEN** não existe versão de contrato PJ válida configurada
- **THEN** o sistema falha antes do egress com erro operacional explícito e sem tentar variantes em sequência

### Requirement: Captura durável do resultado one-shot
Após um `OBTEREVENTOSPJ134` bem-sucedido, o sistema MUST preservar o resultado one-shot em armazenamento privado antes de concluir o consumo remoto, normalizar cada linha em itens persistidos e distinguir `resultado remoto recebido` de `processamento local concluído`. Payload bruto, CNPJ, certificado, token e conteúdo fiscal MUST NOT aparecer em logs ou respostas JSON. Uma falha local depois do HTTP 200 MUST ser recuperável sem nova chamada ao `OBTER`.

#### Scenario: Falha após resposta remota 200
- **WHEN** a SERPRO retorna a matriz e o processo falha durante a normalização ou o direcionamento local
- **THEN** o artefato privado e seu digest permanecem associados ao run, o estado local fica retryable e nenhum novo `OBTER` é enviado

#### Scenario: Reprocessamento local
- **WHEN** um run possui resultado remoto preservado e processamento local pendente
- **THEN** um retry lê o artefato privado, recria idempotentemente os itens ausentes e conclui o processamento sem egress SERPRO

### Requirement: Interpretação segura da matriz de eventos
O sistema SHALL validar cada linha da matriz como um par `[NI, valor]` e SHALL classificar o valor como `SEM_EVENTO` para string vazia, `ACESSO_NEGADO` para `x`, ou `DATA_EVENTO` para data válida `AAMMDD`. O NI MUST ser resolvido contra estabelecimentos do mesmo escritório; linhas malformadas ou não associadas MUST ser isoladas para diagnóstico sem impedir o processamento das demais linhas.

#### Scenario: Contribuinte com data nova
- **WHEN** uma linha contém CNPJ pertencente ao escritório e uma data E0601 válida
- **THEN** o sistema associa o item ao cliente correto e registra a data do evento sem expor o CNPJ em telemetria

#### Scenario: Procuração ausente
- **WHEN** o valor retornado para um contribuinte é `x`
- **THEN** somente esse cliente recebe estado de ação necessária por autorização, e os demais itens do lote continuam sendo processados

#### Scenario: Linha desconhecida ou malformada
- **WHEN** uma linha não tem exatamente dois elementos, contém data inválida ou NI não pertencente ao escritório
- **THEN** o sistema registra diagnóstico sanitizado e não direciona uma consulta Caixa Postal para essa linha

### Requirement: Direcionamento econômico com fechamento do dia
No modo `ECONOMICO`, o sistema SHALL direcionar `caixa_postal.lista` apenas quando a data E0601 for posterior à última data reconciliada do cliente e anterior à data corrente no fuso do escritório. Uma data igual ao dia corrente MUST permanecer pendente até o fechamento do dia, evitando considerar reconciliadas múltiplas mensagens do mesmo dia quando a origem fornece apenas `AAMMDD`. O direcionamento MUST ser idempotente por escritório, cliente, evento e data.

#### Scenario: Evento de dia já encerrado
- **WHEN** o E0601 retorna data anterior ao dia corrente e posterior à última data reconciliada
- **THEN** o sistema enfileira uma única run LISTAR para o cliente e só avança a data reconciliada após sucesso da lista

#### Scenario: Mais de uma mensagem no mesmo dia
- **WHEN** o E0601 retorna a data corrente e novas mensagens ainda podem chegar naquele dia
- **THEN** o sistema mantém a data pendente e não marca o dia como reconciliado até uma execução posterior ao fechamento desse dia

#### Scenario: Nenhuma alteração
- **WHEN** todos os itens do lote estão vazios ou já reconciliados e não há reconciliação completa devida
- **THEN** o sistema não chama nenhuma operação `/Consultar`

### Requirement: Bootstrap, reconciliação e modos de garantia
Ao habilitar o monitoramento, o sistema SHALL oferecer um bootstrap que enfileira LISTAR para todos os clientes elegíveis ainda não inicializados. O modo `ECONOMICO` SHALL executar reconciliação completa periódica com padrão de 30 dias; o modo `DIARIO_COMPLETO` SHALL enfileirar LISTAR diariamente para todos os clientes elegíveis após confirmação explícita do impacto de custo. Falhas ou períodos de indisponibilidade MUST permanecer visíveis e recuperáveis sem marcar falsamente a carteira como atualizada.

#### Scenario: Primeira ativação
- **WHEN** o operador confirma o bootstrap após visualizar sua estimativa de custo
- **THEN** o sistema enfileira idempotentemente uma LISTAR por cliente elegível ainda não inicializado

#### Scenario: Reconciliação mensal no modo econômico
- **WHEN** a última reconciliação completa de um cliente ultrapassa o intervalo configurado
- **THEN** o sistema enfileira LISTAR mesmo sem E0601 novo e registra o motivo como reconciliação de segurança

#### Scenario: Modo diário completo
- **WHEN** o escritório habilita `DIARIO_COMPLETO` com autorização e orçamento suficientes
- **THEN** todos os clientes elegíveis recebem uma LISTAR diária, independentemente do E0601

### Requirement: Orçamento e política de detalhes
Antes de qualquer chamada potencialmente faturável, o sistema MUST consultar a elegibilidade, a tabela de preços vigente e o orçamento do escritório. Estimativas shadow MUST ser identificadas como estimativas não oficiais; custo desconhecido MUST aparecer como desconhecido e MUST NOT ser convertido em zero. No modo `ECONOMICO`, o limite padrão de DETALHE automático SHALL ser zero e o corpo SHALL ser buscado sob demanda ou por política explícita com cap e idempotência.

#### Scenario: Orçamento insuficiente
- **WHEN** uma LISTAR ou DETALHE excederia o orçamento configurado
- **THEN** a chamada é bloqueada antes do egress e a UI informa o motivo e a operação não executada

#### Scenario: Corpo solicitado pelo usuário
- **WHEN** uma mensagem sem corpo é aberta e o usuário confirma a consulta DETALHE com custo conhecido ou explicitamente desconhecido
- **THEN** o sistema enfileira no máximo uma run idempotente para o ISN daquela mensagem

#### Scenario: Tabela shadow ativa
- **WHEN** a estimativa usa uma versão de preço marcada como shadow
- **THEN** API e UI exibem “estimativa interna, não é preço oficial” junto do valor

### Requirement: Operação manual e estado visíveis na inbox
A página `/monitoring/mailbox` SHALL exibir uma ação primária “Atualizar agora” e o estado operacional do monitoramento sem exigir acesso ao accordion “Consulta manual” do painel geral. A ação MUST apresentar preview de escopo e custo antes de enfileirar chamadas pagas. A UI SHALL diferenciar: nunca sincronizada, sincronizada sem mensagens, bloqueada por autorização, monitoramento saudável, processamento com falha e reconciliação atrasada.

#### Scenario: Caixa nunca sincronizada
- **WHEN** não existem mensagens nem bootstrap concluído para o escritório
- **THEN** a inbox explica que nenhuma sincronização foi realizada e oferece bootstrap/atualização visível

#### Scenario: Sincronização concluída sem mensagens
- **WHEN** a última LISTAR concluiu com sucesso e não retornou mensagens
- **THEN** a inbox mostra “Nenhuma mensagem encontrada” e as datas da última e da próxima verificação, sem sugerir falha

#### Scenario: Atualização manual
- **WHEN** o usuário autorizado aciona “Atualizar agora”
- **THEN** a UI primeiro mostra clientes incluídos, operações previstas, fonte da estimativa e possíveis bloqueios, e só enfileira após confirmação

### Requirement: API e isolamento multi-escritório
Os endpoints de configuração, estado, preview e execução manual SHALL derivar o escritório exclusivamente da sessão `CurrentOffice`, aplicar as permissões fiscais existentes e MUST NOT aceitar `office_id` do cliente HTTP. Jobs, itens de evento, estados de cliente, custos e mensagens MUST permanecer escopados ao mesmo escritório; `PLATFORM_ADMIN` sem contexto fiscal do tenant não recebe acesso implícito.

#### Scenario: Tentativa de acessar outro escritório
- **WHEN** um usuário do escritório A referencia cliente, mensagem ou item pertencente ao escritório B
- **THEN** a API responde sem revelar existência ou dados do recurso e nenhuma run é criada

#### Scenario: Job processa lote do escritório
- **WHEN** um job assíncrono recebe um identificador de run
- **THEN** ele revalida office, cliente, flags, autorização e ownership antes de qualquer egress ou persistência derivada

### Requirement: Indicador de novas mensagens é diagnóstico, não garantia
Se a ação `caixa_postal.indicador` for exposta, o sistema SHALL registrá-la com adapter compatível com `INNOVAMSG63` e rotulá-la como diagnóstico de mensagens ainda não abertas. O indicador MUST NOT substituir E0601, bootstrap ou reconciliação, nem permitir que valor zero seja apresentado como prova de caixa completa.

#### Scenario: Indicador igual a zero
- **WHEN** `INNOVAMSG63` informa que não há mensagem nova
- **THEN** o sistema registra o diagnóstico sem marcar a Caixa Postal como reconciliada e sem apagar pendências existentes

#### Scenario: Ação disponível no catálogo
- **WHEN** `caixa_postal.indicador` aparece como ação executável na UI ou API
- **THEN** existe adapter real registrado para a mesma chave de operação e sua copy esclarece a semântica de não aberta

### Requirement: Resiliência do scheduler assíncrono
O scheduler SHALL despachar jobs Horizon sem sobreposição por escritório/lote, respeitar `TempoEsperaMedioEmMs`, `TempoLimiteEmMin`, o padrão one-shot e os limites/HTTP 429 informados pela SERPRO. Um 429 MUST suspender novas solicitações do mesmo tipo até a próxima janela permitida; expiração MUST gerar estado operacional recuperável e nova solicitação somente em execução posterior autorizada.

#### Scenario: Resultado ainda processando
- **WHEN** o `OBTER` é chamado após o ETA e a SERPRO ainda informa processamento
- **THEN** o job agenda nova tentativa dentro do TTL sem loop bloqueante e sem criar novo protocolo

#### Scenario: Limite remoto atingido
- **WHEN** a SERPRO retorna HTTP 429 para eventos PJ
- **THEN** o escritório fica suspenso para novas solicitações PJ até a janela registrada e a UI mostra a próxima tentativa possível

