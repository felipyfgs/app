## Por quê

A operação oficial `regimeapuracao.consultaranoscalendarios`
(`CONSULTARANOSCALENDARIOS102`) é uma consulta produtiva, sem mutação, mas o
hub hoje a resolve indiretamente como 103. Assim, a lista histórica de anos e
regimes não tem contrato, captura nem apresentação local próprios.

## Mudanças

- Implementar 102 com coordenada, request vazio e resposta tipada/fail-closed.
- Projetar os anos e regimes por `CurrentOffice` e cliente e apresentá-los na
  superfície já existente de Regime do Simples Nacional.
- Cobrir contrato, isolamento, falhas e UI somente com fixture/fake/simulated.

Não faz parte desta change alterar a opção de regime (101), consultar a opção
pontual (103), resolução (104), chamar SERPRO real, habilitar flags, nem ler
credenciais, vault ou dados de `dados/`.

## Capabilities

### New Capabilities

- `regime-calendar-history`: consulta 102 e visualização local segura de anos
  calendário e regimes de apuração.

## Impacto

- Backend: catálogo/DTO/adapter de Simples Nacional, projeção e API local.
- Frontend: endpoint tipado e lista de regimes por cliente.
- Segurança: somente `CurrentOffice`; resposta, logs e UI sem segredos; testes
  sem rede e sem bilhetagem.
