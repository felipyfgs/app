# serpro-go-live-controlado Specification

## Purpose

Go-live controlado do Integra Contador: cobrança fail-closed, budgets, executor único, readiness, canário e kill switch sem cobrir rotas faturáveis por engano.

## Requirements


### Requirement: Classificação de cobrança fail-closed
Antes do transporte, toda operação produtiva MUST possuir rota, serviço, versão e classe de cobrança provenientes de catálogo aprovado. Rotas `/Apoiar` e `/Monitorar` e os códigos HTTP 204, 304, 400, 401, 404, 429, 500 e 503 SHALL ser conciliados como não bilhetados conforme a documentação vigente; respostas 200, 202 e 403 em rota faturável SHALL ser tratadas como bilhetáveis, e qualquer rota/status não explicitamente isento MUST permanecer potencialmente faturável até confirmação.

#### Scenario: Serviço Apoiar
- **WHEN** o `ENVIOXMLASSINADO81` é resolvido para `/Apoiar`
- **THEN** o ledger o classifica como não bilhetado, mas ainda registra a tentativa e o resultado

#### Scenario: Classificação desconhecida
- **WHEN** catálogo, rota ou regra de cobrança está ausente/desatualizada
- **THEN** a chamada real é bloqueada antes do transporte, independentemente de `shadow_mode`

### Requirement: Tabela contratual e ciclo de faturamento versionados
O sistema MUST importar todas as faixas de preço aplicáveis do contrato vigente, armazenar valores em micros de BRL, vigência, fonte/hash e ciclo de apuração contratual. O ciclo 21 do mês anterior a 20 do mês corrente MUST ser calculado separadamente do mês calendário e uma tabela estimada/shadow MUST NOT autorizar produção.

#### Scenario: Preço vigente encontrado
- **WHEN** uma operação potencialmente faturável é avaliada em determinada data e volume acumulado
- **THEN** o sistema resolve a faixa contratual correta e registra versão do preço usada na reserva

#### Scenario: Preço ausente ou fora da vigência
- **WHEN** não existe faixa aprovada para a classe/data
- **THEN** a operação é bloqueada como `PRICE_UNKNOWN`

### Requirement: Orçamentos positivos e atômicos
Produção MUST exigir orçamento monetário positivo global, por `Office` e por canário, além de limites por operação/período quando configurados. Reserva e finalização MUST ser atômicas e idempotentes; `null`, zero interpretado como ilimitado, fail-open ou rate limit desconhecido MUST NOT liberar chamadas potencialmente faturáveis.

#### Scenario: Orçamento não configurado
- **WHEN** uma operação faturável é solicitada sem teto monetário vigente
- **THEN** o gate bloqueia antes do cliente HTTP

#### Scenario: Concorrência no último saldo
- **WHEN** dois workers tentam reservar simultaneamente o saldo restante para uma única chamada
- **THEN** no máximo uma reserva é aceita

### Requirement: Executor produtivo único
Todos os adapters, jobs e controllers MUST passar por um executor central que aplique contexto de contrato, representação, catálogo, kill switches, idempotência, orçamento e ledger. O transporte HTTP de baixo nível MUST NOT ser injetável ou invocável diretamente por módulos de negócio em produção.

#### Scenario: Bypass arquitetural
- **WHEN** um teste de arquitetura encontra módulo de negócio chamando `IntegraContadorClient::execute` fora da allowlist do executor
- **THEN** a suíte falha

#### Scenario: Autentica Procurador não bilhetado
- **WHEN** o executor processa a chamada `/Apoiar`
- **THEN** aplica autenticação, auditoria e ledger mesmo sem debitar orçamento faturável

### Requirement: Cobertura executável comprovada por operação
Uma operação MUST ser marcada `IMPLEMENTED` ou promovida para driver real somente se houver snapshot oficial verificável, coordenadas completas, auth mode, matriz de poderes, classe de cobrança, codec tipado de entrada/saída, fixture compatível e testes positivos/negativos. Erro de banco ou manifesto MUST NOT cair para coordenadas inventadas/default em produção.

