# Baseline — complete-monitoring-visual-fixtures

**Data:** 2026-07-15  
**Change ativa:** `complete-monitoring-visual-fixtures`  
**Dependência de implementação:** `build-complete-fiscal-monitoring-hub`  
**Operador:** agente de implementação (tasks 1.1)

## Status da change hub

| Item | Estado |
|------|--------|
| Change | `build-complete-fiscal-monitoring-hub` (ativa em `openspec/changes/`) |
| Tasks | **153 / 153** concluídas (`- [x]`) |
| Artefatos de planejamento | `proposal`, `design`, `specs`, `tasks` completos |
| Implementação no monorepo | Presente (`backend/` APIs fiscais, `frontend/app/pages/monitoring/*`) |
| Sync para `openspec/specs/` (main) | **Pendente** — capabilities do hub **ainda não** estão no main |
| Archive | **Pendente** — change ainda em `openspec/changes/`, não em `archive/` |

Verificação operacional de backend do hub: [`fiscal-hub-verification-2026-07-15.md`](./fiscal-hub-verification-2026-07-15.md) (suite filtrada PASS).

### Capabilities do hub (delta) vs main specs

Specs da change hub (15):

| Capability (delta hub) | Em `openspec/specs/` (main)? |
|------------------------|------------------------------|
| `dctfweb-mit-monitoring` | Não |
| `fgts-esocial-monitoring` | Não |
| `fiscal-mailbox-monitoring` | Não |
| `fiscal-monitoring-core` | Não |
| `fiscal-situation-monitoring` | Não |
| `simples-mei-monitoring` | Não |
| `tax-declaration-monitoring` | Não |
| `tax-guide-management` | Não |
| `tax-installment-monitoring` | Não |
| `platform-tenant-governance` | Não |
| `serpro-api-usage-ledger` | Não |
| `serpro-integra-contador-access` | Não |
| `frontend-dashboard-experience` | **Sim** (pré-existente; hub delta modifica) |
| `office-access-control` | **Sim** (pré-existente; hub delta modifica) |
| `operations-dashboard` | **Sim** (pré-existente; hub delta modifica) |

**Consequência:** o código do hub está no repositório, mas a fonte de verdade OpenSpec main **não** reflete ainda as capabilities fiscais do hub. Sync/archive do hub é pré-requisito de governança de specs (não bloqueia apply de fixtures sobre o código existente).

## Sobreposições com `complete-monitoring-visual-fixtures`

| Superfície | Hub (implementado) | Esta change |
|------------|--------------------|-------------|
| APIs REST v1 fiscais (71+ rotas) | Criadas e cobertas por testes de domínio | **Congela contratos** (task 1.2); read model overview/carteira (seção 2) |
| Páginas `/monitoring/*` | Rotas Nuxt existentes (estados vazios / contratos parcialmente divergentes) | Completar UI + contratos tipados + fixtures |
| `frontend-dashboard-experience` | Shell + rotas de monitoramento esqueleto | Delta: densidade, filtros, detalhes, estados |
| `dashboard-template-fidelity` | Matriz genérica do shell | Matriz por rota de monitoramento (task 1.3) |
| Fixtures / seeder fiscal | Catálogo demo básico (poucos clientes, zero vínculos fiscais) | `FiscalMonitoringDemoSeeder` tenant `demo` |
| Feature flags | Flags hub OFF por default | Perfil local demo somente leitura |
| Plano de controle SERPRO | Contrato global, ledger, platform admin | **Não toca** — fixtures só no plano de dados do office demo |
| Isolamento `office_id` / `PLATFORM_ADMIN` | Middleware + testes platform | Reafirma em testes de APIs fiscais (task 1.5) |
| Rotas de Guias | `GET/POST /fiscal/guides*` | Task 1.4: remover duplicata de declaração sem mudar URLs |

### O que esta change **não** reabre do hub

- Modelo multi-escritório, cadeia Integra, SecureObjectStore, mutações com 2FA/preflight.
- Cursores ADN/SEFAZ/NSU e canais documentais existentes.
- Credenciais SERPRO globais ou sublicenciamento a tenants.
- Portal de contribuinte final.

## Ordem segura de sync / archive

Esta change **depende do hub implementado no código**. Em termos de OpenSpec:

```
1. Manter hub ACTIVE enquanto apply de fixtures está em andamento
   (código e testes do hub continuam a baseline de runtime).

2. Preferência A — hub primeiro (recomendada para limpar main specs):
   a. openspec validate build-complete-fiscal-monitoring-hub
   b. sync/archive do hub → promove deltas para openspec/specs/
   c. rebase mental dos deltas desta change sobre main já atualizado
   d. concluir apply + validate + sync/archive de complete-monitoring-visual-fixtures

3. Preferência B — paralelo com cuidado (aceitável se urgência de UI):
   a. apply de fixtures sobre o código atual (sem archive do hub)
   b. ao archive: hub ANTES ou no mesmo lote que fixtures
   c. se ambas as changes tocam frontend-dashboard-experience /
      dashboard-template-fidelity / office-access-control /
      operations-dashboard, mesclar deltas manualmente no archive
      (hub first, depois fixtures) para não perder ADDED/MODIFIED

4. Nunca:
   - arquivar fixtures antes do hub se isso sobrescrever main com
     apenas deltas de UI e omitir capabilities fiscais do hub;
   - assumir que main specs já contêm o hub (hoje não contêm);
   - reverter código do hub “só porque” a change ainda não foi arquivada.
```

### Checklist pré-archive (ambas)

- [ ] Suite fiscal hub verde (ver script/docs de verificação do hub)
- [ ] Tasks 1.x–10.x de fixtures concluídas e `openspec validate complete-monitoring-visual-fixtures --json` OK
- [ ] Main specs após archive contêm capabilities fiscais do hub **e** `fiscal-monitoring-demo-fixtures`
- [ ] Produção: seeder/demo flags permanecem inoperantes

## Referências no repositório

| Artefato | Caminho |
|----------|---------|
| Tasks hub | `openspec/changes/build-complete-fiscal-monitoring-hub/tasks.md` |
| Design hub | `openspec/changes/build-complete-fiscal-monitoring-hub/design.md` |
| Tasks fixtures | `openspec/changes/complete-monitoring-visual-fixtures/tasks.md` |
| Design fixtures | `openspec/changes/complete-monitoring-visual-fixtures/design.md` |
| Matriz fidelidade monitoramento | [`monitoring-template-fidelity-matrix.md`](./monitoring-template-fidelity-matrix.md) |
| Template fixado | `.reference/nuxt-dashboard-template` @ commit de referência do skill |
