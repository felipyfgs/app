## 1. Contrato e execução offline

- [x] 1.1 Adicionar fixture sanitizada e testes de coordenada, request vazio,
  envelope/resposta e erros de `CONSULTARANOSCALENDARIOS102` sem HTTP real.
- [x] 1.2 Implementar DTO/codec/adapter específico e projeção idempotente de
  anos e regimes por cliente e escritório.

## 2. API e interface local

- [x] 2.1 Expor POST explícito de consulta e GET local autorizado, sem aceitar
  `office_id` e sem coletar na leitura.
- [x] 2.2 Exibir o histórico de anos/regimes na UI existente do Simples
  Nacional, com estado vazio e tipos públicos mínimos.

## 3. Verificação

- [x] 3.1 Cobrir autenticação, CurrentOffice, falhas, resposta e idempotência
  em Laravel fake/simulated.
- [x] 3.2 Cobrir UI/composable em Nuxt sem chamada SERPRO.
- [x] 3.3 Rodar Pint, testes focais, lint, typecheck, geração, artefatos,
  OpenSpec e revisão de diff/status.
