## Why

A configuração produtiva do SERPRO ainda está dispersa entre ambiente, comandos e telas legadas que permitem ativação sem versionamento e sem teste OAuth explícito. O Proprietário precisa de uma única superfície global, auditada e fail-closed para preparar Trial e Production sem expor segredos nem executar operação fiscal.

## What Changes

- Criar a aba global `/admin/serpro/configuration`, exclusiva do Proprietário, com ambientes Trial/Production isolados, resumo de prontidão, endpoints oficiais somente leitura, credenciais versionadas, gates externos, limites quantitativos, kill switch e histórico.
- Implementar o ciclo `PENDING → VERIFIED → ACTIVE → RETIRED|COMPROMISED`: upload valida PFX e grava PFX/senha/Key/Secret no vault; teste explícito executa somente OAuth mTLS; cutover exige teste recente da mesma versão, senha recente, frase, motivo e janela.
- Criar APIs globais sanitizadas para consultar configuração, cadastrar/verificar/testar/promover versões, registrar gates e manter limites de consumo.
- **BREAKING**: remover o cadastro/ativação direta legado de contratos SERPRO, que permitia pular o ciclo versionado e o teste de conexão.
- Persistir controles operacionais no banco; `SERPRO_KILL_SWITCH=true` no servidor continuará sendo somente bloqueio emergencial prevalente e nunca habilitará operação.
- Manter seis gates Production bloqueadores, com referência, resumo, responsável e data, sem upload documental; configurar início do ciclo, alerta em 80% e limites iniciais positivos por instalação e Office.
- Non-goals: executar OAuth real em CI/deploy, chamar rota fiscal SERPRO, habilitar DTE ou outra capability, manter preços em reais, dispensar gate, produzir parecer jurídico ou resolver ticket externo.

## Capabilities

### New Capabilities

- `serpro-configuracao-global`: Superfície única e auditada para configuração global SERPRO, gates externos, limites quantitativos e controle operacional por ambiente.

### Modified Capabilities

- `serpro-credenciais-produtivas`: Substitui ativação direta por versões no vault com teste OAuth explícito, sanitização e cutover vinculado à evidência recente.

## Impact

- **Backend:** migrations, modelos e serviços globais; rotas `/api/v1/platform/serpro/*`; descontinuação das mutações legadas de contrato; auditoria e redaction.
- **Frontend:** página Settings no painel global, composables/tipos e testes sem recuperação de segredo.
- **Segurança:** PFX, senha, Consumer Key/Secret e tokens permanecem exclusivamente no `SecureObjectStore`; respostas mostram apenas CNPJ mascarado, fingerprint, validade, final da key e timestamps.
- **Dependências:** requer as changes `tornar-platform-admin-proprietario-unico` e `adaptar-aprovacoes-serpro-proprietario-unico`; não altera contexto fiscal tenant nem aceita `office_id` em rotas globais.

