## Context

### Situação atual

- Backend (`NfseNoteStatus`) grava enums granulares alinhados ao ADN: `ACTIVE`, `SUBSTITUTE`, `CANCELLED`, `SUPERSEDED`, `JUDICIAL`, `UNKNOWN`, com `fromCStat` e eventos corretos.
- Labels de UI ainda espelham o enum (“Gerada”, “Substituta”, “Substituída”, …), o que gera chips redundantes na triagem.
- `official_status_code` (cStat) e eventos já existem no detalhe/API — o problema é **camada de apresentação e filtro**, não parse.
- Produto = captura/triagem para escritório (não emissor). Mercado de captura (Espião, consulta pública) e APIs (eNotas/Focus) usam eixo **Autorizada/válida vs Cancelada/inválida**.

### Restrições

- Não alterar semântica de parse/cStat/eventos da change `align-nfse-national-status-situations`.
- Não inventar status de emissão (Rascunho, Aguardando retorno).
- Tenancy `office_id`; sem expor XML/segredos em listagem.
- Template Nuxt dashboard: chips e filtros consistentes com o restante do painel.

## Goals / Non-Goals

**Goals:**

- Três labels operacionais na UI: **Autorizada**, **Cancelada**, **Em revisão**.
- Cobrir **todos** os casos fiscais e internos via grupo + detalhe (cStat, eventos, vínculo de substituição).
- Filtros e insights contam por grupo; detalhe preserva granularidade.
- Labels centralizados (backend e/ou frontend) para não divergir.

**Non-Goals:**

- Migrar coluna `status` no banco ou renomear enums.
- Remover `SUBSTITUTE` / `SUPERSEDED` / `JUDICIAL`.
- Emissão/cancelamento de NFS-e.
- Filtro avançado “só substitutas” no MVP (pode ser follow-up).

## Decisions

### 1. Duas camadas de vocabulário

| Camada | Campo / UI | Valores |
|--------|------------|---------|
| **Domínio (persistido)** | `status` | `ACTIVE`, `SUBSTITUTE`, `CANCELLED`, `SUPERSEDED`, `JUDICIAL`, `UNKNOWN` |
| **Oficial (auditoria)** | `official_status_code` + descrição | cStat 100/101/102/103 + texto |
| **Operacional (apresentação)** | label / filtro / insight | Autorizada · Cancelada · Em revisão |

**Alternativa rejeitada:** colapsar enums no banco → perde auditoria e reprocessamento de eventos.

### 2. Mapa enum → grupo operacional

| Grupo UI | Label | Enums |
|----------|-------|--------|
| autorizado | **Autorizada** | `ACTIVE`, `SUBSTITUTE`, `JUDICIAL` |
| cancelado | **Cancelada** | `CANCELLED`, `SUPERSEDED` |
| revisao | **Em revisão** | `UNKNOWN` |

Justificativa:

- **SUBSTITUTE (101)** = nota **válida** que substituiu outra → Autorizada.
- **SUPERSEDED** = original **inválida** para escrituração → Cancelada (detalhe explica “substituída por…”).
- **JUDICIAL (102)** = raro; se a projeção mantém a nota como válida, entra em Autorizada; detalhe mostra cStat 102. Se no futuro o evento invalidar, eventos já elevam para CANCELLED/SUPERSEDED.

**Alternativa rejeitada:** manter “Substituída” como 3º status de vida → confunde com “Substituta” e com cancelamento sem ganho na triagem diária.

### 3. Onde implementar o mapa

| Local | Responsabilidade |
|-------|------------------|
| `NfseNoteStatus::label()` | Passa a retornar **label operacional** (Autorizada / Cancelada / Em revisão) |
| `NfseNoteStatus::officialLabel()` ou `cStatDescription()` | Texto oficial curto (Gerada, Substituição gerada, …) a partir de cStat/status granular |
| `NfseNoteStatus::operationalGroup()` | Constante de grupo para filtros/insights (`AUTHORIZED` / `CANCELLED` / `REVIEW`) |
| Frontend `statusLabel` / badge | Consome o mesmo mapa (espelho em `format.ts` se API não enviar label; preferir alinhar strings) |

