## Context

Existem dois eixos legítimos:

1. **Módulo-first** — carteira operacional (ex.: todos os PGDAS-D da carteira).
2. **Empresa-first** — ao entrar na empresa, ver o portfólio de obrigações/processos daquele cliente.

Hoje (2) existe como shell com muitas seções, mas o overview é “Snapshots atuais”, pouco útil como mapa dos monitoramentos.

## Goals / Non-Goals

**Goals:**

- Dual-view explícito e estável.
- Overview da empresa = grades/lista de **processos monitorados** (só os que tiverem projeção/dado local).
- Click → seção já existente (`/pgdasd`, `/sitfis`, …).

**Non-Goals:**

- Substituir Simples/MEI por essa lista.
- Um único mega-endpoint na fase 1.
- Histórico completo embutido em cada card (só status + última consulta + link).

## Decisions

1. **Fase 1 (esta change):** overview montado no SPA com leituras locais já usadas pelas seções (snapshots/portfolio por módulo quando barato) ou um DTO mínimo novo `GET /api/v1/fiscal/clients/{id}/monitoring-overview` se o fan-out for >3–4 calls — preferir **um endpoint agregado read-only** se já houver serviço de portfolio por client; senão cards a partir de `snapshots` + links estáticos das seções com “sem dados” honesto.
2. **Cards:** label do processo, badge de situação quando houver, `last_valid_query_at` / equivalente, CTA “Abrir”.
3. **Navegação:** manter rail de seções; overview deixa de ser tabela de snapshots (snapshots podem virar item secundário ou sumir do overview).
4. **Lista empresas:** reutilizar `/clients` CRM ou adicionar atalho em `/monitoring` “Por empresa” apontando para a mesma rota de detalhe — sem duplicar cadastro.

## Risks / Trade-offs

- [Fan-out N+1 no overview] → Mitigação: endpoint agregado fail-closed / seções sem dado = card “Sem evidência local”.
- [Conflito de merge em clientId.vue] → Coordenar com slim-pgdasd.
- [Usuário procura snapshots] → Link “Execuções/Achados” no rail permanece.

## Migration Plan

Deploy front (+ API se houver overview). Rollback = reverter PR. Carteiras por módulo intactas.
