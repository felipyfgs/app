## 1. N0 — Contrato e limites seguros

- [x] 1.1 Confirmar no catálogo local e na documentação oficial `CCMEI/DADOSCCMEI122`, rota `Consultar`, versão `1.0`, entrada vazia e campos sensíveis de saída; registrar a fonte na matriz.
- [x] 1.2 Mapear os adapters, DTOs, projeções, rotas e detalhe de cliente existentes, delimitando os paths exclusivos da change e os pontos coordenados.

## 2. N1 — Consulta e evidência backend

- [x] 2.1 Implementar o codec/DTO CCMEI com allowlist, decodificação fail-closed e descarte de QR code/Base64, CPF e payload bruto.
  Depende de: 1.1, 1.2
- [x] 2.2 Implementar o serviço e a projeção tenant-scoped, obtendo contribuinte do cliente autorizado por `CurrentOffice` e respeitando feature flags, kill switch e allowlist.
  Depende de: 1.1, 1.2
- [x] 2.3 Criar contrato HTTP e autorização para consultar e listar apenas a evidência CCMEI do mesmo cliente/escritório, com erros e logs sanitizados.
  Depende de: 2.1, 2.2
- [x] 2.4 Cobrir backend com fixtures fake/simulated: envelope vazio, sucesso, retorno inválido, capability desabilitada, isolamento e ausência de dados sensíveis.
  Depende de: 2.1, 2.2, 2.3

## 3. N2 — Interface tenant-scoped

- [x] 3.1 Implementar tipos e composable para o contrato CCMEI sem representar QR code, CPF completo ou payload bruto.
  Depende de: 2.3
- [x] 3.2 Adicionar ao detalhe do cliente a ação explícita de consulta e o histórico com estados de carregamento, vazio, erro e sucesso, reutilizando componentes existentes.
  Depende de: 3.1
- [x] 3.3 Cobrir a interface com testes Nuxt para os estados e para a atualização de histórico sanitizado.
  Depende de: 3.1, 3.2

## 4. N3 — Verificação integrada e evidências

- [x] 4.1 Executar lint, typecheck, testes e build/generate pertinentes de backend e frontend, registrando comandos e resultados reais.
  Depende de: 2.4, 3.3
- [x] 4.2 Revisar `git status`, `git diff --stat` e `git diff`, validar OpenSpec estrito e atualizar a checklist CCMEI com resultado local, pendência de homologação externa e arquivos afetados.
  Depende de: 4.1
