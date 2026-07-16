# Domínio operacional (Work)

Camada de processos e tarefas do escritório contábil.

## Personas (não são papéis de segurança)

| Persona | Papel existente | Escopo no MVP |
|---------|-----------------|---------------|
| Gestor / coordenador | `ADMIN` | Departamentos, modelos, geração, reatribuição, lote, dispensa/reabertura |
| Executor | `OPERATOR` | Fila, execução de tarefas atribuídas, assumir livres do departamento, comentários/evidências |
| Consulta | `VIEWER` | Leitura de filas, processos, calendário e KPIs (sem mutações) |

Não há login de cliente final/contribuinte. Processos pertencem a `Client` já cadastrado no tenant.

## Fronteiras

- Todo conteúdo é plano de dados (`office_id` obrigatório).
- `PLATFORM_ADMIN` não herda acesso operacional sem membership tenant + escritório selecionado.
- Nenhum serviço operacional chama SERPRO, ADN, SEFAZ ou escreve cursores NSU/nNF.

## Objetos de valor

- `CompetenceMonth` — competência `YYYY-MM`
- `DueRule` / `DueDateCalculator` — prazos civis no timezone do escritório
- `ProcessStateCalculator` — estado derivado das tarefas
- `WorkRiskCalculator` / `QueueBucketResolver` — riscos e fila determinística
