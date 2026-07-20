## Why

Consultas fiscais (ex.: PGDASD do cliente) hoje leem só a tabela local de poderes/procuração e bloqueiam com `PROXY_POWER_MISSING` quando o poder está ausente, vencido ou foi invalidado (ex.: troca de A1). Já existe Integra Procurações (`procuracoes.obter`) e sync assíncrono, mas o caminho de consulta **não** garante “checar local → buscar e-CAC se precisar → atualizar → consultar”. Sem esse passo, o contador vê falha mesmo com token do escritório ativo.

## What Changes

- Antes de executar consulta Integra que exige poder e-CAC (começando por Simples/MEI / PGDASD e demais adapters do mesmo gate), o sistema SHALL **assegurar** procuração fresca do cliente:
  1. consultar projeção local (`TaxProxyPower` / snapshot);
  2. se ausente, revogada, stale ou sem o poder exigido → chamar Integra Procurações (e-CAC);
  3. atualizar tabelas locais;
  4. só então seguir para a consulta alvo (ex. PGDASD).
- Em invalidação derivada (troca/remoção de A1, autor alterado), invalidar também snapshots de procuração e enfileirar/permitir re-sync sob demanda na próxima consulta.
- Reutilizar `ClientProcuracaoSyncService` / `TaxProxyPowerService::syncFromApi` — sem importação manual de poderes.
- Non-goals: mutações fiscais; ligar scheduler global de procurações por default; mei no Compose; abrir flags SERPRO em produção; parecer jurídico sobre poderes e-CAC.

## Capabilities

### New Capabilities

- `pre-consult-procuracao-ensure`: orquestração ensure-before-consult (local → Integra Procurações → local → consulta) e alinhamento pós-invalidação de A1.

### Modified Capabilities

- (nenhuma — `openspec/specs/` sem capability canônica de procuração; comportamento novo isolado nesta capability)

## Impact

- API: `SimplesMeiAdapter` / executor de runs fiscais; `ClientProcuracaoSyncService`; pontualmente `invalidateDerivedAuthorization` / reonboarding; testes de elegibilidade e sync.
- Web: sem UI nova obrigatória (comportamento transparente; opcionalmente mensagem transitória se sync assíncrono curto).
- SERPRO: uma chamada `procuracoes.obter` sob demanda quando a evidência local não for usável — fail-closed se sync falhar.

### Dependências entre changes

- Nível: `C0`
- Bases estáveis: main specs / archive; pipeline de sync já existente no código
- Depende de: nenhuma (coordenada com `office-serpro-auto-onboarding` já complete — token/onboarding; esta change cobre o gap de procuração pré-consulta)
- Capability/contrato: `pre-consult-procuracao-ensure`
- Marco exigido: n/a
- Relação: n/a
- Desbloqueia: apply desta change
- Paralelismo: ok com changes de UI/admin SERPRO sem ownership de elegibilidade/procuração