#### Scenario: Catálogo lista operação sem codec
- **WHEN** uma operação publicada não possui codec e fixture aprovados
- **THEN** ela permanece `CATALOGUED`/`NOT_IMPLEMENTED` e não pode ser executada por adapter genérico

#### Scenario: Revisão no mesmo dia
- **WHEN** a fonte oficial muda mais de uma vez na mesma data
- **THEN** cada snapshot recebe versão/hash imutável distinto e não reescreve o histórico

### Requirement: Idempotência interna e resultado remoto incerto
`X-Request-Tag` MUST ser tratado apenas como correlação, não como garantia oficial de idempotência. A plataforma SHALL namespacear a chave interna por `Office`, ambiente, operação e entidade; uma reserva concorrente/finalizada MUST reutilizar resultado durável ou bloquear, nunca repetir HTTP silenciosamente. Emitir/Declarar e qualquer mutação SHALL usar estados `reserved`, `dispatched`, `acknowledged`, `uncertain` e `reconciled` e permanecem fora deste go-live.

#### Scenario: Replay após resposta finalizada
- **WHEN** a mesma operação lógica é reapresentada com chave já finalizada
- **THEN** o resultado persistido é devolvido sem nova chamada ao SERPRO

#### Scenario: Timeout após dispatch
- **WHEN** o resultado remoto é desconhecido
- **THEN** o estado vira `uncertain` e não há retry cego, principalmente em rotas mutantes

### Requirement: Rate limit e circuit breaker atômicos
Contadores de limite, reservas e probes half-open MUST usar primitivas atômicas compartilhadas. Breakers SHALL ser segmentados por dependência/solução, contar apenas falhas técnicas relevantes e MUST NOT ser fechados pelo sucesso de operação alheia. Limites desconhecidos não podem equivaler a ilimitado em produção.

#### Scenario: Half-open concorrente
- **WHEN** múltiplos workers tentam sondar um circuito half-open
- **THEN** somente o número configurado de probes prossegue

#### Scenario: Erro de negócio 403
- **WHEN** uma operação retorna 403 por autorização
- **THEN** o ledger registra possível cobrança e o breaker técnico global não é incrementado como indisponibilidade

### Requirement: Filas e flags verificáveis no dispatch e no job
Toda fila nomeada por jobs SERPRO MUST possuir consumidor Horizon configurado e ser verificada pelo preflight. Flags e allowlists MUST existir para cada módulo executável, inclusive cadastro e processos, e ser reavaliadas imediatamente antes do HTTP; retirar uma flag após enqueue MUST bloquear o job.

#### Scenario: Fila sem supervisor
- **WHEN** o código declara `onQueue()` sem consumidor correspondente
- **THEN** um teste de configuração e o readiness falham

#### Scenario: Flag retirada com job pendente
- **WHEN** um job foi enfileirado e sua capacidade é desabilitada antes da execução
- **THEN** o job encerra bloqueado sem chamar o SERPRO e preserva histórico de leitura anterior

### Requirement: Escada de testes sem cobrança
O go-live SHALL avançar, em ordem, por: testes offline/fixtures; Trial/demonstração simulada; validação TLS/cadeia; OAuth mTLS; `Autentica Procurador` em `/Apoiar`; e canário em `/Monitorar`. Cada etapa MUST produzir evidência, não pode pular dependência e MUST NOT executar operação Consultar/Emitir/Declarar.

#### Scenario: Validação padrão da mudança
- **WHEN** a implementação e o preflight são executados sem aprovação de canário faturável
- **THEN** nenhuma chamada bilhetável ou mutante é realizada

#### Scenario: Trial aprovado
- **WHEN** cenários oficiais de demonstração passam
- **THEN** o sistema comprova codecs e fluxos apenas como simulação e não promove estado produtivo de Termo, procuração ou cliente

