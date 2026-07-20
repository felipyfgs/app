## ADDED Requirements

### Requirement: Contrato canônico hierárquico do monitor
O sistema SHALL manter no backend uma única fonte normativa para superfícies, capabilities e actions consultivas, contendo rota canônica, fonte, estado oficial, classe de operação, parâmetros públicos, tipo de resultado, política documental, handler e disponibilidade, e SHALL derivar dela a API de coverage e o inventário de consultas manuais.

#### Scenario: Capability anteriormente tratada como exceção
- **WHEN** DEFIS, CCMEI, Regime de Apuração, apoio Sicalc ou contagem PagtoWeb possui operação produtiva de leitura e handler implementado
- **THEN** a capability aparece na superfície canônica, no coverage e no inventário manual sem cadastro paralelo

#### Scenario: Action sem implementação completa
- **WHEN** uma operação do catálogo não possui handler ou dimensão obrigatória de implementação
- **THEN** o contrato a apresenta como indisponível e nenhum endpoint a marca como executável

### Requirement: Rotas canônicas desacopladas do fornecedor
O sistema SHALL anunciar rotas de produto canônicas para cada superfície e SHALL representar submódulos SERPRO como capabilities da página, preservando redirecionamentos legados sem publicar esses caminhos como destino principal.

#### Scenario: Acesso legado ao submódulo
- **WHEN** o usuário abre `/monitoring/dctfweb/mit` ou `/monitoring/simples-mei/pgdasd`
- **THEN** a SPA redireciona para a página canônica correspondente e seleciona ou torna acessível a capability sem criar nova superfície órfã

### Requirement: Fronteira estritamente consultiva
O sistema MUST permitir execução remota pelo workspace somente para operação produtiva, implementada, `is_mutating=false`, classificada `READ`, autorizada pelo resolvedor fiscal e associada a handler consultivo; transmissão, encerramento, adesão, geração de novo documento e demais mutações MUST ser recusados no dispatcher e novamente no job.

#### Scenario: Operador atualiza uma consulta permitida
- **WHEN** um `ADMIN` ou `OPERATOR` solicita atualização de action `READ` elegível para cliente do escritório atual
- **THEN** o sistema despacha a consulta tenant-scoped e retorna estado síncrono ou assíncrono sem executar outra classe de operação

#### Scenario: Requisição direta de operação mutante
- **WHEN** qualquer usuário tenta executar pelo workspace uma action de transmissão, encerramento, adesão ou geração fiscal
- **THEN** o backend recusa antes do transporte, não enfileira efeito remoto e registra motivo sanitizado

### Requirement: Cobertura visível a todos os membros do escritório
O sistema SHALL exibir a cobertura central e contextual, incluindo fonte, frescor, limitações, tipo de resultado e disponibilidade, para `ADMIN`, `OPERATOR` e `VIEWER` autenticados e vinculados ao escritório atual, sem conceder acesso fiscal implícito a `PLATFORM_ADMIN` fora do tenant.

#### Scenario: Viewer consulta cobertura contextual
- **WHEN** um `VIEWER` abre uma página do monitor
- **THEN** ele visualiza dados históricos e cobertura da página, mas não recebe controle de atualização nem ação mutante

#### Scenario: Usuário tenta consultar outro escritório
- **WHEN** um usuário solicita coverage, resultado ou documento pertencente a escritório diferente do contexto atual
- **THEN** a API não revela existência, metadados, contadores nem identificadores do recurso

### Requirement: Experiência completa de todas as superfícies
O Nuxt SHALL oferecer jornadas consultivas coerentes para dashboard, Simples/MEI, DCTFWeb/MIT, FGTS/eSocial, parcelamentos, SITFIS, Caixa Postal, declarações, guias, cadastros, processos fiscais e detalhe do cliente, incluindo cada capability registrada e seus estados loading, vazio, erro, bloqueado, processando e resultado.

#### Scenario: Capability registrada possui destino funcional
- **WHEN** o contrato público retorna uma capability disponível em uma superfície
- **THEN** a página correspondente apresenta seus dados semânticos, parâmetros permitidos, ação `READ` conforme papel e último estado, sem depender de JSON bruto

#### Scenario: Falha de carregamento preserva contexto
- **WHEN** a API da capability falha e existe snapshot anterior válido
- **THEN** a interface mantém o último dado identificado por data/fonte e mostra a falha da atualização sem transformar o snapshot em sucesso recente

