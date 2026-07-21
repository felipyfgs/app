## Why

Hoje o monitoramento privilegia **módulo → empresas**. O usuário também precisa do eixo inverso: **empresa → todos os processos monitorados** no resumo. As duas visualizações devem coexistir — carteiras por módulo continuam; o detalhe da empresa passa a ser o hub dos processos daquele CNPJ.

## What Changes

- **Manter** as carteiras por módulo (`/monitoring/simples-mei`, DCTFWeb, declarações, etc.).
- **Fortalecer** o eixo empresa: ao abrir `/monitoring/clients/:id`, o **resumo (overview)** lista os processos monitorados (PGDAS-D, PGMEI, DCTFWeb, SITFIS, guias, FGTS, …) com status/última consulta derivados das projeções/API já existentes — não a tabela genérica de “snapshots” como peça principal.
- Cada card/linha do resumo leva ao detalhe enxuto daquele processo (abas/seções já existentes).
- Enxugar chrome acessório no detalhe (coordenado com `slim-monitoring-client-pgdasd`: sem pills cruzadas, sem description de marketing na superfície PGDAS-D).
- Entrada opcional “Empresas monitoradas” no hub `/monitoring` (lista → detalhe), **sem remover** o menu por módulo.

Non-goals:
- Não apagar carteiras por módulo.
- Não inventar status sem dado na API.
- Não ligar SERPRO ao só abrir o resumo.
- Redesign completo do shell fora de escopo.

## Capabilities

### New Capabilities

- `company-monitoring-overview`: resumo por empresa com processos monitorados agrupados; dual-entry com carteiras por módulo.

### Modified Capabilities

- (nenhuma em main)

## Impact

- Web: `pages/monitoring/clients/[clientId].vue` (overview), possível lista em `/monitoring` ou `/monitoring/clients`, navegação.
- API: preferir agregar no front a partir de endpoints locais já existentes; endpoint agregado só se fan-out ficar inviável (fase 2).
- Coordenação: `slim-monitoring-client-pgdasd` (chrome), `pgdasd-history-period-layout` (detalhe PGDAS).

### Dependências entre changes

- Nível: `C0` (pode iniciar em paralelo; merge cuidadoso em `[clientId].vue` com `slim-monitoring-client-pgdasd`)
- Depende de: nenhuma bloqueante
- Relação com `slim-monitoring-client-pgdasd`: `coordenada` (marco `apply`)
- Desbloqueia: hub empresa-first utilizável sem perder módulo-first
