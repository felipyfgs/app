## 1. N0 — Contrato oficial e limites

- [x] 1.1 Confirmar a documentação oficial de `DEFIS` / `CONSDECLARACAO142`, incluindo rota, versão, entrada, resposta, poder e campos proibidos.
- [x] 1.2 Mapear os pontos de extensão de catálogo, adapter, projeção, rota e UI, mantendo 141, 143 e 144 inalteradas.

## 2. N1 — Backend seguro

- [x] 2.1 Implementar codec fail-closed com fixture sanitizada e testes de lista e resposta inválida.
  Depende de: 1.1, 1.2
- [x] 2.2 Implementar observação/projeção idempotente tenant-scoped e evidência por allowlist sem `idDefis`.
  Depende de: 2.1
- [x] 2.3 Expor POST confirmado e GET local autorizado por `CurrentOffice`, rejeitando `office_id` e sem coleta em leitura.
  Depende de: 2.2

## 3. N2 — Interface e cobertura

- [x] 3.1 Implementar tipos, composable e modal de histórico DEFIS no monitor de declarações.
  Depende de: 2.3
- [x] 3.2 Adicionar confirmação explícita e testes Nuxt para vazio, erro, sucesso e atualização local.
  Depende de: 3.1
- [x] 3.3 Cobrir Laravel fake/simulated para projeção, autorização, isolamento, idempotência e logs sanitizados.
  Depende de: 2.3

## 4. N3 — Gates e evidências

- [x] 4.1 Executar Pint, Composer, PHPUnit, ESLint, typecheck, Vitest, generate, catálogo e OpenSpec; atualizar a matriz e evidência com pendência de homologação externa.
  Depende de: 3.2, 3.3
