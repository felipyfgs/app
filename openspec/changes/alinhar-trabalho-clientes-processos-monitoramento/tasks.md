## 1. N0 — Dados e contratos de domínio

- [x] 1.1 Adicionar migração aditiva, casts e relações para origem/versionamento do catálogo, regras de abrangência e contexto de Monitoramento em modelos/processos; cobrir rollback e defaults com teste Feature de schema/model.
- [x] 1.2 Implementar o manifesto dos cinco modelos-base, `ProcessTemplateCatalog` e `WorkMonitoringContextRegistry`, com teste Unit da versão, tarefas ordenadas, chaves allowlisted e ausência de URLs/coordenadas externas arbitrárias.

## 2. N1 — APIs tenant-scoped e geração auditável

- [x] 2.1 Implementar listagem/instalação tenant-scoped do catálogo e ampliar criação/edição/publicação de modelos com departamento, contexto e `audience_rules`; cobrir autorização, cópia independente, `lock_version` e referências cross-tenant em Feature tests.
  Depende de: 1.1, 1.2
- [x] 2.2 Implementar `ProcessAudienceResolver` com regime vigente por competência, fallback sinalizado, tags `ANY`/`ALL`, exclusões e precedência das exceções; cobrir mudança temporal de regime, empresa inativa/externa e seleção sanitizada em Unit/Feature tests.
  Depende de: 1.1
- [x] 2.3 Integrar a seleção estruturada ao preview/confirmação mantendo compatibilidade com `client_ids`, congelar a prova no lote e ampliar resumos/itens; cobrir expiração, edição concorrente e idempotência sem egress em Feature tests.
  Depende de: 2.1, 2.2
- [x] 2.4 Ampliar a coleção de processos com empresa/CNPJ, tarefas compactas, `active_only` e links allowlisted para cadastro/Monitoramento, preservando paginação e tenancy; cobrir projeção e isolamento em Feature tests.
  Depende de: 1.1, 1.2

## 3. N2 — Experiência operacional integrada

- [x] 3.1 Refatorar `/work/templates` em Biblioteca e Meus modelos, permitindo instalar, criar/editar tarefas/departamento/abrangência e gerar por filtros com inclusões/exclusões e prévia explicativa; adicionar Vitest dos contratos de API e da normalização do formulário.
  Depende de: 2.1, 2.3
- [x] 3.2 Criar `WorkProcessAccordionList` e aplicar em `/work/processes`, com uma linha por processo/empresa, expansão acessível das tarefas, composição responsiva inspirada na referência e links explícitos; cobrir markup, interação e responsividade em Vitest.
  Depende de: 2.4
- [x] 3.3 Consolidar `/work` como visão Tarefas, ajustar taxonomia/navegação e preservar ações, filtros, comentários e evidências da fila; atualizar testes de navegação e jornada crítica.
  Depende de: 2.4
- [x] 3.4 Adicionar bloco isolado “Trabalho operacional” ao overview da empresa, com falha parcial, progresso/prazo e links filtrados, sem alterar evidência fiscal nem disparar egress; cobrir utilitário/componente e integração em Vitest/Feature.
  Depende de: 2.4; externa: `completar-central-declaracoes-serpro` no marco `apply`, relação coordenada

## 4. N3 — Gates integrados e prontidão

- [x] 4.1 Executar e corrigir gates API (`composer validate --strict --no-check-publish`, `vendor/bin/pint --test`, `php artisan test`) e registrar cobertura dos cenários Work/tenant/sem egress.
  Depende de: 2.1, 2.2, 2.3, 2.4, 3.4
- [x] 4.2 Executar e corrigir gates Web (`pnpm run lint`, `pnpm run typecheck`, `pnpm run generate`, `pnpm run test`, `pnpm run test:fidelity`, `pnpm run test:artifacts`) e conferir a composição desktop/móvel do acordeão.
  Depende de: 3.1, 3.2, 3.3, 3.4
- [x] 4.3 Validar Compose e OpenSpec estritamente, auditar requisito por requisito contra código/testes e confirmar que nenhum serviço `mei`/`mei-worker`, flag externa ou segredo foi introduzido.
  Depende de: 4.1, 4.2
- [x] 4.4 Corrigir permissões dos artefatos novos do Work para leitura pelo PHP-FPM, adicionar regressão que inspeciona o manifesto, tornar o unwrap de identidade tolerante a payload não estruturado e validar bootstrap/API como `www-data` para impedir resposta fatal em HTML.
  Depende de: 4.3
