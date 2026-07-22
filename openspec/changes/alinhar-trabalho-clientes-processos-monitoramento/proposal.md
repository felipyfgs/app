## Why

O módulo Trabalho já persiste modelos, processos e tarefas, mas a operação está fragmentada entre uma fila orientada a tarefas, uma listagem que abre outra página e uma geração que exige IDs de clientes. Precisamos tornar explícita a unidade operacional — um processo por empresa e competência — e conectar modelos, segmentação da carteira e evidência de Monitoramento sem duplicar nem inventar estado fiscal.

## What Changes

- Estabelecer duas visões operacionais complementares: **Processos**, com uma linha por processo de cada empresa e expansão inline das tarefas, e **Tarefas**, com a fila transversal existente para execução diária.
- Enriquecer a listagem de processos com empresa/CNPJ, competência, departamento, responsável, situação, prazo, progresso, riscos e todas as tarefas necessárias ao acordeão, preservando paginação e isolamento por escritório.
- Criar uma biblioteca versionada de modelos-base da plataforma para PGDAS, Folha de Pagamento, Fechamento Contábil, Parcelamentos e MEI; a instalação gera uma cópia editável e independente no escritório, sem sobrescrever customizações em atualizações futuras do catálogo.
- Permitir que o modelo do escritório mantenha departamento, tarefas, vínculo opcional com um módulo de Monitoramento e regra padrão de abrangência por regime tributário e tags de cliente.
- Substituir a entrada manual de `client_id` na geração por seleção auditável da carteira: filtros por regime vigente na competência, tags em modo qualquer/todas, tags excluídas e inclusões/exclusões manuais, com prévia de selecionados, bloqueados e motivos.
- Congelar no lote a regra, as exceções, os clientes e o contexto tributário usados na seleção; mudanças posteriores no modelo ou na carteira afetam apenas gerações futuras.
- Expor atalhos bidirecionais tenant-safe: do processo/tarefa para o cadastro e o Monitoramento da empresa e, no overview de Monitoramento por empresa, para seus processos operacionais ativos.
- Preservar a separação semântica entre processo operacional do Work e processo fiscal/e-Processo; o Work consome apenas contexto local já persistido e não dispara consulta externa ao abrir listas ou acordeões.
- Atualizar cobertura Feature, Unit/Vitest, fidelity/artifacts e jornada crítica para provar seleção temporal, tenancy, catálogo instalável, geração idempotente, acordeão responsivo e ligações com Monitoramento.
- Non-goals: executar SERPRO live; emitir parecer jurídico; realizar mutações ou transmissões fiscais; ligar flags/canais SEFAZ/SERPRO/MEI; criar `mei`/`mei-worker` no Compose; restaurar `services/mei`; implementar atualização automática destrutiva de modelos instalados; usar targets indisponíveis de backup/restore.

## Capabilities

### New Capabilities

- `operational-work-orchestration`: biblioteca e modelos do escritório, seleção auditável de empresas, geração por competência e as visões Processos/Tarefas centradas na unidade empresa-processo-tarefas.

### Modified Capabilities

- `company-monitoring-overview`: adiciona o contexto de processos operacionais ativos da empresa e navegação para o Work, sem alterar ou sintetizar as evidências fiscais do overview.

## Impact

- API Laravel: catálogo Work, metadados/regras de abrangência dos modelos, resolução temporal da carteira, payload de prévia e projeção detalhada da listagem de processos.
- Dados: migração aditiva de `process_templates` e `operational_processes`; snapshots de geração continuam sendo a prova histórica, sem copiar payload fiscal bruto.
- Web Nuxt: `/work/processes`, `/work`, `/work/templates`, tipos/cliente API e overview `/monitoring/clients/:clientId`; shell canônico permanece inalterado.
- Testes: PHPUnit Feature/Unit e Vitest para catálogo, filtros, regime por competência, exceções, tenancy, expansão inline, links e ausência de egress implícito.

### Dependências entre changes

- Nível: `C1`.
- Bases estáveis: `company-monitoring-overview` em `openspec/specs/`, tabelas/serviços Work existentes e catálogo de clientes com regimes e tags.
- Depende de: `completar-central-declaracoes-serpro` — contrato de navegação atual do detalhe por empresa, marco `apply`, relação `coordenada`; nenhuma operação declarativa é consumida ou alterada.
- Capability/contrato e marco exigido: a composição aplicada de `/monitoring/clients/:clientId`; a implementação Work pode avançar em paralelo e o pequeno ponto de integração deve ser reconciliado antes dos gates.
- Desbloqueia: automações futuras que transformem evidência fiscal persistida em tarefa operacional com confirmação humana.
- Paralelismo: backend e superfícies exclusivamente Work podem avançar em paralelo às changes fiscais; edições em `monitoring/clients/[clientId].vue`, `routes/api.php` e inventários de superfície devem preservar os diffs concorrentes e ser validadas em conjunto.
