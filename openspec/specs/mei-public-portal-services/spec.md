# mei-public-portal-services Specification

## Purpose
TBD - created by archiving change automatizar-servicos-publicos-mei. Update Purpose after archive.
## Requirements
### Requirement: Operações públicas catalogadas
O microserviço SHALL executar somente `pgmei.gerardaspdf`, `pgmei.gerardascodbarra`, `pgmei.dividaativa` e `dasnsimei.consultimadecrec` nesta capability e SHALL validar o input específico antes de abrir o navegador.

#### Scenario: Operação ou campo não catalogado
- **WHEN** um job contém operação ou campo de input fora do catálogo C1
- **THEN** o microserviço rejeita o job sem iniciar Playwright nem enviar dado ao portal

#### Scenario: CNPJ alfanumérico
- **WHEN** o job contém CNPJ com exatamente 14 caracteres ASCII alfanuméricos
- **THEN** o valor permanece string sem cast numérico durante validação, navegação e resultado

### Requirement: Navegação fail-closed e versionada
Cada handler SHALL validar checkpoints semânticos das páginas, separar navegação de parsing, distinguir integração passiva de hCaptcha de desafio efetivo e classificar drift, captcha e incompatibilidade de formato antes de permitir fallback. A submissão pública de identificação SHALL ocorrer no máximo uma vez por execução e não SHALL alterar o significado fiscal de `submitted`.

#### Scenario: Marcador esperado ausente
- **WHEN** URL, título, formulário ou marcador esperado não corresponde à versão suportada
- **THEN** o job termina com `PORTAL_DRIFT`, versão do parser e `submitted=false` quando nenhum efeito remoto fiscal foi iniciado

#### Scenario: Integração invisível presente antes da submissão
- **WHEN** o formulário contém widget, textarea ou iframe de hCaptcha invisível, mas não há desafio interativo visível nem rejeição explícita
- **THEN** o handler não aciona solver, submete a identificação uma única vez e aguarda um checkpoint semântico do portal

#### Scenario: Aprovação automática pelo portal
- **WHEN** o checkpoint de sucesso aparece após a única submissão de identificação sem desafio efetivo
- **THEN** o handler continua a operação no mesmo contexto efêmero sem custo de solver e sem uma segunda submissão da identificação

#### Scenario: Captcha sem solver habilitado
- **WHEN** o portal apresenta desafio efetivo e nenhum solver permitido resolve o desafio
- **THEN** o job retorna `CAPTCHA_EXHAUSTED`, `submitted=false`, sem técnica de evasão, reload, nova submissão de identificação ou registro do conteúdo do captcha

#### Scenario: Solver externo explicitamente autorizado
- **WHEN** um desafio efetivo aparece e flag, chave, operação allowlisted, custo unitário e orçamento autorizam resolver o hCaptcha
- **THEN** o microserviço cria no máximo um job externo, faz polling com deadline, injeta o token no mesmo contexto efêmero e aguarda o callback/checkpoint pendente sem recarregar nem ressubmeter a rota de identificação

#### Scenario: Validação rejeita a identificação
- **WHEN** o portal responde à única submissão com um marcador de validação suportado e nenhum efeito fiscal foi iniciado
- **THEN** o job falha de forma determinística com `submitted=false`, sem solver e sem repetir a submissão

#### Scenario: Resultado da identificação permanece ambíguo
- **WHEN** nenhum checkpoint de sucesso, validação ou desafio efetivo aparece até o deadline após a única submissão
- **THEN** o job retorna `PORTAL_DRIFT` com `submitted=false` e não recarrega nem repete a submissão nessa execução

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

