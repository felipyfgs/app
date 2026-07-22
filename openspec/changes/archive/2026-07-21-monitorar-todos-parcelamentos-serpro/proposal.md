## Why

O módulo de Parcelamentos já possui uma superfície e projeções preliminares, mas o monitoramento não interpreta o contrato oficial do Integra Contador: usa chaves divergentes da SERPRO, mistura parcelas de pedidos distintos e não possui cobertura dedicada de API/UI. Isso impede afirmar que todas as modalidades produtivas estão realmente monitoradas.

## What Changes

- Monitorar de ponta a ponta as oito modalidades de parcelamento em produção no catálogo SERPRO: `PARCSN`, `PARCSN-ESP`, `PERTSN`, `RELPSN`, `PARCMEI`, `PARCMEI-ESP`, `PERTMEI` e `RELPMEI`.
- Normalizar os cinco serviços oficiais de cada modalidade (`PEDIDOSPARC`, `OBTERPARC`, `PARCELASPARAGERAR`, `DETPAGTOPARC` e `GERARDAS`) por `operation_key`, preservando cada pedido, parcela e pagamento em seu vínculo correto.
- Expor catálogo, execução manual, carteira consolidada e detalhe local de pedidos/parcelas no backend com isolamento por `Office`, validação de modalidade e comportamento fail-closed.
- Completar `/monitoring/installments` no padrão Nuxt UI do painel, com agrupamento por regime/modalidade, situações honestas, consulta das modalidades suportadas, resumo e detalhe de pedidos/parcelas/pagamentos.
- Exibir `PARC-PAEX` e `PARC-SIPADE` como modalidades catalogadas em prospecção/indisponíveis, sem permitir chamada, pois a documentação oficial ainda não fornece contrato executável.
- Acrescentar testes unitários e Feature no Laravel, testes Vitest/fidelity no Nuxt e cenários de contrato que comprovem todas as modalidades e os bloqueios de operações não produtivas.

Non-goals: habilitar egress SERPRO live ou flags de produção; implementar adesão, reparcelamento, desistência ou outra mutação fiscal; promover `PARC-PAEX`/`PARC-SIPADE` antes de a SERPRO publicar contratos produtivos; emitir parecer jurídico; criar `mei`/`mei-worker` no Compose; usar targets de backup/restore indisponíveis.

## Capabilities

### New Capabilities

- `serpro-installments-monitoring`: contrato completo de monitoramento, persistência, APIs e UI para todas as modalidades de parcelamento suportadas pelo catálogo SERPRO, incluindo visibilidade fail-closed das modalidades ainda em prospecção.

### Modified Capabilities

- Nenhuma. A implementação continuará respeitando os contratos existentes de carteira, URL canônica, ordenação real e download autenticado.

## Impact

- API Laravel: domínio `Services/Integra/Parcelamento`, executor SERPRO central, projeções/modelos de parcelamentos, catálogo/fixtures, endpoints e carteira `installments`.
- Web Nuxt: `/monitoring/installments`, client tipado, tipos/utilitários e componentes aderentes ao shell existente.
- Testes: PHPUnit Unit/Feature e Vitest/fidelity/artifacts da superfície de Parcelamentos.
- Integração externa: somente operações oficiais do Integra Contador marcadas como produtivas/executáveis; nenhuma credencial ou chamada live será habilitada.

### Dependências entre changes

- Nível: `C0`.
- Bases estáveis: catálogo SERPRO versionado, executor `SerproOperationExecutor`, main specs de monitoramento e shell do painel.
- Depende de: nenhuma change ativa; capability/contrato `serpro-installments-monitoring`; marco exigido `specs`; relação `coordenada`.
- Desbloqueia: monitoramento operacional e futuras emissões assistidas de guias de parcelamento.
- Paralelismo: pode avançar em paralelo com changes de Caixa Postal e SITFIS, desde que não altere os mesmos inventários de superfície; conflitos em `AppServiceProvider`, rotas e artefatos gerados serão mesclados preservando o trabalho existente.
