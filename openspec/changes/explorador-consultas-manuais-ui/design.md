## Context

Hoje o monitoramento tem:

- **Catálogo + superfícies** (`MonitoringSurfaceRegistry`) ligando páginas a
  `operation_key`s oficiais.
- **Adapters e POSTs de consulta** esparsos (DEFIS 142/143/144, CCMEI, MIT 317,
  PAGTOWEB, PGMEI, SITFIS refresh, parcelamentos, etc.).
- **UI irregular**: `PendingSearchButton` / enqueue de leitura em alguns
  módulos; em outros só “refresh” da projeção local; vários históricos só
  abrem modal sem CTA de consultar; DEFIS/CCMEI não têm submódulo próprio na
  navegação de simples-mei.

O operador não consegue responder: “para este cliente, o que eu já posso
consultar manualmente e onde vejo o resultado?”.

Constraints: tenancy `CurrentOffice`, flags default OFF, capabilities
`disabled|simulated|real`, sem mutações nesta change, UI alinhada ao
arquétipo dashboard, bilhetagem só em ação explícita.

## Goals / Non-Goals

**Goals:**

- Um inventário acionável de consultas **somente-leitura** por office/cliente
  e por superfície de monitoramento.
- Execução manual confirmada reutilizando callers/adapters existentes (sem
  segundo pipeline SERPRO).
- UI do explorador + CTAs nas telas de módulo mostrando elegibilidade,
  última consulta e projeção/histórico local.
- Cobertura de teste offline (fake/simulated) para inventário, bloqueios e
  um fluxo consult→projeção por família prioritária.

**Non-Goals:**

- Mutações (transmitir, gerar DAS/DARF, encerrar MIT, aderir/reparcelar).
- Ops `PROSPECTION`, `CANCELED`, `UNDER_CONSTRUCTION`.
- Ligar `SERPRO_CAPABILITY_*=real` ou allowlists de produção.
- Novo motor genérico que monte envelope a partir de formulário livre do
  frontend (sem adapter).
- EVENTOSATUALIZACAO como produto de carteira (fica fora da onda 1 de UI).
- Parecer jurídico ou interpretação fiscal automática.

## Decisions

### 1. Inventário derivado de superfícies + catálogo (não lista hardcode na UI)

Fonte: `MonitoringSurfaceRegistry.operationKeys` ∩ catálogo
(`official_state=PRODUCTION`, `platform_support=IMPLEMENTED`,
`is_mutating=false`). Cada item do inventário expõe:

- `action_id` estável (ex. `surface:operation_key`)
- rótulo humano, módulo, superfície, `operation_key` **somente server-side
  para execução** — a UI pública recebe `action_id` + labels, sem obrigar
  o browser a conhecer `idSistema`/`idServico` (pode mostrar chave
  sanitizada se útil para suporte)
- `eligibility`: `ready | module_off | capability_off | token_missing |
  power_missing | adapter_missing | mutating_blocked`
- `params_schema` mínimo (ano, competência, modalidade…) quando o adapter
  exige
- `last_result_summary` da projeção local do cliente (se houver)

Alternativa rejeitada: página admin de “catálogo bruto” com 119 linhas sem
ligação a adapters — inútil para o escritório.

### 2. Execução por despacho a adapters existentes

`POST /api/v1/fiscal/manual-consults` (nome final alinhado às rotas
existentes) recebe `action_id`, `client_id`, `params`, `confirmed: true`.
O serviço resolve o handler:

| Família | Handler reutilizado |
|---------|---------------------|
| PGDASD docs/consulta | `PgdasdPostConsultService` / coleta documental |
| DEFIS 142–144 | controllers/services DEFIS já existentes |
| CCMEI 122/123 | `Ccmei*` post-consult |
| PGMEI 24 | consult PGMEI |
| REGIME 102–104 | regime * monitoring services |
| DCTFWEB leitura | adapters recibo/XML/dec |
| MIT 315–317 | Mit* (sem 314) |
| SITFIS | refresh assíncrono existente |
| Mailbox/DTE | list/detail/indicator adapters |
| PAGTOWEB 71/73 | payment list/count |
| SICALC 52 | revenue support |
| Parcelamentos leitura | `ParcelamentoReadAdapter` |
| PNR vínculos | registration refresh |
| e-Processo 271 | tax process refresh |

