## Why

A carteira SITFIS em produção mostra Erro/Bloqueado sem relatório real: o monitoring não sincroniza o poder e-CAC `00002` antes da consulta, e o fluxo trata HTTP 304 do `/Apoiar` (cache oficial com `protocoloRelatorio` no `ETag`) como falha — contra a documentação SERPRO. Sem isso, a Situação Fiscal nunca completa solicit→emit→PDF.

## What Changes

- Garantir procuração usável (`00002`) via `EnsureClientProcuracaoForConsult` no caminho de monitoring/refresh SITFIS (mesmo contrato do Simples/MEI).
- Tratar HTTP 304 em `sitfis.solicitar_protocolo` como sucesso de cache: extrair `protocoloRelatorio` do header `ETag` e seguir para espera/`emitir`.
- Remover o force-retry + `SITFIS_NOT_MODIFIED_EMPTY` como caminho padrão quando o body 304 está vazio mas o ETag traz o protocolo.
- Não promover snapshots Failed/Blocked/Skipped sem evidência a `is_current` (não demover relatório anterior válido na carteira).

## Capabilities

### New Capabilities

- (nenhuma)

### Modified Capabilities

- `pre-consult-procuracao-ensure`: escopo explícito para SITFIS monitoring + refresh (poder `00002`), além de Simples/MEI.
- `sitfis-protocol-persist`: 304 `/Apoiar` como sucesso com protocolo no ETag; snapshot sem evidência não vira `is_current`.

## Impact

- API: `SitfisFlowService`, binding DI, `FiscalSnapshotPersistence`, testes Sitfis/PreConsult/Persistence.
- Specs: deltas em `pre-consult-procuracao-ensure` e `sitfis-protocol-persist`.
- UI: sem redesign; carteira passa a refletir relatório quando a integração completa.
- Non-goals: redesign UI; abrir kill-switch SERPRO; mudar `FISCAL_PROFILE`; mei no Compose; ops backup/restore; parecer jurídico.

### Dependências entre changes

- Nível: `C0`
- Bases estáveis: main specs `pre-consult-procuracao-ensure`, `sitfis-protocol-persist`; archive `fix-sitfis-protocol-persist`, `pre-consult-procuracao-ensure`
- Depende de: nenhuma (change ativa `completar-sitfis-monitoring-integra` é coordenada/opcional — superfície UI já entregue; esta change corrige integração)
- Capability/contrato: `pre-consult-procuracao-ensure`, `sitfis-protocol-persist`
- Marco exigido: n/a
- Relação: coordenada com `completar-sitfis-monitoring-integra` (não bloqueante)
- Desbloqueia: refresh SITFIS real com PDF na carteira quando A1 + `00002` ok
- Paralelismo: não editar as mesmas deltas em paralelo com outra change nestas duas capabilities
