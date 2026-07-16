# serpro-operacao-observavel Specification

## Purpose

Operação observável SERPRO: readiness sanitizada, ledger reconciliável, jobs assíncronos, auditoria, alertas sem PII e runbooks de recuperação.

## Requirements


### Requirement: Readiness verificável e sanitizada
O sistema SHALL fornecer comando e API de preflight que avaliem dependências globais, do `Office`, cliente e operação e retornem PASS/FAIL com códigos e evidências sanitizadas. Health check MUST ser read-only e MUST NOT gerar token, Termo, consumo ou alteração de estado remoto implicitamente.

#### Scenario: Preflight sem rede de negócio
- **WHEN** o operador solicita apenas diagnóstico
- **THEN** nenhuma chamada fiscal é feita e o resultado diferencia configuração local, evidência live anterior e estado desconhecido

#### Scenario: Visão do escritório
- **WHEN** um membro consulta readiness tenant-scoped
- **THEN** recebe apenas gates e evidências do `CurrentOffice`, sem orçamento global ou dados de outro escritório

### Requirement: Ledger completo e reconciliável
Toda tentativa remota MUST gerar reserva/entrada idempotente com contrato, `Office`, cliente pseudonimizado, operação, rota, versão, classe de cobrança, preço, request tag, timestamps, latência e resultado. O ledger MUST registrar 304, falha local pré-transporte, transporte incerto e resposta remota sem transformar incerteza em gratuidade.

#### Scenario: Timeout após envio
- **WHEN** o cliente não consegue saber se o SERPRO processou a requisição
- **THEN** a entrada fica `POSSIBLY_BILLABLE` até reconciliação e um retry não mutante exige a mesma idempotência/política

#### Scenario: Erro pré-transporte
- **WHEN** um gate local bloqueia antes de abrir conexão
- **THEN** o evento é auditado sem reservar consumo remoto faturável

### Requirement: Execuções assíncronas duráveis
Jobs SERPRO MUST persistir run, tentativa, cursor, estado, erro sanitizado e correlação; falha retornada pelo serviço MUST provocar estado/retry compatível, e persistência por página/lote SHALL ser transacional. Locks MUST cobrir ou renovar o pior tempo de execução e `Retry-After`/tempos oficiais MUST ser respeitados.

#### Scenario: Serviço retorna falha lógica
- **WHEN** o adapter devolve resposta de erro permanente
- **THEN** o job não termina como sucesso nem repete indefinidamente

#### Scenario: Worker cai após uma página
- **WHEN** o job reinicia após persistir parte de um lote
- **THEN** retoma do cursor durável sem duplicar chamada ou projeção já confirmada

### Requirement: X-Request-Tag opaca
Cada chamada SHALL enviar `X-Request-Tag` de no máximo 32 caracteres, estável para correlação e sem CNPJ, CPF, nome, e-mail ou identificador interno diretamente reconhecível. O mesmo valor MUST conectar ledger, logs sanitizados e arquivo de consumo.

#### Scenario: Tag derivada de identidade
- **WHEN** a tag é gerada para uma operação de cliente
- **THEN** usa identificador aleatório ou HMAC truncado e a varredura confirma ausência de PII

### Requirement: Conciliação de consumo e fatura
O sistema MUST importar o detalhamento oficial de consumo e a fatura sem confiar em `office_id` fornecido no arquivo, reconciliar por período contratual, rota, status e request tag e sinalizar chamadas ausentes, duplicadas, preço divergente ou `POSSIBLY_BILLABLE`. Aprovação humana SHALL ser exigida para fechar divergências materiais.

#### Scenario: Arquivo oficial diverge do ledger
- **WHEN** uma linha faturada não possui entrada compatível ou o valor usa faixa diferente
- **THEN** o período permanece aberto com incidente de conciliação e nenhuma correção destrutiva automática é feita

#### Scenario: Importação tenant-safe
- **WHEN** os dados são projetados para um escritório
- **THEN** apenas suas próprias entradas e totais são expostos; valores globais e outros tenants ficam restritos à plataforma

### Requirement: Auditoria resistente a adulteração
Ações sensíveis, mudanças de gate, rotação, consentimento, assinatura, aceite, aprovação, kill switch e conciliação MUST produzir auditoria append-only com ator, método e instante da confirmação aplicável, tempo, motivo, versão e hash encadeado ou armazenamento imutável equivalente. A auditoria MUST NOT exigir nem afirmar TOTP/2FA quando o produto usa reconfirmação de senha. Segredos, senhas e XML bruto MUST ser excluídos do evento.

