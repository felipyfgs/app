## ADDED Requirements

### Requirement: Classificação SITFIS baseada na melhor fonte disponível
O hub SHALL priorizar campos estruturados do retorno SITFIS quando eles contiverem situação/pendências utilizáveis. Quando o contrato oficial retornar somente o PDF, o hub SHALL extrair apenas os marcadores necessários do documento para classificar a situação, com fallback fail-closed. O texto integral do PDF MUST NOT ser persistido em logs nem exposto em JSON público.

#### Scenario: Retorno estruturado permanece prioritário
- **WHEN** o retorno SITFIS contém estrutura conhecida de pendências além de eventual documento
- **THEN** o parser MUST usar a estrutura conhecida para a projeção
- **AND** MUST NOT depender do PDF para substituir informação estruturada válida

#### Scenario: PDF é a única fonte de situação
- **WHEN** `RELATORIOSITFIS92` retorna sucesso com `dados.pdf` e sem campo estruturado de situação/pendências
- **THEN** o parser SHALL extrair do PDF somente marcadores oficiais necessários à classificação
- **AND** falha ou ambiguidade de extração MUST resultar em `ATTENTION`, nunca em regularidade presumida

### Requirement: Semântica conservadora dos marcadores do PDF SITFIS
O parser SHALL classificar como `PENDING` o PDF com ao menos uma seção oficial `Pendência - ...`; SHALL classificar como `UP_TO_DATE` apenas o PDF que contenha a declaração geral explícita de ausência de pendências nos controles conjuntos da Receita Federal e da Procuradoria-Geral da Fazenda Nacional e nenhuma seção de pendência; e SHALL usar `ATTENTION` nos demais casos. Nenhuma dessas classificações SHALL definir `is_negative_certificate` ou `claims_negative_certificate` como true.

#### Scenario: Relatório com seção de pendência
- **WHEN** o PDF contém uma ou mais seções `Pendência - <tipo>`
- **THEN** a situação MUST ser `PENDING`
- **AND** cada tipo distinto MUST gerar finding determinístico rastreável ao snapshot

#### Scenario: Relatório declara ausência geral de pendências
- **WHEN** o PDF contém a declaração de ausência nos controles conjuntos RFB+PGFN
- **AND** não contém seção `Pendência - ...`
- **THEN** a situação MUST ser `UP_TO_DATE`
- **AND** `is_negative_certificate` e `claims_negative_certificate` MUST permanecer false

#### Scenario: Frase apenas da PGFN não neutraliza pendência RFB
- **WHEN** o PDF contém pendência da Receita Federal e informa ausência somente nos controles da PGFN
- **THEN** a situação MUST continuar `PENDING`

#### Scenario: Layout inconclusivo permanece atenção
- **WHEN** o texto não pode ser extraído, excede limites ou não contém marcador conclusivo
- **THEN** a situação MUST ser `ATTENTION`
- **AND** o artefato oficial MUST permanecer disponível para revisão

### Requirement: Projeções e contadores representam o snapshot SITFIS corrente
A carteira SITFIS SHALL calcular `findings_count` somente a partir dos findings ativos do snapshot SITFIS corrente e `pending_count` somente a partir de itens operacionais `OPEN` originados de runs SITFIS. Ao promover novo snapshot SITFIS, findings anteriores do módulo MUST ser desativados e itens abertos ausentes na nova projeção MUST ser resolvidos sem afetar outros módulos.

#### Scenario: Achados históricos não inflam o contador
- **WHEN** um cliente possui múltiplas versões de snapshot SITFIS
- **THEN** `findings_count` MUST contar apenas findings ativos ligados ao snapshot corrente
- **AND** findings de outros módulos MUST NOT entrar no total

#### Scenario: Pendência desaparece em relatório sucessor
- **WHEN** uma seção SITFIS anteriormente aberta não aparece no novo snapshot corrente conclusivo
- **THEN** o finding anterior MUST ser desativado
- **AND** o item operacional correspondente MUST deixar de estar `OPEN`

### Requirement: Reprocessamento local versionado de evidências SITFIS
O hub SHALL oferecer comando local com `--dry-run` e escopo explícito de office/client para reprocessar PDFs SITFIS existentes a partir do `SecureObjectStore`. O comando MUST NOT chamar SERPRO nem criar uma nova busca; quando a projeção mudar, SHALL criar snapshot sucessor rastreável que reutiliza run/evidência e conserva o `observed_at` original. Reexecução com mesmo parser e mesma projeção MUST ser idempotente.

#### Scenario: Dry-run não altera dados nem consulta SERPRO
- **WHEN** o operador executa o reprocessamento com `--dry-run`
- **THEN** o comando reporta somente as mudanças previstas
- **AND** MUST NOT persistir snapshot/finding/pending item
- **AND** MUST NOT invocar executor, cliente HTTP ou operação SERPRO

#### Scenario: Reprocessamento promove versão sucessora
- **WHEN** uma evidência atual classificada genericamente como `ATTENTION` contém marcador conclusivo
- **THEN** o comando cria versão sucessora com mesma run, evidência e `observed_at`
- **AND** registra a versão/origem do reprocessamento sem alterar os bytes imutáveis

#### Scenario: Reexecução idempotente
- **WHEN** o snapshot corrente já foi processado pela mesma versão e possui mesma situação/seções
- **THEN** o comando MUST NOT criar outra versão

### Requirement: Download autenticado do relatório na carteira SITFIS
Toda ação de documento SITFIS na SPA SHALL obter o artefato pelo cliente Sanctum autenticado. O path `/api/v1/fiscal/evidence/{id}/download` MUST NOT ser usado como destino de navegação Nuxt.

#### Scenario: Download pelo menu de ações
- **WHEN** o usuário seleciona “Ver relatório oficial” no menu `Ações` de uma empresa
- **THEN** a UI MUST solicitar o PDF pelo proxy/API autenticado
- **AND** MUST permanecer em `/monitoring/sitfis`, sem navegar para uma página `/api/v1/...`

#### Scenario: Download pelo detalhe
- **WHEN** o usuário seleciona o botão de relatório no detalhe SITFIS
- **THEN** a UI MUST usar o mesmo fluxo autenticado
- **AND** a resposta PDF MUST ser entregue como download local
