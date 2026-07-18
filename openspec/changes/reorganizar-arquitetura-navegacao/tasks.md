## 1. N0 — Base canônica e baseline

- [x] 1.1 Registrar baseline completo de rotas, permissões, ações e estados visuais

  Implementação: reler o worktree e o estado aplicado de `padronizar-autorizacao-multitenant`; inventariar cada rota `SHELL`, `CHILD`, `AUTH` e `REDIRECT`, sua área, item ativo, ações e perfis aplicáveis; criar `validation-matrix.md` nesta change com viewports desktop/mobile, critérios de loading, vazio, erro, detalhe, foco e overflow, sem editar arquivos pertencentes a outras changes.

  Evidência: matriz cobre todas as páginas registradas em `frontend/tests/fixtures/template-parity-matrix.md`, aponta aliases/destinos finais e registra conflitos de ownership antes de qualquer alteração de frontend.

- [x] 1.2 Implementar tipos e utilitários genéricos da hierarquia de navegação

  Implementação: definir contratos tipados para área, tab, subtab, destino folha, regra de ativo, capacidade e variante responsiva; implementar flatten, filtragem, seleção de grupo/subtab e resolução de rota ativa sem acessar `office_id` ou assumir papel; manter os utilitários independentes dos catálogos de domínio.

  Evidência: testes unitários cobrem limites de cinco itens, grupo unitário, paths exact/prefix, detalhes dinâmicos, destino não autorizado, ordem estável e ausência de mutação do catálogo de entrada.

- [x] 1.3 Extrair componente responsivo de navegação de seção a partir do arquétipo Settings

  Implementação: reutilizar `UDashboardToolbar`, `UNavigationMenu` e componentes Nuxt UI existentes para renderizar tabs desktop, subtabs e seletor mobile a partir de fixtures tipadas; preservar foco, estado atual, nome acessível e alvos de toque de 44 px; não embutir regras de domínio ou autorização.

  Evidência: testes Nuxt montam desktop/mobile, teclado, item ativo, grupo unitário, labels longos e fallback de seletor sem depender de rolagem horizontal para descoberta.

## 2. N1 — Áreas e contextos independentes

- [x] 2.1 Migrar Trabalho para tabs de área e navegação contextual de processo

  Depende de: 1.1, 1.2, 1.3.

  Implementação: organizar Minha fila, Processos, Calendário e Modelos por capacidade; manter presets da fila e visões de calendário como controles locais; substituir a barra da área por Resumo, Tarefas, Comentários e Histórico no detalhe de processo; preservar query, paths e mestre–detalhe de tarefas.

  Evidência: testes cobrem visibilidade de Modelos, paths, estado ativo, filtros sem novos destinos, detalhe contextual, retorno e renderização mobile/desktop.

- [x] 2.2 Migrar catálogo, detalhe e modal de Clientes para a taxonomia aprovada

  Depende de: 1.1, 1.2, 1.3.

  Implementação: manter Lista/Dashboard no catálogo; agrupar o detalhe em Visão geral, Dados, Fiscal e Integrações; reutilizar os grupos compatíveis no modal; preservar Novo cliente, edição, lazy pages, aside, paths de CCMEI/SICALC/Pagamentos/Renúncias e as mudanças locais existentes.

  Evidência: testes cobrem dez rotas/seções, grupo ativo, ação por permissão, modal sem destinos inexistentes, deep link/reload e ausência de overflow nos dois viewports.

- [x] 2.3 Migrar a área Fiscal para cinco grupos sem alterar modos locais

  Depende de: 1.1, 1.2, 1.3.

  Implementação: publicar Visão geral, Obrigações, Regularidade, Financeiro e Comunicações; distribuir os onze módulos conforme o design; manter PGDAS-D/PGMEI, DCTFWeb/MIT, modalidades, KPIs e triagem como controles locais; preservar `MonitoringModuleTable`, Caixa Postal mestre–detalhe e rotas legadas.

  Evidência: testes cobrem todos os módulos, ordem/grupo, estado ativo, redirects de submódulo, alternância local sem query/path novo, desktop sem faixa de onze itens e seletor mobile completo.

- [x] 2.4 Migrar o detalhe fiscal do cliente para cinco grupos contextuais

  Depende de: 1.1, 1.2, 1.3.

  Implementação: agrupar quatorze seções em Visão geral, Atividade, Obrigações, Financeiro e Regularidade; exibir `Achados` para `findings`; manter `:section?`, carregamento lazy, cache por `sessionEpoch`, retry, proteção cross-tenant e conteúdo de cada seção.

  Evidência: testes cobrem cada section key/path, grupo/subtab ativo, acesso direto, troca rápida de seção/tenant, falha parcial, label pt-BR e responsividade.

