## Why

A consulta DCTFWeb já persiste recibos em `dctfweb_declarations` / evidências e DARFs em `dctfweb_darf_documents`, mas as abas **Declarações** e **Guias** do detalhe do cliente só enriquecem PGDAS-D. O usuário não vê DCTFWeb nessas superfícies como vê PGDAS.

## What Changes

- Enriquecer `GET /api/v1/fiscal/declarations` para obrigações `DCTFWEB` a partir de `dctfweb_declarations` (+ recibo/documento quando houver).
- Quando houver `client_id` e declaração DCTFWeb local sem projeção de hub, incluir linha sintética na lista (para não sumir dado já consultado).
- Estender `GET /api/v1/fiscal/guides?client_id=` para unir DARFs de `dctfweb_darf_documents` (shape de guia), com dedupe.
- Ajustar labels da UI (recibo / DARF) no detalhe do cliente.
- Testes espelhando o padrão PGDAS.

Non-goals:
- Não emitir DARF automaticamente após CONSRECIBO (mutação + bilhetagem).
- Não chamar SERPRO ao abrir as abas.
- Não materializar `tax_guides` permanentemente.
- Não redesenhar `/monitoring/dctfweb` nem inventar pagamento a partir do recibo.

## Capabilities

### New Capabilities

- `client-detail-dctfweb-hub-wiring`: abas Guias e Declarações do detalhe do cliente consomem dados locais DCTFWeb (recibo + DARF emitido).

### Modified Capabilities

- (nenhuma em main)

## Impact

- API: `DeclarationDctfwebEnrichmentService`; `DeclarationHubController`; `ClientGuidesQueryService`.
- Web: colunas em `pages/monitoring/clients/[clientId].vue`.
- Dados: leitura de `dctfweb_declarations`, `dctfweb_evidence_versions`, `dctfweb_darf_documents`.

### Dependências entre changes

- Nível: `C0`
- Bases estáveis: `wire-client-guias-declaracoes-pgdasd` (padrão de read-model)
- Depende de: nenhuma bloqueante
- Relação com `wire-client-guias-declaracoes-pgdasd`: `coordenada` (marco `apply`) — mesmo controller/guides service
- Desbloqueia: DCTFWeb visível nas mesmas abas que PGDAS após consulta/emissão local
