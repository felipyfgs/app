## ADDED Requirements

### Requirement: Duas visões operacionais consistentes

O módulo Trabalho SHALL oferecer a visão **Processos**, centrada em um processo de uma empresa e competência, e a visão **Tarefas**, centrada na fila transversal de tarefas. Ambas MUST ler e alterar os mesmos processos e tarefas persistidos, sem manter estado operacional duplicado no frontend ou no Monitoramento.

#### Scenario: Processo e tarefas na mesma linha operacional

- **WHEN** o usuário abre a visão Processos
- **THEN** cada linha representa exatamente um processo operacional de uma empresa
- **AND** a linha identifica empresa, processo, competência, situação, prazo, progresso e responsabilidade

#### Scenario: Fila transversal permanece disponível

- **WHEN** o usuário abre a visão Tarefas
- **THEN** encontra as tarefas dos diferentes processos conforme os filtros e permissões da fila
- **AND** abrir uma tarefa mantém referência ao processo e à empresa de origem

### Requirement: Processo expansível com tarefas inline

A visão Processos SHALL permitir expandir a linha sem navegar para outra página e SHALL revelar todas as tarefas daquele processo em ordem, com situação, prazo, responsável e indicadores de criticidade/evidência disponíveis. A coleção da API MUST fornecer os dados compactos necessários sem uma chamada por linha.

#### Scenario: Expansão no desktop

- **WHEN** o usuário aciona o controle de expansão de um processo
- **THEN** a lista exibe as tarefas ordenadas imediatamente abaixo da linha
- **AND** o controle comunica `aria-expanded` e a região expandida é associada ao processo

#### Scenario: Expansão no telefone

- **WHEN** a visão é usada em viewport móvel
- **THEN** empresa, processo, situação e prazo permanecem legíveis em composição empilhada
- **AND** as tarefas expandidas permanecem acionáveis sem scroll horizontal obrigatório

#### Scenario: Acesso ao detalhe completo

- **WHEN** o usuário precisa comentar, anexar evidência ou executar uma ação detalhada
- **THEN** cada processo e tarefa oferece link explícito para seu detalhe canônico
- **AND** expandir ou recolher a linha não causa navegação involuntária

### Requirement: Biblioteca de modelos-base instalável

O sistema SHALL publicar uma biblioteca versionada com modelos-base de PGDAS, Folha de Pagamento, Fechamento Contábil, Parcelamentos e MEI. Instalar um modelo MUST criar uma cópia tenant-scoped editável, registrar chave/versão de origem e nunca alterar automaticamente uma cópia já personalizada.

#### Scenario: Escritório instala modelo-base

- **WHEN** um membro autorizado instala um item da biblioteca
- **THEN** o sistema cria o modelo e suas tarefas somente no escritório atual
- **AND** registra a chave e a versão do catálogo que originaram a cópia

#### Scenario: Catálogo legível pelo runtime PHP

- **WHEN** a aplicação inicia sob o usuário não proprietário do PHP-FPM
- **THEN** o manifesto e as classes do catálogo Work permanecem legíveis
- **AND** a API conclui o bootstrap e responde JSON, sem vazar warning ou fatal error em HTML
- **AND** um payload de identidade não estruturado é tratado pelo frontend como sessão indisponível, sem lançar erro pelo operador `in`

#### Scenario: Nova versão não sobrescreve personalização

- **WHEN** a versão disponível no catálogo é posterior à versão instalada
- **THEN** o sistema pode sinalizar atualização disponível
- **AND** MUST NOT substituir nome, tarefas, departamentos ou regras do modelo do escritório

#### Scenario: Modelo criado do zero

- **WHEN** o escritório cria um modelo próprio sem usar a biblioteca
- **THEN** pode definir nome, prazos, departamento, tarefas e abrangência
- **AND** o modelo não recebe chave fictícia de catálogo

### Requirement: Modelo do escritório configurável

Um membro autorizado SHALL poder configurar no modelo nome, descrição, situação ativa, departamento padrão, prazo, tarefas ordenadas, vínculo opcional allowlisted com Monitoramento e regra padrão de abrangência. Departamentos, responsáveis e tags informados MUST pertencer ao `CurrentOffice`.

#### Scenario: Personalização de tarefas e abrangência

- **WHEN** o usuário edita um modelo instalado ou próprio
- **THEN** pode adicionar, remover e reordenar tarefas e configurar filtros por regimes e tags
- **AND** a edição incrementa `lock_version` para invalidar previews concorrentes

#### Scenario: Referência de outro tenant

- **WHEN** o payload informa departamento, responsável, tag ou cliente de outro escritório
- **THEN** o sistema rejeita ou ignora a referência sem revelar os dados do outro escritório

#### Scenario: Contexto de Monitoramento desconhecido

- **WHEN** o payload informa uma chave de Monitoramento fora da allowlist
- **THEN** o sistema rejeita a alteração
- **AND** nunca aceita URL ou coordenada SERPRO arbitrária como substituição

### Requirement: Seleção auditável da carteira

