## Context

O Work atual já possui `work_departments`, `process_templates`, `process_template_tasks`, lotes de geração, `operational_processes` e `operational_tasks`. A geração materializa um processo por `template + client + competence`, salva `template_snapshot` e protege duplicidade; a fila `/work` já executa tarefas com optimistic locking. Entretanto, `/work/processes` recebe as tarefas no eager load mas não as publica na coleção, a linha navega para outro detalhe em vez de expandir, e `/work/templates` exige uma lista textual de IDs.

O cadastro de empresas já oferece `tax_regime`, períodos de vigência em `client_tax_regime_periods` e tags em `client_categories`. O Monitoramento possui um overview empresa-first e projeções fiscais locais, mas “processos monitorados” e “processos operacionais” são conceitos diferentes. Esta change é transversal porque cria o contrato Work e acrescenta um bloco operacional ao overview existente; manter as duas capabilities explícitas evita esconder essa alteração de produto.

A referência visual fornecida mostra a anatomia esperada em telefone: cabeçalho da empresa, uma linha para o processo com situação/prazo e tarefas aninhadas quando expandido. A implementação seguirá essa hierarquia, mas utilizará o shell, cores semânticas, controles e breakpoints já adotados pelo Nuxt UI do repositório.

## Goals / Non-Goals

**Goals:**

- Tornar `empresa + modelo + competência` a unidade explícita da visão Processos.
- Manter Processos e Tarefas como duas perspectivas sobre os mesmos registros, sem duplicar estado.
- Entregar catálogo versionado de modelos-base e cópia editável por escritório.
- Resolver a carteira por regras simples e auditáveis de regime/tags, com exceções manuais.
- Usar o regime vigente na competência sempre que houver período conhecido e registrar qualquer fallback.
- Preservar isolamento por `CurrentOffice`, autorização existente e optimistic locking.
- Ligar Work, cadastro e Monitoramento por links allowlisted e leituras locais, sem egress implícito.
- Manter geração idempotente e histórico congelado após confirmação.

**Non-Goals:**

- Criar um motor BPMN, grafo arbitrário de dependências ou cron configurável pelo usuário.
- Executar tarefas fiscais automaticamente, transmitir declarações ou disparar mutações SERPRO.
- Sincronizar de volta o estado da tarefa para uma projeção fiscal ou considerar conclusão operacional como evidência fiscal.
- Atualizar automaticamente uma cópia instalada quando o catálogo receber nova versão.
- Ligar flags/canais externos, criar serviços Compose de MEI ou armazenar payload fiscal bruto no Work.

## Decisions

### 1. Catálogo da plataforma em código e cópia tenant-scoped

O catálogo será um manifesto PHP versionado (`config/work_process_catalog.php`) lido por `ProcessTemplateCatalog`. Cada entrada terá `key`, `version`, metadados, regra padrão de prazo, papel de departamento sugerido, `monitoring_module_key`, regra padrão de abrangência e tarefas ordenadas. `GET /api/v1/work/template-catalog` publica somente campos de negócio; `POST /api/v1/work/template-catalog/{key}/install` cria um `ProcessTemplate` do escritório e suas tarefas em transação.

A cópia armazenará `catalog_key` e `catalog_version`, mas permanecerá independente. Uma nova versão do manifesto aparecerá como disponível; não haverá merge ou overwrite silencioso. O nome da cópia poderá receber sufixo quando já existir, mantendo a unicidade atual por escritório.

Alternativas consideradas: tabela global editável de catálogo, que exigiria uma nova superfície administrativa e política de rollout; ou seed por escritório, que perde identidade de origem e torna atualização destrutiva. O manifesto versionado é determinístico, revisável e suficiente para os cinco modelos iniciais.

### 2. Metadados aditivos no modelo e snapshot no processo

Uma migração adicionará a `process_templates`:

- `catalog_key` e `catalog_version`, ambos nullable;
- `monitoring_module_key`, nullable e validado por allowlist;
- `audience_rules` JSON, com defaults vazios.

`operational_processes` receberá `monitoring_module_key` nullable. A geração copia o valor do modelo para o processo e continua gravando `template_snapshot`; processos existentes permanecem válidos com `null`. O modelo continua usando `lock_version` e `is_active`: esta change não introduz publicação semântica separada, porque o snapshot e a rejeição do preview após edição já protegem a execução confirmada.

### 3. Regra de abrangência estruturada, sem query builder livre

O contrato normalizado de `audience_rules` será:

```json
{
  "tax_regimes": ["SIMPLES_NACIONAL"],
  "category_ids": [1, 2],
  "category_match": "ANY",
  "excluded_category_ids": [9]
}
```

