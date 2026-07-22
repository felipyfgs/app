## Context

O painel já possui três fontes complementares de padrão visual: o template Nuxt Dashboard fixado em `.local/reference/nuxt-dashboard-template`, as cascas produtivas `Shell*`/`Monitoring*` e os gates/testes de `apps/web`. As skills globais `nuxt` e `nuxt-ui` cobrem o framework e a biblioteca, mas não conhecem as decisões locais do hub fiscal. A nova skill precisa orquestrar essas fontes sem duplicar o template, inventar uma biblioteca paralela ou mudar a UI nesta entrega.

As pastas `.codex/skills/` e `.cursor/skills/` já são ignoradas pelo Git. A instalação será local ao workspace, enquanto `AGENTS.md` e os artefatos OpenSpec registram o contrato de uso no projeto.

## Goals / Non-Goals

**Goals:**

- Criar uma skill curta, acionável e progressivamente detalhada por quatro referências.
- Fixar uma ordem de autoridade que preserve regras de produto, componentes atuais e o arquétipo visual.
- Orientar criação, edição integral do componente tocado, revisão e padronização de qualquer superfície visual/UX de `apps/web`.
- Encaminhar decisões de Nuxt e Nuxt UI às skills especializadas e à API instalada.
- Tornar verificáveis a estrutura da skill, os caminhos citados, o espelho e seu comportamento em cenários representativos.

**Non-Goals:**

- Refatorar páginas ou componentes produtivos, inclusive dívidas conhecidas de tabelas e seletor de tema.
- Mudar o template ou seu commit fixado.
- Criar componentes, scripts, assets ou scanners próprios da skill.
- Alterar contratos backend, tenancy, permissões, dependências ou infraestrutura.
- Versionar as pastas locais de skills ou alterar `.gitignore`.

## Decisions

### `.codex` será a fonte canônica e `.cursor` um espelho integral

A skill será inicializada pelo `init_skill.py` em `.codex/skills/ui-archetype/`, incluindo `agents/openai.yaml` e `references/`. A pasta completa será replicada em `.cursor/skills/ui-archetype/` e validada com `diff -qr`.

Alternativa considerada: manter apenas uma árvore ou usar symlink. Foi rejeitada porque o projeto já declara suporte operacional às duas árvores e o plano exige um espelho físico idêntico.

### O `SKILL.md` será o roteador do fluxo, não um manual monolítico

O arquivo principal conterá gatilhos, precedência, fluxo obrigatório, decisões rápidas e critérios de conclusão. Detalhes serão organizados em:

- `stack-and-authority.md`: fontes, Nuxt 4, Nuxt UI 4 e inspeção de `.nuxt/ui/`;
- `archetypes.md`: padrões do template e arquivos equivalentes;
- `product-components.md`: cascas produtivas, usos diretos de `U*` e exceções;
- `checklist.md`: criação, edição, auditoria, usabilidade, testes e gates.

Alternativa considerada: copiar exemplos ou assets do template. Foi rejeitada porque criaria uma segunda fonte sujeita a defasagem.

### A implementação atual prevalecerá sobre a forma do template quando codificar contratos locais

A precedência será: requisitos do usuário/OpenSpec/domínio/permissões/tenancy; componentes, utilitários e testes atuais; forma visual do template; API instalada do Nuxt UI e tema gerado; convenções gerais do Nuxt. O template orienta composição e hierarquia, mas melhorias produtivas de estados, mobile e acessibilidade permanecem canônicas.

Alternativa considerada: reproduzir o template literalmente. Foi rejeitada porque ele usa dados mockados e não cobre todos os estados, localização, responsividade tabular e acessibilidade necessários ao produto.

### A skill comporá as skills `nuxt` e `nuxt-ui`

Toda tarefa de UI deverá consultar `nuxt-ui` para seleção, API, slots, tema, formulários e overlays; decisões de routing, layouts, SSR/data flow e organização deverão consultar `nuxt`. Antes de assumir uma prop ou slot, o agente deverá conferir `.nuxt/ui/<component>.ts` quando disponível.

