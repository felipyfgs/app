## Context

O painel é uma SPA Nuxt 4/Nuxt UI 4 servida estaticamente pelo Nginx e autenticada no mesmo domínio por Fortify/Sanctum. A referência visual está fixada em `.reference/nuxt-dashboard-template`, commit `0f30c09`, e contém padrões maduros para shell, dashboard, tabelas administrativas, mestre–detalhe, settings, menus, slideovers e responsividade.

O change anterior `refactor-frontend-dashboard-ux` adaptou esses padrões ao domínio, mas definiu fidelidade principalmente como “gramática visual”. Isso permitiu que estruturas equivalentes fossem substituídas por composições próprias. O novo change exige derivação literal: copiar o código do template e fazer apenas as adaptações necessárias ao produto, preservando o modelo visual e interacional.

Os usuários são funcionários do escritório contábil. A fidelidade não pode comprometer: isolamento por escritório, perfis `ADMIN`/`OPERATOR`/`VIEWER`, confirmação de 2FA administrativo, paginação server-side, tratamento de NSU, auditoria ou sigilo de PFX/XML.

## Goals / Non-Goals

**Goals:**

- Tornar a fidelidade literal ao template auditável por diff, matriz, testes e evidências visuais.
- Preservar a árvore estrutural, os componentes Nuxt UI, slots, classes, hierarquia de ações, densidade, dimensões e comportamentos do código de referência.
- Reduzir divergências visuais não justificadas entre rotas e componentes.
- Cobrir estados reais de carregamento, vazio, erro, sucesso, bloqueio e restrição de acesso com a mesma linguagem visual do template.
- Garantir paridade responsiva e por teclado em desktop, mobile e largura mínima de 360 px.
- Manter todas as integrações tipadas e dados reais da API Laravel.

**Non-Goals:**

- Copiar textos, marcas, avatares, mocks ou entidades demonstrativas.
- Criar funções fictícias apenas para reproduzir elementos visuais do exemplo.
- Alterar backend, contratos de API, banco, tenancy ou segurança fiscal.
- Reinterpretar o layout, criar composição visual alternativa ou produzir uma imagem estática que pareça fiel mas tenha comportamento diferente.
- Tornar `.reference/` uma dependência de build ou runtime.

## Decisions

### 1. O código do template fixado é a origem obrigatória

Cada área do produto será criada copiando o arquivo ou bloco correspondente da referência. A implementação deverá começar com copiar e colar, seguido de adaptações localizadas para texto, idioma, rotas, tipos, dados da API, permissões e regras obrigatórias. A matriz registrará: arquivo origem, arquivo destino, blocos preservados, adaptações feitas e motivo de cada alteração que não seja apenas conteúdo.

Estrutura de componentes, ordem dos blocos, slots, classes utilitárias, props visuais, larguras, gaps, paddings, bordas, cantos, variantes, breakpoints e comportamento de overlays MUST permanecer iguais ao código fonte correspondente.

Uma divergência só será aceita quando causada por:

1. autorização, 2FA, tenancy ou proteção de dado sensível;
2. contrato real da API que não possa ser adaptado mantendo o markup;
3. ausência incontornável de função real, após tentar preservar o mesmo modelo visual com outra ação válida.

Preferência pessoal, simplificação, melhoria sugerida, abstração, “componente equivalente” ou redução de código não serão justificativas.

Alternativas rejeitadas: reimplementar olhando o template, aproximar visualmente ou criar um design system paralelo. Essas abordagens permitem deriva; o fluxo obrigatório é copiar, adaptar e conferir o diff.

### 2. Fidelidade será avaliada em quatro camadas

- **Estrutural:** mesma composição de `UDashboardGroup`, sidebar, navbar, toolbar, body, grid, cards, tabelas, menus, modais e slideovers.
- **Visual:** mesma tipografia, escala, espaçamento, largura, densidade, bordas, cantos, cores semânticas, iconografia e tratamento de superfícies.
- **Interacional:** mesmas posições e prioridades de ações, abertura/fechamento, foco, atalhos, menus, loading e feedback.
- **Responsiva:** mesma transição entre sidebar/painel/slideover, visibilidade de colunas e ausência de overflow do documento.

A geometria deverá ser visualmente indistinguível nas mesmas condições. Diferenças de conteúdo dinâmico serão estabilizadas, mas a comparação não poderá mascarar estrutura, dimensões ou espaçamento.

### 3. Baselines visuais serão determinísticas e sanitizadas

Será criado um modo de fixture exclusivamente de teste no frontend, interceptado pelo Playwright, com respostas tipadas e sanitizadas. Ele não será incluído como `server/api`, não substituirá o backend em desenvolvimento/produção e não conterá XML, PFX ou respostas ADN.

As capturas serão produzidas em `1440×900`, `390×844` e, para overflow, `360×800`, com:

- fonte carregada antes da captura;
- animações e relógio estabilizados;
- color mode definido;
- dados e datas determinísticos;
- autenticação simulada apenas na camada de rede do teste;
- screenshots por estado e rota.

Alternativa rejeitada: usar dados reais locais. Isso tornaria o baseline instável e poderia incluir informação fiscal.

### 4. Comparação será por contrato e não por um único percentual global

Cada tela terá zonas comparáveis: shell, header, toolbar, área principal, tabela/detalhe e overlays. A revisão combinará:

