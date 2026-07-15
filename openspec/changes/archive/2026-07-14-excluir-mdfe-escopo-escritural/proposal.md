## Why

MDF-e é um documento logístico e não integra a necessidade de escrituração deste produto interno do escritório contábil. Mantê-lo no catálogo, nas filas e nos contratos operacionais cria complexidade, dependência de tabela e risco de operação sem benefício para o escopo definido.

## What Changes

- **BREAKING** Remove MDF-e dos tipos operacionais aceitos pelo catálogo, sincronização, elegibilidade, painel e exportação.
- Faz `kind=MDFE`, quando recebido por compatibilidade com clientes antigos, retornar coleção vazia sem consultar projeção ou exigir tabela de MDF-e.
- Desabilita permanentemente o canal e a fila MDF-e no runtime, ainda que exista variável de ambiente legada.
- Remove MDF-e das opções e dos tipos expostos pela interface.
- Mantém artefatos de banco e código legado inertes nesta change, sem rollback destrutivo de migration nem exclusão de dados existentes.

## Capabilities

### New Capabilities

- Nenhuma.

### Modified Capabilities

- `client-credential-management`: limita uso do A1 e elegibilidade aos canais escriturais suportados, excluindo MDF-e.
- `cte-mdfe-full-capture`: remove a captura MDF-e e preserva somente a captura e o cursor CT-e.
- `fiscal-document-catalog`: exclui MDF-e da identidade de tipo e define resposta vazia compatível sem acesso à projeção.
- `frontend-dashboard-experience`: remove MDF-e dos filtros, estados e saúde operacional apresentados na interface.
- `mdfe-document-sync`: remove integralmente os requisitos de captura, cursor e parse MDF-e.
- `multi-dfe-catalog-projection`: exclui projeções e eventos MDF-e do catálogo multi-DF-e.
- `operations-dashboard`: exclui o canal MDF-e da inbox e do resumo operacional.
- `xml-delivery`: exclui MDF-e dos tipos disponíveis para download e exportação.

## Impact

- Backend Laravel: enums de tipo/canal, elegibilidade, configuração de filas e testes do catálogo.
- Frontend Nuxt: contratos TypeScript, fixtures e texto de disponibilidade do catálogo.
- API: `GET /api/v1/documents?kind=MDFE` permanece tolerado, mas responde vazio e não consulta `mdfe_documents`.
- OpenSpec: oito capabilities são ajustadas; a capability exclusiva de MDF-e é removida.

## Não-objetivos

- Remover migration, tabela ou dados MDF-e já existentes por operação destrutiva.
- Implementar escrituração, captura, manifestação, download ou exportação MDF-e.
- Alterar os fluxos de NFS-e, NF-e, NFC-e ou CT-e além da retirada de referências a MDF-e.
