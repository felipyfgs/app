## Why

O manifesto `official-sources.v2026-07-16.json` registra hashes sintéticos e, portanto, não comprova o conteúdo oficial capturado. Como o catálogo, a matriz de poderes e o ledger usam essa proveniência para autorizar decisões fail-closed, novas implementações e canários devem permanecer bloqueados até existir uma cadeia de evidência real, reproduzível e testada.

## What Changes

- Substituir o manifesto de fontes por um snapshot datado com hashes SHA-256 reais do conteúdo oficial estável recuperado, distinguindo fonte HTTP estável, referência oficial dinâmica, evidência de transporte e referência histórica sem URL.
- Reconciliar o hash da matriz de procurações com a própria página oficial e atualizar consumidores/configuração sem aceitar fallback silencioso.
- Validar offline estrutura, hash, ausência de padrões placeholder e coerência entre catálogo, matriz de poderes e registro de fontes.
- Disponibilizar uma verificação explícita e read-only das fontes HTTP vigentes que falhe fechado em indisponibilidade ou divergência, sem rodar em testes offline comuns.
- Corrigir o ledger e as evidências piloto para separar 25 mutações produtivas de 33 mutações totais, rejeitar classificações históricas insuficientes e registrar o bloqueio de proveniência.
- Non-goals: habilitar SERPRO live; executar operação de negócio ou mutação fiscal; alterar coordenadas oficiais que continuam 1:1; tratar Trial como produção; arquivar changes; commitar ou fazer push.

## Capabilities

### New Capabilities

- `serpro-fontes-oficiais`: registro versionado, verificável e fail-closed das fontes oficiais que sustentam catálogo, procurações e decisões operacionais SERPRO.

### Modified Capabilities


## Impact

- Recursos SERPRO em `backend/resources/serpro/`, configuração `backend/config/serpro.php`, registro/validação em `backend/app/Services/Serpro/` e testes focados.
- Ledger e evidências sanitizadas em `docs/ops/`, sem conteúdo fiscal, credenciais ou payload bruto.
- Nenhuma mudança de API tenant, UI, banco ou capability de egress.

### Dependências entre changes

- **Nível:** C0.
- **Bases estáveis:** `schema-conventions`, `AGENTS.md`, catálogo oficial local `2026.07.16.1` e documentação oficial SERPRO vigente.
- **Depende de:** nenhuma change ativa.
- **Capability/contrato:** nova `serpro-fontes-oficiais`; marco exigido `verify`; relação `bloqueante` para novas decisões baseadas no snapshot.
- **Desbloqueia:** retomada segura das changes SERPRO ativas e novos lotes do ledger após `verify`, sem alterar retroativamente o estado das changes já concluídas.
- **Paralelismo:** só pode avançar em paralelo com trabalho que não edite manifestos, matriz de poderes, registro de fontes ou as mesmas linhas do ledger; canários e promoção de operações aguardam esta change.
