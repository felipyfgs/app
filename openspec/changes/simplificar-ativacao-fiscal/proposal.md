## Why

A ativação fiscal está espalhada entre flags extensas, allowlists, capabilities e controles manuais, o que dificulta desenvolver, operar e trocar com segurança entre ambientes. Precisamos de uma regra única — módulos consultivos disponíveis por padrão e restrições por exceção — com onboarding automático e contenção central das operações perigosas.

## What Changes

- Introduzir `FISCAL_PROFILE=dev|trial|production` e `FISCAL_KILL_SWITCH`, com política central por classe de operação e bloqueio permanente de mutações fiscais nesta fase.
- Criar controles provider-neutral persistidos para restrições globais e por escritório, administrados exclusivamente pela plataforma e auditados.
- Disponibilizar todos os módulos consultivos após o onboarding, sem ativação por escritório, preservando os dados existentes durante restrições.
- Unificar consultas manuais, scheduler e jobs no mesmo resolvedor de disponibilidade, inclusive com revalidação ao iniciar jobs e coleta de recuperação após liberação.
- Criar APIs e UI da plataforma para visualizar estados, restringir, liberar e sincronizar módulos globalmente ou por escritório.
- Automatizar o onboarding do A1 do escritório, Termo, token do procurador, sincronização por cliente de `PROCURACOES/OBTERPROCURACAO41` e primeira coleta.
- Corrigir o contrato local de procurações, persistir validade/evidência, automatizar expiração e alertas de certificado/procuração.
- **BREAKING**: descontinuar as flags fiscais antigas durante uma versão e removê-las dos exemplos de ambiente ao concluir a transição.
- Não inclui mutações fiscais, transmissão/adesão, parecer jurídico, chamadas SERPRO live em testes, webhooks inexistentes, outbound de alertas nem liberação implícita de dados fiscais a administradores da plataforma.

## Capabilities

### New Capabilities

- `fiscal-module-governance`: disponibilidade por perfil, política de operações, kill switch, restrições globais/por escritório, APIs/UI administrativas, auditoria e integração com consultas, jobs e scheduler.
- `fiscal-office-readiness`: onboarding automático do escritório, certificado/Termo/token, sincronização e validade de procurações por cliente, coleta inicial e agenda mensal.

### Modified Capabilities


## Impact

- Backend Laravel: configurações fiscais/SERPRO, catálogo de operações, modelos e migrations, resolvedores, jobs, schedulers, onboarding, procurações, auditoria e rotas/controllers de plataforma e escritório.
- Frontend Nuxt: client da API, navegação administrativa, página “Módulos fiscais”, status de onboarding e estados de restrição sem ocultar dados históricos.
- Banco de dados: nova tabela `fiscal_module_controls` e eventuais campos/índices de evidência e validade nos modelos fiscais existentes.
- Operação: migração gradual das flags antigas para os dois controles de ambiente; nenhuma credencial, PFX, token ou payload fiscal será exposto em JSON ou logs.

### Dependências entre changes

- Nível: `C0`.
- Bases estáveis: código atual de integração SERPRO, vault, tenancy, auditoria, onboarding e procurações; changes concluídas não integram o DAG ativo.
- Depende de: nenhuma.
- Capability/contrato consumido: contratos atuais de integração fiscal e identidade de `Office`/`CurrentOffice`.
- Marco exigido: nenhum upstream ativo.
- Relação: coordenada com a base estável, sem dependência bloqueante.
- Desbloqueia: futura habilitação controlada de geração documental ou mutações fiscais, após uma change específica e análise de risco.
- Paralelismo: somente trabalhos sem sobreposição em políticas fiscais, rotas de plataforma, onboarding/procurações ou UI de módulos podem avançar em paralelo.

Esta change é transversal porque a mesma decisão de disponibilidade precisa proteger, de forma atômica, API manual, fila, scheduler, onboarding e UI; dividi-la antes do resolvedor central criaria períodos em que caminhos diferentes aplicariam políticas contraditórias.