O preview de geração SHALL resolver as empresas a partir da regra do modelo ou de filtros temporários, com regimes, tags em modo `ANY` ou `ALL`, tags excluídas e inclusões/exclusões manuais. Exclusão manual MUST ter precedência; inclusão manual SHALL ultrapassar filtros organizacionais, mas MUST NOT ultrapassar inatividade, ausência no tenant ou duplicidade.

#### Scenario: Tags em modo ANY

- **WHEN** a regra contém duas tags com `category_match=ANY`
- **THEN** uma empresa com ao menos uma dessas tags é elegível, salvo regra de exclusão

#### Scenario: Tags em modo ALL

- **WHEN** a regra contém duas tags com `category_match=ALL`
- **THEN** somente empresas com todas as tags são elegíveis, salvo regra de exclusão

#### Scenario: Precedência das exceções

- **WHEN** uma empresa casa com os filtros, aparece na inclusão e também na exclusão manual
- **THEN** ela não é materializada no lote
- **AND** a prévia informa que foi excluída explicitamente

#### Scenario: Inclusão bloqueada

- **WHEN** uma inclusão manual aponta para empresa inativa, externa ao tenant ou já gerada para modelo/competência
- **THEN** nenhum novo processo é criado para essa referência
- **AND** a prévia apresenta conflito sanitizado e acionável

### Requirement: Regime tributário relativo à competência

Ao filtrar por regime, o sistema MUST usar o período tributário que cobre a competência quando existir. Sem período aplicável, SHALL usar a projeção atual normalizada apenas como fallback explícito; regime desconhecido MUST NOT casar com filtro de regime conhecido.

#### Scenario: Mudança de regime entre competências

- **WHEN** uma empresa possui Simples Nacional em janeiro e Lucro Presumido em fevereiro
- **THEN** a geração de janeiro usa Simples Nacional e a de fevereiro usa Lucro Presumido

#### Scenario: Fallback para cadastro atual

- **WHEN** não existe período tributário para a competência e o cadastro possui regime normalizável
- **THEN** o preview identifica a origem como fallback do perfil atual
- **AND** apresenta alerta antes da confirmação

#### Scenario: Regime desconhecido

- **WHEN** o modelo filtra Simples Nacional e a empresa não possui período nem regime atual reconhecido
- **THEN** a empresa não é selecionada silenciosamente pelo filtro

### Requirement: Preview congelado e geração idempotente

O lote de geração SHALL registrar a regra normalizada, exceções, empresas selecionadas, origem da seleção, regime resolvido e versão do modelo. Confirmar o lote MUST usar esse snapshot, criar no máximo um processo por `office + template + client + competence` e rejeitar preview expirado ou modelo alterado.

#### Scenario: Carteira muda após preview

- **WHEN** tags ou regime da empresa mudam depois do preview e antes da confirmação
- **THEN** a confirmação usa a seleção e os metadados congelados no lote
- **AND** uma nova geração exige nova prévia para refletir a mudança

#### Scenario: Modelo muda após preview

- **WHEN** `lock_version` do modelo muda depois do preview
- **THEN** a confirmação é rejeitada e solicita nova prévia

#### Scenario: Confirmação repetida

- **WHEN** a mesma confirmação idempotente é repetida
- **THEN** o sistema retorna o resultado existente sem criar segundo processo ou tarefas duplicadas

### Requirement: Contexto seguro entre Work, empresa e Monitoramento

Processos e tarefas SHALL manter referência à empresa e MAY manter uma chave pública allowlisted de módulo de Monitoramento. As superfícies Work SHALL oferecer links internos para cadastro/Monitoramento quando aplicável, mas MUST NOT copiar situação fiscal como situação operacional nem provocar egress ao listar ou expandir.

#### Scenario: Processo PGDAS abre contexto correto

- **WHEN** um processo com `monitoring_module_key=PGDASD` é exibido
- **THEN** o usuário recebe link interno para a seção PGDAS-D da mesma empresa
- **AND** a rota não é construída a partir de URL arbitrária fornecida pelo cliente HTTP

#### Scenario: Processo sem módulo fiscal

- **WHEN** um processo como Fechamento Contábil não possui módulo allowlisted
- **THEN** cadastro e detalhe operacional continuam disponíveis
- **AND** a interface não inventa link ou situação fiscal

#### Scenario: Leitura operacional sem egress

- **WHEN** o usuário lista processos, expande tarefas ou abre o bloco Work no overview da empresa
- **THEN** o sistema usa somente dados locais tenant-scoped
- **AND** não chama SERPRO, SEFAZ ou provider MEI implicitamente

### Requirement: Isolamento e autorização do escritório

Todos os modelos instalados, regras, previews, processos e tarefas SHALL ser resolvidos pelo `CurrentOffice`. O sistema MUST descartar `office_id` recebido do cliente HTTP e MUST manter as policies e o requisito de membership real para instalação, edição e geração.

#### Scenario: Leitura tenta forçar outro office

- **WHEN** o usuário envia `office_id` de outro escritório ao listar catálogo instalado ou processos
- **THEN** a resposta continua limitada ao `CurrentOffice`

#### Scenario: Viewer tenta instalar ou gerar

- **WHEN** um usuário sem permissão mutável tenta instalar modelo ou confirmar geração
- **THEN** o sistema nega a operação
