## Why

O orquestrador MEI já oferece isolamento, HMAC, jobs e fallback, mas o provider portal ainda executa apenas uma fixture. Precisamos automatizar os serviços públicos de consulta e emissão do PGMEI e da DASN-SIMEI para reduzir chamadas SERPRO sem enfraquecer evidência, idempotência ou segurança fiscal.

## What Changes

- Implementa no microserviço os fluxos `pgmei.gerardaspdf`, `pgmei.gerardascodbarra`, `pgmei.dividaativa` e `dasnsimei.consultimadecrec` com Playwright, parsers versionados e fixtures sanitizadas.
- Diferencia consulta resumida DASN-SIMEI de declaração/recibo integral e impede promoção de cobertura parcial para total.
- Valida downloads de PDF e código de barras, registra metadados seguros e entrega artefatos efêmeros para ingestão no `SecureObjectStore` pelo Laravel.
- Ativa o `ReceitaPortalProvider` somente por flags e allowlist, preservando Portal -> SERPRO apenas para falhas classificadas anteriores à submissão.
- Expõe no Laravel e no Nuxt consulta de automação, histórico DASN-SIMEI e emissão de DAS por competência, sem comunicação direta Nuxt-Python.
- Mantém live egress, smoke real e captcha externo OFF por padrão.
- Não implementa benefício, transmissão de declaração, DAS de excesso, CCMEI/Gov.br, sessão humana remota ou qualquer mutação da C2.

## Capabilities

### New Capabilities
- `mei-public-portal-services`: Navegação pública PGMEI/DASN-SIMEI, parsers, downloads, classificação de drift/captcha e contrato de resultado dos quatro serviços C1.

### Modified Capabilities
- `simples-mei-monitoring`: Consumo tenant-scoped dos resultados públicos, emissão de DAS por competência, histórico DASN-SIMEI, proveniência e fallback sem cobrança SERPRO quando o portal vence.

## Impact

- `services/mei/`: executores Playwright por operação, páginas locais de contrato, parsers e artefatos.
- `apps/api/`: provider portal live controlado, endpoints/requests de consulta e emissão, projeções/evidências e testes de ledger/fallback.
- `apps/web/`: aba DASN-SIMEI, emissão de DAS, progresso, artefatos e badges de proveniência.
- `docker-compose.yml` e `compose.prod.yml`: permanecem internos; não há nova porta pública nem credencial de banco/vault no Python.

### Dependências entre changes

- Nível: `C1`.
- Bases estáveis: monitoramento fiscal, filas Horizon, `SecureObjectStore`, catálogo SERPRO e Nuxt SPA.
- Depende de: `adicionar-orquestrador-portal-mei`.
- Capability/contrato: consome HMAC/jobs/artefatos de `mei-automation-orchestrator` e provider router/tentativas de `simples-mei-monitoring`.
- Marco exigido: `verify`; relação bloqueante.
- Desbloqueia: `habilitar-operacoes-assistidas-e-mutantes-mei` após `verify`.
- Paralelismo: parsers Python e contratos de apresentação Nuxt podem avançar em paralelo; integração Laravel depende dos contratos Python e do provider router C0.