### Requirement: Estado consultivo uniforme e resistente a respostas obsoletas
O sistema SHALL projetar consultas como `IDLE`, `QUEUED`, `PROCESSING`, `READY`, `NO_DATA`, `FAILED`, `BLOCKED` ou `UNSUPPORTED`, acompanhado de instante, proveniência, cobertura e motivo sanitizado, e a SPA MUST descartar respostas iniciadas antes de mudança de tenant, filtro ou capability.

#### Scenario: Consulta assíncrona ainda não terminou
- **WHEN** SITFIS ou outra action assíncrona retorna `202` ou aguarda próxima etapa
- **THEN** a interface mostra `QUEUED` ou `PROCESSING`, respeita o tempo de espera e não anuncia documento disponível

#### Scenario: Escritório muda durante o request
- **WHEN** o usuário troca de escritório antes de uma resposta do tenant anterior chegar
- **THEN** a resposta antiga é ignorada e não altera tabela, contador, modal ou coverage do novo contexto

### Requirement: Documentos somente a partir de evidência autorizada
O sistema SHALL mostrar download apenas para evidência existente, pertencente ao escritório atual, com `available=true` e `href` autorizado não vazio; superfícies estruturadas e operações de geração bloqueadas MUST NOT fabricar link, conteúdo ou disponibilidade.

#### Scenario: Documento já coletado
- **WHEN** uma consulta documental possui descriptor autorizado e artefato íntegro armazenado
- **THEN** a interface mostra label, tipo, data e download pela rota Laravel protegida

#### Scenario: Operação poderia gerar documento novo
- **WHEN** o catálogo contém uma action de geração, mas não existe evidência previamente coletada
- **THEN** a cobertura informa a limitação e a interface não oferece botão de gerar ou baixar

### Requirement: Cobertura parcial não representa regularidade
O sistema MUST preservar estados `UNSUPPORTED`, `NO_DATA`, `BLOCKED` e cobertura parcial sem convertê-los em “em dia”, “pago”, “entregue” ou documento disponível, mantendo a proveniência de agregadores e fontes externas.

#### Scenario: Guia e pagamento FGTS sem provider
- **WHEN** fechamento ou eventos eSocial existem, mas guia ou confirmação de pagamento não possuem fonte oficial
- **THEN** a página mostra os campos suportados e identifica guia/pagamento como `UNSUPPORTED`

#### Scenario: Declaração agregada sem evidência integral
- **WHEN** a agenda fiscal conhece a obrigação, mas a fonte de origem não possui recibo ou documento
- **THEN** a página Declarações mostra a cobertura parcial e não cria evidência por inferência

### Requirement: Outbound permanece fora do workspace
O sistema SHALL tratar preferências e histórico de comunicação apenas como informação local e MUST NOT apresentar envio imediato ou automático como funcional enquanto não existir capability outbound específica, idempotente, auditável e habilitada por change independente.

#### Scenario: Usuário abre comunicação de PGDAS-D, PGMEI ou DCTFWeb
- **WHEN** a capability outbound não está instalada/habilitada
- **THEN** a interface pode mostrar preferências e histórico, mas informa indisponibilidade e não apresenta controle que simule envio real

### Requirement: Contrato público sanitizado
A API do workspace MUST expor somente identificadores públicos, labels, rotas, schemas de parâmetros e campos semânticos necessários à UI, removendo coordenadas SERPRO, secrets, payloads fiscais brutos e detalhes internos de handler.

#### Scenario: Coverage é consultado pela SPA
- **WHEN** um membro autenticado solicita `/api/v1/fiscal/monitoring/coverage`
- **THEN** a resposta não contém `operation_key`, `idSistema`, `idServico`, token, PFX, XML integral, credencial ou `office_id` fornecido pelo cliente

### Requirement: Gates automatizados e Trial separado
O projeto SHALL validar todas as superfícies e capabilities com testes unitários/de contrato e SHALL manter jornadas E2E locais representativas contra Nuxt e Laravel com rede fiscal externa bloqueada; smoke Trial MUST exigir comando e credencial explícitos, usar somente cenários oficiais configurados e nunca integrar o gate padrão de CI.

#### Scenario: Gate padrão do frontend
- **WHEN** `pnpm run test:gate`, geração da SPA e o gate E2E local são executados em ambiente de teste
- **THEN** login, tenancy, cobertura, estados consultivos, documento existente, assíncrono, bloqueio, FGTS parcial e permissões de `VIEWER` são verificados sem chamada SERPRO live

#### Scenario: Smoke Trial autorizado
- **WHEN** um operador executa o smoke Trial com flag e credencial explícitas
- **THEN** somente os cenários oficiais configurados são chamados, o resultado é sanitizado e o relatório declara que transporte/schema não comprovam situação fiscal produtiva
