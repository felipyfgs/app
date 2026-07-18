## Contexto

O snapshot técnico oficial identifica 102 como consulta sem parâmetros que
retorna `dados` com `anoCalendario` e `regimeApurado`, exige a procuração
e-CAC `00060` quando há representação e usa a capability `simples_mei`.
O adapter genérico atual usa o alias `CONSULTAR`, que resolve a operação 103;
portanto não é correto reutilizá-lo para 102.

## Decisões

### Contrato próprio para 102

Um codec validará envelope e `dados` (objeto JSON ou lista JSON serializada),
normalizando somente anos de quatro dígitos e regimes conhecidos. Resposta
incompleta ou ambígua falha fechada e não muda a projeção.

### Captura explícita, leitura local separada

O POST autorizado aciona a consulta pelo caminho central já protegido. O GET
da tela lê apenas os períodos persistidos do escritório atual. Abrir a página
não aciona o SERPRO.

### UI mínima na superfície existente

Não haverá nova tela: a lista de regimes do cliente já é a representação
natural do histórico. A UI deverá revelar ano e regime, com estado vazio, sem
exibir payload bruto ou evidência do cofre.

## Riscos

- O retorno oficial pode chegar como string JSON: o codec trata as duas formas
  documentadas e recusa o restante.
- Uma consulta pode ser bilhetada fora do teste: toda evidência usa fake ou
  simulated; nenhum canário é automático.
- Mistura de clientes/offices: a rota busca o cliente dentro de `CurrentOffice`
  e testes garantem ausência cross-tenant.
