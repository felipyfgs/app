## Contexto

O shell autenticado segue o Nuxt UI Dashboard fixado em `.reference/nuxt-dashboard-template` no commit `0f30c09` e já usa `UDashboardSidebar`, `UDashboardNavbar`, `UDashboardToolbar`, `UNavigationMenu` e busca global. A expansão funcional, porém, ocorreu por adição de destinos no mesmo nível: `MONITORING_NAV_ITEMS` possui onze itens, o detalhe cadastral do cliente possui dez seções, o detalhe fiscal possui quatorze e Conta mistura seis destinos pessoais, tenant e comerciais.

As rotas e as páginas já possuem contratos úteis que precisam ser preservados: tabs de rota em shells Settings-like, alternâncias locais em `UTabs`, mestre–detalhe responsivo em Caixa Postal e Work, filtros server-side, redirects legados e visibilidade orientada por permissões. O problema é de arquitetura da informação e composição do chrome, não de ausência de componentes.

Stakeholders: operadores do escritório, administradores tenant, administradores da plataforma, usuários com capacidades limitadas, suporte e mantenedores. Restrições: Nuxt 4 SPA estática, Nuxt UI v4, `CurrentOffice` como autoridade, backend como autoridade de autorização, rotas atuais compatíveis, worktree com changes ativas e ausência deliberada de Playwright E2E.

## Objetivos / Não objetivos

**Goals:**

- Publicar uma taxonomia única para sidebar, busca global, atalhos e navegação local.
- Limitar cada contexto visual a duas camadas de navegação, com no máximo cinco itens por camada em desktop.
- Reduzir a largura ocupada pela barra superior sem remover destinos ou ações.
- Separar destinos, ações, filtros e modos locais pela semântica correta.
- Preservar URLs, permissões, deep links, redirects, estados e funcionalidades.
- Manter descoberta equivalente em desktop e mobile, com teclado, foco e nomes acessíveis.
- Permitir que novas superfícies sejam incluídas em grupos estáveis sem ampliar indefinidamente uma única faixa.
- Validar todas as rotas aplicáveis em desktop e mobile, além dos gates automatizados.

**Non-Goals:**

- Alterar backend, APIs, modelos, tenancy, contratos fiscais ou autorização.
- Habilitar SERPRO live, mutações fiscais, outbound ou feature flags.
- Criar links para funcionalidades futuras antes de seus contratos estarem implementados.
- Renomear paths, remover aliases ou migrar filtros para URL quando o contrato atual exige estado local.
- Reescrever páginas, tabelas ou formulários fora do chrome de navegação.
- Criar um design system ou shell novo fora do arquétipo fixado.

## Decisões

### Catálogo canônico orientado a áreas

Um modelo tipado será a fonte de verdade dos destinos e de sua hierarquia. Ele alimentará sidebar, busca global, atalhos e componentes locais sem duplicar rótulos, paths, regras de ativo ou permissões. As áreas globais serão, nesta ordem: Início, Trabalho, Clientes, Fiscal, Documentos, Operações, Conta e Admin. Conta e Admin permanecem no grupo de gestão e Admin só aparece para capacidade global compatível.

Alternativa rejeitada: manter catálogos independentes por componente. Isso preservaria a divergência atual entre sidebar, tabs e command palette e tornaria alterações futuras não atômicas.

### Duas camadas visíveis e substituição de contexto

O primeiro nível local representa grupos funcionais; o segundo, destinos folha. Quando o usuário entra no detalhe de uma entidade, a navegação da área é substituída pelo contexto da entidade, acompanhada de breadcrumb ou ação de retorno. Ela não é empilhada como uma terceira linha.

Uma camada com um único destino não será renderizada apenas para preencher espaço. O limite é de cinco itens por camada no desktop; um grupo maior deve ser subdividido ou reclassificado antes da implementação.

Alternativa rejeitada: três ou mais barras empilhadas. Embora preserve todos os níveis simultaneamente, aumenta altura, carga cognitiva e complexidade no mobile.

### Rotas são links; modos locais são controles

Destinos com URL compartilhável usam `UNavigationMenu` e navegação Nuxt. `UTabs` é usado somente para alternância de conteúdo no mesmo route record. Filtros, status, períodos, modalidades e presets ficam na toolbar de dados ou no corpo, não no catálogo de navegação.

Os modos `PGDAS-D | PGMEI`, `DCTFWeb | MIT`, visão de calendário, modalidades de parcelamento e presets da fila continuam locais conforme os contratos existentes. Não será criada query ou rota apenas para satisfazer a nova aparência.

Alternativa rejeitada: representar todo controle como rota. Isso quebraria decisões OpenSpec existentes, aumentaria o número de destinos públicos e misturaria localização com filtro.

### Taxonomia por área

**Trabalho**

