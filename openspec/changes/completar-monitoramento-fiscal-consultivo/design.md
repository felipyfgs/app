## Context

O monitor fiscal atual combina três contratos que evoluíram separadamente: `MonitoringSurfaceRegistry` no Laravel, `MONITORING_SURFACE_MATRIX` no Nuxt e exceções em `ManualConsultActionCatalog`. O catálogo oficial contém 119 operações, das quais 98 estão em produção, mas o workspace registra 15 superfícies e cerca de 65 vínculos; DEFIS, CCMEI, Regime, apoio Sicalc e contagem PagtoWeb entram por uma lista paralela. A SPA possui páginas funcionais e dados persistidos, porém a cobertura documental aparece somente no dashboard, as rotas de submódulo divergem entre backend e frontend e não existe gate E2E de navegador.

A change atravessa catálogo, políticas de execução, DTOs, APIs Laravel e todas as páginas do monitor Nuxt. Ela continua subordinada a `FiscalModuleAvailabilityService`, `CurrentOffice`, kill switch, readiness e elegibilidade de procuração. O produto será estritamente consultivo: pode atualizar dados por operações `READ` e baixar evidências já coletadas, mas não transmite, encerra, adere, gera novo documento fiscal nem envia comunicação.

## Goals / Non-Goals

**Goals:**

- Tornar o backend a única fonte normativa de superfícies, subcapacidades, ações consultivas, rotas e políticas documentais.
- Representar todas as jornadas do monitor com projeções semânticas, estados explícitos e cobertura contextual visível para todos os membros do escritório.
- Permitir que `ADMIN` e `OPERATOR` solicitem somente atualizações `READ`, mantendo `VIEWER` estritamente somente leitura.
- Preservar dados históricos durante bloqueio e impedir resposta tardia de outro tenant ou filtro.
- Cobrir API, componentes e jornadas completas com fixtures locais determinísticas, além de smoke Trial separado e seguro.

**Non-Goals:**

- Transmitir declarações, encerrar MIT, aderir ou renegociar parcelamento, gerar DAS/DARF/guia nova ou executar qualquer `FISCAL_MUTATION`/`DOCUMENT_GENERATION`.
- Enviar e-mail, WhatsApp ou outro outbound; preferências existentes permanecem legíveis, sem promessa de execução.
- Criar provider de FGTS Digital, fazer scraping de portal humano ou preencher guia/pagamento sem fonte oficial.
- Expor payload SERPRO bruto, `operation_key`, `idSistema`, `idServico`, PFX, tokens, XML fiscal integral ou identificadores de outro escritório.
- Tornar SERPRO Trial/Production dependência de CI, validar situação fiscal real ou produzir parecer jurídico.

## Decisions

### 1. Registro hierárquico único no Laravel

Um contrato tipado canônico representará `surface -> capabilities -> actions`. A superfície corresponde à rota de produto; a capability corresponde a uma cápsula funcional como PGDAS-D, DEFIS, CCMEI, MIT ou Sicalc; a action liga uma consulta `READ` ao handler, schema público de parâmetros, tipo de resultado, disponibilidade, fonte e política documental. `MonitoringSurfaceRegistry`, inventário manual, API de coverage e portfolio serão projeções desse mesmo registro.

Ações sem handler continuam catalogadas, mas aparecem como indisponíveis e não executáveis. A SPA receberá identificadores públicos estáveis, labels e campos semânticos, nunca coordenadas SERPRO. Um teste de integridade proibirá capability/action órfã e rota divergente.

Alternativa considerada: apenas adicionar as dez exceções à matriz atual. Rejeitada porque manteria duas fontes normativas e novo drift surgiria na próxima operação.

### 2. Rotas canônicas representam páginas; cápsulas são estado local

As rotas públicas canônicas permanecem `/monitoring/simples-mei` e `/monitoring/dctfweb`; PGDAS-D, PGMEI, DEFIS, CCMEI, Regime, DCTFWeb e MIT são capabilities da página, não novas URLs obrigatórias. As rotas legadas com submódulo continuam redirecionando para compatibilidade, mas o contrato público anuncia somente a rota canônica.