Alternativa considerada: duplicar orientações completas dessas skills. Foi rejeitada para evitar instruções divergentes após atualizações das bibliotecas.

### A normalização ficará limitada ao componente legado tocado

Ao editar um componente legado, o agente deverá alinhar o componente inteiro aos contratos aplicáveis, preservando comportamento e cobrindo testes. Componentes vizinhos não entrarão automaticamente no escopo; uma expansão material exige nova autorização e, quando for produto, cobertura OpenSpec.

Alternativa considerada: correção apenas na linha alterada ou refatoração de toda a região. A primeira perpetua inconsistências internas; a segunda aumenta risco e escopo sem autorização.

### Regras automatizáveis ampliarão gates existentes

A skill não terá scripts. Novas regras verificáveis deverão virar testes unitários/feature ou extensão do fidelity gate existente. `test:fidelity` nunca será evidência isolada para listas, pois a matriz atual não possui páginas classificadas como `LIST`.

Alternativa considerada: criar um scanner próprio da skill. Foi rejeitada para não duplicar inventário, mensagens e manutenção.

### `AGENTS.md` fará a integração obrigatória

Um patch cirúrgico declarará `$ui-archetype` obrigatória para mudanças visuais/UX em `apps/web` e a incluirá na lista de skills locais. Não será criado `apps/web/AGENTS.md`, evitando instruções concorrentes.

## Mapa de dependências

```text
C0 / N0  proposal + design + spec
             |
             v
C0 / N1  inicialização e conteúdo canônico da skill
             |
             v
C0 / N2  integração em AGENTS.md + espelho Cursor
             |
             v
C0 / N3  validação estrutural + forward-tests somente leitura
```

- **Ownership:** esta change possui apenas a nova capability, as duas pastas da skill e os trechos específicos de `AGENTS.md`.
- **Marcos:** nenhuma dependência externa; N1 requer artefatos OpenSpec, N2 requer a fonte canônica e N3 requer toda a instalação.
- **Paralelismo:** inventários e forward-tests podem rodar em paralelo; escrita da fonte, espelho e integração seguem a ordem acima.
- **Compatibilidade:** nenhuma change ativa precisa alterar contrato ou artefato para esta entrega.
- **Rollout:** uso imediato no workspace após validação; futuras mudanças de produto continuam em suas próprias changes.
- **Rollback:** remover as duas pastas novas e reverter somente as linhas adicionadas a `AGENTS.md`; não há migração de dados ou runtime.

## Risks / Trade-offs

- [As pastas locais são ignoradas e podem não acompanhar outro clone] → registrar o contrato em `AGENTS.md`, declarar a limitação e não prometer distribuição pelo Git.
- [O espelho pode divergir após manutenção] → tratar `.codex` como fonte única e exigir `diff -qr` em toda alteração da skill.
- [Referências a componentes podem ficar obsoletas] → mandar inspecionar o análogo produtivo e os testes em cada uso, sem presumir que o documento substitui o código.
- [A skill pode ser acionada em trabalho exclusivamente backend] → delimitar o gatilho a qualquer impacto visual/UX em `apps/web` e explicitar a exclusão de DTOs Laravel sem esse impacto.
- [Regras rígidas podem bloquear uma exceção legítima] → permitir desvios intencionais apenas quando documentados, testados e subordinados aos requisitos do produto.
- [A validação estrutural pode passar sem comprovar utilidade] → complementar `quick_validate.py` com cenários de encaminhamento independentes e revisão das respostas.

## Migration Plan

1. Inicializar e preencher a fonte canônica com o gerador da `skill-creator`.
2. Aplicar a integração mínima em `AGENTS.md`.
3. Espelhar a pasta inteira para `.cursor`.
4. Validar metadados, tamanho, caminhos, commit do template e igualdade do espelho.
5. Executar cenários somente leitura de monitoramento, settings, tabela interna e backend-only.
6. Corrigir a skill se algum cenário não escolher fontes, cascas, testes ou gates adequadamente.

## Open Questions

Nenhuma. A distribuição local, o commit do template, a identidade green/zinc e o limite de normalização foram definidos no plano aprovado.
