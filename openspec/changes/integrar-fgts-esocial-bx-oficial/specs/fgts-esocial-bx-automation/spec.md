## ADDED Requirements

### Requirement: Fonte oficial eSocial BX read-only e fail-closed

O hub SHALL oferecer um driver `official_bx` para consultar eventos FGTS pelo eSocial BX oficial e MUST manter o driver desabilitado por padrão. O driver SHALL operar somente em Produção Restrita ou Produção explicitamente configurada, SHALL exigir gate adicional para egress de Produção e MUST NOT chamar portal FGTS Digital, Gov.br, SERPRO Integra Contador, CAPTCHA, cookies ou automação de navegador.

#### Scenario: Defaults não realizam egress

- **WHEN** a aplicação inicia sem configuração explícita do driver FGTS/eSocial
- **THEN** `EsocialEventClient` permanece desabilitado e nenhuma chamada externa é realizada

#### Scenario: Produção exige gate adicional

- **WHEN** o driver oficial está selecionado para Produção e o gate de egress produtivo está desligado
- **THEN** readiness e sincronização são bloqueadas antes do transporte mTLS

#### Scenario: Fonte não usa portal nem SERPRO

- **WHEN** uma competência FGTS é sincronizada pelo driver oficial
- **THEN** apenas os endpoints allowlisted do eSocial BX são elegíveis e nenhum fallback humano ou `operation_key` SERPRO é usado

### Requirement: Identidade, credencial e isolamento tenant

Toda consulta eSocial BX SHALL resolver `Office` pelo contexto autenticado e `Client` pelo mesmo `office_id`. A integração MUST usar somente `ClientCredential` ACTIVE, não expirada e pertencente à raiz do CNPJ do cliente; PFX e senha MUST permanecer no `SecureObjectStore` e somente em memória durante assinatura/mTLS. Ausência ou invalidade da credencial SHALL bloquear o enqueue e MUST NOT consultar cliente de outro escritório.

#### Scenario: Cliente de outro escritório é invisível

- **WHEN** um usuário autenticado solicita readiness ou sync com `client_id` pertencente a outro `office_id`
- **THEN** a API retorna recurso não encontrado e não materializa credencial nem cria ledger externo

#### Scenario: Credencial ausente bloqueia antes da fila

- **WHEN** o cliente do escritório atual não possui A1 ACTIVE utilizável
- **THEN** readiness informa blocker estável e `sync` não enfileira job nem chama o eSocial

#### Scenario: Segredos e XML não são serializados

- **WHEN** coverage, readiness, ledger, competência ou evento são retornados pela API ou registrados em log
- **THEN** PFX, senha, chave privada e XML bruto MUST NOT aparecer na resposta/log e a evidência completa permanece no vault

### Requirement: Protocolo SOAP e eventos oficiais validados

O driver SHALL usar SOAP 1.1, mTLS com TLS validado e mensagens XMLDSig RSA-SHA256/SHA-256/C14N enveloped conforme o Manual do Desenvolvedor. Para cada competência, SHALL consultar identificadores agregados de S-1299 e S-5013 e, quando houver IDs, SHALL baixar no máximo 50 arquivos por solicitação. O parser MUST aceitar somente XML bem formado, evento whitelisted e competência correspondente; fault, HTTP inválido ou resposta incompatível MUST NOT promover estado fiscal.

#### Scenario: Consulta e download válidos

- **WHEN** o eSocial retorna identificadores S-1299/S-5013 e arquivos válidos da competência solicitada
- **THEN** o driver produz DTOs oficiais com código, competência, versão, recibo/hash e payload XML para persistência protegida

#### Scenario: Nenhum registro é sucesso vazio

- **WHEN** a consulta de identificadores retorna o código oficial 406
- **THEN** o driver trata o tipo como resultado vazio bem-sucedido, sem criar evidência sintética

#### Scenario: Resposta parcial não vira certeza completa

- **WHEN** o eSocial retorna código 203 ou quantidade total maior que os identificadores entregues
- **THEN** o resultado é marcado parcial e a API não declara cobertura completa daquela dimensão

#### Scenario: Evento divergente é rejeitado

- **WHEN** um arquivo contém tipo não whitelisted, competência diferente, XML malformado ou SOAP fault
- **THEN** nenhuma competência fiscal é promovida com esse arquivo e o diagnóstico público permanece sanitizado

### Requirement: Limites oficiais e concorrência por empregador

