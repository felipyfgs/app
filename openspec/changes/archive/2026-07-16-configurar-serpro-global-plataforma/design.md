## Context

O backend já possui contratos, versões de credencial, vault, gates externos, runtime controls, ledger e confirmações do Proprietário. Porém as mutações estão distribuídas e o fluxo legado de contrato ainda permite cadastrar e ativar material diretamente. A UI global atual separa Contratos, Readiness, Orçamento e Rollout, sem uma visão por ambiente nem um caminho obrigatório upload → verificação local → teste OAuth → cutover.

Esta change é global: rotas usam Sanctum, `EnsureActiveUser` e `EnsurePlatformAdmin`, sem `EnsureOfficeContext`. Trial e Production são partições independentes. Não há job nem transporte fiscal; o único transporte remoto permitido é OAuth mTLS disparado explicitamente pelo Proprietário. A UI seguirá o arquétipo Settings de `.reference/nuxt-dashboard-template` fixado em `0f30c09` e será SPA estática.

## Goals / Non-Goals

**Goals:**

- centralizar a configuração global SERPRO em um read model sanitizado e uma página exclusiva;
- tornar obrigatório o ciclo versionado de credenciais no vault e impedir ativação direta;
- separar Trial/Production em contrato, versão, token, gates e readiness;
- persistir gates, limites e switches operacionais no banco, com bloqueio externo prevalente;
- manter trilha auditável sem guardar PDF nem devolver segredo;
- registrar vocabulário no `CONTEXT.md` e a decisão de controle no ADR.

**Non-Goals:**

- chamar `/Apoiar`, `/Monitorar`, `/Consultar`, `/Emitir` ou `/Declarar`;
- executar OAuth live em CI, deploy, health ou leitura da página;
- habilitar Office, cliente, DTE ou outra capability fiscal;
- substituir o ledger existente, precificar consumo em reais ou consultar saldo remoto;
- armazenar contrato/ticket em PDF ou dispensar gate externo.

## Decisions

### 1. Um agregado global por ambiente alimenta uma única API de configuração

`SerproPlatformConfigurationService` comporá contrato, versão ativa/pendente, endpoints oficiais, seis gates, limites quantitativos, runtime controls e resumo de Offices pendentes. `GET /api/v1/platform/serpro/configuration?environment=trial|production` será somente leitura e sanitizado.

As mutações serão explícitas:

- `POST /credential-versions` cadastra versão `PENDING` no vault;
- `POST .../{version}/verify` repete validações locais e move para `VERIFIED`;
- `POST .../{version}/test-connection` executa somente OAuth mTLS e registra evidência sanitizada ligada ao fingerprint/versão;
- `POST .../{version}/cutover` usa confirmação reforçada e exige teste recente da mesma versão;
- `PATCH /external-gates/{gate}` e `PUT /usage-limits` atualizam configuração auditável.

Alternativa: manter endpoints por tela legada. Rejeitada porque favorece estados parciais e dificulta provar prontidão completa.

### 2. Segredos entram uma vez e nunca saem

O multipart recebe PFX, senha, Key e Secret; `SerproCredentialVersionService` valida o arquivo, extrai somente CNPJ/fingerprint/validade e grava cada segredo com AAD de ambiente/versão no `SecureObjectStore`. Banco e API guardam apenas metadados, incluindo os quatro últimos caracteres da Consumer Key. Nem endpoint de detalhe nem histórico oferecem download/recuperação.

Alternativa: criptografar campos no banco. Rejeitada para preservar a fronteira já estabelecida do vault e reduzir superfícies de dump/log.

### 3. Verificação local e teste OAuth são transições distintas

`verify` prova integridade, titularidade, chave privada e validade sem rede. `test-connection` somente aceita versão `VERIFIED`, lê o mesmo material do vault, usa o endpoint oficial fixo e registra `tested_at`, resultado, fingerprint e validade da evidência. O teste não ativa a versão. Cutover Production exige sucesso recente, por padrão em até 15 minutos, mesma versão/fingerprint e confirmação do Proprietário ainda válida.