O preview aceitará `selection`, contendo filtros opcionais para aquela competência e `include_client_ids`/`exclude_client_ids`. Se filtros temporários forem omitidos, usa-se `audience_rules` do modelo. Não será aceito SQL, expressão livre, `office_id`, URL ou coordenada fiscal no payload.

Semântica:

- apenas empresas ativas do `CurrentOffice` entram;
- `category_match=ANY` exige ao menos uma tag escolhida; `ALL` exige todas;
- qualquer tag excluída retira a empresa;
- inclusão manual ultrapassa os filtros organizacionais de regime/tags, mas não inatividade, inexistência no tenant ou duplicidade;
- exclusão manual vence inclusão e filtro;
- IDs de outro escritório nunca aparecem nem como nome na resposta.

Alternativa considerada: reutilizar diretamente o filtro atual de `/clients`, que só expressa categorias em modo ANY e regime atual. Um resolvedor Work dedicado torna precedência e prova histórica explícitas sem alterar o contrato geral do catálogo de clientes.

### 4. Regime tributário resolvido na competência

`ProcessAudienceResolver` buscará primeiro um `client_tax_regime_periods` que cubra o primeiro dia da competência. Havendo mais de um registro inválido/sobreposto, usará o mais recentemente observado e emitirá alerta de ambiguidade. Sem período aplicável, normalizará `clients.tax_regime`, marcará a origem como `CURRENT_PROFILE_FALLBACK` e mostrará alerta no preview; regime desconhecido não casa com filtro explícito.

Cada item do preview publicará nome/CNPJ mascarado, regime resolvido, origem do regime, tags, origem da seleção e conflitos. O `request_snapshot` armazenará a regra normalizada, inclusões, exclusões e evidência de seleção por cliente, tornando o lote reproduzível mesmo se tags ou cadastro mudarem depois.

### 5. Preview/generation compatível e idempotente

O endpoint existente continuará aceitando `client_ids` por compatibilidade, convertendo-os em inclusões manuais quando `selection` não for enviado. O novo frontend enviará `selection`. `OperationalProcessGenerationService` delegará a resolução ao novo serviço, manterá o hash e a chave idempotente e continuará materializando apenas itens não bloqueados.

`preview_summary` ganhará contagens de selecionados por regra, incluídos manualmente, excluídos e bloqueados. Itens bloqueados por duplicidade/inatividade continuarão visíveis com motivo. A confirmação nunca reavalia a carteira: usa o snapshot do preview e rejeita modelo alterado ou preview expirado.

### 6. Projeção de Processos preparada para acordeão

`GET /api/v1/work/processes` publicará tarefas compactas já ordenadas, empresa com CNPJ mascarado, `monitoring_context` allowlisted e os agregados existentes. A consulta continuará paginada e fará eager load das relações necessárias; não haverá request por linha ao expandir.

No Nuxt, `WorkProcessAccordionList.vue` renderizará uma linha/carteira por processo, botão com `aria-expanded` e região de tarefas. Desktop exibirá cabeçalho tabular; telefone empilhará empresa, processo, situação e prazo de forma semelhante à referência. Expandir não navegará. Links explícitos abrirão processo completo, empresa, Monitoramento e tarefa na fila. Apenas uma expansão por vez será o default para reduzir altura, sem impedir trocar rapidamente de linha.

Alternativa considerada: adicionar expansão genérica a `ShellDataTable`. A hierarquia empresa-processo-tarefas e o layout móvel específico são domínio Work; ampliar o shell compartilhado aumentaria risco de regressão nas carteiras fiscais.

### 7. Duas visões sobre a mesma fonte de verdade

A rota `/work/processes` será a visão por processo/empresa. `/work` será rotulada “Tarefas” e manterá a fila mestre-detalhe existente, com filtros e transições. A tarefa expandida no processo apontará para `/work/tasks/:id`; ações detalhadas, comentários e evidências continuam na visão Tarefas. Calendário e Modelos seguem como ferramentas auxiliares, não como uma terceira representação do estado operacional.

### 8. Contexto de Monitoramento allowlisted e somente leitura

`WorkMonitoringContextRegistry` mapeará chaves conhecidas (`PGDASD`, `PGMEI`, `INSTALLMENTS`, entre outras realmente suportadas) para label e rota interna do cliente. O backend monta o href tenant-scoped usando somente IDs já autorizados. Modelos manuais podem deixar a chave vazia; valores fora da allowlist são rejeitados.

O overview `/monitoring/clients/:clientId` fará uma leitura local de `/work/processes?client_id=...&active_only=1` e exibirá até os processos ativos com progresso/prazo, além do link para a lista filtrada. Falha do bloco Work será parcial e não apagará as evidências fiscais. Nenhuma dessas leituras chama SERPRO/SEFAZ/MEI ou muda situação fiscal.