Se a API já devolve só `status` string, o frontend aplica o mesmo mapa documentado — fonte de verdade do mapa no PHP com testes; frontend espelha.

### 4. Filtros e insights

**Filtro de situação** na listagem e export:

- Opções UI: Todas · Autorizada · Cancelada · Em revisão.
- Query: ou `status_group=AUTHORIZED|CANCELLED|REVIEW`, ou expandir no controller para `whereIn(status, [...])`.
- Filtro legado por enum único (`status=SUBSTITUTE`) **pode permanecer** para API/debug; a UI principal não oferece mais chips por enum.

**Insights:**

| Card | Contagem |
|------|----------|
| Autorizadas (ou “Válidas”) | `ACTIVE` + `SUBSTITUTE` + `JUDICIAL` |
| Canceladas | `CANCELLED` + `SUPERSEDED` |
| Em revisão | `UNKNOWN` |

### 5. Modal / detalhe

Sempre mostrar:

1. Badge **operacional** (Autorizada / Cancelada / Em revisão).
2. Linha **situação oficial**: `cStat {code} · {descrição}` quando houver código.
3. Se `SUPERSEDED` ou evento de substituição: texto *Substituída* + referência quando disponível.
4. Se `SUBSTITUTE`: texto *Nota de substituição* (cStat 101).
5. Eventos de cancelamento com data/motivo quando existirem.

### 6. Cores de badge

| Grupo | Tom |
|-------|-----|
| Autorizada | success |
| Cancelada | error |
| Em revisão | warning |

Não colorir “Substituta” vs “Gerada” de forma distinta na lista (ambos success).

### 7. Cobertura de casos (checklist de design)

| Caso | Lista | Detalhe |
|------|-------|---------|
| cStat 100 | Autorizada | NFS-e Gerada (100) |
| cStat 101 | Autorizada | Substituição gerada (101) |
| cStat 102 | Autorizada | Decisão judicial (102) |
| cStat 103 | Autorizada | Avulsa (103) |
| Evento cancelamento | Cancelada | Evento + motivo |
| Substituída (evento) | Cancelada | Substituída por… |
| UNKNOWN / parse | Em revisão | Alerta de parse |

## Risks / Trade-offs

| Risco | Mitigação |
|-------|-----------|
| Contador perde filtro “só substitutas” | Detalhe + cStat; follow-up de filtro avançado se pedido |
| Confusão SUPERSEDED = “Cancelada” | Texto explícito no modal; opcional tooltip no chip “Cancelada (substituída)” se UX exigir |
| Frontend e backend com mapas divergentes | Testes PHP de label + checklist manual; espelhar tabela no design |
| Integrações externas que parseiam label “Gerada” | Documentar que label mudou; `status` enum estável (**não** breaking de valor) |
| Insights somam SUPERSEDED em canceladas e alteram números do piloto | Esperado e desejado; comunicar no release notes interno |

## Migration Plan

1. Atualizar `NfseNoteStatus` (label + group + official description) e testes unitários.
2. Ajustar controller de listagem/export se o filtro UI usar grupo.
3. Atualizar frontend: `format.ts`, `AppStatusBadge`, `NotesFilters`, export, insights, modal.
4. Smoke manual: lista, filtro Autorizada/Cancelada, modal cStat 100/101, nota cancelada se houver.
5. **Sem** migration de DB; **sem** remap de linhas (enums já corretos).

**Rollback:** reverter labels/filtros para o vocabulário por enum; dados intactos.

## Open Questions

Nenhuma bloqueante. Follow-ups opcionais:

- Filtro avançado por cStat ou “somente substitutas”.
- Chip com subtítulo “substituída” vs “cancelada por evento” na lista (se o escritório pedir).