#### Scenario: Edição de evento histórico
- **WHEN** um evento armazenado é alterado ou removido fora do fluxo autorizado
- **THEN** a verificação de integridade detecta a quebra e abre alerta

#### Scenario: Ação com senha recente
- **WHEN** uma ação sensível é autorizada por reconfirmação de senha
- **THEN** o evento SHALL registrar o método e instante da confirmação sem registrar a senha, hash de senha ou dado reutilizável

### Requirement: Alertas, SLOs e circuit breaker sem PII
Métricas SHALL cobrir disponibilidade OAuth/API, latência, erro por classe, breaker, filas, expirações, orçamento, 429, drift documental e reconciliação. Labels MUST ter cardinalidade limitada e nenhuma PII. Alertas MUST indicar runbook e distinguir falha global de falha de um `Office`.

#### Scenario: Falha 5xx sustentada
- **WHEN** erros transitórios atingem o limiar do circuit breaker
- **THEN** o circuito abre, retries usam backoff/jitter dentro da janela segura e o alerta referencia o runbook sem payload fiscal

#### Scenario: Falha isolada de autorização
- **WHEN** apenas um escritório recebe 403 por representação/poder
- **THEN** o alerta e bloqueio permanecem tenant-scoped e não abrem o circuito global sem evidência sistêmica

### Requirement: Proveniência e detecção de drift documental
Catálogo, API Reference, autenticação, layout do Termo, serviços x procurações, códigos de retorno, limites, cadeia TLS e tabela contratual MUST possuir snapshots/metadados versionados com URL, data de coleta e hash. Mudança de hash SHALL abrir revisão antes de alterar automaticamente comportamento produtivo sensível.

#### Scenario: API Reference atualizada
- **WHEN** a coleta periódica detecta conteúdo diferente na fonte oficial
- **THEN** o sistema registra diff sanitizado, marca capacidades afetadas `REVIEW_REQUIRED` e mantém fail-closed onde a mudança altera protocolo, poder ou cobrança

### Requirement: Protocolo assíncrono de Eventos observável
O fluxo `/Monitorar` MUST persistir protocolo, `TempoEsperaMedioEmMs`, `TempoLimiteEmMin`, cursor/lote e estado one-shot. O polling SHALL usar os valores de cada resposta, e depois de uma obtenção `200` o resultado MUST ser tratado como consumido e persistido antes de nova tentativa.

#### Scenario: TTL diferente do exemplo documental
- **WHEN** a resposta informa tempo limite distinto do default conhecido
- **THEN** o scheduler usa o valor recebido e não um TTL hardcoded

#### Scenario: Resultado obtido uma vez
- **WHEN** a obtenção retorna 200 e o SERPRO remove o resultado remoto
- **THEN** a plataforma persiste atomicamente a evidência/projeção e não depende de segunda leitura

### Requirement: Governança contratual e privacidade
Antes do go-live, a plataforma MUST registrar aprovação da versão contratual/tarifária, papéis de controlador/operador, finalidade, hipótese legal, categorias, retenção, sigilo fiscal, instruções do escritório, resposta a incidentes e revisão jurídica aplicável. Divergência material de vigência contratual, preço ou responsabilidade SHALL permanecer como bloqueio documental, sem ser resolvida por inferência do software.

#### Scenario: Vigência contratual conflitante
- **WHEN** capa e cláusula do contrato apresentam prazos incompatíveis e não há confirmação formal
- **THEN** o readiness registra `LEGAL_REVIEW_REQUIRED` e não declara o go-live plenamente aprovado

#### Scenario: Offboarding de escritório
- **WHEN** a relação com um escritório termina
- **THEN** o sistema revoga acesso e agenda eliminação de PFX, tokens e Termos conforme retenção legal documentada, preservando apenas auditoria necessária

### Requirement: Runbooks e evidência de recuperação
O repositório SHALL conter runbooks executáveis para rotação de Key/Secret, renovação de certificados, Termo rejeitado, 401/403/429/5xx, cadeia TLS, estouro de orçamento, vazamento, kill switch, conciliação e rollback. Um restore drill MUST comprovar banco, vault e chave externa em ambiente isolado antes do go-live.

#### Scenario: Exercício de incidente
- **WHEN** o runbook de credencial comprometida é ensaiado
- **THEN** ele bloqueia versão exposta, invalida tokens, rotaciona a credencial, valida a nova versão e preserva trilha de auditoria sem revelar segredo
