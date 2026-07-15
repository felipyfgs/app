## ADDED Requirements

### Requirement: Navegação horizontal do Monitoramento Fiscal
O sistema SHALL exibir, nas rotas `/monitoring`, uma navegação horizontal compartilhada para Dashboard, Simples/MEI, DCTFWeb/MIT, FGTS, Parcelamentos, Situação Fiscal, Caixas Postais, Declarações e Guias, preservando a navegação lateral do shell e indicando a rota ativa semanticamente.

#### Scenario: Troca de módulo
- **WHEN** o usuário seleciona outro módulo na toolbar
- **THEN** a rota correspondente é aberta, o item fica ativo e os filtros do módulo anterior não contaminam a nova consulta

#### Scenario: Navegação móvel
- **WHEN** a toolbar é exibida em viewport móvel
- **THEN** todos os destinos continuam acessíveis por rolagem ou composição responsiva sem causar overflow horizontal no documento

### Requirement: Carteira fiscal por módulo com visão operacional
Cada módulo SHALL apresentar Total, Em dia, Processando, Pendências e Atenção, seguido de filtros e tabela server-side por cliente. Os indicadores e a tabela MUST derivar do mesmo escopo tenant-scoped e dos mesmos filtros normalizados.

#### Scenario: Página preenchida
- **WHEN** a API retorna overview e clientes monitorados
- **THEN** a página mostra faixa de KPIs, razão social, CNPJ mascarado, competência, situação, cobertura/origem, última consulta e ações permitidas

#### Scenario: KPI acionado
- **WHEN** o usuário seleciona Pendências ou Atenção
- **THEN** o filtro reproduzível na URL é atualizado, a paginação reinicia e a API server-side é consultada

#### Scenario: Atualização falha após dados válidos
- **WHEN** uma atualização falha depois de uma resposta válida
- **THEN** os dados anteriores permanecem visíveis, o horário da última atualização válida é preservado e o erro sanitizado é apresentado

### Requirement: Busca e filtros orientados ao operador
As carteiras MUST oferecer busca server-side por razão social, nome fantasia ou CNPJ, filtros por situação e competência e filtros especializados somente quando suportados pelo módulo. A UI MUST NOT exigir ID numérico do cliente como fluxo principal.

#### Scenario: Busca por CNPJ
- **WHEN** o usuário informa CNPJ com ou sem máscara
- **THEN** a URL recebe o valor normalizado, a página volta para 1 e somente clientes do office ativo compatíveis são retornados

#### Scenario: Navegação voltar/avançar
- **WHEN** o usuário usa histórico do navegador após alterar filtros
- **THEN** os controles, submódulo, página e resultados refletem a query atual sem estado obsoleto

#### Scenario: Filtro não suportado
- **WHEN** um módulo não oferece determinada dimensão de filtro
- **THEN** o controle não é exibido como elemento decorativo ou processado apenas sobre a página atual

### Requirement: Contratos fiscais tipados e discriminados
O frontend SHALL consumir DTOs discriminados por `module_key` para overview, carteira e detalhes e MUST NOT usar fallback genérico que apresente registro de outro módulo ou converta incompatibilidade de contrato em campo vazio.

#### Scenario: Contrato incompatível
- **WHEN** a API retorna payload que não corresponde ao contrato esperado
- **THEN** a tela apresenta erro de carregamento sanitizado e os testes de contrato falham, em vez de preencher colunas com `—` silenciosamente

#### Scenario: Simples sem snapshots próprios
- **WHEN** a consulta dedicada de Simples/MEI não retorna registros
- **THEN** a página mostra vazio de Simples/MEI e não usa snapshots genéricos de DCTFWeb, SITFIS ou outro módulo

#### Scenario: Valores monetários
- **WHEN** Guia, DARF ou Parcela retorna valor em centavos
- **THEN** a interface usa o campo tipado em centavos e formata moeda sem tratar o valor como string arbitrária

### Requirement: Experiência completa de Simples Nacional e MEI
A página Simples/MEI SHALL oferecer submódulos PGDAS-D, PGMEI, DASN-SIMEI e Regime, apresentando aplicabilidade, competência, última obrigação, guia e situação por cliente por meio dos endpoints dedicados.

#### Scenario: Alternância de submódulo
- **WHEN** o usuário alterna entre PGDAS-D, PGMEI, DASN-SIMEI e Regime
- **THEN** a URL, KPIs, colunas e consulta mudam para o contrato do submódulo sem perder filtros compatíveis

#### Scenario: Cliente não aplicável
- **WHEN** um cliente não pertence ao regime exigido pelo submódulo
- **THEN** a linha apresenta `NOT_APPLICABLE` com motivo e não oferece geração/transmissão indevida

