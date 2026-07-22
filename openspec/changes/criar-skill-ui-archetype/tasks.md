## 1. N0 — Estrutura canônica

- [x] 1.1 Inicializar `.codex/skills/ui-archetype/` com `init_skill.py`, incluindo `SKILL.md`, `agents/openai.yaml` e `references/`, sem `assets/` ou scripts próprios.

## 2. N1 — Conteúdo operacional

- [x] 2.1 Escrever `SKILL.md` com gatilhos, exclusão backend-only, precedência, fluxo obrigatório, escolhas rápidas e critérios de conclusão.
  Depende de: 1.1
- [x] 2.2 Escrever `stack-and-authority.md`, `archetypes.md`, `product-components.md` e `checklist.md` com os contratos de Nuxt, Nuxt UI, template, cascas produtivas, usabilidade, testes e gates.
  Depende de: 1.1
- [x] 2.3 Configurar `agents/openai.yaml` com o nome, descrição curta e prompt padrão aprovados.
  Depende de: 1.1

## 3. N2 — Integração local

- [x] 3.1 Aplicar patch cirúrgico em `AGENTS.md` para tornar `$ui-archetype` obrigatória em mudanças visuais/UX de `apps/web` e listá-la entre as skills locais.
  Depende de: 2.1, 2.2
- [x] 3.2 Espelhar a pasta canônica completa em `.cursor/skills/ui-archetype/`, sem alterar `.gitignore` nem criar `apps/web/AGENTS.md`.
  Depende de: 2.1, 2.2, 2.3

## 4. N3 — Gates integrados e evidências

- [x] 4.1 Executar `quick_validate.py`, confirmar `SKILL.md` abaixo de 500 linhas, ausência de caminhos legados, existência dos caminhos citados e template no commit `0f30c09d697160ef5dd0aaaec27fae8d7195d930`.
  Depende de: 3.1, 3.2
- [x] 4.2 Confirmar o espelho com `diff -qr` e validar estritamente a change OpenSpec.
  Depende de: 3.1, 3.2
- [x] 4.3 Executar forward-tests somente leitura para carteira de monitoramento, settings legado, tabela interna e DTO Laravel backend-only; corrigir a skill se os cenários não evidenciarem o contrato esperado.
  Depende de: 3.1, 3.2
