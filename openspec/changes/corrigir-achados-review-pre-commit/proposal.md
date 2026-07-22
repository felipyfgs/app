## Why

A revisão pré-commit encontrou falhas que tornam a entrega atual insegura ou não compilável: segredos criptográficos preenchidos em arquivo de exemplo, herança ampla de ambiente pelo subprocesso RPA, classificação incorreta de guia “não paga” e um erro TypeScript no fluxo bulk. Os gates atuais também não executam os testes Go/Python que deveriam impedir essas regressões.

## What Changes

- Remover valores de `APP_KEY` e `VAULT_MASTER_KEY` dos exemplos versionados e fazer o gate de artefatos rejeitar segredos operacionais preenchidos.
- Isolar o ambiente do subprocesso FGTS Digital por allowlist e corrigir a classificação explícita de pagamento não confirmado, com testes de regressão PHP e Python.
- Corrigir e simplificar a construção tipada dos payloads bulk no Nuxt, eliminando o auto-import duplicado sem afrouxar o contrato da API.
- Remover o cliente PHP NopeCHA redundante e desatualizado; preservar o solver canônico dentro do worker Playwright definido pela change FGTS Digital.
- Ampliar o CI para testar o gateway Go e o contrato Python/RPA, além de construir a imagem Horizon RPA que os executa.
- Non-goals: habilitar SERPRO/FGTS/WhatsApp em produção, realizar mutações fiscais, alterar pareceres de procuração, ativar canais SEFAZ, adicionar `mei`/`mei-worker` ao Compose ou restaurar targets de backup/restore indisponíveis.

## Capabilities

### New Capabilities

- `delivery-integrity-gates`: Regras de integridade da entrega para segredos versionados, isolamento de subprocessos, compilação tipada e execução obrigatória dos testes das stacks introduzidas.

### Modified Capabilities

Nenhuma capability canônica é modificada; as correções de FGTS Digital e Work restauram os contratos já declarados nas changes ativas das quais esta change depende.

## Impact

- API/RPA: `ProcessFgtsDigitalPortalClient`, parser/testes Python do FGTS Digital, remoção do provider PHP NopeCHA redundante e exemplos de ambiente.
- Web: payload bulk, auto-imports Nuxt e scanner de artefatos sensíveis.
- CI/infra: workflow de CI, build do target Horizon RPA, testes Python e novo gate Go.
- OpenSpec: nova capability transversal de integridade; sem alteração de rotas ou payloads públicos.

### Dependências entre changes

- Nível: `C2`.
- Bases estáveis: políticas de segredos do repositório, gates Laravel/Nuxt existentes e capabilities já arquivadas de navegação do cliente.
- Depende de: `automatizar-guias-fgts-digital-portal`, capability `fgts-digital-guide-automation`, marco `apply`, relação `bloqueante`, porque corrige o worker e o isolamento RPA; `gerenciar-listas-trabalho-bulk-sort`, capability `work-list-management`, marco `apply`, relação `coordenada`, porque corrige o payload bulk; `adicionar-comunicacao-whatsapp-nativa`, capability `whatsapp-native-gateway`, marco `apply`, relação `coordenada`, porque adiciona o gate Go.
- Desbloqueia: verificação honesta e commit seguro do conjunto pendente.
- As correções podem ser aplicadas em paralelo lógico entre API, web e CI, mas não devem arquivar ou sincronizar capabilities dependidas antes das respectivas changes de origem.
