## Why

As consultas MEI dependem hoje do SERPRO como transporte exclusivo, o que aumenta o custo por operação e impede contingência por portais oficiais. Precisamos de um orquestrador interno que permita escolher fontes por operação sem deslocar autenticação, tenancy, evidências ou auditoria para fora do Laravel.

## What Changes

- Adiciona um microserviço Python interno para executar jobs de navegador isolados, assinar o contrato Laravel-Python e manter resultados efêmeros no Redis.
- Adiciona persistência tenant-scoped das tentativas de automação, proveniência `RECEITA_PORTAL` e metadados de verificação do artefato.
- Introduz roteamento de providers para `INTEGRA_MEI`, preservando o provider SERPRO atual e deixando o provider portal desabilitado por padrão.
- Integra os serviços ao Docker Compose local e de produção sem expor porta pública.
- Não habilita portal live, captcha pago, sessão Gov.br, mutações fiscais nem altera cobrança comercial nesta change.

## Capabilities

### New Capabilities
- `mei-automation-orchestrator`: Contrato interno, segurança HMAC, ciclo de jobs, isolamento do navegador, persistência de tentativas e operação Docker do microserviço.
- `simples-mei-monitoring`: Seleção de provider, proveniência e fallback seguro para as operações `INTEGRA_MEI` existentes.

### Modified Capabilities

Nenhuma capability principal existente está versionada no repositório.

## Impact

- Novo serviço em `services/mei/`, novos containers internos e variáveis de ambiente com defaults OFF.
- Backend Laravel recebe client HTTP assinado, modelos/migrações de tentativas e provider router para MEI.
- O Nuxt permanece a única interface de usuário e não se comunica diretamente com o microserviço.

### Dependências entre changes

- Nível: `C0`.
- Bases estáveis: núcleo de monitoramento fiscal, `FiscalSourceAdapter`, filas Redis/Horizon, `SecureObjectStore` e catálogo SERPRO local.
- Depende de: nenhuma change ativa.
- Capability/contrato: cria `mei-automation-orchestrator` e `simples-mei-monitoring`.
- Marco exigido: nenhum upstream; relação coordenada com os contratos existentes.
- Desbloqueia: `automatizar-servicos-publicos-mei` após `verify`.
- Paralelismo: artefatos Python e contrato Laravel podem avançar em paralelo depois da especificação; registro de provider depende dos dois.
