# Supersessão de `padronizar-tabelas-carregamento-incremental`

Data da decisão: **2026-07-16**  
Change substituta: `ui-template-fidelity-total`  
Decisão do produto: desconsiderar as decisões de UI/UX das changes anteriores e migrar integralmente para `.reference/nuxt-dashboard-template` @ `0f30c09`.

## Estado da change substituída

`padronizar-tabelas-carregamento-incremental` estava incompleta, com **17/27** tarefas marcadas, e ainda não havia sido sincronizada. Ela determinava:

- remover footer e `UPagination`;
- usar carregamento automático por rolagem;
- introduzir sentinel, sticky custom e virtualização;
- aceitar essas diferenças como exceção ao template.

Essas decisões estão revogadas. A change antiga:

- **não deve ser sincronizada** nas main specs;
- **não deve ser arquivada como concluída**;
- **não terá tarefas restantes marcadas artificialmente como feitas**;
- deve sair do diretório de changes ativas por limpeza explícita, preservando este registro histórico.

## Direção substituta

Listas administrativas voltam integralmente a `app/pages/customers.vue`:

1. navbar e ação primária;
2. utilitários no body;
3. `UTable` e `:ui` literais;
4. footer, contagem e `UPagination`.

Paginação, busca, filtro e sorting continuam server-side. Infinite scroll, sentinel, auto-load, exaustão silenciosa, virtualização, sticky custom e footer ausente são proibidos.

## Contratos seguros incorporados

Somente estes aprendizados funcionais são reaproveitados na substituta:

- escopo tenant/autorização antes de filtro, sorting e paginação;
- allowlist de sorting e desempate determinístico;
- cancelamento ou descarte de respostas obsoletas;
- reset de página/seleção ao trocar consulta ou escritório;
- ausência de truncamento silencioso;
- seleção apenas quando existe ação real;
- estados loading, vazio, erro e dados anteriores preservados;
- nenhuma alteração de cursor fiscal NSU/nNF.

## Código produzido sob a decisão revogada

Uso de `useInfiniteTable`, `ShellInfiniteTableLoader`, sentinels, virtualização, sticky custom ou ausência de footer deve ser removido. O experimento posterior de paginação em `PURGE-2026-07-16.md` aponta para a direção correta, mas permanece parcial enquanto mantiver wrappers de chrome como `ShellListShell`, `ShellTableFooter` e `DocsWorkspace`.

## Critério de encerramento

A supersessão estará operacionalmente limpa quando:

- a change antiga sair de `openspec/changes/` sem sync;
- nenhuma spec/delta ativa citar `INC` ou autorizar infinite scroll;
- nenhum arquivo de runtime importar os componentes/composables revogados;
- os gates e as skills versionadas refletirem footer + `UPagination` e bundles literais.
