# Checklist de aprovação — guias assistidas e operações mutantes

**Change:** `build-complete-fiscal-monitoring-hub` · task **16.10**  
**Atualizado:** 2026-07-15  
**Default de produto:** **TODAS as mutações OFF**

## Princípio

Consulta/monitoramento RO estável **precede** qualquer mutação. Cada operação mutante (e o fluxo de **guias assistidas/emissão**) exige **aprovação separada**, allowlist própria e ensaio em ambiente controlado.

## Definições

| Tipo | Exemplos | Risco |
|------|----------|-------|
| RO | Consultas Sitfis, listagens, downloads de relatório | Médio (sigilo/custo) |
| Guia assistida | Geração/emissão de guia de pagamento (SICALC etc.) | Alto (financeiro) |
| Mutante fiscal | Transmitir PGDASD, transmitir DCTFWeb, encerrar MIT, aderir parcelamento | Crítico (estado fiscal) |

## Gates obrigatórios antes de qualquer mutante

- [ ] Piloto RO (C1) estável ≥ duração mínima (16.9)  
- [ ] 16.6 smoke RO PASS  
- [ ] 16.7 sem divergência material aberta  
- [ ] 16.8 drills D4 (kill mutações) e D2 PASS  
- [ ] Aceite do office piloto **específico para mutação** (anexo ao 16.5)  
- [ ] Procurações com poderes **explicitamente** suficientes  
- [ ] Catálogo marca operação como mutante  
- [ ] Idempotência + estado incerto implementados e testados  
- [ ] Preflight + confirmação reforçada + TOTP recente  
- [ ] Orçamento e custo estimado exibidos ao operador  

## Flags (camadas — todas precisam alinhar)

```env
FEATURES_GLOBAL_ENABLED=true
FEATURES_KILL_SWITCH=false
FEATURES_MUTATING_ENABLED=true
FEATURES_MUTATING_KILL_SWITCH=false

FISCAL_MUTATIONS_ENABLED=true
FISCAL_MUTATIONS_KILL_SWITCH=false

# Por módulo
FEATURE_GUIAS_MUTATING_ENABLED=true
FEATURE_GUIAS_OFFICE_ALLOWLIST=<office_piloto>

# Por operação (exemplos em config/fiscal_mutations.php)
FEATURE_MUT_SICALC_EMITIR_GUIA_ENABLED=true
FEATURE_MUT_SICALC_EMITIR_GUIA_OFFICE_ALLOWLIST=<office_piloto>
FEATURE_MUT_PGDASD_TRANSMITIR_ENABLED=false
# ...
```

Camadas extras: `FEATURE_MUTACOES_*`, `FISCAL_MONITORING_MUTATING_ENABLED` quando o job de monitoramento puder disparar efeitos colaterais.

## Checklist por operação

Copiar uma seção por `system.operation` (ex.: `INTEGRA_PAGAMENTO.SICALC.EMITIR_GUIA`).

### Identificação

| Campo | Valor |
|-------|--------|
| Código operação | |
| Sistema Integra | |
| Módulo produto | guias / simples_mei / dctfweb_mit / parcelamentos / … |
| Office allowlist inicial | |
| Ambiente | HOMOLOGATION / PRODUCTION |
| Solicitante | |
| Aprovador produto | |
| Aprovador ops | |
| Aprovador jurídico/compliance (se aplicável) | |

### Engenharia

- [ ] Testes Feature/Unit da operação (feliz + timeout + replay + cross-tenant)  
- [ ] Idempotency key estável e tenant-aware  
- [ ] Em timeout: estado `UNCERTAIN` / reconciliação **antes** de retry  
- [ ] Anti-repeat window (`FISCAL_MUTATIONS_ANTI_REPEAT_SECONDS`)  
- [ ] Preflight TTL e desafio TOTP (`FISCAL_MUTATIONS_TOTP_WINDOW_MINUTES`)  
- [ ] Audit de preflight, submit, resultado, falha  
- [ ] Nenhum segredo em log  
- [ ] Ledger registra tentativa mesmo em falha possivelmente faturável  

### Operação / negócio

- [ ] Roteiro de suporte (o que dizer se ficar incerto)  
- [ ] Limite de emissões/dia no piloto  
- [ ] Contribuintes nomeados no aceite  
- [ ] Comunicação: “ação irreversível / efeito fiscal”  
- [ ] Critério de rollback de **flag** (não desfaz efeito na RFB/SERPRO)  

### Ensaio

- [ ] 1 operação em homologação com contribuinte de teste  
- [ ] Simular timeout e provar que não duplica  
- [ ] Simular kill switch no meio do fluxo  
- [ ] Provar que office fora da allowlist é bloqueado  
- [ ] Provar VIEWER não muta  

### Decisão

| Resultado | ☐ GO · ☐ NO-GO · ☐ GO com ressalvas |
|-----------|--------------------------------------|
| Data | |
| Ressalvas | |
| Data de reavaliação | |

## Ordem recomendada de liberação mutante

1. **Guias assistidas** (emissão) — valor alto, blast radius financeiro local  
2. Transmissões de declaração com recibo verificável  
3. Adesões / encerramentos (maior irreversibilidade)

Nunca liberar “todas as mutações” com um único flag em GA.

## Rollback

1. `FEATURES_MUTATING_KILL_SWITCH=true` **ou** `FISCAL_MUTATIONS_KILL_SWITCH=true`  
2. Desligar flags da operação  
3. **Não** reenviar automaticamente operações `UNCERTAIN`  
4. Reconciliar no portal/API oficial antes de qualquer retry manual  
5. Preservar evidências e ledger  

## Registro mestre (piloto)

| Operação | GO? | Data | Offices | Notas |
|----------|-----|------|---------|-------|
| SICALC EMITIR_GUIA | NO-GO default | | | |
| PGDASD TRANSMITIR | NO-GO default | | | |
| DCTFWEB TRANSMITIR | NO-GO default | | | |
| MIT ENCERRAR | NO-GO default | | | |
| PARCELAMENTO ADERIR | NO-GO default | | | |
