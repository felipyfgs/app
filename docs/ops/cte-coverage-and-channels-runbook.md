# Runbook operacional — cobertura e canais CT-e

**Change:** `complete-cte-capture-with-distdfe-autxml-and-import` · tasks **16.1–16.6**  
**Atualizado:** 2026-07-15  
**Complementa:** `docs/ops/cte-prod-smoke-runbook.md`, `docs/ops/cte-schema-compatibility-matrix.md`, `docs/ops/document-coverage-matrix.md`

Documento de operação contínua do escritório contábil (tenant). **Não** é portal do contribuinte final.

## 1. Checklist por perfil de cliente (16.1)

### 1.A — Clientes **não transportadores** (participam do frete, não emitem CT-e)

Papéis típicos: remetente, destinatário, expedidor, recebedor, tomador.

- [ ] Estabelecimento + e-CNPJ A1 da **raiz** do cliente no vault
- [ ] Canal `CTE_DISTDFE` elegível após smoke (`SEFAZ_CTE_ENABLED` + política de allowlist)
- [ ] Ownership único de DistDFe **da raiz do cliente** (sem segundo capturador no mesmo A1/base)
- [ ] Expectativa: captura **automática** dos CT-e em que o CNPJ completo aparece em rem/dest/exped/receb/toma
- [ ] **Não** esperar CT-e de **saída** (emitente) neste canal
- [ ] Cobertura: estados `CAPTURED_ORIGINAL` / `NO_ACTIVITY` / `BLOCKED` / `HISTORICAL_GAP` conforme período

### 1.B — Transportadoras (**emitentes**) com `autXML` do escritório

- [ ] Identidade fiscal do **escritório** + A1 office (finalidade autXML)
- [ ] ERP do emitente inclui o CNPJ completo do escritório em `<autXML>` **antes** de autorizar
- [ ] Sem retroatividade: CT-e já autorizados sem autXML **não** voltam pelo stream do office
- [ ] Canal `CTE_AUTXML_DISTDFE` com allowlist do office; flag default off até piloto
- [ ] Ownership único do `distNSU` do **CNPJ-base do escritório**
- [ ] Qualidade pode ser `AUTXML_ORIGINAL` ou `AUTXML_REDACTED` (ver §5)
- [ ] Preferir import do original quando o cliente precisar de referências integrais

### 1.C — Transportadoras que **dependem** de import / push (sem autXML ou gap)

- [ ] Marcar expectativa `PENDING_IMPORT` no período em que a cobertura automática não existe
- [ ] Procedimento: **Import XML/ZIP** (Documentos / lote) e/ou **EMITTER_PUSH** (§6)
- [ ] Associar pelo `emit/CNPJ` completo no mesmo `office_id`
- [ ] Após import válido: interesse `ISSUER/OUT`, qualidade `ORIGINAL` se assinatura ok
- [ ] Encerrar pendência sem apagar proveniência anterior (ex.: redigido + original)

### Matriz resumida

| Perfil | Canal primário | Complementar | Saída (ISSUER/OUT)? |
|--------|----------------|--------------|---------------------|
| Não transportador (5 papéis) | `CTE_DISTDFE` (cliente) | — | Não (não é emitente) |
| Emitente + autXML | `CTE_AUTXML_DISTDFE` (office) | Import original | Sim |
| Emitente sem autXML | Import / `EMITTER_PUSH` | — | Sim, após ingestão |
| Emitente no DistDFe do **próprio** CNPJ | **Não suportado** (contrato AN) | quarentena se aparecer | Não promover como OUT |

---

## 2. Papéis e automação (16.2)

| Papel no CT-e (modelo 57) | Captura automática? | Canal | Direção no cliente |
|---------------------------|---------------------|-------|--------------------|
| Expedidor (`exped`) | **Sim** | DistDFe do **cliente** | `IN` |
| Remetente (`rem`) | **Sim** | DistDFe do cliente | `IN` |
| Destinatário (`dest`) | **Sim** | DistDFe do cliente | `IN` |
| Recebedor (`receb`) | **Sim** | DistDFe do cliente | `IN` |
| Tomador (`toma3`/`toma4`) | **Sim** | DistDFe do cliente | `IN` |
| Emitente (`emit`) | **Não** via DistDFe do próprio CNPJ | autXML office **ou** import/push | `OUT` |

