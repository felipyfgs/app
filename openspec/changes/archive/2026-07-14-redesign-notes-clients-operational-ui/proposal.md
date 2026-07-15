## Why

O escritório já captura NFS-e reais (piloto cliente 8, 100+ notas com projeção enriquecida), mas a UI ainda se comporta como **viewer técnico** (chave truncada, lista estreita estilo inbox, certificado/captura escondidos no detalhe). Referência de mercado (SaaS fiscal tipo HubStrom) mostra o que o operador espera: **KPIs de carteira, chips de status, busca única e tabela densa**. Sem redesenhar Notas e Clientes como posto de trabalho do contador, a captura correta no backend não se traduz em operação diária eficiente.

## What Changes

- Reposicionar **Notas fiscais** como catálogo fiscal escaneável: linhas densas (ou tabela) com número, partes (nome+CNPJ), papel, competência, valor, situação e local; detalhe legível; filtros com busca principal + secundários.
- Reposicionar **Clientes** como lista operacional de captura: tabela densa com chips de **A1**, **captura** e **sync**; KPIs no topo (total, com A1, sem A1, a vencer, bloqueados); busca única por nome/CNPJ.
- Manter o **shell Nuxt UI Dashboard** e a fidelidade estrutural ao template (`UDashboard*`, densidade, a11y); adaptar arquétipos (lista admin / mestre–detalhe) sem clonar multi-hub nem módulos fora do MVP.
- Consumir campos de projeção já existentes (`number`, `issuer_name`, `taker_name`, locais, `official_status_code`, resumos de credencial/elegibilidade) e expor na API de listagem o que faltar para a tabela de clientes (status de sync/A1 agregados).
- Alargar o painel mestre de Notas (deixar de esmagar conteúdo em ~25%) ou adotar tabela full-width + detalhe em drawer/slideover, preservando URL e permissões.

## Capabilities

### New Capabilities

Nenhuma capability de domínio fiscal nova. A mudança é de experiência operacional.

### Modified Capabilities

- `frontend-dashboard-experience`: requisitos de densidade, hierarquia de informação e padrões de lista para Notas e Clientes como posto operacional (não viewer de IDs técnicos).
- `fiscal-document-catalog` (delta leve): listagem e detalhe MUST apresentar campos de projeção legíveis ao operador (número, nomes, valor, competência, papel), sem exigir download de XML para triagem.
- `client-credential-management` (delta leve): listagem de clientes MUST permitir triagem visual de certificado e elegibilidade de captura sem abrir o detalhe.

## Impact

| Área | Efeito |
|------|--------|
| `frontend/` Notas, Clientes | Recomposição de páginas/componentes; tipos e labels |
| API notes/clients | Possível enriquecimento de list payload (resumo A1/sync); sem breaking de contratos core |
| Specs frontend + catálogo + cadastro | Deltas de requisitos de apresentação |
| Template Nuxt | Arquétipos existentes; **não** novo starter |
| Backend ADN/sync | Fora de escopo (já captura) |

## Não-objetivos

- Clonar multi-hub HubStrom (XMLHub, TaskHub, Academy…).
- Procuração e-CAC, mapa do Brasil, gráficos sem série real da API.
- Emitir/cancelar NFS-e, DANFSe/PDF, portal do cliente final.
- Override de NSU, multi-escritório SaaS, KMS cloud.
- Redesign completo de Exportações/Sincronizações/Admin (só consistência se tocar shell).
- Trocar stack ou abandonar fidelidade ao template Nuxt UI Dashboard.
