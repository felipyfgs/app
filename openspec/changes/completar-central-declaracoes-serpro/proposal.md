## Why

A central `/monitoring/declarations` replica apenas parte da referência visual e omite duas obrigações declarativas presentes no catálogo oficial do Integra Contador: DASN-SIMEI e MIT. Também não informa, de forma auditável, a diferença entre operação oficial catalogada, operação produtiva executável pelo hub e cobertura externa ao SERPRO. A auditoria do catálogo e da documentação oficial em 2026-07-21 encontrou 33 operações no domínio declarativo: 23 em produção e 10 em prospecção. Embora as 23 produtivas já tenham coordenadas oficiais, nem todas possuem fluxo público completo da UI ao executor, o que impede afirmar cobertura integral.

## What Changes

- Ampliar a central para PGDAS-D, DEFIS, DASN-SIMEI, DCTFWeb e MIT, preservando FGTS Digital e DIRF como referências externas claramente separadas.
- Expor no catálogo público de declarações uma matriz sanitizada derivada do snapshot oficial SERPRO, com estado de cobertura, capacidade de consulta/transmissão, rotas documentadas e data de verificação, sem expor `operation_key`, `idSistema` ou `idServico`.
- Expor um catálogo público sanitizado das 33 operações declarativas, com identificador público opaco, classe de operação, estado oficial, disponibilidade em runtime, parâmetros públicos curados e fluxo suportado, sem permitir que o frontend escolha coordenadas SERPRO.
- Completar a cadeia das 23 operações em `PRODUCTION`: 13 consultas/apoios por ações manuais tenant-safe e 10 emissões/declarações por preflight, confirmação, idempotência, acompanhamento, reconciliação segura e gates de mutação existentes.
- Manter as 10 operações em `PROSPECTION` visíveis e bloqueadas. Em particular, DASN-SIMEI deve refletir o aviso oficial de indisponibilidade para contratação, sem promoção artificial para `PRODUCTION`.
- Filtrar lista e overview de `declarations` também por `DASN_SIMEI` e `MIT`, com a mesma população e sem dados sintéticos.
- Refatorar o seletor de obrigações com as mesmas tabs dos filtros do painel e expor a central por uma ação compacta, sem cards descritivos entre as tabs e a carteira.
- Reutilizar os históricos locais já existentes de DASN-SIMEI e MIT a partir das ações por cliente, sem consulta SERPRO implícita ao abrir os modais.
- Atualizar testes de contrato, codecs, adapters, policy, API, portfolio, componentes, composables, fidelity e artifacts para validar cobertura integral produtiva e estados fail-closed.
- Permanecem fora do escopo: ligar flags ou cohorts de mutação por padrão, transmissão em lote, inventar contrato para operação em prospecção, validar mérito fiscal do conteúdo informado, emitir parecer jurídico, habilitar credenciais/canais de produção, adicionar `mei`/`mei-worker` ao Compose ou usar targets indisponíveis de backup/restore.

## Capabilities

### New Capabilities

- (nenhuma)

### Modified Capabilities

- `declarations-obligation-hub`: amplia o contrato da central para todas as obrigações declarativas relevantes do catálogo Integra Contador, explicita cobertura oficial versus externa e conecta os históricos DASN-SIMEI/MIT existentes.

## Impact

- API Laravel: catálogo público de declarações/operações, registro de ações públicas, codecs/adapters declarativos, fachada de execução, filtro de submódulos do portfolio e projeção da cobertura oficial.
- Web Nuxt: tipos, cliente API, página `/monitoring/declarations`, tabs de filtro, central de operações, formulários guiados e históricos existentes.
- Testes: matriz exata das 23 operações produtivas e 10 em prospecção, contratos sanitizados, codecs, autorização, idempotência, filtros por obrigação, componentes, composables e fidelity.
- Integração: snapshot versionado `official-service-catalog.v2026-07-16.json` e documentação oficial verificada; nenhuma credencial live, ativação de flag ou coordenada recebida do frontend.

### Dependências entre changes

- Nível: `C0`.
- Bases estáveis: `declarations-obligation-hub` em `openspec/specs/` e catálogo oficial SERPRO versionado no repositório.
- Depende de: nenhuma change ativa.
- Capability/contrato e marco exigido: `declarations-obligation-hub` já sincronizada em main specs; relação `coordenada` com as superfícies locais existentes de PGDAS-D, DEFIS, DASN-SIMEI, DCTFWeb e MIT.
- Desbloqueia: nenhuma change conhecida.
- Paralelismo: pode avançar em paralelo às changes de parcelamentos e caixa postal; qualquer edição concorrente em `declarations.vue`, `fiscal-modules.ts` ou `ModulePortfolioQueryService.php` deve ser reconciliada antes dos gates.