### Requirement: Limites dos eventos de atualização
O canário `/Monitorar` MUST respeitar os limites oficiais versionados, incluindo até 1.000 requisições diárias para PF, 1.000 para PJ e lote de no máximo 1.000 contribuintes enquanto esses valores forem os vigentes. Um `429` MUST suspender novas solicitações até a janela permitida, sem retry agressivo.

#### Scenario: Lote acima do limite
- **WHEN** o planejador recebe mais contribuintes que o máximo vigente
- **THEN** divide deterministicamente em lotes permitidos e aplica limite diário antes de enfileirar

#### Scenario: Limite diário atingido
- **WHEN** o SERPRO retorna `429` ou o contador local alcança a cota
- **THEN** o scheduler adia o restante e emite alerta sem tentar contornar o limite

### Requirement: Máquina de estados de prontidão
O sistema SHALL calcular gates explícitos `CONFIGURED`, `CREDENTIALS_ROTATED`, `TLS_OK`, `OAUTH_OK`, `TERM_LOCAL_VALID`, `TERM_SERPRO_ACCEPTED`, `POWERS_VERIFIED`, `FREE_SMOKE_OK`, `CANARY_READY` e `PRODUCTION_READY`. Estados globais e por `Office`/cliente MUST permanecer separados, e qualquer regressão MUST retirar automaticamente a elegibilidade dependente.

#### Scenario: Certificado expira após readiness
- **WHEN** o certificado deixa de ser válido
- **THEN** `TLS_OK` e todos os estados dependentes são retirados e os jobs param antes do transporte

#### Scenario: Escritório não promovido
- **WHEN** o contrato global está saudável mas um `Office` não tem Termo aceito
- **THEN** somente o global permanece pronto e aquele escritório continua bloqueado

### Requirement: Canário faturável requer autorização separada
Uma primeira chamada potencialmente faturável MUST ser opcional, read-only, delimitada por ambiente, `Office`, cliente, operação, custo máximo, quantidade máxima, janela curta e chave de idempotência. Ela SHALL exigir aprovação registrada por dois usuários distintos: um `PLATFORM_ADMIN` e um `Office ADMIN`, cada um com reconfirmação da própria senha válida por no máximo quinze minutos. TOTP/2FA MUST NOT ser exigido, e o canário MUST NOT fazer parte de CI, setup, deploy, health check ou preflight.

#### Scenario: Usuário deseja testar sem pagar
- **WHEN** não existe aprovação de canário faturável ativa
- **THEN** o processo encerra em `FREE_SMOKE_OK` sem executar Consultar, Emitir ou Declarar

#### Scenario: Aprovação incompleta
- **WHEN** falta um dos aprovadores, sua reconfirmação recente, teto ou escopo exato
- **THEN** a chamada permanece bloqueada

#### Scenario: Conta dual tenta aprovar pelos dois papéis
- **WHEN** a mesma conta dual tenta registrar as aprovações global e do Office
- **THEN** o sistema SHALL aceitar no máximo uma delas e continuar exigindo um segundo usuário autorizado

### Requirement: Flags, kill switch e rollback
Drivers reais e feature flags SHALL iniciar desligados e ser promovidos por capacidade e allowlist de `Office`. O kill switch global MUST prevalecer sobre qualquer flag, interromper novos jobs e preservar reservas/evidências para conciliação. Mutações fiscais MUST permanecer desligadas nesta mudança.

#### Scenario: Kill switch acionado
- **WHEN** um operador autorizado aciona o kill switch durante o canário
- **THEN** novas chamadas e retries são bloqueados, jobs pendentes são drenados/cancelados com segurança e nenhuma mutação é liberada

#### Scenario: Rollback de uma família
- **WHEN** somente uma família apresenta erro
- **THEN** sua flag/allowlist é retirada sem habilitar fallback fake nem afetar famílias independentes já validadas
