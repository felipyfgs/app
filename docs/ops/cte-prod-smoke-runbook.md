# Smoke produtivo restrito — CT-e (DistDFe cliente + autXML escritório)

**Change:** `complete-cte-capture-with-distdfe-autxml-and-import` · tasks **15.1–15.9**  
**Atualizado:** 2026-07-15  
**Status global:** **PENDING_OPS** — nenhum smoke com SEFAZ real registrado nesta documentação

Relacionados:

- Status dos gates: `docs/ops/cte-pilot-gates-status.md`
- Canais e cobertura: `docs/ops/cte-coverage-and-channels-runbook.md`
- Rollout / allowlist: `docs/ops/cte-rollout-allowlist.md`
- Aceite do escritório: `docs/ops/cte-pilot-acceptance.md`
- Matriz de schema: `docs/ops/cte-schema-compatibility-matrix.md`
- Baseline pré-change: `docs/ops/archive/2026-07-15/cte-baseline-state-2026-07-15.md`

## Princípios

- **Fora de CI.** Nenhum A1/PFX de produção ou homologação no pipeline.
- **Somente captura** de XML já autorizado — sem emissão, cancelamento, inutilização ou portal.
- Streams **separados**: DistDFe do **cliente** (`CTE_DISTDFE`) ≠ DistDFe **autXML do escritório** (`CTE_AUTXML_DISTDFE`). Nunca misturar A1, cursor, lock ou circuito.
- Evidência sanitizada: correlação, cStat, `ultNSU`/`maxNSU` mascarados se necessário, latência, qualidade, papel — **nunca** XML, Base64, PFX, senha, PEM, chave privada, token de integração ou CNPJ completo em repositório.
- Feature flags permanecem **OFF** (ou allowlist vazia) se **qualquer** gate fiscal, criptográfico ou operacional falhar.

## Proibições absolutas

| Proibido | Motivo |
|----------|--------|
| Logar / colar XML fiscal (parcial ou completo) | vazamento + imutabilidade |
| Exportar PFX, senha, PEM, chave privada | cofre |
| Registrar token `EMITTER_PUSH` em plain | exibido uma única vez |
| Automatizar portal SEFAZ / Gov.br / CAPTCHA | non-goal |
| `consNSU` como varredura ou backfill | orçamento + cStat 656 |
| Avançar cursor com decode/persistência falha | perda de NSU |
| Ativar `SEFAZ_CTE_*` amplo sem smoke PASS | risco operacional |

## 1. Autorização e janela

### 1.1 Quem autoriza

| Papel | Responsabilidade |
|-------|------------------|
| Plataforma (ops) | janela, flags, kill switch, evidência sanitizada |
| ADMIN do office piloto | A1 já custodiado, aceite de cobertura, confirmação de ownership DistDFe |
| Operador fiscal (escritório) | escolha do cliente/emitente piloto (máscaras) |

- [ ] Ticket/autorização formal da janela (preencher id abaixo)
- [ ] Aceite piloto CT-e assinado ou trial interno documentado (`cte-pilot-acceptance.md`)
- [ ] Backup/restore verificável nas últimas 24h (ver `archive/2026-07-15/cte-backup-drill-2026-07-15.md` ou política vigente)
- [ ] Ownership único de `distNSU` declarado para **cada** CNPJ-base que será consultado (cliente e, se aplicável, escritório)

### 1.2 Identificação da janela (preencher)

| Campo | Valor |
|-------|--------|
| Data/hora início (UTC) | |
| Data/hora fim planejado (UTC) | |
| Ambiente | HOMOLOGATION / PRODUCTION |
| Operador plataforma | |
| Office piloto (id interno / slug) | |
| Ticket / autorização | |
| Resultado global | **PENDING_OPS** / PASS / FAIL |

### 1.3 CNPJs e chaves — apenas máscaras

Exemplos de máscara aceitos no repositório / ticket público:

| Entidade | Máscara (exemplo de formato) | Uso no smoke |
|----------|------------------------------|--------------|
| Cliente não-emitente (papel IN) | `12.345.***/****-01` | stream `CTE_DISTDFE` |
| Estabelecimento consultado | mesma raiz; full só em cofre | mTLS + `cUFAutor` |
| Escritório (autXML) | `98.765.***/****-99` | stream `CTE_AUTXML_DISTDFE` |
| Emitente piloto | `11.222.***/****-33` | roteamento ISSUER/OUT |
| Chave CT-e | primeiros 8 + `…` + últimos 4 | correlação; sem XML |

