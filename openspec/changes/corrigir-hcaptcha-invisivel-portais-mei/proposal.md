## Why

Os portais atuais do PGMEI e da DASN-SIMEI carregam a integração do hCaptcha invisível antes de existir um desafio efetivo, mas o microserviço interpreta a mera presença de `.h-captcha` como bloqueio e encerra a consulta com `CAPTCHA_EXHAUSTED` antes de submeter a identificação. Isso impede obter dados reais mesmo quando o próprio portal aprovaria a navegação normal sem interação ou solver externo.

## What Changes

- Distingue integração de hCaptcha carregada, desafio efetivamente exigido e aprovação automática pelo portal.
- Submete uma única vez o formulário público de identificação e aguarda checkpoints semânticos de sucesso, validação, desafio ou drift, sem reload nem repetição automática.
- Aciona o contrato de solver somente quando houver desafio efetivo e a política já existente autorizar; caso contrário, falha fechado com `CAPTCHA_EXHAUSTED` e `submitted=false` para o efeito fiscal.
- Preserva a separação entre identificação pública e submissão fiscal, incluindo a proibição de replay após qualquer ação mutante.
- Acrescenta fixtures e testes de regressão para hCaptcha invisível aprovado, desafio explícito, erro de validação e mudança inesperada do portal.
- Mantém egress live, smoke com CNPJ real e solver externo OFF por padrão e exige as guardas já existentes para qualquer execução controlada.
- Não implementa bypass de CAPTCHA, stealth/anti-detecção, dependência de runtime do `docapi`, habilitação padrão do NoPeCHA, consulta ampla de optantes, transmissão DASN ou nova operação fiscal mutante.

## Capabilities

### New Capabilities

Nenhuma.

### Modified Capabilities

- `mei-public-portal-services`: refina a navegação fail-closed para não confundir o widget invisível carregado com um desafio efetivo e define os checkpoints da submissão pública de identificação.

## Impact

- `services/mei/`: detecção de CAPTCHA, estado de navegação PGMEI/DASN-SIMEI, telemetria sanitizada, fixtures e testes.
- Contrato externo dos jobs: preserva os códigos e o significado fiscal de `submitted`; pode acrescentar apenas metadados internos/sanitizados de checkpoint, sem token, sitekey, CNPJ ou HTML bruto.
- Portais da Receita Federal: uma submissão normal do formulário público por tentativa live autorizada; nenhum novo endpoint ou fornecedor externo obrigatório.
- Referência de pesquisa: `felipyfgs/docapi` não será incorporado como dependência nem como fonte de técnicas de evasão.

### Dependências entre changes

- Nível: `C2`.
- Bases estáveis: filas e HMAC do orquestrador MEI, execução Playwright efêmera, flags/allowlists de egress e contrato de solver fail-closed.
- Depende de: `automatizar-servicos-publicos-mei`.
- Capability/contrato: modifica `mei-public-portal-services`, criada pela change upstream, e consome seus handlers PGMEI/DASN-SIMEI e sua taxonomia de resultados.
- Marco exigido: `verify`; relação bloqueante. O marco já está satisfeito pelo checklist concluído da change upstream, mas ela permanece ativa até archive.
- Desbloqueia: validação live controlada e uso confiável do provider portal nas operações públicas já catalogadas.
- Paralelismo: não deve alterar `mei-public-portal-services` em paralelo com a upstream; após o `verify` dela, implementação do detector e fixtures pode avançar em paralelo desde que os arquivos tenham ownership distinto e convirjam antes dos gates integrados.