- [x] 2.5 Migrar Documentos e Operações preservando processamento e detalhes

  Depende de: 1.1, 1.2, 1.3.

  Implementação: agrupar Por cliente, Catálogo e Processamento em Documentos; mover somente a localização visual de Importações e Exportações para Processamento; manter Saúde, Sincronizações e Fechamento em Operações; preservar `/docs/:accessKey`, lotes, modais, filtros, ações e paths atuais.

  Evidência: testes cobrem estado ativo de `/docs`, catálogo, detalhe documental, importações/lote, `/exports`, `/health`, `/syncs` e `/closing`, além de equivalência de ações por capacidade e mobile.

- [x] 2.6 Migrar Conta e Administração com compatibilidade multitenant

  Depende de: 1.1, 1.2, 1.3; externa: `padronizar-autorizacao-multitenant` no marco `specs`, relação coordenada.

  Implementação: agrupar Conta em Perfil, Organização, Pessoas e acesso e Plano; agrupar SERPRO em Operação, Integração e Canário com suas subtabs; manter Escritórios e somente publicar Perfis/Administradores quando suas superfícies proprietárias existirem; reconciliar helpers transitórios sem implementar RBAC, lifecycle ou APIs da upstream.

  Evidência: testes cobrem matriz de capacidades tenant/global, ausência de links futuros inativos, Conta parcial/completa, administrador sem tenant, console SERPRO e aliases `/settings/*`.

## 3. N2 — Integração do shell e compactação do chrome

- [x] 3.1 Integrar áreas canônicas na sidebar, busca global e atalhos

  Depende de: 2.1, 2.2, 2.3, 2.4, 2.5, 2.6; externa: `padronizar-autorizacao-multitenant` no marco `specs`, relação coordenada.

  Implementação: agregar os catálogos por área no shell; apresentar Início, Trabalho, Clientes, Fiscal, Documentos, Operações, Conta e Admin conforme capacidade; manter grupos operação/gestão, `OfficeIdentity`, command palette, quick actions, shortcuts e rebuild estável após rota/identidade/tenant.

  Evidência: testes de navegação cobrem todas as identidades atuais e canônicas disponíveis, flatten de todos os destinos folha, ausência de duplicação, grupo ativo em detalhes e descarte de estado após troca de contexto.

- [x] 3.2 Compactar navbars e preservar equivalência integral de ações

  Depende de: 2.1, 2.2, 2.3, 2.4, 2.5, 2.6.

  Implementação: revisar cada `UDashboardNavbar` afetado; manter no máximo uma ação primária exposta e mover ações secundárias para `Mais ações` com label, ícone e permissão equivalentes; separar filtros de navegação; aplicar truncamento acessível e preservar contadores, refresh, import/export, criação, edição e ações de risco. Em Caixa Postal, o chrome Fiscal (navbar + tabs de grupo) fica acima do mestre–detalhe em largura total; filtros/triagem ficam só no painel da lista.

  Evidência: matriz antes/depois prova que nenhuma ação autorizada foi removida; testes cobrem menu, permissão, teclado, título longo e ausência de overflow em desktop/mobile.

- [x] 3.3 Consolidar compatibilidade de rotas, aliases e estados ativos

  Depende de: 2.1, 2.2, 2.3, 2.4, 2.5, 2.6.

  Implementação: testar e ajustar somente quando necessário regras exact/prefix/override, redirects `/notes/*`, `/settings/*`, 2FA, submódulos fiscais e rotas dinâmicas; preservar history, deep link, reload e parâmetros aceitos sem criar item duplicado.

  Evidência: testes parametrizados percorrem todas as URLs da baseline, confirmam destino final, contexto destacado e comportamento funcional equivalente.

## 4. N3 — Gates integrados e evidência de prontidão

- [ ] 4.1 Executar gate completo de qualidade e geração do frontend

  Depende de: 3.1, 3.2, 3.3.

  Evidência: `cd frontend && pnpm run test:gate` e `cd frontend && pnpm run generate` passam integralmente, sem reduzir ou pular testes e sem versionar artefatos gerados.

- [ ] 4.2 Executar gates de fidelidade, segurança de artefatos e OpenSpec

  Depende de: 4.1.

  Evidência: `cd frontend && pnpm run test:fidelity`, `cd frontend && pnpm run test:artifacts` e validação OpenSpec strict da change/specs passam; revisão confirma alinhamento ao template `0f30c09`, ausência de segredos e nenhum contrato alheio incorporado.

- [ ] 4.3 Concluir validação visual e acessível rota a rota

  Depende de: 4.1, 4.2.

  Evidência: `validation-matrix.md` registra PASS para cada rota `SHELL`, `CHILD` relevante e `AUTH` em desktop e mobile, com identidades/permissões aplicáveis, loading, vazio, erro, detalhe, menus, teclado, foco, nomes acessíveis e ausência de overflow; redirects têm destino final verificado e toda divergência encontrada foi corrigida e revalidada.