- assertions estruturais e acessíveis;
- screenshots com tolerância definida por zona;
- medidas de bounding boxes para elementos críticos;
- verificação de tokens/classes compartilhados;
- inspeção manual registrada para casos não determinísticos.

Um diff global pode esconder deslocamentos importantes em áreas pequenas; por isso, a aprovação exigirá que nenhuma zona crítica ultrapasse sua tolerância.

### 5. Shell deve manter a forma do template sem violar tenancy

O layout continuará espelhando `layouts/default.vue`. A identidade do escritório usará a mesma aparência e dimensões do `TeamsMenu`, mas sem dropdown ou troca de escritório. Sidebar, busca, menus, rodapé do usuário, command palette e slideover global manterão os mesmos slots e comportamento de collapse.

O conteúdo do menu e das ações será derivado das permissões tipadas. Itens indisponíveis não serão apenas ocultados visualmente: rotas continuam protegidas no middleware e backend.

### 6. Cada rota terá um arquétipo concreto da referência

| Área do produto | Baseline principal |
|---|---|
| Shell autenticado | `app/layouts/default.vue`, `TeamsMenu.vue`, `UserMenu.vue`, `NotificationsSlideover.vue` |
| Dashboard | `app/pages/index.vue` e `components/home/HomeStats.vue` |
| Clientes e listas | `app/pages/customers.vue` e modais de customers |
| Notas mestre–detalhe | `app/pages/inbox.vue`, `InboxList.vue`, `InboxMail.vue` |
| Cliente/Administração | `app/pages/settings.vue` e páginas de settings |
| Formulários e estados | componentes Nuxt UI usados nesses arquétipos |

O conteúdo fiscal e as ações serão substituídos, mas a estrutura e o ritmo permanecerão.

### 7. Abstrações não podem afastar o código da referência

O markup copiado deverá permanecer explícito quando isso facilitar sua comparação com a origem. Presets só poderão ser mantidos se expandirem exatamente para as mesmas classes e não alterarem slots, ordem ou props. Uma abstração existente será removida se impedir conferir a identidade do código com o template.

Alternativa rejeitada: criar um design system paralelo. Nuxt UI e o próprio template já são o sistema visual adotado.

### 8. A revisão ocorrerá rota a rota e estado a estado

A implementação seguirá esta ordem: fundação visual e harness; shell; dashboard; listas; settings; mestre–detalhe; overlays; acessibilidade; regressão visual completa. Uma rota só será concluída após passar matriz, testes e capturas nas viewports aplicáveis.

Não será feita uma reescrita total simultânea. Isso mantém as mudanças revisáveis e permite rollback por módulo.

### 9. Segurança é parte do baseline de teste

Fixtures, snapshots, traces e screenshots MUST NOT conter PFX, senha, chave privada, PEM, XML fiscal, cookie, token, `vault_object_id` ou resposta ADN bruta. O teste deverá falhar se esses padrões aparecerem nos artefatos.

Downloads continuam explícitos, autorizados e auditados. Nenhum esforço de fidelidade poderá adicionar preview de XML ou recuperação de certificado.

## Risks / Trade-offs

- **Baselines frágeis após atualização de navegador ou fonte** → fixar versões, aguardar fontes e separar mudanças intencionais de regressões.
- **Fidelidade conflitar com regras reais** → exigir registro da exceção com requisito, arquivo afetado e evidência; segurança e domínio prevalecem.
- **Screenshots aprovarem comportamento incorreto** → combinar comparação visual com assertions funcionais e acessíveis.
- **Excesso de abstração afastar o markup da referência** → preferir composição explícita e presets pequenos, rastreáveis ao arquivo original.
- **Artefatos de teste vazarem dados** → usar fixtures sintéticas, scanner de segredos e não capturar ambientes com dados reais.
- **Mudanças amplas causarem regressão funcional** → migrar por arquétipo, manter composables/API e executar suíte após cada módulo.
- **Cópia literal introduzir função fictícia** → copiar apenas composição visual/interacional suportada; registrar elementos omitidos por ausência de função real.

## Migration Plan

1. Congelar a matriz de referência e registrar o commit/arquivos fonte.
2. Criar harness determinístico de screenshots e fixtures sanitizadas.
3. Capturar o estado atual como diagnóstico, sem aceitá-lo como baseline final.
4. Ajustar shell e fundação visual.
5. Migrar dashboard, listas, settings e mestre–detalhe em unidades independentes.
6. Validar overlays, teclado, foco, desktop, mobile e 360 px.
7. Capturar baselines finais e documentar exceções autorizadas.
8. Reexecutar lint, typecheck, unitários, componentes, Playwright e build.

Rollback será feito por módulo; não há migration de banco. A referência permanece somente leitura durante todo o processo.

## Open Questions

Resolvido no início do apply:

- Os baselines finais serão snapshots por zona para permitir revisão de shell, navbar, toolbar, conteúdo, tabela/detalhe e overlays sem um diff global mascarar deslocamentos locais. Capturas completas serão mantidas apenas como diagnóstico descartável em `test-results/`.
- Claro será validado visualmente em todas as rotas e viewports previstas. Escuro terá baseline visual representativa do shell, dashboard e overlays, além de teste funcional de color mode em todas as rotas.
