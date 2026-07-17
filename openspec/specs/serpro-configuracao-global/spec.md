# serpro-configuracao-global Specification

## Purpose

Configuração global SERPRO da instalação: partições Trial/Production, gates externos, limites quantitativos, kill switch emergencial e proibição de mutação legada de contrato — exclusivas do Proprietário `PLATFORM_ADMIN`, sem depender de contexto de Office.

## Requirements

### Requirement: Configuração global é exclusiva, unificada e separada por ambiente
O sistema SHALL oferecer ao Proprietário `PLATFORM_ADMIN` único a rota `/admin/serpro/configuration` e `GET /api/v1/platform/serpro/configuration`, com partições independentes `TRIAL` e `PRODUCTION` para contrato, versões, tokens, prontidão, gates, limites e histórico. A leitura MUST usar autenticação global e MUST NOT depender de contexto de Office nem aceitar `office_id` como autoridade.

#### Scenario: Proprietário alterna o ambiente
- **WHEN** o Proprietário seleciona Trial ou Production
- **THEN** a resposta SHALL conter somente o estado sanitizado da partição escolhida e endpoints oficiais somente leitura

#### Scenario: Usuário sem papel global acessa a configuração
- **WHEN** um usuário autenticado sem `PLATFORM_ADMIN` chama a rota ou API
- **THEN** o sistema MUST negar acesso sem revelar metadados SERPRO

### Requirement: API de configuração nunca recupera segredo
PFX, senha, Consumer Key, Consumer Secret, Bearer, JWT e identificadores internos do vault MUST permanecer exclusivamente no `SecureObjectStore`. Respostas e histórico SHALL mostrar somente CNPJ mascarado, fingerprint, validade, final da Consumer Key, estados e timestamps; nenhum endpoint SHALL oferecer download ou recuperação dos segredos.

#### Scenario: Versão é cadastrada
- **WHEN** o Proprietário envia os quatro segredos válidos
- **THEN** a API SHALL retornar metadados sanitizados e nenhuma subsequente leitura SHALL devolver o conteúdo enviado

#### Scenario: Erro de validação ocorre
- **WHEN** PFX, senha, Key ou Secret são inválidos
- **THEN** logs, auditoria e resposta MUST conter somente erro sanitizado e MUST NOT persistir material parcial fora do vault

### Requirement: Gates externos de Production são mínimos e não dispensáveis
O sistema MUST manter os seis gates externos baseline como bloqueadores de `PRODUCTION`. Aceitar um gate SHALL exigir referência, resumo, responsável, data, ator e senha recentemente confirmada; gate ausente, incompleto, vencido ou rejeitado MUST bloquear prontidão e MUST NOT possuir waiver silencioso ou upload de PDF.

#### Scenario: Gate completo é aceito
- **WHEN** o Proprietário registra todos os campos e a confirmação recente
- **THEN** o gate SHALL ser aceito com auditoria sanitizada e vinculado ao ambiente Production

#### Scenario: Referência está ausente
- **WHEN** uma tentativa omite referência, resumo, responsável ou data
- **THEN** o gate MUST permanecer bloqueador

### Requirement: Limites quantitativos usam ledger local e bloqueiam ausência de saldo
O sistema SHALL configurar dia inicial do ciclo entre 1 e 28, alerta em 80%, limite global positivo e limite positivo por Office. Consumo SHALL ser calculado pelo ledger local no ciclo vigente; `null`, zero, negativo, limite ausente ou ambiente divergente MUST bloquear nova operação e MUST NOT significar ilimitado.

#### Scenario: Consumo atinge oitenta por cento
- **WHEN** o ledger alcança 80% do menor limite aplicável
- **THEN** o sistema SHALL sinalizar alerta sem aumentar ou dispensar o teto

#### Scenario: Limite chega a cem por cento
- **WHEN** o ledger mais a reserva solicitada alcança ou excede o teto aplicável
- **THEN** a operação MUST ser bloqueada antes do transporte

### Requirement: Kill switch externo é somente bloqueio emergencial
O estado operacional normal SHALL ser persistido e auditado no banco. `SERPRO_KILL_SWITCH=true` no servidor MUST prevalecer e bloquear toda nova chamada; `false` MUST NOT ativar contrato, credencial, capability, Office ou operação, nem contornar gates e limites.

#### Scenario: Painel indica ativo mas env bloqueia
- **WHEN** o controle persistido permitir operação e `SERPRO_KILL_SWITCH=true`
- **THEN** o sistema MUST bloquear antes do transporte e mostrar origem externa sanitizada

#### Scenario: Env é aberto em instalação não promovida
- **WHEN** `SERPRO_KILL_SWITCH=false` mas não há versão ativa e gates completos
- **THEN** nenhuma operação SHALL se tornar elegível

### Requirement: Mutação direta de contrato legado é proibida
O sistema MUST remover as interfaces que cadastram, ativam, substituem ou desbloqueiam contrato diretamente. Todo novo material SHALL entrar por versão de credencial e seguir verificação, teste e cutover; contratos históricos MAY permanecer em leitura sanitizada.

#### Scenario: Cliente chama endpoint legado de ativação
- **WHEN** uma requisição tenta criar ou ativar contrato pela rota antiga
- **THEN** a API MUST responder como interface removida sem alterar contrato, vault ou versão ativa
