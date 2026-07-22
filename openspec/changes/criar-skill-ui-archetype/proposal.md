## Why

As regras de interface do painel estão distribuídas entre o template fixado, as cascas produtivas `Shell*`/`Monitoring*`, os gates do frontend e as skills gerais de Nuxt. Sem um fluxo local único e acionável, mudanças em `apps/web` podem escolher arquétipos, componentes e critérios de validação de forma inconsistente.

## What Changes

- Criar a skill local `ui-archetype` em `.codex/skills/` e um espelho byte a byte em `.cursor/skills/`.
- Documentar a precedência entre requisitos do produto, implementação atual, template fixado, API instalada do Nuxt UI e convenções do Nuxt.
- Mapear os arquétipos do template para as cascas produtivas do hub, incluindo páginas, settings, listas, monitoramento, mestre–detalhe, formulários, overlays e tabelas internas.
- Tornar explícitos os fluxos de criação, edição integral do componente tocado, auditoria somente leitura, acessibilidade, responsividade, estados assíncronos, testes e gates.
- Declarar em `AGENTS.md` que a skill é obrigatória para qualquer criação, edição, revisão ou padronização visual/UX em `apps/web`.
- Validar estrutura, referências, commit do template, espelhamento e comportamento da skill com cenários somente leitura.
- Não refatorar a interface produtiva, não alterar o template, não criar scanner novo e não modificar `.gitignore` nesta change.

## Capabilities

### New Capabilities

- `ui-archetype-agent-workflow`: fluxo canônico para agentes criarem, editarem, revisarem e validarem interfaces do painel Nuxt conforme o arquétipo visual e os componentes atuais do produto.

### Modified Capabilities

- Nenhuma.

## Impact

- Arquivos afetados: `.codex/skills/ui-archetype/`, `.cursor/skills/ui-archetype/`, `AGENTS.md` e os artefatos desta change.
- Não há alteração de API, banco, dependências, runtime, backend ou interface produtiva.
- A skill continua local e ignorada pelo Git; `.codex` é a fonte canônica e `.cursor` é apenas o espelho operacional.

### Dependências entre changes

- **Nível:** C0 — independente.
- **Base estável:** template em `0f30c09d697160ef5dd0aaaec27fae8d7195d930`, cascas `Shell*`/`Monitoring*`, testes atuais do frontend e skills `nuxt`/`nuxt-ui`.
- **Depende de:** nenhuma change ativa.
- **Contrato introduzido:** skill e instrução de projeto que governam futuras mudanças visuais/UX em `apps/web`.
- **Marco externo:** nenhum.
- **Relação com outras changes:** pode ser aplicada em paralelo desde que outra change não edite os mesmos trechos de `AGENTS.md` ou as pastas da nova skill.
- **Desbloqueia:** alinhamento consistente de futuras mudanças de UI; não bloqueia a entrega de changes já ativas.

### Fora de escopo

- Corrigir divergências visuais ou de acessibilidade já existentes.
- Alterar o seletor de paletas, tabelas internas legadas ou qualquer componente produtivo.
- Atualizar ou copiar o template de referência.
- Criar validações duplicadas fora dos testes e gates existentes.
