# Tarefas

## 1. Configuração e seleção

- [x] 1.1 Adicionar configuração fail-closed do scheduler, ambiente, allowlist, idade e limite de lote.
- [x] 1.2 Criar serviço read-only que seleciona clientes elegíveis sem criar autorização ou snapshot.

## 2. Despacho e defesa em profundidade

- [x] 2.1 Implementar comando de despacho, saída sanitizada e registro no scheduler.
- [x] 2.2 Marcar jobs automáticos e revalidar flag, allowlist, capability e autorização antes da chamada.

## 3. Verificação

- [x] 3.1 Cobrir configuração padrão, allowlist, autorização inapta, periodicidade e limite com testes de feature/unitários sem egress.
- [x] 3.2 Rodar Pint e testes focados; validar a change OpenSpec em modo estrito.