### Mensagem operacional para o escritório

> Remetente, destinatário, expedidor, recebedor e tomador: o sistema captura o XML completo pelo DistDFe do A1 do cliente, sem manifestação de ciência (regra CT-e).  
> **Emitente (transportadora):** o Ambiente Nacional **não** devolve o CT-e principal ao gerador no DistDFe do próprio certificado. É necessário **autXML do escritório**, **import** ou **entrega autenticada (push)**.

- Ausência de papel comprovado → **quarentena**, não inventar `TAKER`.
- Mesmo documento pode gerar **múltiplos** interesses (papéis) no mesmo office.
- `emit/CNPJ` igual ao consultado no canal cliente → `UNEXPECTED_OWN_ISSUER_DOCUMENT` (não `ISSUER/OUT`).

---

## 3. Janela de 90 dias / 3 meses (16.3)

| Conceito | Significado no produto |
|----------|------------------------|
| ~90 dias / 3 meses | **Janela típica de disponibilidade** de documentos na distribuição do Ambiente Nacional (comportamento de serviço SEFAZ, sujeito a NT vigente) |
| **Não é** | Promessa de histórico completo, backfill ilimitado ou arquivo fiscal substituto da guarda do emitente |
| Antes da ativação do cursor | Documentos anteriores podem **não** ser recuperáveis via DistDFe → `HISTORICAL_GAP` ou import |
| Após ativação | Cursor sequencial (`ultNSU`); não “puxar por data” como canal primário |
| `consNSU` | Só NSU **já conhecido** (reparo); **proibido** usar como varredura/descoberta |

### Checklist de comunicação com o cliente

- [ ] Explicar que ativar o canal **não** reconstrói anos de CT-e automaticamente
- [ ] Histórico: solicitar XML ao emitente / ERP e usar import ou push
- [ ] Cobertura do painel reflete o que foi capturado/importado, não “tudo que a SEFAZ já autorizou na vida”

---

## 4. Ownership, cStat 137/656, consNSU, circuitos, reconciliação (16.4)

### 4.1 Ownership único do consumo

- **Um** dono de `distNSU` por CNPJ-base + ambiente + serviço CT-e.
- Cliente e escritório são bases **diferentes** → streams separados (ok), mas cada base só pode ter um capturador ativo.
- Segundo ERP/robô na mesma base → risco de **cStat 656** e cursor inconsistente.
- Declarar ownership no onboarding; conflito → **não** ativar automação até reconciliação.

### 4.2 cStat operacionais

| cStat | Significado | Ação |
|-------|-------------|------|
| **138** | Documentos localizados | Persistir página completa → avançar para `ultNSU` da resposta |
| **137** | Nenhum documento / fila alcançada | Quiet ≥ ~1h; **não** martelar o serviço |
| **108 / 109** | Serviço paralisado | Retryável com quiet; inbox |
| **593** | Certificado não vinculado ao CNPJ | Permanente; bloquear stream; corrigir A1/cadastro |
| **656** | Consumo indevido | Circuito por CNPJ-base+ambiente; **proibir** retry precoce; revisar ownership e rps |

### 4.3 Cursor e páginas

1. Persistir **todos** os docs/eventos/quarentenas da página antes de confirmar cursor.
2. Cursor = `ultNSU` da **resposta**, nunca o maior NSU inferido de item.
3. Máx. ~20 páginas por job → requeue se ainda houver fila.
4. 5 falhas consecutivas de decode no mesmo ponto → **bloquear**; **não** pular NSU.
5. Duas passagens: CT-e principal **antes** de eventos.

### 4.4 `consNSU` (conhecido apenas)

- Uso: reparo de NSU **já conhecido** (orçamento baixo por job, ex. `SEFAZ_CTE_CONS_NSU_BUDGET_PER_JOB`).
- **Não** usar para descoberta, backfill histórico ou “buscar por chave” (não existe `consChCTe` no produto).
- Não alterar o cursor sequencial **antes** da persistência bem-sucedida do reparo.

