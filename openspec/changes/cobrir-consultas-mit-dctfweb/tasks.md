## 1. N0 — Contrato e evidência offline

- [x] 1.1 Registrar fixture sanitizada e teste de coordenada, request, resposta e erros de `mit.listaapuracoes` (`LISTAAPURACOES317`) sem cliente HTTP real.
  - Depende de: `integrar-monitoramento-dctfweb` (apply)

## 2. N1 — Execução e projeção MIT

- [x] 2.1 Implementar DTO, alias/mapa, caller e adapter tipado de 317, com validação fail-closed e logs sanitizados.
  - Depende de: 1.1
- [x] 2.2 Persistir/projetar a lista de apurações por `CurrentOffice` e cliente sem criar artefato de documento ou efeito fiscal.
  - Depende de: 2.1

## 3. N2 — API local segura

- [x] 3.1 Expor a consulta e o resumo/lista local de 317 por rota autorizada, sem aceitar `office_id` e com ausência cross-tenant.
  - Depende de: 2.2
- [x] 3.2 Corrigir o contrato de histórico/download DCTFWeb para concatenar listas vazias, priorizar `download_path` e preservar MIME/nome seguros de XML/PDF.
  - Depende de: 2.2

## 4. N3 — Interface MIT e documentos locais

- [x] 4.1 Ampliar os tipos/composables e a cápsula MIT para mostrar apurações 317 já persistidas, sem coleta em GET, modal ou montagem.
  - Depende de: 3.1
- [x] 4.2 Atualizar o modal de histórico DCTFWeb para renderizar documentos por `download_path`, inclusive estado vazio e XML.
  - Depende de: 3.2

## 5. N4 — Verificação integrada

- [x] 5.1 Cobrir feature tests de autenticação, `CurrentOffice`, parâmetros, respostas, falhas e downloads DCTFWeb/MIT com fake/simulated.
  - Depende de: 3.1, 3.2
- [x] 5.2 Cobrir testes Nuxt da lista MIT, documentos DCTFWeb, ações de download e estados vazios sem request SERPRO.
  - Depende de: 4.1, 4.2
- [x] 5.3 Executar Pint, testes Laravel/Nuxt direcionados, typecheck, geração, artefatos, OpenSpec, `git status` e `git diff`; registrar gates globais externos que impeçam fechamento.
  - Depende de: 5.1, 5.2
