## 1. N0 — Contrato e persistência segura

- [x] 1.1 Confirmar a coordenada e os filtros do `PAGTOWEB/CONTACONSDOCARRPG73` no catálogo oficial versionado e registrar a classificação de bilhetagem.
- [x] 1.2 Criar migrations, modelos e projeções para observações de contagem, retendo somente resultado e metadados sanitizados.
- [x] 1.3 Implementar codec/projetor/adapter de contagem com allowlist de filtros, resposta escalar não negativa e falhas sanitizadas.
- [x] 1.4 Estender Fake/Simulated e testes unitários de contrato para a operação, inclusive payload inválido e ausência de campos sensíveis.

## 2. N1 — API tenant-scoped do monitor

- [x] 2.1 Expor leitura de histórico e disparo confirmado para cliente do `CurrentOffice`, com permissões de guias e rejeição de `office_id` no request.
  - Depende de: 1.2, 1.3
- [x] 2.2 Integrar a execução à cadeia central de operação, autenticação, procuração, poder `00004`, capability e billing sem chamada HTTP direta.
  - Depende de: 1.1, 1.3
- [x] 2.3 Cobrir controller, tenancy, feature flag, erros e persistência com testes de feature offline.
  - Depende de: 2.1, 2.2

## 3. N2 — Surface de guias no cliente

- [x] 3.1 Adicionar tipos, cliente HTTP e composable para a contagem de pagamentos, preservando mensagens de erro seguras.
  - Depende de: 2.1
- [x] 3.2 Criar painel e rota filha de cliente usando o arquétipo de settings, com confirmação explícita e aviso de possível bilhetagem.
  - Depende de: 3.1
- [x] 3.3 Criar testes unitários da UI para renderização, consulta confirmada e erro sanitizado.
  - Depende de: 3.2

## 4. N3 — Evidências e gates integrados

- [x] 4.1 Atualizar a matriz de cobertura e a evidência piloto com link oficial, validação offline e bloqueio explícito de consulta externa real.
  - Depende de: 2.3, 3.3
- [x] 4.2 Executar validação OpenSpec, lint/typecheck/testes/build frontend e validações PHP completas; corrigir todos os erros atribuíveis.
  - Depende de: 4.1
