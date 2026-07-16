# Pendências offset → cursor

Esta change remove a paginação visível e normaliza a experiência incremental. Os endpoints abaixo ainda usam `LengthAwarePaginator`; o adaptador frontend anexa páginas sequenciais com ordenação total e deduplicação. A migração de contrato fica separada porque exige alterar SQL, testes e compatibilidade de API sem misturar cursores fiscais/NSU.

## Prioridade alta

- `GET /api/v1/exports`: trocar `id DESC + page` por cursor opaco baseado em `id`; é o feed mais mutável por criação e polling.
- `GET /api/v1/fiscal/mailbox/messages`: cursor `(received_at_official, id) DESC` para evitar deslocamento durante ingestão.
- `GET /api/v1/outbound/deadline/pending`: cursor `(due_at, id) ASC`, preservando filtros de competência/faixa/modelo/raiz/fonte/cliente.

## Prioridade média

- `GET /api/v1/fiscal/guides`: cursor `id DESC`.
- `GET /api/v1/work/processes`: cursor `id DESC` após filtros operacionais.
- `GET /api/v1/work/templates`: cursor `(name, id) ASC`.
- `GET /api/v1/documents/import-batches`: cursor `id DESC`.
- `GET /api/v1/documents/import-batches/{batch}/items`: cursor `(item_index, id) ASC`.
- `GET /api/v1/office/serpro-usage/entries`: cursor `(occurred_at, id) DESC`.
- `GET /api/v1/office/serpro-authorization/proxy-powers`: cursores distintos por coluna permitida, sempre com `id` como desempate.
- `GET /api/v1/outbound/svrs-nfce/recoveries`: manter o filtro obrigatório de cliente na UI e adotar a tupla da ordem atual mais `id`.

## Prioridade posterior

- `GET /api/v1/clients` e `GET /api/v1/documents/by-client`: cursores diferentes por coluna permitida, sempre incluindo `id` como desempate.
- `GET /api/v1/fiscal/modules/{module}/clients`: cursor compatível com os aliases calculados de situação, competência e última consulta; preservar overview/totalizações separadas.
- `GET /api/v1/office/autxml`: cursor `(cnpj, id) ASC` para o checklist de estabelecimentos ativos; manter identidade, stream e cobertura fora do bloco paginado em futura revisão do contrato.

## Regras para cada migração

- Derivar o escritório apenas da sessão e aplicar o escopo antes de filtro, ordenação e cursor.
- Assinar ou tornar opaco o cursor; não aceitar `office_id` nem estado de outro filtro dentro de token reutilizável.
- Codificar todos os campos da ordenação e o ID único, com semântica coerente em `ASC` e `DESC`.
- Reiniciar o cursor quando tenant, filtros ou sorting mudarem.
- Cobrir empates, inserção entre blocos, retry, cursor inválido, filtros e isolamento entre escritórios.
- Durante compatibilidade, devolver `next_cursor` sem remover `meta.total` até os consumidores deixarem de depender da contagem.
