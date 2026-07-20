## ADDED Requirements

### Requirement: Operações públicas catalogadas
O microserviço SHALL executar somente `pgmei.gerardaspdf`, `pgmei.gerardascodbarra`, `pgmei.dividaativa` e `dasnsimei.consultimadecrec` nesta capability e SHALL validar o input específico antes de abrir o navegador.

#### Scenario: Operação ou campo não catalogado
- **WHEN** um job contém operação ou campo de input fora do catálogo C1
- **THEN** o microserviço rejeita o job sem iniciar Playwright nem enviar dado ao portal

#### Scenario: CNPJ alfanumérico
- **WHEN** o job contém CNPJ com exatamente 14 caracteres ASCII alfanuméricos
- **THEN** o valor permanece string sem cast numérico durante validação, navegação e resultado

### Requirement: Navegação fail-closed e versionada
Cada handler SHALL validar checkpoints semânticos das páginas, separar navegação de parsing e classificar drift, captcha e incompatibilidade de formato antes de permitir fallback.

#### Scenario: Marcador esperado ausente
- **WHEN** URL, título, formulário ou marcador esperado não corresponde à versão suportada
- **THEN** o job termina com `PORTAL_DRIFT`, versão do parser e `submitted=false` quando nenhum efeito remoto foi iniciado

#### Scenario: Captcha sem solver habilitado
- **WHEN** o portal exige captcha e nenhum solver permitido resolve o desafio
- **THEN** o job retorna `CAPTCHA_EXHAUSTED` sem técnica de evasão e sem registrar o conteúdo do captcha

#### Scenario: Solver externo explicitamente autorizado
- **WHEN** flag, chave, operação allowlisted, custo unitário e orçamento autorizam resolver o hCaptcha
- **THEN** o microserviço cria no máximo um job externo, faz polling com deadline, injeta o token no mesmo contexto efêmero e não recarrega nem ressubmete a rota de identificação

#### Scenario: Portal aceita somente CNPJ numérico
- **WHEN** o formulário rejeita um CNPJ alfanumérico válido antes da submissão
- **THEN** o job retorna `PORTAL_CNPJ_FORMAT_UNSUPPORTED` com `submitted=false`

### Requirement: Artefatos íntegros e efêmeros
O microserviço SHALL validar nome, tipo, tamanho e SHA-256 dos artefatos antes de disponibilizá-los por download HMAC e SHALL removê-los após o TTL configurado.

#### Scenario: PDF de DAS válido
- **WHEN** o portal conclui emissão e entrega conteúdo PDF dentro do limite
- **THEN** o job retorna descriptor com `application/pdf`, magic bytes válidos, tamanho e SHA-256 sem incluir o conteúdo na resposta JSON

#### Scenario: Download inválido
- **WHEN** o conteúdo anunciado como PDF não possui assinatura PDF ou excede o limite
- **THEN** o job falha antes de publicar artefato e não promove o conteúdo ao Laravel

### Requirement: Resultado DASN com cobertura explícita
O parser DASN-SIMEI SHALL distinguir resumo público de declaração ou recibo integral e SHALL representar ausência de campos sem inferência.

#### Scenario: Somente histórico resumido disponível
- **WHEN** o portal retorna apenas anos e status das declarações
- **THEN** o resultado usa `coverage=SUMMARY` e não declara possuir recibo ou declaração integral

#### Scenario: Artefato integral validado
- **WHEN** uma declaração ou recibo integral é baixado e validado
- **THEN** somente o item correspondente pode usar `coverage=FULL` e referenciar seu descriptor

### Requirement: Execução live desabilitada por padrão
Os handlers SHALL operar por fixtures locais até que live egress, operação e allowlist estejam explicitamente habilitados.

#### Scenario: Live egress desligado
- **WHEN** um job de operação oficial chega sem live egress habilitado e não está em modo fixture
- **THEN** o job falha com `LIVE_EGRESS_DISABLED` antes de qualquer request externo
