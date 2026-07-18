## Why

A operação produtiva `pagtoweb.comparrecadacao` (`COMPARRECADACAO72`) constava no ledger como implementada, mas o código atual só a inventaria: não há contrato de domínio, tratamento seguro do PDF, API tenant-scoped nem fluxo de negócio no painel. Isso impede o escritório de obter comprovantes de arrecadação e cria uma divergência entre o estado documentado e o produto.

## What Changes

- Implementar a consulta manual confirmada do comprovante de arrecadação PAGTOWEB 7.2, respeitando a rota oficial `Emitir`, o poder exigido, a bilhetagem e as pré-condições documentais do catálogo.
- Persistir exclusivamente metadados sanitizados no banco e os bytes PDF no `SecureObjectStore`, com projeção idempotente, download same-origin autorizado e auditoria.
- Expor histórico local, solicitação explícita e download no monitoramento de guias, sem consulta em GET, montagem de componente ou abertura de modal.
- Corrigir o ledger para refletir a implementação real e preservar `BLOCKED` até Trial/canário autorizado, sem classificar mocks como evidência real.

Não inclui transmissão fiscal, emissão/alteração de DARF, qualquer chamada SERPRO real, habilitação de capability, mutação de dados fiscais, outbound ou alteração de procurações.

## Capabilities

### New Capabilities

- `pagtoweb-arrecadacao-receipt`: obtenção manual, armazenamento seguro, histórico e download autorizado de comprovantes de arrecadação PAGTOWEB 7.2.

### Modified Capabilities

- Nenhuma.

## Impact

- Backend: catálogo/resolver da operação, DTO/codec/adapter PAGTOWEB, projeção no cofre, controller/API tenant-scoped e testes Laravel.
- Frontend: tipos, composable/API, painel de guias e testes Nuxt seguindo `panel-ui` e `ui-archetype`.
- Operação: linha `pagtoweb.comparrecadacao` do ledger e evidência sanitizada; capability permanece default OFF e o canário continua dependente de autorização explícita.

### Dependências entre changes

- Nível: `C0`.
- Bases estáveis: `exibir-pagamentos-pagtoweb-71` e `cobrir-contagem-pagamentos-pagtoweb-73`, ambas concluídas, fornecem padrões de PagtoWeb, tenancy e confirmação.
- Depende de: nenhuma change ativa.
- Capability/contrato: consome apenas os contratos estáveis de guias e `SecureObjectStore`; marco exigido: `archive`; relação: coordenada.
- Desbloqueia: cobertura local verificável de `pagtoweb.comparrecadacao` e posterior Trial/canário autorizado.
- Paralelismo: pode avançar em paralelo com changes sem tocar nos mesmos contratos de guias, catálogo ou ledger; alterações nesses arquivos serão serializadas pelo coordenador.