O hub SHALL bloquear consultas entre os dias 1 e 7, SHALL limitar conservadoramente a 10 acessos eSocial BX por empregador/dia somando consulta e download e MUST impedir solicitações concorrentes do mesmo empregador. Cada tentativa de transporte SHALL ser reservada em ledger durável antes do egress; falha de lock, quota esgotada ou códigos oficiais 403/404/405 SHALL resultar em bloqueio/retry controlado sem fallback externo.

#### Scenario: Janela mensal fechada

- **WHEN** readiness ou sync é avaliado entre os dias 1 e 7 no timezone configurado
- **THEN** a operação é bloqueada antes de materializar credencial ou consumir chamada

#### Scenario: Décima primeira chamada é negada

- **WHEN** o ledger já contém 10 tentativas reservadas no dia para o mesmo empregador e ambiente
- **THEN** a próxima chamada não alcança o transporte e retorna blocker de quota

#### Scenario: Jobs concorrentes são serializados

- **WHEN** dois jobs tentam sincronizar simultaneamente o mesmo empregador
- **THEN** somente um obtém o lock distribuído e o outro é reprogramado/bloqueado sem egress concorrente

#### Scenario: Auditoria não contém payload

- **WHEN** uma chamada é reservada e concluída ou falha
- **THEN** o ledger registra tenant, cliente, ambiente, operação, status e códigos sanitizados, mas MUST NOT armazenar XML, PFX, senha ou CNPJ completo

### Requirement: Persistência idempotente e estados FGTS independentes

Eventos válidos SHALL ser persistidos idempotentemente por escritório, cliente, competência, tipo e SHA-256, com bytes no vault. S-1299 SHALL alimentar somente o estado de fechamento; S-5013 e eventual S-5003 oficial SHALL alimentar somente totalização. Guia, pagamento, débito e pendências do FGTS Digital MUST permanecer `UNSUPPORTED`, e nenhum totalizador SHALL ser interpretado como emissão, quitação ou regularidade completa.

#### Scenario: Repetição não duplica evidência

- **WHEN** o mesmo XML oficial é baixado novamente para a mesma competência
- **THEN** a persistência reutiliza a evidência lógica existente e não duplica o status

#### Scenario: Fechamento não prova totalização ou pagamento

- **WHEN** existe S-1299 sem S-5013/S-5003
- **THEN** fechamento pode ficar `CONFIRMED`, totalização segue `UNKNOWN`/`ABSENT` conforme a janela e guia/pagamento seguem `UNSUPPORTED`

#### Scenario: Totalizador não prova quitação

- **WHEN** existe S-5013 ou S-5003 válido
- **THEN** totalização pode ficar `PRESENT`, mas `declares_fgts_digital_debt` permanece falso e pagamento não muda para pago

### Requirement: Coverage, readiness, API, scheduler e UI honestos

O manifesto SHALL distinguir eventos aceitos de eventos buscados automaticamente, identificar driver/ambiente/fonte oficial, expor limites e manter links documentais sem segredos. A API tenant SHALL oferecer readiness por cliente e SHALL usar o mesmo preflight em `sync` e `sync-now`. O job Horizon e o adapter do scheduler fiscal SHALL respeitar os mesmos blockers. A página `/monitoring/fgts` SHALL mostrar a fonte e limitações, permitir a sincronização por competência quando ready e continuar exibindo guia/pagamento como `UNSUPPORTED`.

#### Scenario: S-5003 é aceito mas não buscado sem CPF

- **WHEN** o manifesto do driver oficial é consultado
- **THEN** S-5003 aparece entre eventos aceitos e não entre eventos automáticos, com motivo explícito de que a consulta por trabalhador exige CPF/identificador oficial local

#### Scenario: Readiness pronto permite enqueue

- **WHEN** driver, egress, feature, janela, quota e credencial do cliente estão elegíveis
- **THEN** readiness retorna `ready=true` e `sync` enfileira o job tenant-scoped para a competência validada

#### Scenario: Scheduler obedece ao mesmo gate

- **WHEN** uma execução agendada encontra janela bloqueada, quota esgotada ou credencial inválida
- **THEN** o adapter registra resultado bloqueado com código estável e não chama o transporte

#### Scenario: UI mantém cobertura parcial visível

- **WHEN** o operador abre `/monitoring/fgts`
- **THEN** a UI apresenta a fonte eSocial BX, eventos automáticos e limitações, sem sugerir que guia, pagamento ou pendências do portal foram consultados