### Requirement: Experiência completa de DCTFWeb e MIT
A página DCTFWeb/MIT SHALL separar encerramento MIT, transmissão DCTFWeb, recibos, evidências, DARF e pagamento, usando códigos oficiais do catálogo no preflight e nas ações permitidas.

#### Scenario: DCTFWeb transmitida sem pagamento conhecido
- **WHEN** uma declaração possui recibo de transmissão e DARF ainda sem pagamento conhecido
- **THEN** a tabela apresenta os dois estados de forma independente e não reduz ambos a um único badge

#### Scenario: Preflight de transmissão
- **WHEN** um administrador autorizado inicia transmissão
- **THEN** o request usa `solution_code`, `service_code` e `operation_code` tipados do registro/catálogo, apresenta consequência e respeita o gate somente leitura/2FA

### Requirement: Experiência completa de Parcelamentos
A página Parcelamentos SHALL apresentar modalidades do catálogo, pedidos, saldo, parcelas, próxima parcela, atrasos e guias associadas, com detalhe navegável e sem aba inexistente no cliente.

#### Scenario: Pedido com parcela atrasada
- **WHEN** um pedido possui parcela vencida e não paga
- **THEN** a carteira contabiliza Atenção/Pendência, mostra a próxima ação e abre o detalhe das parcelas do mesmo office

#### Scenario: Deep-link do cliente
- **WHEN** o usuário abre Parcelamentos a partir de um cliente
- **THEN** a rota de detalhe fiscal renderiza a seção Parcelamentos com dados lazy daquele cliente

### Requirement: Situação Fiscal em carteira e detalhe normalizado
A página SITFIS SHALL mostrar a carteira completa com situação, idade/TTL do snapshot, quantidade de achados e atualização, e SHALL renderizar o detalhe normalizado em componente acessível sem exibir JSON bruto.

#### Scenario: Snapshot vigente
- **WHEN** o cliente possui snapshot SITFIS dentro do TTL
- **THEN** a linha mostra situação, cobertura, origem, idade e vencimento provenientes de `snapshot` e do envelope tipado

#### Scenario: Snapshot expirado
- **WHEN** o snapshot ultrapassa o TTL
- **THEN** a página informa expiração, mantém o último resultado identificado e oferece refresh somente a papel permitido

#### Scenario: Detalhe de pendências
- **WHEN** o usuário abre um cliente SITFIS
- **THEN** o slideover lista pendências normalizadas, protocolos e timestamps sem `<pre>` de resposta remota

### Requirement: Caixa Postal em mestre–detalhe responsivo
A página Caixa Postal SHALL seguir o arquétipo Inbox: lista e detalhe adjacentes no desktop, detalhe em slideover/drawer abaixo de `lg` e `/monitoring/mailbox/{id}` como rota canônica. Campos e triagem MUST seguir o contrato backend.

#### Scenario: Seleção de mensagem no desktop
- **WHEN** o usuário seleciona uma mensagem em desktop
- **THEN** a URL muda para o ID autorizado, a lista permanece visível e o painel mostra `subject_preview`, `received_at_official`, DTE, prazo e leitura oficial

#### Scenario: Seleção de mensagem no mobile
- **WHEN** o usuário seleciona uma mensagem em mobile
- **THEN** o detalhe abre em overlay acessível, pode ser fechado por teclado e retorna foco ao item selecionado

#### Scenario: Triagem interna
- **WHEN** um usuário permitido altera a triagem
- **THEN** somente `NEW`, `IN_REVIEW` ou `RESOLVED` é enviado e a interface reafirma que isso não altera ciência/leitura oficial

#### Scenario: Corpo ou anexo protegido
- **WHEN** o usuário autorizado solicita corpo ou anexo
- **THEN** a UI usa o endpoint protegido, não embute identificador de cofre e trata indisponibilidade separadamente da ausência de mensagem

### Requirement: Central de Declarações baseada no resumo real
A página Declarações SHALL renderizar o resumo retornado pela API e listar obrigação, aplicabilidade, competência, vencimento, situação de entrega e evidência com nomes de campos alinhados ao resource backend.

#### Scenario: Resumo e lista preenchidos
- **WHEN** a API retorna contagens e projeções
- **THEN** os KPIs correspondem à consulta e cada linha usa `obligation_code`/`obligation_name`, período, prazo e situação tipados

#### Scenario: Filtro por competência e situação
- **WHEN** o usuário aplica competência e situação
- **THEN** o frontend envia os parâmetros aceitos pela API e não filtra somente os registros da página atual