Alternativa considerada: criar uma rota por endpoint SERPRO. Rejeitada porque acopla navegação ao fornecedor e fragmenta a experiência contábil.

### 3. Projeções semânticas vencem resposta genérica

Cada capability define campos públicos próprios e um dos tipos `STRUCTURED`, `PDF`, `ASYNC_PDF`, `AGGREGATE` ou `UNAVAILABLE`. O normalizador SERPRO continua tratando o envelope técnico, mas controllers e query services entregam DTOs semânticos. A UI não renderiza JSON bruto nem infere situação fiscal a partir de mensagens livres.

Documentos são exibidos somente quando um `FiscalEvidenceDocument` autorizado informa `available=true` e `href` não vazio. Operações de geração permanecem visíveis apenas como limitação da cobertura, nunca como ação executável.

Alternativa considerada: um renderer universal de JSON. Rejeitada porque vaza detalhes do provider, dificulta acessibilidade e permite interpretações fiscais inconsistentes.

### 4. Estado consultivo comum e preservação de histórico

Todas as consultas usam um estado público comum: `IDLE`, `QUEUED`, `PROCESSING`, `READY`, `NO_DATA`, `FAILED`, `BLOCKED` ou `UNSUPPORTED`, acompanhado de `observed_at`, `source_provenance`, cobertura e motivo sanitizado. `202` mantém `QUEUED/PROCESSING`; falha de atualização não apaga o último snapshot válido. Troca de escritório, filtro ou capability incrementa uma geração local e descarta respostas obsoletas.

Alternativa considerada: cada página interpretar status próprios. Rejeitada porque já produz comportamentos diferentes entre SITFIS, portfolio e consultas manuais.

### 5. A execução é `READ` em duas fronteiras

O inventário público só marca como executável uma action de catálogo produtiva, implementada, `is_mutating=false`, classificada `READ` e com handler. A API revalida disponibilidade, tenant, papel, procuração e classe ao despachar; o job revalida novamente antes do transporte. `VIEWER` recebe a mesma cobertura e os mesmos dados históricos, mas nunca o controle de atualização.

Alternativa considerada: esconder botões mutantes somente no Nuxt. Rejeitada porque não protege chamadas diretas nem jobs já enfileirados.

### 6. Cobertura central e contextual para todos

O dashboard mantém a visão agregada das 15 superfícies. Cada página recebe uma visão contextual reduzida às capabilities daquela rota, usando o mesmo componente e contrato. `ADMIN`, `OPERATOR` e `VIEWER` visualizam fonte, frescor, cobertura, limitações e campos/resultados esperados; detalhes técnicos internos permanecem sanitizados.

O frontend deixa de manter uma matriz normativa manual. Caso uma pequena tabela compile-time seja necessária para navegação ou fail-closed, ela será gerada do registro e haverá gate que compare o snapshot com a API/manifesto; ausência do contrato nunca habilita ação ou documento.

Alternativa considerada: manter o painel somente no dashboard. Rejeitada porque o usuário não consegue relacionar a limitação à linha ou ação que está usando.

### 7. FGTS e fontes parciais permanecem honestas

FGTS/eSocial publica cobertura por campo. Fechamento, totalização e eventos podem ficar `READY`; guia e pagamento ficam `UNSUPPORTED` enquanto não houver provider oficial. Agregadores como Declarações e Guias preservam a proveniência da obrigação de origem e não promovem resumo parcial a evidência integral.

Alternativa considerada: tratar ausência como “em dia”. Rejeitada porque ausência de dado não prova regularidade fiscal.

### 8. E2E local volta como gate determinístico

Será reintroduzido um runner E2E de navegador para um conjunto pequeno de jornadas críticas contra Laravel/Nuxt locais com banco semeado e transports externos bloqueados. O gate cobre login, troca de tenant, dashboard, uma capability estruturada, uma documental, uma assíncrona, uma bloqueada, FGTS parcial e `VIEWER` sem ação. Testes de contrato cobrem todas as surfaces/capabilities; E2E não precisa repetir cada combinação de operação.

