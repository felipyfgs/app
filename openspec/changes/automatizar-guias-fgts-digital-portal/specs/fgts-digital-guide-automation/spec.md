## ADDED Requirements

### Requirement: Provider de portal inicia fechado e expõe readiness verificável

O sistema SHALL oferecer driver `disabled|fixture|portal_browser` para FGTS Digital, SHALL iniciar em `disabled`, MUST manter egress produtivo e mutações desligados por padrão e SHALL expor readiness tenant-scoped sem material sensível.

#### Scenario: Defaults não acessam o portal

- **WHEN** nenhuma configuração FGTS Digital é definida
- **THEN** o driver é `disabled`, readiness fica bloqueada e nenhum processo/browser é iniciado

#### Scenario: Configuração inválida falha antes do egress

- **WHEN** host, ambiente, runtime ou combinação de flags não pertence à allowlist
- **THEN** readiness retorna código estável de configuração e não materializa credencial nem inicia o processo RPA

### Requirement: Autenticação respeita identidade, procuração e resolução externa controlada

O sistema SHALL autenticar com A1 ativo do cliente ou identidade de escritório explicitamente configurada, MUST exigir vínculo de procuração para `PROCURADOR_PJ`, SHALL vincular sessão cifrada ao tenant, fingerprint, perfil e empregador e MAY resolver hCaptcha pela API NopeCHA somente com opt-in e orçamento; proxy compartilhado é opcional e MUST ser usado por browser e provider quando configurado.

#### Scenario: Escritório atua como procurador autorizado

- **WHEN** o office possui identidade A1 ativa, vínculo de procuração válido e o empregador aparece no perfil `PROCURADOR_PJ`
- **THEN** a sessão é vinculada ao office, certificado, perfil e cliente selecionado e a navegação pode prosseguir

#### Scenario: Cliente de outro office não reutiliza vínculo

- **WHEN** uma operação tenta usar sessão, credencial ou procuração pertencente a outro office
- **THEN** o sistema nega acesso antes do browser e não revela a existência do material

#### Scenario: hCaptcha é resolvido no contexto real do navegador

- **WHEN** o login apresenta hCaptcha e o solver NopeCHA está habilitado
- **THEN** o worker envia `sitekey`, URL e contexto à API externa, inclui o proxy compartilhado quando configurado, aplica o token de uso único e só conclui `SESSION_READY` após comprovar marcador autenticado do FGTS Digital

#### Scenario: Solver ativo sem proxy compartilhado

- **WHEN** o driver de CAPTCHA é `nopecha`, a chave está válida e não há proxy configurado
- **THEN** readiness permite a execução e o worker solicita o token diretamente à API externa sem enviar o campo `proxy`

#### Scenario: Proxy opcional está malformado

- **WHEN** o driver de CAPTCHA é `nopecha` e um proxy não vazio possui esquema, host, porta ou autenticação inválidos
- **THEN** readiness e execução retornam `CAPTCHA_PROXY_INVALID` antes de consumir créditos ou iniciar autenticação

#### Scenario: Token não autentica ou desafio não é suportado

- **WHEN** o Gov.br reapresenta o hCaptcha, rejeita o token ou exige validação de dispositivo/antifraude diferente
- **THEN** a execução retorna `CAPTCHA_TOKEN_REJECTED` ou `HUMAN_CHALLENGE_REQUIRED`, não inventa sessão e não executa mutação

### Requirement: Consulta e download persistem guias e artefatos oficiais

O sistema SHALL consultar débitos/guias e situação de pagamento, reimprimir ou baixar PDF/relatórios, SHALL validar e cifrar os artefatos e SHALL deduplicar guias por identificador oficial ou hash seguro no escopo do cliente.

#### Scenario: Guia existente é sincronizada

- **WHEN** o portal retorna uma guia válida com documento PDF
- **THEN** o hub persiste origem `FGTS_DIGITAL_PORTAL`, campos allowlisted, `checked_at` e descriptor autenticado sem expor HTML, cookie ou token

#### Scenario: Documento inválido não é promovido

- **WHEN** o download não possui assinatura PDF/mime/tamanho aceitos ou diverge da guia consultada
- **THEN** o artefato é rejeitado, a guia não recebe documento válido e o run registra apenas diagnóstico sanitizado