### 9. Autorização e tenancy

Listar catálogo e processos seguirá policies de leitura Work. Instalar/editar modelos e confirmar geração continuará dentro de `EnsureWorkRealMembership` e das policies atuais. Todos os relacionamentos de departamento, categoria, membership e cliente serão resolvidos com `CurrentOffice`; `office_id` recebido continuará descartado. O catálogo global não contém dado de tenant.

## Risks / Trade-offs

- [Catálogo em código exige deploy para novo modelo] → manter `key/version` explícitos e separar instalação de atualização; uma console administrativa pode vir em change futura.
- [Fallback para regime atual pode classificar competência histórica incorretamente] → sinalizar `CURRENT_PROFILE_FALLBACK`, guardar a origem no snapshot e permitir revisão antes da confirmação; valor desconhecido não casa silenciosamente.
- [Payload da lista cresce com tarefas] → limitar a 100 processos por página, selecionar somente colunas necessárias e evitar evidências/comentários na projeção compacta.
- [Vazamento entre offices por IDs de exceção] → resolver toda coleção sob `CurrentOffice`, sanitizar ausentes como conflito genérico e cobrir com Feature tests multi-tenant.
- [Confusão entre monitoramento e conclusão operacional] → labels distintos, contexto somente leitura e nenhum sincronismo de status entre os domínios.
- [Edição concorrente do modelo após preview] → manter `template_lock_version` e rejeitar confirmação, exigindo nova prévia.
- [Bilhetagem SERPRO acidental ao abrir o overview] → usar exclusivamente endpoints Work e projeções locais; testes com `Http::fake()`/`Http::assertNothingSent()`.
- [Conflito com changes fiscais ativas] → limitar o patch compartilhado no detalhe de Monitoramento a um componente/bloco aditivo e rodar gates com o estado integrado do worktree.
- [Segredos ou payload fiscal em Work] → persistir apenas chaves públicas, IDs e situação resumida; nunca PFX, tokens, XML ou corpo SERPRO.
- [Introdução indevida de serviço MEI no Compose] → nenhuma alteração em Compose ou `services/mei`; gate de infra permanece obrigatório.
- [Arquivo novo legível apenas pelo autor local interrompe o bootstrap do PHP-FPM] → manter artefatos PHP/configuração com leitura para o usuário de runtime e cobrir o manifesto Work com teste explícito de permissões, além de validar o bootstrap como `www-data`.

## Mapa de dependências

```text
Base estável Work + clients/tags/regimes
              │
              ├── N0 dados/catálogo + testes
              │        │
              │        ├── N1 seleção/preview + testes
              │        │        │
              │        │        └── N2 UI Modelos/geração + testes
              │        │
              │        └── N1 projeção Processos + testes
              │                 │
              │                 └── N2 acordeão/Tarefas + testes
              │
completar-central-declaracoes-serpro (apply, coordenada)
              │
              └── N2 bloco Work no overview da empresa + testes

                         N3 gates integrados
```

- Ownership Work: migration nova, `Services/Work`, controllers Work, componentes/pages Work e testes próprios.
- Ownership compartilhado: somente imports/rotas aditivas em `routes/api.php`, `createWorkApi.ts` e um bloco isolado em `monitoring/clients/[clientId].vue`.
- A upstream de Declarações não é bloqueante para backend/Work; seu estado aplicado é preservado e o gate coordenado valida o arquivo compartilhado.
- Rollout é aditivo: migrar colunas nullable, publicar API compatível, depois usar o novo frontend. Rollback de aplicação mantém colunas sem uso; rollback da migration só é seguro antes de qualquer processo novo depender dos metadados.

## Migration Plan

1. Aplicar a migração aditiva com defaults/nullable e sem reescrever linhas existentes.
2. Disponibilizar catálogo, resolvedor de abrangência e projeções compatíveis com `client_ids` legado.
3. Publicar frontend de Modelos e Processos; modelos existentes aparecem em “Meus modelos” com regras vazias.
4. Adicionar o bloco Work ao overview por empresa.
5. Validar tenancy, ausência de egress, geração idempotente e gates completos antes de disponibilizar a mudança.
6. Em rollback, remover primeiro o frontend novo; a API antiga continua funcional. Não remover colunas enquanto existirem modelos/processos que carreguem seus metadados.

## Open Questions

- Nenhuma questão bloqueante. Atualização assistida/diff entre versões do catálogo, recorrência automática e ações fiscais vinculadas ficam explicitamente para changes futuras.