**Nunca** commitar CNPJ completo de produção, chave de 44 dígitos completa com XML, ou fingerprint de PFX além do já exposto por health sanitizada.

## 2. Endpoints e WSDL (task 3.9 — referência de config)

Fontes de verdade no monorepo: `backend/config/sefaz.php` bloco `cte` (+ `cte_autxml`, `cte_emitter_push`).  
**Confirmar na NT/WSDL vigente no dia do smoke** — defaults abaixo são os do config atual, não um substituto de readiness SEFAZ.

| Item | Produção (default config) | Homologação (default config) | Chave env / config |
|------|---------------------------|------------------------------|--------------------|
| Endpoint ASMX | `https://www1.cte.fazenda.gov.br/CTeDistribuicaoDFe/CTeDistribuicaoDFe.asmx` | `https://hom1.cte.fazenda.gov.br/CTeDistribuicaoDFe/CTeDistribuicaoDFe.asmx` | `SEFAZ_CTE_DISTDFE_URL` / `_HOM` · `sefaz.cte.production` / `homologation` |
| SOAPAction | `http://www.portalfiscal.inf.br/cte/wsdl/CTeDistribuicaoDFe/cteDistDFeInteresse` | idem | `SEFAZ_CTE_SOAP_ACTION` · `sefaz.cte.soap_action` |
| Namespace WSDL | `http://www.portalfiscal.inf.br/cte/wsdl/CTeDistribuicaoDFe` | idem | `SEFAZ_CTE_NAMESPACE` · `sefaz.cte.namespace` |
| Layout dist | `1.00` | idem | `SEFAZ_CTE_LAYOUT_VERSION` · `sefaz.cte.layout_version` |
| Serviço | `CTeDistribuicaoDFe` / `cteDistDFeInteresse` | SOAP 1.2 + mTLS PFX BLOB | ver matriz schema |

Readiness de endpoint/WSDL **sem** biblioteca comunitária de runtime: cliente próprio (`HttpSefazCteDistDfeClient`) + smoke HTTP/TLS controlado. Detalhe de cStat e leiautes: `cte-schema-compatibility-matrix.md`.

### Checklist 3.9 (endpoint/WSDL)

- [ ] URL do ambiente alvo resolve e apresenta certificado TLS válido (hostname/peer)
- [ ] SOAPAction e namespace batem com o config deployado
- [ ] Layout `1.00` no envelope `distDFeInt`
- [ ] Sem dependência nova de client comunitário de CT-e no `composer.json` de produção
- [ ] Resultado: **PENDING gate** até execução humana

## 3. Flags padrão seguro (antes e se gate falhar)

```env
# Streams CT-e — default seguro
SEFAZ_CTE_ENABLED=false
SEFAZ_CTE_AUTXML_DISTDFE_ENABLED=false
SEFAZ_CTE_AUTXML_KILL_SWITCH=false
SEFAZ_CTE_AUTXML_ALLOW_ALL_OFFICES=false
SEFAZ_CTE_AUTXML_OFFICE_ALLOWLIST=
SEFAZ_CTE_EMITTER_PUSH_ENABLED=false
```

**Regra:** se qualquer passo 15.2–15.9 falhar ou for abortado, reverter imediatamente para o bloco acima e registrar FAIL em `cte-pilot-gates-status.md`.

### Janela controlada (exemplo — só durante smoke)

```env
# Ligar o mínimo necessário, um stream por vez
SEFAZ_CTE_ENABLED=true
# Allowlist / office piloto conforme implementação de elegibilidade
# AutXML: só após 15.5–15.6
# SEFAZ_CTE_AUTXML_DISTDFE_ENABLED=true
# SEFAZ_CTE_AUTXML_OFFICE_ALLOWLIST=<office_id>
# SEFAZ_CTE_EMITTER_PUSH_ENABLED=true   # só se testar 15.8 via push
```

## 4. Critérios de parada (stop)

Interromper a janela e desligar flags se ocorrer **qualquer** item:

| # | Critério de parada |
|---|--------------------|
| S1 | cStat **593** (certificado não vinculado) ou falha permanente de identidade |
| S2 | cStat **656** recorrente ou circuito aberto sem ownership resolvido |
| S3 | 5 falhas consecutivas de decode no mesmo ponto de cursor |
| S4 | Assinatura **INVALID** em artefato classificado como original (não redigido) |
| S5 | Vazamento de XML/PFX/token em log, métrica ou resposta de health |
| S6 | Cross-tenant / roteamento para office errado |
| S7 | Cursor avançou sem persistência da página |
| S8 | Operador sem autorização ou janela expirada |
| S9 | TLS/mTLS instável ou peer verification desligada |