Smoke Trial roda em comando separado, exige flag explícita e segredo fora do repositório, usa somente os quatro cenários oficiais configurados e sanitiza artefatos. Nunca roda no `test:gate` ou CI padrão.

Alternativa considerada: depender apenas de testes que leem o código-fonte Vue. Rejeitada porque eles não provam roteamento, renderização, autenticação nem integração SPA/API.

## Mapa de dependências

```text
N0 contrato canônico + matriz de aceite + harness de teste
 ├─> N1 API/projeções/políticas
 └─> N1 fundação Nuxt do workspace
       └─> N2 páginas e capabilities consultivas
             └─> N3 jornadas E2E + gates integrados
```

- Ownership `fiscal-monitoring-workspace`: registro de superfícies/capabilities, contrato público, projeções do monitor, integração Nuxt e gates E2E.
- Não existe upstream ativo; changes concluídas são bases estáveis e não serão reabertas.
- Backend e harness podem avançar em paralelo após o contrato N0. As páginas só migram depois que tipos públicos e política de execução estiverem estáveis.
- Outbound e mutações fiscais são consumidores futuros e não podem editar esta capability antes de seu gate integrado.
- Rollout publica primeiro contrato/API compatível, depois SPA e por último remove o uso da matriz manual. Rollback mantém o endpoint compatível e restaura a SPA anterior sem apagar snapshots/evidências.

## Risks / Trade-offs

- [Change ampla atravessa todas as páginas] → migrar por capability sobre um contrato compatível e limitar a menos de 20 tasks verificáveis.
- [Catálogo oficial muda depois do snapshot] → validar versão/estrutura no build e falhar fechado para action desconhecida.
- [Contrato público excessivamente técnico] → separar labels/cobertura para usuário de coordenadas e envelope internos, que nunca saem da API.
- [Ação classificada incorretamente como leitura] → exigir concordância entre catálogo, classe de política e allowlist do handler; divergência bloqueia execução.
- [Resposta antiga aparece após troca de escritório] → geração de request ligada a `sessionEpoch` e descarte de resposta obsoleta.
- [E2E instável ou pesado] → poucas jornadas representativas, fixtures locais, zero rede externa e limpeza automática de browser/processos.
- [Trial induz confiança indevida] → badge e aviso permanentes; smoke separado informa apenas transporte/schema.
- [Dados FGTS incompletos confundem usuário] → cobertura por campo e estado `UNSUPPORTED`, nunca fallback positivo.

## Migration Plan

1. Publicar o registro hierárquico e o contrato público mantendo os campos atuais de `/fiscal/monitoring/coverage` durante a transição.
2. Derivar o inventário manual e validar todas as rotas/actions contra o contrato canônico; adicionar DEFIS, CCMEI, Regime, Sicalc e PagtoWeb.
3. Publicar DTOs/estados comuns e gates `READ` em dispatcher/job sem remover endpoints legados.
4. Migrar o Nuxt para um provider de workspace e inserir cobertura contextual página a página, mantendo histórico e navegação atuais.
5. Remover consumo da matriz manual somente depois do gate de equivalência e executar API, web, generate e E2E local.
6. Executar smoke Trial opcional com as quatro fixtures oficiais e registrar somente evidência sanitizada.

Rollback: desativar a nova SPA/contrato por versão, manter endpoints compatíveis e snapshots existentes, sem rollback destrutivo de banco. Como a change não transmite nem cria efeito fiscal remoto, não há reconciliação de mutação.

## Open Questions

- A escolha da API/provider de e-mail e WhatsApp será tratada em proposta independente e não bloqueia esta change.
- Um provider oficial futuro para guia/pagamento FGTS deverá criar capability própria; até lá a cobertura parcial é requisito, não pendência desta entrega.
