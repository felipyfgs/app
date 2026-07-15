## Context

### Situação atual

- `NfseXmlParser::mapOfficialStatus`: `100→ACTIVE`, **`101→CANCELLED`** (errado), `102→REPLACED`, default ACTIVE.
- UI: labels ACTIVE=Ativa, CANCELLED=Cancelada, UNKNOWN=Em revisão, AUTHORIZED=Autorizada (vocabulário de NF-e).
- Insights de triagem contam `review` como UNKNOWN + AUTHORIZED.
- Cancelamento real na NFS-e Nacional é tipicamente **evento**, não cStat 101.
- Leiaute Sefin/ADN (documentação Portal NFS-e): **100 = Gerada**, **101 = Substituição Gerada**; eventos de cancelamento e cancelamento por substituição.

### Restrições

- Não emitir/cancelar nota no painel; só capturar e projetar.
- Manter `official_status_code` (cStat textual) + `status` operacional.
- Tenancy office; projeção imutável do XML original — status operacional pode ser atualizado por eventos.

## Goals / Non-Goals

**Goals:**

- Mapeamento cStat alinhado ao padrão nacional.
- Status pós-evento (cancelada / substituída) na projeção.
- UI e filtros coerentes com Gerada / Substituta / Cancelada / Substituída / Em revisão.
- Detalhe sempre mostra cStat + descrição legível.

**Non-Goals:**

- Emitir/cancelar/substituir via API do produto.
- Cobrir todos os cStat de *resposta de webservice* (só documento + efeito de eventos na nota).
- DANFSe oficial.

## Decisions

### 1. Dois campos, duas semânticas

| Campo | Fonte | Uso |
|-------|--------|-----|
| `official_status_code` | cStat do XML da nota | Auditoria, detalhe (`cStat 100`) |
| `status` | Mapa cStat + eventos | Chip, filtro, insight, export |

Não misturar com `RegistrationStatus` de estabelecimento nem credencial A1.

### 2. Enum operacional de `status` (string estável)

| Valor | Label UI | Cor | Origem principal |
|-------|----------|-----|------------------|
| `ACTIVE` | Gerada | success | cStat 100 (e 103 avulsa tratada como válida) |
| `SUBSTITUTE` | Substituta | info | cStat **101** |
| `CANCELLED` | Cancelada | error | Evento de cancelamento (e indícios inequívocos) |
| `SUPERSEDED` | Substituída | neutral | Evento cancelamento por substituição na original |
| `JUDICIAL` | Decisão judicial | warning | cStat 102 (quando presente no XML) |
| `UNKNOWN` | Em revisão | warning | Parse falho, cStat ausente/desconhecido |

Remover uso de `AUTHORIZED` como situação de NFS-e na UI de notas (pode permanecer no dicionário global se outros módulos usarem).

`REPLACED` legado: migrar semanticamente para `SUPERSEDED` ou `JUDICIAL` conforme origem; novos parses não gravam `REPLACED`.

### 3. Mapa cStat → status no parse

```
100 → ACTIVE
101 → SUBSTITUTE   // era CANCELLED — correção crítica
102 → JUDICIAL
103 → ACTIVE       // avulsa válida; opcional AVULSA depois
null/vazio → UNKNOWN
outro → UNKNOWN    // não assumir ACTIVE (evita esconder o desconhecido)
```

### 4. Eventos atualizam a projeção

Ao persistir/processar evento vinculado a `access_key` da nota:

| Tipo de evento (normalizado) | Efeito em `nfse_notes.status` |
|------------------------------|--------------------------------|
| Cancelamento de NFS-e | `CANCELLED` |
| Cancelamento por substituição | `SUPERSEDED` na original |
| Cancelamento deferido por análise fiscal | `CANCELLED` |
| Solicitação de análise fiscal | **não** sobrescreve status final; evento fica no histórico |

Se o tipo XML não casar com tabela de eventos, registrar evento e **não** forçar status (manter cStat-based).

Prioridade: **evento de cancelamento/substituição vence** status ACTIVE/SUBSTITUTE do XML base.

### 5. UI

- `statusLabel` / `AppStatusBadge`: entradas SUBSTITUTE, JUDICIAL, SUPERSEDED; CANCELLED error; SUBSTITUTE info.
- Filtros Notes/Export: Gerada, Substituta, Cancelada, Substituída, Em revisão (sem Autorizada como situação NFS-e).
- Insights: `review` = só UNKNOWN (e opcionalmente parse); `cancelled` = CANCELLED; válido/geradas = ACTIVE + SUBSTITUTE (ou chips separados).
- Modal detalhe: badge situação + texto `cStat {code} · {descrição oficial curta}`.

### 6. Dados existentes

Comando/artisan ou job one-shot:

1. Remap: se `official_status_code=101` e `status=CANCELLED` → `SUBSTITUTE` (salvo se houver evento de cancelamento na mesma chave).
2. Opcional: reaplicar eventos ordenados por `event_at` para corrigir SUPERSEDED/CANCELLED.

Rollback: reverter código + remap inverso documentado (101 SUBSTITUTE → comportamento antigo não recomendado).

## Risks / Trade-offs

| Risco | Mitigação |
|-------|-----------|
| Eventos com type string instável no XML | Normalização tolerante + testes com fixtures reais do piloto |
| Notas 101 já marcadas CANCELLED no piloto | Script de remap com dry-run |
| UI “Ativa” vs “Gerada” | Preferir **Gerada** no label (oficial); aceitar alias “Ativa” no chip se UX pedir |
| default UNKNOWN em cStat novo | Melhor que fingir ACTIVE; alerta de parse se necessário |

## Migration Plan

1. Parser + testes unitários.  
2. Hook de evento → update status.  
3. Frontend labels/filtros/insights/modal.  
4. Remap dados piloto.  
5. Validar amostra de notas reais (100/101/canceladas).  

## Open Questions

Nenhuma bloqueante. Se o piloto não trouxer cStat 102/103, mapear e deixar sem fixture até aparecer XML real.