#### Scenario: Situação desconhecida permanece desconhecida

- **WHEN** o portal não comprova a situação de pagamento
- **THEN** o status persistido é `UNKNOWN` com horário da última consulta, sem inferir quitação

### Requirement: Guia rápida é emitida como mutação idempotente

O sistema SHALL suportar guia rápida `MONTHLY`, `TERMINATION`, `CONSIGNMENT` e `MIXED` por fluxo preview-autorização-execução, MUST reservar chave de idempotência e SHALL reconciliar guia existente antes e depois do clique final.

#### Scenario: Autorização válida emite uma única vez

- **WHEN** um usuário autorizado confirma preview vigente e não existe guia equivalente
- **THEN** um job serializado emite a guia, reconcilia número/valor/documento e conclui a operação uma única vez

#### Scenario: Repetição reutiliza guia equivalente

- **WHEN** a mesma competência, tipo e seleção já possuem guia equivalente no portal ou ledger
- **THEN** a execução retorna `REUSED`, sincroniza o artefato disponível e não clica em emitir novamente

#### Scenario: Resposta se perde após confirmação

- **WHEN** o clique final pode ter sido aceito mas a resposta não permite confirmar número e valor
- **THEN** a operação fica `RECONCILIATION_REQUIRED` e não recebe retry automático de emissão

### Requirement: Guia parametrizada exige preview fiel e autorização vinculada

O sistema SHALL permitir filtros e seleção de débitos para guia parametrizada, SHALL devolver preview com totais e fingerprint e MUST rejeitar autorização expirada ou divergente.

#### Scenario: Seleção alterada invalida autorização

- **WHEN** competência, débitos, vencimento, tipo ou total diferem do preview autorizado
- **THEN** a execução é bloqueada antes da mutação e exige novo preview

#### Scenario: Política agendada é opt-in e limitada

- **WHEN** uma política explícita permite emissão para clientes, tipos e limites definidos e readiness está pronta
- **THEN** o scheduler pode enfileirar a emissão com a mesma idempotência e auditoria do fluxo manual

### Requirement: Pagamento permanece operação somente leitura

O sistema SHALL consultar e exibir a situação de pagamento e informações do documento, mas MUST NOT pagar, iniciar Pix, copiar segredo de pagamento para log nem marcar uma guia como paga sem evidência do portal.

#### Scenario: Guia contém QR Code Pix

- **WHEN** o PDF ou detalhe do portal apresenta QR Code ou código Pix
- **THEN** o hub preserva o documento protegido e não executa nem agenda o pagamento

### Requirement: Execuções são isoladas, serializadas e auditáveis

Toda operação SHALL ser tenant-scoped, SHALL adquirir locks por identidade/perfil/empregador e por idempotência de mutação, SHALL registrar ledger sanitizado e MUST eliminar material efêmero ao final.

#### Scenario: Duas execuções concorrem pela mesma identidade

- **WHEN** dois jobs tentam navegar com a mesma sessão, perfil e empregador
- **THEN** apenas um obtém o lock e o outro é reagendado antes do egress

#### Scenario: Diagnóstico público não vaza segredo

- **WHEN** readiness, run ou erro é serializado em API/log
- **THEN** PFX, senha, cookie, chave NopeCHA, credencial de proxy, token CAPTCHA, HTML bruto, CPF e CNPJ completo estão ausentes

### Requirement: API, scheduler e painel representam estados reais do portal

O sistema SHALL expor coverage/readiness, sync, preview, autorização, emissão, runs e downloads em rotas Sanctum tenant-scoped, e a página `/monitoring/fgts` SHALL distinguir eSocial BX de FGTS Digital e apresentar estados bloqueados e desafios humanos de forma acionável.

#### Scenario: Usuário somente leitura tenta emitir

- **WHEN** um membro sem permissão de mutação chama autorização ou emissão
- **THEN** a API nega a ação e nenhum job ou ledger de mutação é criado

#### Scenario: Mudança de contrato do portal é visível

- **WHEN** o RPA não reconhece uma página ou resposta obrigatória
- **THEN** a execução retorna `PORTAL_CONTRACT_CHANGED`, suspende mutações do vínculo e o painel mostra diagnóstico sanitizado
