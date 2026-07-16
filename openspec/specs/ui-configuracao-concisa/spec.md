# ui-configuracao-concisa

## Purpose

Política de UI concisa em Settings: alertas excepcionais, hierarquia textual curta, estados compactos de consentimento/A1, consequências só na confirmação e departamentos em `/settings`.

## Requirements

### Requirement: Alertas persistentes são excepcionais
As páginas `/settings` MUST usar `UAlert` somente para erro real, bloqueio que exige ação ou risco imediato. Sucesso e estados normais SHALL usar toast, badge ou estado compacto, e o aviso “Configuração unificada em implantação” MUST NOT ser renderizado.

#### Scenario: Configuração carregada normalmente
- **WHEN** o perfil, consentimento e credencial são carregados sem erro ou bloqueio
- **THEN** a página SHALL mostrar estados compactos sem `UAlert` informativo persistente

#### Scenario: Erro impede salvar
- **WHEN** a API rejeita uma alteração e o usuário precisa corrigir ou tentar novamente
- **THEN** a interface MAY usar um único alerta acionável associado ao erro

### Requirement: Hierarquia textual é curta
Cada página de Settings SHALL possuir no máximo uma introdução curta, e cada seção SHALL possuir no máximo uma descrição de uma linha. A interface MUST NOT inserir cards explicativos entre etapas nem repetir a mesma orientação na página e no modal.

#### Scenario: Página com várias seções
- **WHEN** o usuário abre `/settings`
- **THEN** títulos, rótulos e estados SHALL comunicar o fluxo sem parágrafos repetidos entre as seções

### Requirement: Consentimento e A1 usam estados compactos
Consentimento aceito SHALL ser exibido como estado compacto, nunca como alerta. A ausência de A1 SHALL usar empty state com a ação `Enviar certificado`, sem linguagem alarmista. A mensagem de que o certificado não pode ser baixado e sua explicação de armazenamento seguro MUST aparecer no máximo uma vez e somente no fluxo de envio.

#### Scenario: Consentimento já aceito
- **WHEN** a versão vigente foi aceita
- **THEN** a seção SHALL mostrar status e data de forma compacta sem `UAlert`

#### Scenario: Certificado ausente
- **WHEN** o Office ainda não possui A1
- **THEN** a seção SHALL mostrar o empty state e o botão `Enviar certificado` sem aviso de risco

#### Scenario: Modal de upload aberto
- **WHEN** o usuário inicia o envio do A1
- **THEN** a orientação de segurança/sem download SHALL aparecer uma única vez no fluxo e MUST NOT estar duplicada no fundo da página

### Requirement: Consequências ficam somente na confirmação
As consequências de trocar CNPJ ou remover A1 MUST aparecer somente no modal de confirmação correspondente, em no máximo duas frases e sem `UAlert` aninhado. A página base MUST NOT repetir essas consequências.

#### Scenario: Troca de CNPJ
- **WHEN** o usuário informa um CNPJ diferente e abre a confirmação
- **THEN** o modal SHALL resumir o impacto em até duas frases antes da ação confirmada

#### Scenario: Remoção de A1
- **WHEN** o usuário abre a confirmação de remoção
- **THEN** o modal SHALL conter o impacto conciso sem renderizar `UAlert` dentro dele

### Requirement: Detalhes internos não aparecem ao usuário
Settings MUST NOT exibir nomes ou explicações de `CurrentOffice`, vault, OAuth, tokens, implementação pendente ou auditoria interna. Mensagens de erro SHALL ser sanitizadas e orientadas à ação possível pelo usuário.

#### Scenario: Falha técnica interna
- **WHEN** uma operação falha por detalhe de infraestrutura ou integração
- **THEN** o usuário SHALL receber estado acionável sem nomes internos, segredos ou payload técnico

### Requirement: Departamentos pertence a Settings
A gestão tenant-scoped de Departamentos SHALL estar disponível em `/settings/departments`. `/admin/departments` MUST deixar de existir, e todas as rotas `/admin/*` SHALL permanecer exclusivas da plataforma.

#### Scenario: Office ADMIN gerencia departamentos
- **WHEN** um Office ADMIN abre `/settings/departments`
- **THEN** a página SHALL operar somente sobre o `CurrentOffice` resolvido no servidor

#### Scenario: URL antiga
- **WHEN** qualquer usuário tenta abrir `/admin/departments`
- **THEN** o frontend SHALL redirecionar para a rota apropriada ou responder como rota inexistente sem manter a tela antiga

### Requirement: Regressão de conteúdo é verificada automaticamente
A suíte frontend MUST incluir um teste específico da superfície de Settings que impeça o retorno do aviso removido, de alertas informativos persistentes e das explicações duplicadas de A1. O teste SHALL abranger a página e os modais relevantes.

#### Scenario: Texto redundante é reintroduzido
- **WHEN** uma alteração volta a renderizar qualquer conteúdo proibido pela política
- **THEN** o teste de superfície SHALL falhar antes da entrega