- Tabs: Minha fila (`/work`), Processos (`/work/processes`), Calendário (`/work/calendar`) e Modelos (`/work/templates`, quando autorizado).
- Presets da fila permanecem filtros.
- Detalhe de processo substitui a navegação da área por Resumo, Tarefas, Comentários e Histórico.

**Clientes**

- Catálogo: Lista (`/clients`) e Dashboard (`/clients/dashboard`).
- Detalhe: Visão geral → Resumo; Dados → Cadastro e Estabelecimentos; Fiscal → CCMEI, Receitas SICALC, Pagamentos e Renúncias; Integrações → Certificado A1, Sincronização e Captura de saídas.
- O modal de cliente reutiliza a mesma taxonomia apenas para seções que ele efetivamente oferece.

**Fiscal**

- Visão geral → Dashboard fiscal (`/monitoring`).
- Obrigações → Simples/MEI, DCTFWeb/MIT e Declarações.
- Regularidade → SITFIS, FGTS/eSocial, Cadastro/Vínculos e Processos fiscais.
- Financeiro → Parcelamentos e Guias.
- Comunicações → Caixas Postais.
- Alternâncias de submódulo permanecem dentro do conteúdo, sem terceira barra de navegação.
- Em superfícies mestre–detalhe (Caixa Postal), a navegação local da área Fiscal (tabs de grupo) fica em largura total **acima** do split lista/detalhe; filtros e triagem permanecem na toolbar do painel da lista.

**Detalhe fiscal do cliente**

- Visão geral → Resumo.
- Atividade → Execuções, Achados e Pendências.
- Obrigações → Declarações, PGDAS-D e FGTS.
- Financeiro → Parcelamentos e Guias.
- Regularidade → SITFIS, Cadastro/Vínculos, CCMEI, Renúncias e Processos fiscais.
- O rótulo visível `Findings` será normalizado para `Achados`; o identificador técnico pode permanecer inalterado.

**Documentos**

- Por cliente (`/docs`) e Catálogo (`/docs/catalog`) são tabs diretas.
- Processamento agrupa Importações (`/docs/imports`) e Exportações (`/exports`).
- Detalhes de documento e lote herdam, respectivamente, Catálogo e Importações.

**Operações**

- Saúde (`/health`), Sincronizações (`/syncs`) e Fechamento (`/closing`).
- Importações e Exportações deixam o agrupamento Operações, mas seus paths e capacidades permanecem iguais.

**Conta**

- Perfil → perfil pessoal.
- Organização → Escritório e Departamentos.
- Pessoas e acesso → Equipe e, somente quando implementado por sua change proprietária, Perfis e permissões.
- Plano → Assinatura e Consumo.

**Administração da plataforma**

- Sidebar: Escritórios, Administradores quando a superfície existir, e SERPRO.
- SERPRO: Operação → Status, Consumo e Liberação; Integração → Acesso, Contratos e Cobertura; Canário → Canário DTE.
- Criação e detalhe de escritório permanecem fluxos contextuais, não tabs globais.

**Autenticação e aliases**

- Login, ativação, primeiro acesso e onboarding permanecem fora do shell.
- Aliases `/notes/*`, `/settings/*`, redirects de 2FA e submódulos legados continuam sem itens próprios.

### Barra superior compacta

Cada `UDashboardNavbar` terá sidebar collapse, título ou breadcrumb e no máximo uma ação primária diretamente exposta. Ações secundárias serão agrupadas em `UDropdownMenu` rotulado como `Mais ações`; nenhuma capacidade será removida, e ações condicionais continuarão seguindo permissões e contexto. Contadores essenciais podem permanecer junto ao título.

Tabs e subtabs ficam em `UDashboardToolbar`, separadas de filtros. Títulos longos podem truncar visualmente, mas preservam nome acessível e tooltip quando necessário.

Alternativa rejeitada: esconder ações por breakpoint sem substituto. Isso reduziria largura, mas criaria regressão funcional e de descoberta.

### Componente responsivo único

Será extraído um componente pequeno de navegação de seção a partir dos padrões atuais. Ele recebe o catálogo já filtrado e renderiza:

- desktop: `UNavigationMenu` com destaque e até cinco itens;
- mobile: seletor rotulado com todos os itens quando eles não couberem sem rolagem de descoberta;
- subtabs curtas: faixa compacta acessível, migrando para seletor pelo mesmo breakpoint quando necessário.

O componente não decide autorização nem tenancy; recebe apenas destinos já autorizados. A busca global continua indexando todos os destinos folha autorizados, inclusive os não expostos simultaneamente na sidebar.

Alternativa rejeitada: manter scroll horizontal como solução padrão. O scroll continua permitido para dados densos, mas não será o único mecanismo para descobrir navegação.

### Preservação de autorização e estado

