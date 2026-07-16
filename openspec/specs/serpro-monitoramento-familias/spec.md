# serpro-monitoramento-familias

## Purpose

Cobertura produtiva das famílias de monitoramento fiscal via adapters tipados, drivers explícitos, projeções isoladas por office e mutações fail-closed.

## Requirements

### Requirement: Cobertura produtiva por adapters tipados
O monitoramento SHALL mapear as 98 operações produtivas nas famílias autorização/eventos, SITFIS, Caixa Postal, DCTFWeb/MIT, Simples/MEI, parcelamentos, guias/pagamentos, Cadastro/Vínculos e e-Processo.

#### Scenario: Adapter executa operação
- **WHEN** um adapter de família solicitar dados externos
- **THEN** ele SHALL usar `operation_key`, codec tipado e gateway real da capacidade, sem caminho legado por coordenadas

### Requirement: Drivers explícitos sem fallback
Cada família MUST possuir driver `disabled|simulated|real`; produção MUST rejeitar `simulated` e uma falha real MUST NOT cair em fake.

#### Scenario: Família desabilitada
- **WHEN** o driver estiver `disabled`
- **THEN** o monitoramento SHALL informar capacidade indisponível sem fabricar snapshot produtivo

### Requirement: Isolamento e idempotência das projeções
Toda projeção fiscal SHALL conter `office_id`, `client_id`, identidade oficial e versão de evidência, com unicidade que impeça mistura ou duplicação entre offices.

#### Scenario: Mesmo CNPJ em dois offices
- **WHEN** dois offices monitorarem o mesmo CNPJ
- **THEN** jobs, locks, snapshots, achados, processos e consumo SHALL permanecer separados

### Requirement: Ledger e proveniência por chamada
Cada chamada SHALL registrar office, cliente, operação, rota, status, tag, latência, faturamento e proveniência sem armazenar segredo ou payload fiscal em log.

#### Scenario: Resultado simulado
- **WHEN** uma capacidade simulated for usada fora de produção
- **THEN** o resultado SHALL ser marcado como simulado e MUST NOT contar como evidência produtiva

### Requirement: Mutações fail-closed
Operações mutantes produtivas SHALL permanecer desligadas por padrão e exigir flag, allowlist, assinatura writable, ADMIN, TOTP recente, confirmação, elegibilidade, idempotência, orçamento, contrato saudável e kill switch aberto.

#### Scenario: Scheduler encontra mutação pendente
- **WHEN** um ciclo automático de monitoramento identificar uma ação mutante possível
- **THEN** ele MUST NOT criar nem executar a intenção mutante

#### Scenario: Gate ausente
- **WHEN** qualquer gate mutante estiver ausente
- **THEN** o transporte externo SHALL ser bloqueado antes da chamada

### Requirement: Execução assíncrona controlada
Refreshes e polling SHALL usar Horizon, locks por office/cliente/operação e reagendamento orientado por espera oficial.

#### Scenario: Resposta ainda processando
- **WHEN** o SERPRO retornar estado pendente
- **THEN** o job SHALL persistir o protocolo e reagendar sem duplicar a solicitação inicial
