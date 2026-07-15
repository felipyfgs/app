# Template de aceite operacional — piloto CT-e

**Change:** `complete-cte-capture-with-distdfe-autxml-and-import` · task **16.9**  
**Atualizado:** 2026-07-15  
**Status do aceite:** **NÃO ASSINADO** (template)

## Propósito

Acordo entre **software house (plataforma)** e **escritório contábil piloto** sobre cobertura real de CT-e (modelo 57), canais oficiais, limites de janela e procedimento de pendências.

> Preencher uma cópia por piloto (ticket/GDrive). **Não** colar PFX, senhas, tokens de integração, XML fiscal ou CNPJ completos de produção neste repositório.

## 1. Identificação

| Campo | Valor |
|-------|--------|
| Nome do escritório (Office) | |
| `office_id` interno | |
| CNPJ do escritório (máscara) | |
| Ambiente | HOMOLOGATION / PRODUCTION (piloto) |
| Período do piloto | de ____/____/____ a ____/____/____ |
| Responsável escritório (nome/e-mail) | |
| Responsável plataforma (nome/e-mail) | |
| Ticket / ID do aceite | |

## 2. Matriz de cobertura acordada

Marcar o que entra no piloto e o canal esperado:

| Perfil de cliente | Incluído? | Canal primário | Complementar | Notas |
|-------------------|-----------|----------------|--------------|-------|
| Não transportador (rem/dest/exped/receb/toma) | [ ] | `CTE_DISTDFE` (A1 cliente) | — | captura automática dos 5 papéis |
| Transportadora com autXML do escritório | [ ] | `CTE_AUTXML_DISTDFE` | Import original se redigido | ERP inclui CNPJ office em autXML **antes** de autorizar |
| Transportadora sem autXML | [ ] | Import XML/ZIP e/ou `EMITTER_PUSH` | — | cobertura `PENDING_IMPORT` até ingestão |
| CT-e OS modelo 67 / outros | [ ] Fora | — | preservar sem projeção 57 | non-goal de projeção completa |

### 2.1 Lista de raízes / clientes (máscaras)

| # | CNPJ (máscara) | Apelido | Perfil (2.A/B/C) | A1 custodiado? | Ownership DistDFe ok? |
|---|----------------|---------|------------------|----------------|------------------------|
| 1 | | | | [ ] | [ ] |
| 2 | | | | [ ] | [ ] |
| 3 | | | | [ ] | [ ] |

Máximo recomendado no primeiro piloto: **1–5** raízes no stream cliente e **1** office no stream autXML.

### 2.2 Explicitamente fora do piloto

- [x] Portal ou login do contribuinte final  
- [x] Scraping / Gov.br / CAPTCHA / automação de portal SEFAZ  
- [x] Emissão, cancelamento ou inutilização de CT-e pelo painel  
- [x] Consulta por chave (`consChCTe`) ou varredura via `consNSU`  
- [x] Promessa de histórico completo além da janela de disponibilidade (~90 dias / 3 meses no AN)  
- [x] Reconstrução de referências `999…` no XML redigido  
- [x] Sublicença de PFX, tokens ou material de cofre ao tenant de forma exportável  

## 3. Limites e honestidade de produto

O escritório declara ciência de que:

- [ ] Os cinco papéis de interesse (não emitente) são capturados pelo DistDFe do **cliente**  
- [ ] O **emitente** exige autXML do escritório, import ou push — DistDFe do próprio CNPJ **não** é canal de saída  
- [ ] ~90 dias / 3 meses é **janela de disponibilidade**, não arquivo histórico completo  
- [ ] Cópia `AUTXML_REDACTED` pode ter chaves referenciadas como 44×`9`; original pode ser solicitado ao emissor  
- [ ] Um único consumidor de `distNSU` por CNPJ-base/ambiente; risco de cStat **656** se houver concorrência  
- [ ] Feature flags podem ser desligadas pela plataforma se gates fiscais/crypto/ops falharem  

## 4. Procedimento de pendências (acordado)

| Estado / sintoma | Procedimento do escritório | Papel mínimo |
|------------------|----------------------------|--------------|
| `PENDING_IMPORT` (emitente sem autXML/gap) | Solicitar XML ao ERP/emissor → Import ou push | OPERATOR |
| `AUTXML_REDACTED` insuficiente | Import do original do emitente | OPERATOR |
| `HISTORICAL_GAP` (pré-ativação) | Import histórico; não esperar DistDFe retroativo | OPERATOR |
| `BLOCKED` / 656 / circuito | Não forçar retry; acionar plataforma + revisar ownership | ADMIN + ops |
| 593 certificado | Renovar/corrigir A1 no vault (sem export) | ADMIN + 2FA |
| Quarentena (emit desconhecido, bytes divergentes, evento órfão) | Resolver via UI/API auditada; não aceitar conflito cego | ADMIN |
| Fila 137 | Aguardar quiet; sem martelo manual | — |

Detalhe: `docs/ops/cte-coverage-and-channels-runbook.md`.

## 5. Segurança

O escritório declara que:

- [ ] Possui mandato dos clientes para custódia de A1 e captura de documentos fiscais no escopo  
- [ ] Não solicitará export de PFX, senha, PEM ou token de integração em plain após a emissão  
- [ ] Tokens `EMITTER_PUSH` serão armazenados no cofre do ERP e rotacionados/revogados se vazarem  
- [ ] Incidentes de acesso indevido serão reportados em até 24h úteis  

A plataforma declara que:

- [ ] Dados sob `office_id` do escritório; sem misturar tenants  
- [ ] Logs/métricas sem XML/PFX/tokens  
- [ ] Kill switch e rollback preservam cursores e documentos  
- [ ] Smoke e ativação seguem `cte-prod-smoke-runbook.md` / `cte-rollout-allowlist.md`  

## 6. Critérios de aceite operacional

| # | Critério | Pass/Fail | Evidência (sanitizada) |
|---|----------|-----------|------------------------|
| A1 | Smoke stream cliente (15.2–15.4) ou N/A justificado | | |
| A2 | Smoke stream autXML (15.5–15.7) ou N/A justificado | | |
| A3 | Fallback import/push (15.8) se houver emitente sem autXML | | |
| A4 | Matriz de cobertura (§2) refletida no painel/API | | |
| A5 | Procedimento de pendências treinado (§4) | | |
| A6 | Zero vazamento de segredo em amostragem de log | | |
| A7 | Flags/allowlist alinhadas ao escopo; sem GA acidental | | |
| A8 | Ao menos um ciclo de monitoramento pós-ativação (ver 16.7) | | |

## 7. Rollback e saída

- Remoção da allowlist / flags false / kill switch encerra novas consultas.  
- Documentos e cursores **não** são apagados no rollback.  
- Escritório pode solicitar export **permitido** (XML de catálogo conforme política de qualidade) e encerramento do piloto.  

## 8. Assinaturas

| Parte | Nome | Data | Assinatura / OK formal |
|-------|------|------|------------------------|
| Escritório | | | **PENDENTE** |
| Plataforma | | | **PENDENTE** |

---

## Estado atual (repositório)

| Campo | Valor |
|-------|--------|
| Aceite assinado? | **Não** |
| Motivo | Template criado; execução humana do piloto pendente |
| Próximo passo | Completar smoke 15.2–15.9 e preencher cópia do aceite no ticket |