O catálogo usa os helpers de capacidade existentes e será compatível com `effective_permissions` da change `padronizar-autorizacao-multitenant`. O frontend apenas oculta ou apresenta destinos; chamadas diretas continuam autorizadas pelo backend. Troca de tenant continua invalidando `sessionEpoch` e estados tenant-scoped.

Não serão aceitos `office_id`, seletores livres de tenant ou elevação implícita de `PLATFORM_ADMIN` como parte da navegação.

### Verificação automatizada e visual

Os testes unitários cobrirão catálogo, ordem, grupos, paths, aliases, estado ativo, permissões e representação mobile. Os gates obrigatórios serão `pnpm run test:gate`, `pnpm run generate`, `pnpm run test:fidelity` e `pnpm run test:artifacts`.

Uma matriz de evidência será criada durante o apply com cada rota `SHELL`, `CHILD` relevante e `AUTH`. Cada tela alterada será inspecionada renderizada em desktop e mobile, cobrindo estado normal e, quando aplicável, loading, vazio, erro, permissão negada, detalhe, menu de ações, foco por teclado e overflow. Redirects serão validados por destino, não por screenshot intermediária. A ausência de Playwright não reduz esse escopo; a evidência será registrada de forma reproduzível com viewport, identidade e resultado.

## Mapa de dependências

```text
padronizar-autorizacao-multitenant (C0, specs tenant-access-governance)
                         │ contrato coordenado de capacidades
                         ▼
reorganizar-arquitetura-navegacao (C1)
    N0 catálogo + componente responsivo + testes-base
       ├── N1 shell/áreas operacionais
       ├── N1 detalhes de clientes
       └── N1 Conta/Admin
                    ▼
             N2 gates + matriz visual
```

- Ownership desta change: `navigation-architecture`, componentes de chrome e testes de navegação.
- Ownership preservado da upstream: papéis canônicos, perfis, `effective_permissions`, lifecycle e APIs multitenant.
- Arquivos compartilhados: `navigation.ts`, `account-navigation.ts`, `useDashboard.ts`, helpers de permissions e testes correlatos. Antes de editá-los, o apply deve reler o diff da upstream e fazer mudanças mínimas, sem incorporar tasks alheias.
- Marco liberador: as specs de `tenant-access-governance`, já disponíveis. O fechamento desta change exige compatibilidade com o estado aplicado encontrado, mas não exige ativar o cutover multitenant.
- Pontos paralelos: depois do catálogo e do componente-base, shells de áreas, detalhes de clientes e Conta/Admin podem avançar separadamente quando não disputarem arquivos.

## Riscos / Trade-offs

- [Hierarquia reduzir descoberta imediata] → manter todos os destinos folha na busca global e no seletor mobile, com no máximo dois acionamentos a partir da área.
- [Ação secundária parecer removida] → menu `Mais ações` sempre rotulado, ordem estável e testes de equivalência antes/depois.
- [Estado ativo incorreto em detalhes e aliases] → regras de prefixo/exact centralizadas e matriz de rotas, incluindo detalhes dinâmicos e redirects.
- [Conflito com RBAC multitenant em andamento] → serializar arquivos compartilhados, usar helpers compatíveis e não editar contratos pertencentes à upstream.
- [Regressão mobile por menus largos] → seletor responsivo, alvos de toque de pelo menos 44 px e inspeção em viewport móvel.
- [Excesso de abstração] → extrair somente o componente de navegação e os catálogos necessários; páginas e componentes de dados permanecem intactos.
- [Validação visual não automatizada] → matriz de evidência completa, comandos reprodutíveis e gates estruturais automatizados complementares.
- [Worktree alheio extenso] → inspecionar diff por arquivo antes de editar, nunca sobrescrever mudanças não relacionadas e interromper a área se houver conflito material.

## Plano de migração

1. Registrar baseline de rotas, permissões, ações e matriz visual antes de editar o chrome.
2. Introduzir catálogo canônico e componente responsivo com testes, ainda sem remover caminhos antigos.
3. Migrar shell e áreas por fatias, mantendo paths, aliases e compatibilidade de estado ativo.
4. Migrar detalhes cadastral/fiscal e Conta/Admin, reconciliando a upstream multitenant.
5. Remover apenas duplicações de catálogo comprovadamente substituídas; não remover páginas nem redirects.
6. Executar gates e matriz visual completa; qualquer regressão restaura a fatia afetada, pois não há migração de dados ou contrato HTTP.
7. Sincronizar a spec principal e arquivar somente após todas as evidências estarem concluídas.

Rollback de código restaura os componentes e catálogos anteriores. Como URLs, backend e banco não mudam, não há rollback de dados.

## Questões em aberto

Nenhuma. A inclusão visual de destinos futuros permanece condicionada às changes proprietárias; os grupos definidos já reservam espaço sem publicar links inativos.