## 5. Separação dos streams

| Stream | Canal | Credencial | Cursor | Lock / circuito |
|--------|-------|------------|--------|-----------------|
| Cliente (5 papéis IN) | `CTE_DISTDFE` | A1 **do cliente** (raiz) | por estabelecimento + ambiente | CNPJ-base cliente + ambiente |
| Escritório autXML | `CTE_AUTXML_DISTDFE` | A1 **do escritório** | por office + CNPJ-base office + ambiente | office + CNPJ-base + ambiente + canal |
| Import / push | `MANUAL_*` / `EMITTER_PUSH` | token integração (hash) / sessão tenant | N/A (batch) | rate limit por office |

Checklist operacional:

- [ ] Job `SyncSefazCteDistDfeJob` (fila `sync-sefaz-cte`) **não** usa `ClientCredential` do office no stream autXML
- [ ] Job `SyncOfficeCteAutXmlDistDfeJob` (fila `sync-sefaz-cte-autxml`) **não** usa A1 de cliente
- [ ] UI/API mostra cards/saúde **distintos** para os dois canais
- [ ] Logs de correlação incluem `channel` / stream sem payload fiscal

---

## 6. Checklists por passo (15.2–15.9)

Cada passo abaixo permanece **PENDING gate** até execução humana com evidência sanitizada em `cte-pilot-gates-status.md`.  
Não marcar PASS no `tasks.md` sem essa evidência.

### 15.2 — Primeira consulta controlada (cliente, um dos cinco papéis)

**Pré-requisitos**

- [ ] A1 do cliente já custodiado no vault (sem export)
- [ ] Cliente com CT-e recente como rem/dest/exped/receb/toma (não emitente)
- [ ] Ownership DistDFe da raiz do cliente declarado
- [ ] `SEFAZ_CTE_ENABLED=true` só na janela; autXML ainda OFF se não for o alvo

**Procedimento**

1. Confirmar elegibilidade (flag, A1, ambiente, estabelecimento).
2. Disparar **uma** sincronização controlada (job manual / comando readiness se existir).
3. Observar primeira resposta: cStat, presença de docZip, latência.
4. **Não** logar corpo SOAP nem Base64.

**Sucesso esperado**

- [ ] Chamada mTLS ok; cStat 137 ou 138 (ou 108/109 com quiet)
- [ ] Sem segredo em log amostrado

**Status:** **PENDING gate**

---

### 15.3 — Confirmar em produção: cStat, ultNSU, maxNSU, decode, papel, assinatura, persistência

**Pré-requisitos:** 15.2 com documento localizado (cStat 138) ou reconsulta na mesma janela.

**Checklist de verificação (sanitizada)**

- [ ] `cStat` registrado (138 = docs; 137 = vazio)
- [ ] `ultNSU` / `maxNSU` coerentes (15 posições); cursor **só** após persistência da página
- [ ] Decode Base64+GZip ok; SHA-256 no vault; bytes imutáveis
- [ ] Papel(is) comprovados no estabelecimento (SENDER/RECIPIENT/EXPEDITOR/RECEIVER/TAKER) — **sem** fallback inventado
- [ ] Direção por interesse (`IN` para os cinco papéis)
- [ ] Qualidade `ORIGINAL` (canal cliente) e assinatura `VALID` (ou quarentena se inválida)
- [ ] **Não** criou `ISSUER/OUT` só porque emit == consultado

**Status:** **PENDING gate**

---

### 15.4 — Fila alcançada (137) e quiet mínimo

**Procedimento**

1. Após esgotar fila (cStat **137**) ou simular quiet pós-137.
2. Tentar segunda chamada **antes** do quiet mínimo (~1h / `quiet_hours`).
3. Confirmar que o sistema **impede** reconsulta precoce (API/comando/Scheduler/UI).

**Sucesso esperado**

- [ ] Segunda chamada bloqueada ou adiada; inbox/estado honesto
- [ ] Cursor não regride nem salta

**Status:** **PENDING gate**

---

### 15.5 — Emitente piloto com escritório em `autXML`

**Pré-requisitos**

- [ ] Identidade fiscal do escritório + A1 office (change autXML dependente)
- [ ] Emitente piloto **incluiu previamente** o CNPJ do escritório em `<autXML>` **antes** de autorizar o CT-e
- [ ] **Não** alterar documento já autorizado para “forçar” autXML
- [ ] Ownership do `distNSU` do **CNPJ-base do escritório** resolvido (ver `autxml-external-distnsu-consumers.md` e inventário CT-e)