Alternativa: testar automaticamente no upload. Rejeitada porque upload/CI não pode iniciar tráfego externo implícito e porque o operador precisa controlar janela e ambiente.

### 4. Controles normais ficam no banco; env somente bloqueia

Estado habilitável, kill switch operacional, gates e limites serão persistidos e auditados. `SERPRO_KILL_SWITCH=true` será combinado por OR e sempre bloqueará; `false` apenas remove o bloqueio externo e não promove qualquer estado. Endpoints oficiais permanecem constantes de configuração read-only, sem edição pelo painel.

Essa decisão será registrada em `docs/adr/` porque separa recuperação emergencial do fluxo auditado normal e impede que um deploy habilite produção acidentalmente.

### 5. Gates e limites quantitativos são mínimos e fail-closed

Os seis gates existentes permanecem enumerados. Production só considera gate aceito quando há referência, resumo, responsável, data e ator; não existe waiver. O limite terá ambiente, ciclo inicial 1–28, alerta padrão 80%, máximo global positivo e máximo por Office positivo. Defaults iniciais de configuração serão dez global e dez para o Office piloto, mas ausência/zero bloqueia.

O ledger local existente será a fonte de consumo; não se cria tabela de preços nem integração de saldo, porque o SERPRO não publica API de saldo em tempo real.

### 6. A UI é uma Settings page global, sem shell novo

`frontend/app/pages/admin/serpro/configuration.vue` reutilizará `UDashboardPanel`, `UDashboardNavbar`, `DashboardContent comfortable`, tabs/sections/forms do arquétipo Settings. Mostrará seletor de ambiente, cards de readiness e seções Credenciais, Gates, Limites, Switches, Histórico e Offices pendentes. Links de Office apontam para `/settings` depois de seleção global auditada já suportada; nenhuma rota recebe `office_id` como escopo fiscal.

## Risks / Trade-offs

- [Resposta ou auditoria vaza segredo] → resources com allowlist, testes de redaction e proibição de serializar chaves do vault.
- [Trial satisfaz gate Production] → chaves únicas por ambiente e consultas sempre filtradas; teste cross-environment.
- [Teste OAuth causa cobrança fiscal] → cliente dedicado apenas ao endpoint `/authenticate`, sem executor de negócio, e teste de arquitetura.
- [Env é interpretado como habilitação] → composição monotônica de bloqueios e testes `true` prevalece/`false` não promove.
- [Cutover usa teste antigo ou de outra versão] → evidência contém version id + fingerprint + expiração e é validada na mesma transação.
- [Fluxo legado contorna versão] → remover rotas POST/activate de contratos e deixar leitura histórica/redirect sem mutação.
- [Página vira inventário de Offices] → somente resumo sanitizado; detalhes continuam no contexto explicitamente selecionado.

## Migration Plan

1. Migrar schema de evidência OAuth, limites quantitativos e metadados sanitizados, sem mover segredos para o banco.
2. Publicar serviços/APIs novos mantendo leitura dos contratos existentes.
3. Remover mutações legadas; contratos ativos existentes permanecem legíveis, mas nova rotação exige criar versão.
4. Publicar a página Configuração e redirecionar a aba Contratos para ela.
5. Manter kill switches fechados; cadastrar e verificar versões Trial/Production separadamente.
6. OAuth real, aceite dos gates e cutover somente em janela operacional com evidência externa.

Rollback: fechar switch operacional e/ou `SERPRO_KILL_SWITCH=true`, preservar versões/vault/auditoria e reverter apenas a UI/API. Nunca reativar versão comprometida nem restaurar segredo em resposta.

## Open Questions

Nenhuma decisão de software bloqueante. Credenciais, referências dos gates, dia do ciclo, Office piloto e execução OAuth real são entradas externas ops-gated.

