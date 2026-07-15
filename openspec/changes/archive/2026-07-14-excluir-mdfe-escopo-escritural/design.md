## Context

A captura MDF-e foi introduzida junto aos canais SEFAZ, mas o produto é voltado à escrituração do escritório contábil e MDF-e não atende esse objetivo. O estado atual já possui referências em enum, elegibilidade, fila, contratos TypeScript, specs e código legado de projeção. A correção precisa impedir execução e exposição sem exigir rollback destrutivo de banco.

## Goals / Non-Goals

**Goals:**

- Retirar MDF-e do catálogo, da captura, das filas, da elegibilidade, da UI e das exportações.
- Garantir que `kind=MDFE` responda vazio sem tocar em `mdfe_documents`.
- Manter compatibilidade de parsing do parâmetro legado para evitar erro 422/500 desnecessário.
- Registrar no OpenSpec que MDF-e está fora do escopo escritural.

**Non-Goals:**

- Apagar tabela, migration ou registros MDF-e existentes.
- Evoluir ou testar o cliente SOAP, parser, job ou projeção MDF-e legados.
- Alterar o comportamento dos canais ADN NFS-e, NF-e DistDFe ou CT-e.

## Decisions

### D1 — Compatibilidade legada sem disponibilidade operacional

`DocumentKind::Mdfe` e `CaptureChannel::MdfeDistDfe` permanecem reconhecíveis internamente, porém `captureAvailable()` e `isEnabled()` retornam falso de forma invariável. A opção MDF-e sai dos filtros publicados. Isso evita quebra abrupta de payloads antigos e, simultaneamente, impede reativação por variável de ambiente.

Alternativa considerada: remover imediatamente os cases dos enums. Rejeitada porque cursores ou payloads persistidos podem conter os valores antigos e falhar na hidratação.

### D2 — Resposta vazia antes de qualquer consulta

O catálogo não cria ramo de query para MDF-e. Quando o único `kind` solicitado é `MDFE`, devolve a estrutura paginada vazia (`data=[]`, `next_cursor=null`). A regra elimina a dependência runtime da relação `mdfe_documents`.

Alternativa considerada: consultar a tabela e retornar vazio quando ausente. Rejeitada porque preservaria a causa do erro reportado e acoplaria a API a uma projeção fora de escopo.

### D3 — Desativação por allowlist operacional

Elegibilidade e workers usam allowlist dos canais ADN, NF-e DistDFe e CT-e. A fila MDF-e sai da supervisão Horizon. O flag `mdfe_enabled` fica invariavelmente falso, independentemente do ambiente.

Alternativa considerada: confiar apenas em `SEFAZ_MDFE_ENABLED=false`. Rejeitada porque uma configuração acidental poderia religar funcionalidade sem suporte de produto.

### D4 — Retenção não destrutiva do legado

Migration, tabela e classes MDF-e existentes não são apagadas nesta change. Elas permanecem inertes para preservar histórico e permitir limpeza de dados em uma operação futura, explícita e auditada.

Alternativa considerada: migration de drop imediato. Rejeitada pelo risco de perda de dados e por não ser necessária para corrigir o runtime.

## Risks / Trade-offs

- [Código legado MDF-e continua no repositório] → Mitigar com allowlist, flag invariavelmente falsa, ausência de binding/dispatch e testes de não exposição.
- [Clientes antigos ainda enviam `kind=MDFE`] → Responder vazio de forma determinística, sem erro e sem query.
- [Configuração antiga de fila permanece em ambientes] → Horizon deixa de consumir a fila; mensagens antigas não são produzidas pelo dispatch operacional.
- [Tabela legada ocupa espaço] → Tratar eventual remoção como change separada com retenção e backup definidos.

## Migration Plan

1. Publicar backend com MDF-e fora da allowlist, da elegibilidade e do Horizon.
2. Publicar frontend sem MDF-e nos tipos e mensagens do catálogo.
3. Validar que `kind=MDFE` retorna vazio mesmo sem a tabela.
4. Manter migration e dados existentes; não executar rollback destrutivo.

Rollback: reverter o código desta change restaura apenas a exposição anterior; nenhum dado é migrado ou apagado.

## Open Questions

Nenhuma. A exclusão de MDF-e do escopo escritural foi definida pelo responsável do produto.
