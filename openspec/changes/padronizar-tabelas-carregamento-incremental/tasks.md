> **STATUS: SUPERSEDED em 2026-07-16.** Os checkboxes abaixo são histórico de uma implementação revogada, não progresso a continuar. Não marcar tarefas restantes nem sincronizar esta change. Ver `../ui-template-fidelity-total/evidence/SUPERSESSION.md`.

## 1. Base canônica e segurança de estado

- [x] 1.1 Corrigir o preset de `UTable` para limitar o container sem fixar o root inteiro e manter `sticky="header"` nativo.
- [x] 1.2 Implementar composable tipado de blocos incrementais com cursor/página, deduplicação, cancelamento/geração e reset por consulta.
- [x] 1.3 Implementar indicador transitório acessível de carga adicional, sem footer persistente, contagem ou mensagem de fim.
- [x] 1.4 Implementar binding reutilizável de `useInfiniteScroll` ao root exposto pelo `UTable`, com guarda de concorrência.
- [x] 1.5 Cobrir os utilitários compartilhados com testes unitários de append, dedupe, resposta obsoleta, erro preservando linhas e reset.

## 2. Contratos server-side

- [ ] 2.1 Garantir ordenação total e allowlist de sorting nos endpoints usados pelas tabelas priorizadas, sempre após escopo de tenant/autorização.
- [x] 2.2 Normalizar no frontend as respostas cursor/página sem enviar ou aceitar `office_id` livre.
- [ ] 2.3 Corrigir filtros declarados no frontend que hoje são ignorados pelo backend ou removê-los da UI/tipagem quando não houver contrato real.
- [ ] 2.4 Cobrir paginação/cursor com empates, asc/desc, filtros, inserção concorrente e isolamento entre escritórios.

## 3. Listas já incrementais ou com truncamento silencioso

- [x] 3.1 Converter Catálogo de Notas, Saúde e Sincronizações de botão explícito para auto-load sem footer persistente.
- [x] 3.2 Corrigir `/work` para anexar todos os blocos da fila sem truncar nos primeiros 25 itens.
- [x] 3.3 Corrigir Caixa Postal para anexar blocos sem truncamento e remover estado de busca sem contrato.
- [x] 3.4 Corrigir seções paginadas do detalhe de monitoramento do cliente, ou limitar explicitamente a “recentes” com deep-link completo quando a seção não for feed.

## 4. Tabelas administrativas principais

- [x] 4.1 Migrar Clientes e documentos por cliente para carregamento incremental, sorting server-side, filtros fixos e seletor de colunas funcional.
- [x] 4.2 Migrar as carteiras compartilhadas de Monitoramento e Guias, incluindo busca visível, sorting global e ações/ícones acessíveis.
- [x] 4.3 Migrar Processos e Modelos Work, preservando ordenação operacional e sem adicionar checkbox sem ação em massa.
- [x] 4.4 Migrar Fechamento e Exportações, preservando polling/ações e evitando duplicação quando novos registros aparecem.
- [x] 4.5 Migrar lotes/itens de Importação e lançamentos de Consumo, mantendo resumos pequenos fora do infinite scroll.

## 5. Recursos tabulares e superfícies secundárias

- [ ] 5.1 Padronizar headers ordenáveis, ícones de direção, ações de linha, aria-labels e column visibility somente onde houver contrato funcional.
- [x] 5.2 Manter checkbox/tri-state no Catálogo de Notas com IDs estáveis e confirmar ausência de seleção decorativa nas demais tabelas.
- [x] 5.3 Aplicar virtualização e altura controlada somente às famílias grandes de linhas previsíveis; manter tabelas compactas/embutidas sem virtualização.
- [ ] 5.4 Corrigir estados vazios/erro frágeis e placeholders literais nas superfícies tabulares secundárias sem convertê-las artificialmente em feeds infinitos.

## 6. Validação

- [ ] 6.1 Atualizar testes unitários e de componentes para sorting, seleção, estados, filtros fixos e carregamento incremental.
- [ ] 6.2 Validar lint, typecheck, testes frontend e testes backend afetados.
- [ ] 6.3 Validar com Playwright desktop, mobile e teclado: sticky, auto-load, indicador transitório, exaustão silenciosa, retry, troca de filtros e ausência de overflow.
- [ ] 6.4 Executar varredura de artefatos e confirmar ausência de material fiscal/credencial sensível.
- [ ] 6.5 Atualizar a matriz/checklist do template com as adaptações, validar a change OpenSpec e registrar pendências de migração offset→cursor separadamente.
