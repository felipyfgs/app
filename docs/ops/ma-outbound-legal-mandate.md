# Parecer fiscal/jurídico e mandato do cliente — canal MA outbound

**Change:** `build-ma-outbound-nfe-nfce-capture` · task 1.3  
**Atualizado:** 2026-07-15

## Separação de operações

| Classe | Operações | Gate de produção |
|--------|-----------|------------------|
| **Somente leitura** | Ingestão de pacote oficial, consulta de protocolo (`NFeConsultaProtocolo` / cStat 562), listagem de lacunas | Feature flags + allowlist + mandato de consulta |
| **Mutantes** | Inutilização, transmissão de sonda 539, cancelamento emergencial | Parecer + mandato específico + ADMIN+2FA + série/período fechados + `SEFAZ_MA_MUTATING_PROBE_ENABLED` |

## Status do parecer

| Item | Status |
|------|--------|
| Parecer fiscal/jurídico para **consulta read-only** | **Pendente** — registrar referência externa quando disponível; código aceita referência de mandato no perfil |
| Parecer para **inutilização / 539 / cancelamento** | **Pendente** — produção mutante permanece **desabilitada** (`G5` no-go até parecer) |
| Modelo de mandato no cadastro | Campo `mandate_reference` + `consent_recorded_at` em `outbound_capture_profiles` |

## Mandato do cliente (modelo operacional)

Antes de ativar captura read-only em produção, o escritório deve registrar:

1. Identificação do cliente (raiz/CNPJ).
2. Escopo: consulta de situação e download oficial de saídas NF-e/NFC-e MA.
3. Exclusões explícitas: emissão comercial, mutação fiscal sem novo mandato.
4. Referência documental (contrato, e-mail, ata) em `mandate_reference` — **sem** anexar PFX/CSC.
5. Data de registro e usuário ADMIN com 2FA recente.

## Decisão

- Caminho **read-only** pode ser implementado e testado com fixtures; produção exige mandato + allowlist.
- Caminho **mutante** permanece com flag off e gates cumulativos no código (`MutatingProbeGateEvaluator`).
