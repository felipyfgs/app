# acesso-global-platform-admin

## Purpose

Acesso global de `PLATFORM_ADMIN` a qualquer Office ativo: seletor privilegiado, capacidades efetivas de ADMIN no contexto, reconfirmação de senha em ações sensíveis, auditoria interna e gate de rollout jurídico/segurança.

## Requirements

### Requirement: Seleção global de escritório
Todo usuário com papel `PLATFORM_ADMIN` SHALL poder listar e selecionar qualquer `Office` ativo por um seletor global. A seleção MUST ser armazenada separadamente da seleção de membership e MUST NOT criar `OfficeMembership`, alterar `users.selected_office_id` ou personificar usuário do escritório.

#### Scenario: Administrador seleciona escritório sem membership
- **WHEN** um `PLATFORM_ADMIN` seleciona um escritório do qual não é membro
- **THEN** o sistema SHALL criar `CurrentOffice` privilegiado para o escritório mantendo o administrador real como ator

#### Scenario: Usuário comum tenta usar o seletor global
- **WHEN** um usuário sem `PLATFORM_ADMIN` chamar o endpoint global de seleção
- **THEN** o sistema SHALL negar o acesso e não alterar seu contexto

### Requirement: Capacidade efetiva integral no contexto privilegiado
No `CurrentOffice` com modo `platform_privileged`, o `PLATFORM_ADMIN` SHALL possuir as mesmas capacidades de um `OfficeRole::ADMIN`, incluindo dados fiscais, configuração, certificado e mutações fiscais. O office MUST continuar sendo resolvido no servidor, e `office_id` do cliente HTTP MUST ser ignorado.

#### Scenario: Leitura fiscal privilegiada
- **WHEN** o administrador da plataforma seleciona um office e consulta seus dados fiscais
- **THEN** policies e queries SHALL autorizar a leitura somente no office selecionado e atribuí-la ao ator real

#### Scenario: Tentativa de trocar escopo pelo body
- **WHEN** uma requisição privilegiada incluir outro `office_id` no body ou query
- **THEN** o sistema SHALL remover o valor e manter exclusivamente o `CurrentOffice` selecionado no servidor

### Requirement: Login comum sem TOTP global
O login e a navegação comuns de `PLATFORM_ADMIN` MUST usar o fluxo comum de autenticação e MUST NOT exigir TOTP como condição global de acesso às rotas de plataforma ou de seleção de escritório.

#### Scenario: Administrador autenticado navega no painel
- **WHEN** um `PLATFORM_ADMIN` conclui o login comum válido
- **THEN** ele SHALL acessar a área de plataforma e o seletor sem desafio TOTP adicional

### Requirement: Reconfirmação de senha para ações sensíveis
Substituição ou remoção do A1 e toda mutação fiscal executada por `PLATFORM_ADMIN` em contexto privilegiado MUST exigir reconfirmação recente da senha do próprio ator. Uma confirmação ausente ou expirada MUST bloquear a operação antes de qualquer efeito ou chamada externa.

#### Scenario: Mutação com confirmação recente
- **WHEN** o administrador reconfirma sua senha e solicita uma mutação fiscal dentro da janela permitida
- **THEN** a operação poderá seguir para os demais gates usando sua identidade real

#### Scenario: Confirmação expirada
- **WHEN** a janela de reconfirmação tiver expirado antes da remoção do A1
- **THEN** o sistema SHALL exigir nova senha e manter a credencial ativa

### Requirement: Controles operacionais permanecem fail-closed
O contexto privilegiado MUST NOT ignorar assinatura writable, feature flags, allowlist do office, elegibilidade, limites, orçamento, contrato, idempotência, rate limit ou kill switch. A falta de qualquer gate aplicável MUST bloquear o transporte externo.

#### Scenario: Flag mutante está desligada
- **WHEN** um `PLATFORM_ADMIN` confirmado solicitar mutação cuja flag está OFF
- **THEN** o sistema SHALL negar a operação antes da chamada externa

### Requirement: Auditoria interna do acesso privilegiado
Leituras e mutações relevantes no modo privilegiado MUST produzir evento append-only com administrador real, office, ação, alvo, resultado, instante e correlação sanitizada. Essa trilha SHALL ser acessível somente na plataforma e MUST NOT aparecer em APIs, exportações ou telas do escritório.

#### Scenario: Administrador altera configuração do escritório
- **WHEN** um `PLATFORM_ADMIN` modifica o perfil no contexto privilegiado
- **THEN** o sistema SHALL auditar o ator e o office sem criar registro como se fosse um usuário do escritório

#### Scenario: Escritório consulta sua auditoria
- **WHEN** um membro do office consulta logs ou exportações disponíveis ao tenant
- **THEN** eventos internos de acesso privilegiado MUST NOT ser retornados

### Requirement: Gate de rollout jurídico e de segurança
A ativação do contexto privilegiado em produção MUST permanecer desligada por padrão até existir aprovação registrada de revisão jurídica de LGPD/sigilo fiscal e de segurança, além de plano de rollout e rollback.

#### Scenario: Deploy sem aprovação registrada
- **WHEN** a versão for implantada sem os gates de aprovação e rollout satisfeitos
- **THEN** a seleção privilegiada de tenant SHALL permanecer indisponível em produção
