## Context

O worktree combina mudanças Laravel, Nuxt, um worker Python/Playwright e um gateway Go. Os gates existentes cobrem Laravel e Nuxt, mas não exercitam o parser Python nem o gateway; além disso, o subprocesso RPA criado pelo Horizon recebe o ambiente padrão do Symfony Process, que inclui credenciais da aplicação mesmo quando um array parcial é informado.

Esta change é transversal por necessidade: os defeitos foram encontrados no mesmo review de entrega e o resultado implantável é um conjunto com segredos sanitizados, código compilável e testes das quatro stacks. Ela não assume ownership funcional das changes dependidas e não altera seus artefatos de capability.

## Goals / Non-Goals

**Goals:**

- Impedir material criptográfico operacional em arquivos de exemplo versionados.
- Executar o RPA com ambiente mínimo explícito e classificar corretamente evidência de não pagamento.
- Restaurar o contrato TypeScript do bulk sem duplicar tipos auto-importados.
- Tornar Go e Python/RPA gates efetivos do CI.
- Remover a implementação PHP NopeCHA paralela que não participa do fluxo canônico Playwright.

**Non-Goals:**

- Alterar rotas, tenancy, permissões ou payloads públicos.
- Habilitar egress, mutações, comunicação ou qualquer flag produtiva.
- Mudar códigos SERPRO, canais SEFAZ, procurações, sidecar MEI ou operações de backup/restore.

## Decisions

### Ambiente RPA será uma allowlist efetiva

O cliente processual montará um mapa que marca como removidas todas as chaves herdadas de `getenv()`, `$_ENV` e `$_SERVER`, reintroduzindo somente `LANG`, `LC_ALL`, `PATH`, `HOME`, `TMPDIR` e `PLAYWRIGHT_BROWSERS_PATH` quando válidas. Isso usa a semântica `false` do Symfony Process para impedir que o merge com o ambiente padrão recoloque segredos.

A alternativa de apenas informar três variáveis no construtor foi rejeitada porque Symfony as combina com o ambiente pai. Executar outro shell sanitizador também foi rejeitado por aumentar quoting e superfície de comando.

### O parser dará precedência à evidência negativa

Expressões explícitas como “não pago”, “em aberto”, “pendente” e “vencido” serão avaliadas antes de “pago”. O teste Python cobrirá a colisão textual que causava falso positivo e preservará `PARTIAL` como a precedência mais alta.

### O solver NopeCHA permanece somente no worker Playwright

O provider PHP e seu teste serão removidos porque usam contrato HTTP diferente, não recebem o contexto real do navegador e não são consumidos pela aplicação. A configuração `fgts_digital.captcha` continuará sendo a única entrada do fluxo, com defaults OFF.

### Gates serão executados na stack que possuem

O CI ganhará um job Go com `go test` e `go vet`. O job de infraestrutura construirá também `horizon` e `whatsapp-gateway`; o teste Python puro será executado na imagem RPA. O scanner web verificará que exemplos de ambiente não preencham chaves criptográficas de alta sensibilidade.

### Payload bulk preservará tipo explícito

Um tipo local para `changes` manterá `action` obrigatório e acomodará os campos opcionais compartilhados. O re-export duplicado de `ClientFiscalSectionKey` será removido da ponte de links; consumidores continuarão importando o tipo da fonte canônica.

### Inventários derivados acompanharão páginas novas

O inventário declarativo receberá `/work/tasks` na seção `work`; a saída viva de `artisan route:list --json` reconciliará também o preview de anexos. O gerador canônico atualizará os snapshots web/API e o relatório de testabilidade. Os gates continuarão comparando os conjuntos completos para impedir deriva futura.

## Mapa de dependências

```text
automatizar-guias-fgts-digital-portal (C1/apply) ─┐
gerenciar-listas-trabalho-bulk-sort (C0/apply) ───┼─> corrigir-achados-review-pre-commit (C2)
adicionar-comunicacao-whatsapp-nativa (C0/apply) ─┘
```

- Ownership RPA: esta change altera somente implementação/testes já entregues pelo upstream, sem editar proposal/design/spec/tasks da change FGTS.
- Ownership Work: o contrato HTTP permanece inalterado; o patch é restrito à tipagem do consumidor Nuxt.
- Ownership gateway: o código Go não muda salvo se o próprio gate revelar falha; o CI apenas passa a exercitá-lo.
- Rollout: todos os defaults continuam OFF; rollback reverte os patches/gates sem migração de dados.

## Risks / Trade-offs

- [Allowlist curta quebra descoberta do Chromium] → preservar explicitamente `PLAYWRIGHT_BROWSERS_PATH`, `HOME`, `TMPDIR`, locale e `PATH`, com teste de execução real do processo.
- [Scanner rejeita placeholders benignos] → restringir a validação a chaves criptográficas/API de alta sensibilidade e aceitar somente valor vazio.
- [CI fica mais lento] → separar Go em job paralelo e reutilizar a imagem Horizon já necessária para o produto.
- [Correção interfere em changes ativas] → manter alterações mínimas e validar individualmente todas as changes strict.
- [Vazamento entre offices, segredos em log ou egress acidental] → nenhum novo acesso a dados/tenant; testes e flags permanecem fail-closed.

## Migration Plan

1. Sanitizar exemplos e adicionar testes negativos.
2. Corrigir RPA e frontend, remover o provider redundante.
3. Executar gates locais de Laravel, Nuxt, Python e Go.
4. Validar Compose/OpenSpec e confirmar defaults OFF e ausência de `mei`/`mei-worker`.
5. Entregar os commits sem promover flags; rollback é puramente de código/configuração versionada.

## Open Questions

Nenhuma decisão funcional permanece aberta; qualquer falha descoberta pelos novos gates será corrigida dentro desta change antes do commit.
