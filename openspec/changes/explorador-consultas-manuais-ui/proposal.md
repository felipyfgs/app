## Why

O hub já tem dezenas de operações SERPRO `PRODUCTION` com `platform_support=IMPLEMENTED`,
adapters e endpoints de consulta, mas a capacidade de **disparar e ver o resultado na
UI** é irregular: algumas telas só recarregam projeção local, outras escondem o
histórico em modais sem CTA, e o operador não tem um mapa único do que já pode
consultar manualmente por cliente/módulo. Isso impede validar ponta a ponta o que
“já funciona” sem caçar rotas e flags.

## What Changes

- Introduzir um **explorador de consultas manuais** (somente leitura) no
  monitoramento: inventário por superfície/`operation_key`, elegibilidade
  (módulo, capability, token do autor, poder e-CAC) e ação explícita de consultar.
- Padronizar o padrão **“consulta bilhetável só com confirmação; UI lê projeção
  local”** em todos os módulos de monitoramento com ops de leitura já implementadas.
- Expor na UI, por cliente e por módulo, o estado da última consulta/projeção e o
  resultado sanitizado já persistido (sem abrir GET que chame SERPRO).
- Cobrir na primeira onda as famílias com backend pronto: PGDASD, DEFIS, CCMEI,
  PGMEI, REGIME, DCTFWEB/MIT (somente leitura), SITFIS, CAIXAPOSTAL/DTE,
  PAGTOWEB/SICALC apoio, parcelamentos (consultas), PNR vínculos, e-Processo
  (lista por interessado).
- Testes offline com fake/simulated; flags default OFF; sem mutações.

Não são objetivos desta change habilitar SERPRO live em produção, mutações
fiscais (transmitir, gerar DAS, encerrar MIT, aderir parcelamento), operações
PROSPECTION/CANCELED, parecer jurídico, nem reescrever o scheduler comercial.

## Capabilities

### New Capabilities

- `manual-consult-explorer`: inventário acionável e execução confirmada de
  consultas manuais somente-leitura do Integra Contador, com projeção local
  tenant-scoped e UI de monitoramento coerente.

### Modified Capabilities

- Nenhuma (main specs ainda só `schema-conventions`; o contrato novo nasce nesta
  change e será promovido no archive/sync).

## Impact

- Backend: inventário de ações manuais a partir de
  `MonitoringSurfaceRegistry` + catálogo oficial; gate de elegibilidade; rotas
  de consult/refresh já existentes reutilizadas ou unificadas sob contrato
  estável; projeções e histórico tenant-scoped; testes Feature com
  fake/simulated.
- Frontend: página/painel explorador + CTAs de consulta nos módulos
  (`/monitoring/**`); composable de inventário/execução; estados de
  capability/token/poder; testes Vitest de UI sem request SERPRO.
- Segurança: `CurrentOffice` obrigatório; sem `office_id` do client; sem
  tokens/XML/bytes do cofre no JSON; mutações bloqueadas; bilhetagem só em
  POST confirmado.

### Dependências entre changes

- Nível: `C0`.
- Bases estáveis: `schema-conventions`; catálogo
  `official-service-catalog.v2026-07-16.json`; adapters e superfícies de
  monitoramento já no código main.
- Depende de: `nenhuma` change ativa como bloqueante. Relação **coordenada**
  (não bloqueante) com superfícies ainda em apply
  (`integrar-monitoramento-pgdasd`, `integrar-monitoramento-dctfweb`,
  `padronizar-autorizacao-multitenant`): esta change não edita artefatos
  OpenSpec delas; em arquivos de código compartilhados, merges devem ser
  serializados se houver sobreposição.
- Marco exigido de bases: código main + catálogo (não `apply` de change
  pendente).
- Desbloqueia: validação operacional “consulta manual → projeção na UI” por
  família de leitura, e ondas futuras de mutações ou ops ainda sem adapter.
- Paralelismo: pode avançar em paralelo com changes que não alterem o
  `MonitoringSurfaceRegistry`, portfolio de módulo ou os mesmos controllers
  de consult; conflitos de ownership em um arquivo = serializar.
