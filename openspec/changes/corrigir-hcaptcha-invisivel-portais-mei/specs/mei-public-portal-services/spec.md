## MODIFIED Requirements

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