**Checklist**

- [ ] CNPJ escritório mascarado registrado no ticket
- [ ] ERP/emissor confirmou inclusão autXML em emissão **futura**/recente elegível
- [ ] `SEFAZ_CTE_AUTXML_*` ainda OFF até 15.6 se preferir freeze

**Status:** **PENDING gate**

---

### 15.6 — Primeiro CT-e no stream do escritório

**Procedimento**

1. Habilitar `SEFAZ_CTE_AUTXML_DISTDFE_ENABLED` + allowlist do office **somente** nesta janela.
2. Rodar `SyncOfficeCteAutXmlDistDfeJob` (ou disparo controlado).
3. Validar: presença exata do escritório em `autXML`; roteamento por `emit/CNPJ` completo no mesmo office.
4. Confirmar `ISSUER/OUT` no cliente emitente; interesses `IN` extras se outros clientes do office participarem.
5. Qualidade `AUTXML_ORIGINAL` ou `AUTXML_REDACTED`; referências `999…` (44 noves) **preservadas** sem reconstrução.
6. Resultado real de assinatura: `VALID` ou `NOT_VERIFIABLE_OFFICIAL_REDACTION` (smoke decide; não forjar).

**Stop:** emitente desconhecido, autXML ausente, office divergente → quarentena, não catálogo cego.

**Status:** **PENDING gate**

---

### 15.7 — Import do original + reconciliação com cópia autXML

**Procedimento**

1. Importar `cteProc` original do mesmo piloto (XML/ZIP) via fluxo de import saídas/CT-e.
2. Confirmar reconciliação: melhor canônico prefere original; aquisição autXML preservada.
3. Proveniência: duas aquisições; cobertura deixa de ser só redigida se original válido.
4. Download informa qualidade; **sem** reconstruir 999.

**Status:** **PENDING gate**

---

### 15.8 — Fallback XML/ZIP (ou push) sem autXML + `PENDING_IMPORT`

**Procedimento**

1. Cliente emitente **sem** autXML: cobertura `PENDING_IMPORT` (ou estado equivalente).
2. Importar ZIP/XML ou usar `EMITTER_PUSH` (flag on só se necessário).
3. Confirmar encerramento de `PENDING_IMPORT` sem apagar razão/origem anterior.
4. Token push: se emitido, **não** colar plain no repositório; revogar ao fim da janela se temporário.

**Status:** **PENDING gate**

---

### 15.9 — Evidências sanitizadas e decisão de flags

**Registro mínimo (sanitizado)**

| Campo | Valor |
|-------|--------|
| Correlação(ões) | |
| Stream(s) testados | `CTE_DISTDFE` / `CTE_AUTXML_DISTDFE` / import / push |
| cStat observados | |
| Páginas processadas (contagem) | |
| Docs promovidos / quarentena (contagens) | |
| Qualidades vistas | ORIGINAL / AUTXML_* |
| Assinatura (resultados) | |
| Segredos em log? | NÃO / SIM (incidente) |
| Flags ao encerrar | todas OFF se FAIL |
| Resultado | **PENDING_OPS** / PASS / FAIL |

**Encerramento obrigatório se FAIL ou incompleto:**

```env
SEFAZ_CTE_ENABLED=false
SEFAZ_CTE_AUTXML_DISTDFE_ENABLED=false
SEFAZ_CTE_AUTXML_KILL_SWITCH=true
SEFAZ_CTE_AUTXML_OFFICE_ALLOWLIST=
SEFAZ_CTE_EMITTER_PUSH_ENABLED=false
```

Atualizar `cte-pilot-gates-status.md` na mesma data.

**Status:** **PENDING gate**

---

## 7. Pós-smoke

- [ ] Amostrar logs/auditoria por padrões (`BEGIN CERTIFICATE`, Base64 longo, `cte_`, JWT-like)
- [ ] Confirmar cursores estáveis (sem salto)
- [ ] Decidir allowlist gradual (`cte-rollout-allowlist.md`) **somente** se 15.2–15.9 PASS nos streams pretendidos
- [ ] Não declarar GA CT-e só com PASS de um stream

## 8. Ver também

- `docs/ops/autxml-runbooks.md` — autXML NF-e (padrões de ownership/656 reutilizáveis)
- `docs/ops/cte-coverage-and-channels-runbook.md` — operação contínua
- `docs/ops/cte-pilot-gates-status.md` — tracking pass/fail
- `mvp.md` — cobertura e não-objetivos de produto