Se não houver handler → `adapter_missing` (fail-closed), nunca envelope
genérico a partir do client.

### 3. Separação consulta × visualização

- Abrir explorador, modal ou histórico: **somente GET local**.
- Disparar SERPRO: **somente POST** com confirmação explícita.
- Após sucesso (síncrono ou job): UI recarrega projeção/histórico do módulo
  de destino (deep-link para `/monitoring/...`).

### 4. UI: explorador central + CTAs nos módulos

```
/monitoring  ──►  "Consultas manuais" (explorador)
                      │
                      ├─ filtro módulo / cliente
                      ├─ lista de ações + elegibilidade
                      └─ confirmar → toast + ir ao módulo

/monitoring/<módulo>  ──►  botão "Consultar" / PendingSearch
                      │
                      └─ mesmo contrato de inventário filtrado
                         pela surface_key da página
```

Arquétipo: list + modal de confirmação (Nuxt UI), sem inventar shell
paralelo. Reaproveitar padrões de `PendingSearchButton`,
`RecentRefreshConfirmModal` e modais DEFIS/MIT.

### 5. Escopo de onda 1 (implantável)

Incluir no inventário **somente** keys que:

1. estão no registry de superfície **ou** têm POST de consult já no
   `routes/api.php`, e
2. têm adapter + projeção testável.

Ampliar registry se faltar superfície para DEFIS/CCMEI (submódulos ou
ações anexas a simples-mei) — sem reescrever portfolio inteiro.

### 6. Feature flag

Gate de produto: módulo de monitoramento já existente **ou** flag
explícita `FEATURE_MANUAL_CONSULT_EXPLORER_ENABLED` (default false) se
precisar de rollout isolado. Preferir reutilizar flags de módulo da
família da ação (ex. consulta DEFIS exige `simples_mei` enabled).

## Mapa de dependências

```
schema-conventions + catálogo SERPRO + código main
        │
        ▼
explorador-consultas-manuais-ui (C0)
        │
        ├── N0 inventário + elegibilidade + API
        ├── N1 despacho adapters + projeções
        ├── N2 UI explorador + CTAs módulos
        └── N3 gates testes
```

Ownership: capability `manual-consult-explorer` só desta change.

Coordenação (não bloqueante):

- `integrar-monitoramento-pgdasd` / `integrar-monitoramento-dctfweb`: não
  editar seus `openspec/changes/**`; se tocar controllers compartilhados,
  serializar PR/merge.
- `padronizar-autorizacao-multitenant`: consumir `CurrentOffice` e
  authorization service como estão no main; não alterar contrato de
  termo/token nesta change.

Rollout: flag/módulo OFF → inventário vazio ou 403; ON + capability
simulated → consult local; real só em ambiente com política operacional
já existente. Rollback: desligar flag/módulo; projeções antigas
permanecem tenant-scoped.

## Risks / Trade-offs

- [Superfície transversal grande] → limitar onda 1 a handlers existentes;
  inventário marca `adapter_missing` em vez de implementá-los todos.
- [Bilhetagem acidental] → POST + `confirmed: true` + testes que garantem
  GET sem SERPRO; capability simulated em CI.
- [Cross-tenant] → `client_id` resolvido no office atual; testes de
  negação.
- [Duplicar rotas vs unificar] → preferir thin façade sobre POSTs
  existentes; não apagar endpoints legados na onda 1.
- [Conflito com changes ativas no mesmo arquivo] → lista de paths
  compartilhados no PR; merge serial quando necessário.
- [Params oficiais incompletos] → schema mínimo fail-closed; erro
  sanitizado sem promover projeção.

## Migration Plan

1. Backend inventário + elegibilidade + façade de despacho + testes.
2. Ampliar registry/ações DEFIS/CCMEI se necessário para o inventário.
3. Frontend explorador + integração CTAs nos módulos prioritários.
4. Gates `php artisan test` (filtro ManualConsult/Fiscal) e
   `pnpm run test:gate` / testes unit do explorador.
5. Dev: flags de módulo ON + capabilities simulated; produção sem mudança
   de default.

## Open Questions

Nenhuma bloqueante. Decisão operacional de capability `real` por família
fica fora desta change (runbook de piloto).
