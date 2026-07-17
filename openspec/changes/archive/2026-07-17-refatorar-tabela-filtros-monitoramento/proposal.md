## Why

A base compartilhada das carteiras fiscais mistura composição visual, estado de filtros, seleção e efeitos de domínio, permitindo filtros divergentes, chaves de linha duplicadas e seleção residual entre contextos. A refatoração consolida um único contrato de tabela e filtros sem alterar APIs, rotas ou regras fiscais.

## What Changes

- Introduzir um contrato tipado e controlado para busca, situação e campos avançados das listas do monitoramento.
- Manter busca e situação imediatas, enquanto os demais filtros usam rascunho aplicado atomicamente no painel recolhível existente.
- Separar a casca do módulo, a tabela server-side e as ações em massa em componentes compartilhados menores.
- Exigir chaves estáveis por entidade e limpar seleção ao mudar página, filtro, ordenação, rota ou Office, preservando IDs válidos em refresh manual.
- Migrar as carteiras de Simples/MEI, DCTFWeb/MIT, Parcelamentos, SITFIS, Declarações, FGTS, Guias, Cadastro/Vínculos e Processos para o mesmo contrato.
- Restringir ações em massa a módulos e capacidades realmente suportados.
- Preservar o arquétipo de lista do dashboard fixado, paginação e ordenação server-side, rotas canônicas e isolamento tenant-aware.
- Non-goals: alterar backend/endpoints, habilitar flags ou canais, executar mutações/live smoke SERPRO, tratar tickets externos ou questões jurídicas/LGPD.

## Capabilities

### New Capabilities

- `tabela-filtros-monitoramento`: comportamento compartilhado de filtros, tabela, seleção e ações contextuais das listas fiscais.

### Modified Capabilities

Nenhuma.

## Impact

- `frontend/app/components/monitoring/**`: composição da toolbar, tabela e ações em massa.
- `frontend/app/composables/useFiscalModulePortfolio.ts` e tipos fiscais: estado controlado e transações de filtro.
- `frontend/app/pages/monitoring/**`: migração dos call sites e chaves estáveis.
- `frontend/tests/unit/**` e configuração Vitest: testes comportamentais em ambiente Nuxt.
- Sem alteração de payloads Laravel, dependências de runtime ou contexto de Office; `office_id` continua proibido no client.