### 4.5 Circuitos e locks

- Lock por estabelecimento (cliente) ou por office+base+canal (autXML).
- Circuito compartilhado por CNPJ-base + ambiente após 656.
- Kill switch autXML: `SEFAZ_CTE_AUTXML_KILL_SWITCH=true` (preserva cursores/docs).

### 4.6 Procedimento de reconciliação (resumo)

| Situação | Ação |
|----------|------|
| 656 / consumidor externo | Parar automação; inventariar quem consome; transferir ownership sem reset cego para zero |
| Evento órfão | Manter quarentena; reconciliar quando o pai chegar (import ou dist) |
| Emitente cadastrado depois | Reprocessar quarentena elegível; não misturar `office_id` |
| Original + redigido | Manter ambas aquisições; canônico prefere original válido |
| Bytes divergentes mesma chave | Quarentena do candidato; não sobrescrever canônico |
| `PENDING_IMPORT` | Import/push válido encerra pendência; preserva razão/origem |

---

## 5. Qualidade `AUTXML_REDACTED` e refs `999…` (16.5)

### O que é

No canal oficial com escritório em `autXML`, o Ambiente Nacional pode **redigir** chaves de NF-e/CT-e referenciadas nos grupos previstos, substituindo por **44 caracteres `9`**.

| Qualidade | Quando |
|-----------|--------|
| `AUTXML_ORIGINAL` | Cópia via autXML **sem** padrão de redação oficial detectado |
| `AUTXML_REDACTED` | Bytes do canal oficial **com** redação `999…` nos grupos previstos |
| `ORIGINAL` | DistDFe dos 5 papéis ou import/push de `cteProc` íntegro |

### Assinatura

| Resultado | Uso |
|-----------|-----|
| `VALID` | XMLDSig ok mesmo no derivado (quando aplicável) |
| `NOT_VERIFIABLE_OFFICIAL_REDACTION` | Redação oficial impede verificação como o original; **não** forjar VALID |
| `INVALID` | Falha real de assinatura / adulteração → **não** promover como canônico confiado |

### Regras

1. **Preservar** bytes exatamente como retornados — sem “reconstruir” chaves.
2. **Não** substituir `999…` por chaves descobertas em outro sistema.
3. Download: preferir original quando existir; se só redigido, entregar com aviso de qualidade.
4. UI/API devem expor qualidade e limitação de forma honesta.

### Quando solicitar o original ao emissor

Solicitar original (import/push) quando:

- [ ] Contabilidade/guarda exige XML com referências de documentos **integrais**
- [ ] Há só `AUTXML_REDACTED` e o cliente precisa de chaves referenciadas legíveis
- [ ] Assinatura ficou `NOT_VERIFIABLE_OFFICIAL_REDACTION` e a política do escritório exige original validável
- [ ] Conflito/quarentena exige segunda fonte oficial do emitente

**Não** é necessário para toda operação se o redigido bastar para o processo interno e a qualidade estiver clara.

---

## 6. API `EMITTER_PUSH` (16.6)

Entrega autenticada de CT-e já autorizado pelo emissor/ERP.  
**Não** emite, cancela, inutiliza nem recebe evento mutante fiscal.

### Flags e limites (config)

```env
SEFAZ_CTE_EMITTER_PUSH_ENABLED=false   # default
# sefaz.cte_emitter_push.rate_limit_per_minute (default 30)
# sefaz.cte_emitter_push.max_payload_bytes (default 5 MiB)
# sefaz.cte_emitter_push.admin_token_rate_limit_per_minute (default 10)
```

### Rotas (tenant / integração)