### Requirement: Central de Guias com estados independentes
A página Guias SHALL apresentar tipo/sistema, competência, `amount_cents`, vencimento, emissão, validade, pagamento e versão como dimensões independentes, com detalhe, download protegido e ações submetidas ao preflight real.

#### Scenario: Guia emitida e não paga
- **WHEN** a API retorna emissão concluída e `payment_status` pendente
- **THEN** a linha mostra valor correto e diferencia emissão de pagamento

#### Scenario: Download autorizado
- **WHEN** um usuário permitido solicita uma versão válida
- **THEN** a UI obtém token de download efêmero e não expõe caminho interno ou objeto do cofre

#### Scenario: Guia demonstrativa
- **WHEN** a guia pertence ao dataset demo
- **THEN** o detalhe e o arquivo informam ausência de validade fiscal e nenhuma confirmação é apresentada como pagamento real

### Requirement: FGTS com cobertura parcial permanente
A página FGTS/eSocial SHALL apresentar fechamento, totalização, eventos e divergências cobertos e MUST manter guia/pagamento como `UNSUPPORTED` quando não existir fonte M2M oficial.

#### Scenario: Competência com fechamento eSocial
- **WHEN** eventos oficiais/sintéticos de eSocial permitem projetar o fechamento
- **THEN** a tela mostra fonte, timestamps, situação e detalhe dos eventos sem afirmar consulta ao portal FGTS Digital

#### Scenario: Guia sem fonte oficial
- **WHEN** não há API oficial suportada para guia ou pagamento FGTS Digital
- **THEN** badge, texto e ajuda mostram `UNSUPPORTED`, sem botão de portal, scraping ou atualização falsa

### Requirement: Detalhe fiscal do cliente completo e lazy
O detalhe `/monitoring/clients/{clientId}` SHALL oferecer seções funcionais para Resumo, Execuções, Findings, Pendências, Parcelamentos, Declarações, Guias, FGTS e SITFIS, carregando somente a seção ativa e distinguindo falha de vazio.

#### Scenario: Abertura por deep-link
- **WHEN** uma carteira abre `tab=installments`, `tab=declarations` ou `tab=fgts`
- **THEN** a seção correspondente existe, fica ativa e carrega somente os endpoints necessários ao cliente autorizado

#### Scenario: Falha parcial
- **WHEN** uma seção falha e dados de resumo do cliente foram carregados
- **THEN** o erro da seção é apresentado com retry e não é convertido silenciosamente em “nenhum registro”

#### Scenario: Cliente de outro office
- **WHEN** a URL contém ID pertencente a outro tenant
- **THEN** o sistema apresenta não encontrado sem revelar identidade, contagens ou existência do cliente

### Requirement: Ações de carteira reais e autorizadas
As páginas de Monitoramento SHALL oferecer somente ações funcionais: adicionar cliente pelo fluxo existente, associar categorias/clientes, atualizar leitura, exportar por filtro e abrir detalhe, condicionadas a papel, feature flag, cobertura e modo demo.

#### Scenario: Viewer na carteira
- **WHEN** um `VIEWER` abre um módulo
- **THEN** a leitura e navegação permanecem disponíveis, mas associação, atualização, exportação proibida e mutações não são oferecidas

#### Scenario: Exportação por filtro
- **WHEN** usuário autorizado exporta a carteira filtrada
- **THEN** o job server-side recebe filtros reproduzíveis, escopo do office ativo e campos sanitizados, sem exportar material sensível

#### Scenario: Controle sem backend
- **WHEN** uma ação não possui endpoint ou comportamento implementado
- **THEN** a interface não exibe botão decorativo que simule conclusão

### Requirement: Estados e testes completos do Monitoramento
Todas as rotas `/monitoring` MUST distinguir carregamento inicial, atualização, preenchido, vazio, erro, não suportado, bloqueado e dado demonstrativo, e SHALL ser cobertas por testes de contrato, interação, permissão e responsividade.

#### Scenario: Matriz visual preenchida
- **WHEN** Playwright abre cada rota com fixtures determinísticas em `1440×900` e `390×844`
- **THEN** navbar, navegação, KPIs, filtros, tabela/detalhe e ações permitidas permanecem utilizáveis e visualmente estáveis

#### Scenario: Largura mínima
- **WHEN** uma rota é executada em 360 px
- **THEN** não existe rolagem horizontal do documento e ações essenciais permanecem alcançáveis por teclado/toque

#### Scenario: Troca de office durante carregamento
- **WHEN** a membership ativa muda enquanto uma requisição fiscal está pendente
- **THEN** a resposta anterior é descartada e nenhum dado do tenant anterior é renderizado

