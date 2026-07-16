# Linha de base — módulo operacional (Work)

**Change:** `add-operational-process-management`  
**Data:** 2026-07-16  
**Ambiente:** `docker compose exec php php artisan test` (sqlite :memory:)

## Suítes executadas antes da implementação

| Suíte | Resultado |
|-------|-----------|
| Architecture | 5 passed |
| Feature/Auth | 18 passed |
| Feature/Exports | 13 passed |
| Feature/Auth/OfficeIsolation | 3 passed |
| Feature/Operations | 13 passed |

## Observações

- Isolamento por `office_id` e stripping de `office_id` do cliente já estão cobertos.
- Export fiscal ZIP permanece separado do futuro export CSV operacional.
- Nenhum teste pré-existente cobre processos/tarefas operacionais (módulo novo).

## Personas (documentação de código)

Ver `backend/app/Domain/Work/README.md`: gestor→ADMIN, executor→OPERATOR, consulta→VIEWER; sem login de cliente final.