| Método | Rota | Quem | Função |
|--------|------|------|--------|
| `POST` | `/api/v1/office/integration-tokens` | ADMIN + 2FA recente | Emite token (**plain uma vez**); escopo `cte:ingest` |
| `GET` | `/api/v1/office/integration-tokens` | ADMIN/OPERATOR/VIEWER | Lista metadados (prefixo, status, expiração) — **sem** secret |
| `POST` | `/api/v1/office/integration-tokens/{token}/revoke` | ADMIN + 2FA | Revoga; sem recuperação |
| `POST` | `/api/v1/integrations/cte/push` | Bearer do token de integração | Ingestão; `office_id` do principal |

Paths exatos podem incluir o prefixo Sanctum/API do deploy; conferir `backend/routes/api.php`.

### Ciclo de vida do token

1. ADMIN com 2FA emite (`name`, opcional `expires_in_days` 1–730).
2. Resposta 201 contém `token` **uma única vez** + `warning`.
3. Persistência: somente **hash** (SHA-256) + prefixo curto para suporte.
4. Rotação: emitir novo → migrar ERP → **revogar** o antigo.
5. Revogação: imediata; tentativas posteriores → não autorizado (sem revelar cadastro de outro office).
6. **Não** há rota de recuperação do valor secreto.

### Exemplos **sem** dados fiscais reais

**Emitir token (sessão cookie Sanctum + CSRF + 2FA já reassert):**

```http
POST /api/v1/office/integration-tokens
Content-Type: application/json

{
  "name": "erp-homolog-cte",
  "expires_in_days": 90
}
```

Resposta (ilustrativa — valores fictícios):

```json
{
  "data": {
    "id": 1,
    "name": "erp-homolog-cte",
    "token_prefix": "cte_xxxxxxxxxx",
    "scope": "cte:ingest",
    "status": "ACTIVE",
    "token": "cte_<segredo-ficticio-nunca-commitar>",
    "warning": "Guarde o token agora; ele não poderá ser recuperado."
  }
}
```

**Push (corpo = XML ou conforme contrato do controller; não logar body):**

```http
POST /api/v1/integrations/cte/push
Authorization: Bearer cte_<segredo>
Content-Type: application/xml

<!-- payload: cteProc modelo 57 autorizado — NÃO usar XML real em docs/tickets públicos -->
```

Resposta de sucesso: identificador durável de lote/item — **nunca** conteúdo de cofre, PFX ou XML ecoado.

**Revogar:**

```http
POST /api/v1/office/integration-tokens/1/revoke
```

### Checklist de segurança push

- [ ] Flag off até necessidade
- [ ] Rate limit e tamanho de payload ativos
- [ ] Tenancy só do principal do token
- [ ] Tentativa cross-office → resposta genérica
- [ ] Auditoria de issue/revoke **sem** plaintext
- [ ] Rotação periódica e revogação de tokens vazados/expirados

---

## 7. Flags de referência rápida

| Flag / config | Default | Função |
|---------------|---------|--------|
| `SEFAZ_CTE_ENABLED` | `false` | DistDFe CT-e do **cliente** |
| `SEFAZ_CTE_AUTXML_DISTDFE_ENABLED` | `false` | Stream autXML do **escritório** |
| `SEFAZ_CTE_AUTXML_OFFICE_ALLOWLIST` | vazio | Piloto gradual |
| `SEFAZ_CTE_AUTXML_ALLOW_ALL_OFFICES` | `false` | Evitar GA acidental |
| `SEFAZ_CTE_AUTXML_KILL_SWITCH` | `false` | Corte rápido |
| `SEFAZ_CTE_EMITTER_PUSH_ENABLED` | `false` | Endpoint push |
| Fila cliente | `sync-sefaz-cte` | `SEFAZ_QUEUE_CTE` |
| Fila autXML | `sync-sefaz-cte-autxml` | `SEFAZ_CTE_AUTXML_QUEUE` |

---

## 8. Ver também

- Smoke: `docs/ops/cte-prod-smoke-runbook.md`
- Gates: `docs/ops/cte-pilot-gates-status.md`
- Rollout: `docs/ops/cte-rollout-allowlist.md`
- Aceite: `docs/ops/cte-pilot-acceptance.md`
- autXML NF-e (padrões 656): `docs/ops/autxml-runbooks.md`
- Matriz produto: `docs/ops/document-coverage-matrix.md`
