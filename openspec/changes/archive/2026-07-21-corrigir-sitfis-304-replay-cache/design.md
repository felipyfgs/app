## Context

O fluxo `SitfisFlowService` já reconhece HTTP 304 como sucesso e extrai `protocoloRelatorio` de `IntegraResponse::etag`. Porém, `IntegraResponseNormalizer::publicHeaders` remove `etag` da coleção pública e `SerproOperationAttemptStore::toResponse` não reconstrói os campos dedicados `etag`/`expiresHeader`. Assim, um replay sticky de um 304 perde o único protocolo disponível. Há ainda respostas observadas em produção com 304 e `expires`, mas sem `ETag`; repetir imediatamente `/Apoiar` produz o mesmo cache vazio até expirar.

## Goals / Non-Goals

**Goals:**

- Tornar o replay de 304 SITFIS semanticamente equivalente à resposta fresca.
- Preservar somente o protocolo necessário em armazenamento sanitizado, sem persistir headers de autenticação.
- Manter 304 sem protocolo como estado transitório até `expires`, sem snapshot `ERROR`.
- Evitar loops rápidos e chamadas `/Apoiar` inúteis dentro da janela de cache.

**Non-Goals:**

- Alterar OAuth/mTLS, Termo de Autorização, power `00002`, bilhetagem ou catálogo SERPRO.
- Ligar kill switches/flags, mudar UI, criar serviços MEI no Compose ou executar mutações fiscais.
- Garantir emissão quando a SERPRO omite permanentemente o protocolo; nesse caso o sistema apenas respeita a expiração e tenta um protocolo novo.

## Decisions

1. **Canonicalizar o protocolo do ETag em `attempt.dados` no ACK de `sitfis.solicitar_protocolo`.**  
   O store já preserva `protocoloRelatorio` em `dados` e o fluxo já o lê antes do body/ETag. Para HTTP 304 com `response->etag` válido, o ACK incluirá `dados.protocoloRelatorio` sanitizado. Isso evita adicionar coluna ou persistir o header bruto. Alternativa rejeitada: liberar `etag` em `publicHeaders`, pois mistura material sensível com headers públicos e amplia risco de exposição.

2. **Reconstruir `etag` e `expiresHeader` no replay quando disponíveis em headers sanitizados, mantendo `dados` como fonte principal.**  
   `toResponse` deve preservar a semântica dos campos dedicados. Para SITFIS, o protocolo canônico em `dados` garante o fluxo mesmo se o header não estiver armazenado. Alternativa rejeitada: tornar sucesso 304 não-sticky sempre; isso gera HTTP repetido e ignora a idempotência.

3. **304 sem protocolo vira requeue até após `expires`.**  
   O fluxo retorna `Partial/Processing`, sem protocolo, com fase específica de espera de cache e `not_before` calculado a partir de `expiresHeader`, limitado por fallback seguro. Ao retomar sem protocolo, volta a `solicit`. Alternativa rejeitada: force-retry imediato, pois o cache oficial permanece igual e os dados reais já mostraram duas respostas 304 consecutivas.

4. **Não persistir snapshot de erro para ausência transitória do ETag.**  
   O resultado parcial usa a mesma orquestração Horizon e não demove evidência anterior válida.

## Risks / Trade-offs

- [Risk] `expires` ausente ou inválido → Mitigation: fallback configurado e limitado, sem loop curto.
- [Risk] Protocolo SITFIS é material sensível → Mitigation: persistir somente em `dados.protocoloRelatorio`, já permitido pelo contrato do attempt store; nunca logar valor.
- [Risk] Requeue atravessa meia-noite/fuso SERPRO → Mitigation: parsear timestamp HTTP e adicionar margem curta antes da nova solicitação.
- [Risk] Mais uma tentativa `/Apoiar` após expiração → Mitigation: rota não bilhetada e apenas uma tentativa após a janela oficial.
- [Risk] Vazamento entre offices → Mitigation: idempotency/attempt continuam office-scoped; nenhuma mudança em resolução de tenant.

## Mapa de dependências

- C1, dependência bloqueante de `corrigir-sitfis-integracao-real` no marco `apply`.
- Ownership: `SitfisFlowService`, `SitfisProtocolState`, `SerproOperationAttemptStore` e delta `sitfis-protocol-persist`.
- A implementação começa após o comportamento base de 304+ETag estar aplicado; não altera artefatos da change upstream.
- Rollout: deploy API e restart de Horizon/scheduler; novos refreshes recuperam clientes em erro.
- Rollback: revert dos arquivos da change; sem migration ou transformação de dados.

## Migration Plan

1. Aplicar código e testes.
2. Reiniciar workers para carregar os bindings/classes atuais.
3. Reenfileirar clientes SITFIS em `ERROR`/`BLOCKED` que já tenham power `00002`.
4. Observar attempts 304: replay deve conter protocolo canônico ou permanecer `PROCESSING` até `expires`.

## Open Questions

- Nenhuma para implementação; o comportamento de 304 e `expires` segue a documentação oficial e os attempts observados.
