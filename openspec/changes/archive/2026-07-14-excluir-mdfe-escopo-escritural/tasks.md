## 1. Contrato operacional do backend

- [x] 1.1 Manter MDF-e apenas como valor legado não disponível nos enums de documento e canal
- [x] 1.2 Limitar elegibilidade e supervisão Horizon à allowlist ADN, NF-e DistDFe e CT-e
- [x] 1.3 Garantir que o catálogo e a exportação não consultem, listem, detalhem ou baixem MDF-e

## 2. Contrato do frontend

- [x] 2.1 Remover MDF-e dos tipos TypeScript, filtros, textos e fixtures operacionais

## 3. Testes e especificações

- [x] 3.1 Cobrir resposta vazia para `kind=MDFE` sem dependência da tabela de projeção
- [x] 3.2 Cobrir ausência de MDF-e nas opções de catálogo e na elegibilidade por canal
- [x] 3.3 Sincronizar os deltas com as specs principais e remover a capability operacional MDF-e
- [x] 3.4 Validar OpenSpec, testes focados do backend, typecheck/testes do frontend e integridade do diff
