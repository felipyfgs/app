# Threat model — hub de monitoramento fiscal (Integra Contador + multi-office)

**Change:** `build-complete-fiscal-monitoring-hub` · task **16.2**  
**Atualizado:** 2026-07-15  
**Relacionados:** ADR `docs/adr/005-control-plane-vs-data-plane.md`, `docs/adr/003-secure-object-vault.md`, `docs/ops/serpro-global-cert-rotation-runbook.md`, `docs/ops/multi-tenant-isolation-checklist.md`

## Escopo

Ativos e ameaças do **SaaS multi-escritório** com contrato SERPRO **global** da software house e dados fiscais **por `office_id`**. Canais ADN/SEFAZ legados permanecem com seus runbooks; aqui o foco é a cadeia Integra Contador e superfícies novas do hub.

## Atores

| Ator | Intenção |
|------|----------|
| Usuário tenant (`ADMIN` / `OPERATOR` / `VIEWER`) | Operar só o escritório ativo |
| `PLATFORM_ADMIN` | Controle de plataforma (contrato, flags, fatura consolidada) — **sem** herdar conteúdo fiscal de tenants |
| Outro tenant / atacante autenticado | Ler ou mutar dados de outro office |
| Atacante externo (rede) | Interceptar, forjar sessão, DDoS de API |
| Insider ops / backup | Acesso indevido a cofre, dumps, logs |
| SERPRO / terceiros de infraestrutura | Disponibilidade, fatura, mudança de contrato |

## Superfícies e ativos críticos

### 1. Certificado global do contratante (e-CNPJ A1)

| Item | Detalhe |
|------|---------|
| Ativo | PFX + senha do contratante da API; Consumer Key/Secret; tokens OAuth mTLS |
| Plano | **Controle** (sem `office_id` de tenant) |
| Comprometimento | Afeta **todos** os tenants — chamadas e fatura |
| Controles | Envelope no `SecureObjectStore`; finalidades distintas; `VAULT_MASTER_KEY` fora do backup comum; kill switch `SERPRO_KILL_SWITCH` / `serpro:contract kill-on`; um ACTIVE por ambiente; replace transacional; health sanitizada; sem rota de “download de cert” |
| Runbook | `serpro-global-cert-rotation-runbook.md` |

### 2. A1 opcional do Autor (escritório)

| Item | Detalhe |
|------|---------|
| Ativo | PFX do procurador/escritório quando modo gerenciado; senha |
| Plano | **Dados** (`office_id` obrigatório) |
| Comprometimento | Escopo do tenant; pode emitir/assinar em nome do Autor |
| Controles | Cofre com purpose tenant; metadados sem material recuperável na API; rotação/revogação por office; preferir A3 interativo quando política exigir; nunca exportar PEM/PFX |
| Risco residual | Operador `ADMIN` malicioso do próprio office (ameaça insider do tenant) — mitigado por audit + 2FA, não por isolamento cross-tenant |

### 3. Termos de Autorização (XML assinado)

| Item | Detalhe |
|------|---------|
| Ativo | XML do Termo, hash, validade, vínculo Autor ↔ destinatário contratante |
| Ameaças | Upload de Termo de outro signatário; reuso após expiração; vazamento do XML completo; reapresentação indevida |
| Controles | Validador de estrutura/assinatura/signatário/destinatário/expiração; `SERPRO_TERM_REPRESENTATION_*` (`PENDING_VALIDATION` default); armazenamento cifrado; API devolve hash/status, não material sensível em logs; bloqueio de cadeia se inválido |

### 4. Tokens (OAuth contratante e token diário do procurador)

| Item | Detalhe |
|------|---------|
| Ativo | Bearer / JWT de acesso |
| Ameaças | Vazamento em log, response, export, suporte; replay; renovação sem revalidar Termo |
| Controles | Sanitização de transporte HTTP (`SerproHttpTransportSanitize`); audit redigido; cache com skew; lock de renovação; tokens só em cofre/memória de processo; `toSanitizedArray()` em health |
| Proibição | Colar token em ticket, chat ou este repositório |

### 5. Mensagens (caixa postal fiscal)

| Item | Detalhe |
|------|---------|
| Ativo | Conteúdo de mensagens, anexos, metadados de contribuinte |
| Ameaças | Cross-tenant; exposição a `PLATFORM_ADMIN`; export massivo; classificação errada de sensibilidade |
| Controles | `office_id` + policies; sensitivity `FISCAL_RESTRICTED` no monitoramento; sem impersonação genérica de plataforma; retenção configurável; audit de leitura/export |

### 6. Relatórios e snapshots (Sitfis, declarações, situação fiscal)

| Item | Detalhe |
|------|---------|
| Ativo | Snapshots, findings, evidências com hash, projeção de situação |
| Ameaças | Inferir “em dia” sem evidência; inventar dados na UI; misturar CNPJ entre offices; vazar evidência bruta |
| Controles | Normalizer que **não** presume regularidade; evidência antes de status `UP_TO_DATE`; idempotência de runs; isolamento por office; evidências com retenção (`FISCAL_EVIDENCE_RETENTION_DAYS`); jobs revalidam tenant |

### 7. Guias e operações mutantes

| Item | Detalhe |
|------|---------|
| Ativo | Guias geradas, comprovantes, operações de transmissão/adesão/emissão |
| Ameaças | Emissão duplicada após timeout; mutação sem 2FA; mutação com procuração revogada; liberação acidental de flag |
| Controles | Defaults **OFF** (`FISCAL_MUTATIONS_*`, `FEATURES_MUTATING_*`); allowlist por operação; preflight + TOTP recente; idempotência e estado incerto; kill switch dedicado; aprovação humana (doc 16.10); anti-repeat window |

## Ameaças transversais

| ID | Ameaça | Impacto | Mitigação |
|----|--------|---------|-----------|
| T1 | Cross-tenant via `office_id` no request | Alto | Middleware remove `office_id` do cliente; `CurrentOffice` via membership |
| T2 | Mesmo CNPJ em dois offices | Alto se query por CNPJ global | Isolar por `office_id`; testes negativos Platform |
| T3 | Job sem `CurrentOffice` ignora global scope | Alto | Revalidar `office_id` no payload; preflight + testes |
| T4 | Fatura SERPRO global sem rateio | Médio (financeiro) | Ledger pré/pós; shadow; reconciliação sem reescrever |
| T5 | Comprometimento master key | Crítico | Custódia offline; rotação de key version; never in `backups/` |
| T6 | Log com PFX/token/Termo | Alto | Sanitizers + review de audit context |
| T7 | Scraping/portais humanos no FGTS | Legal/ops | Arquitetura proíbe; módulo só eSocial parcial |
| T8 | Kill switch não acionado em incidente | Alto | Runbook 16.8 + env + CLI + API platform |
| T9 | Escala antes de conciliação | Financeiro | Gate 16.7 bloqueia coortes |
| T10 | `PLATFORM_ADMIN` lendo fiscal | Alto/sigilo | Policies de plano de controle; testes Architecture |

## Trust boundaries

```
[Browser SPA] --same-origin--> [Nginx] --> [PHP-FPM Laravel]
                                    |
                    +---------------+----------------+
                    |                                |
            Plano de dados                    Plano de controle
            (office_id, Sanctum)              (platform membership)
                    |                                |
            [Postgres tenant rows]            [serpro_contracts, flags]
            [Vault objects tenant]            [Vault objects global]
                    |                                |
                    +-------- mTLS/OAuth ------------+
                                    |
                              [SERPRO Integra]
```

## Controles de detecção

- Audit: `serpro.contract.*`, `serpro.kill_switch.*`, `serpro.authorization.*`, `serpro.usage.*`, mutações fiscais.
- Health sanitizada: `GET /api/v1/platform/serpro/health` e health tenant sem custo interno global.
- Preflight: `ops:preflight-tenant-isolation`.
- Métricas: latência, 4xx/5xx, breaker open, fila Horizon, franquia %.
- Alertas: cert próximo do vencimento; classe de uso `DESCONHECIDA`; divergência de reconciliação.

## Residual / aceito no MVP

- Sem KMS cloud (cofre envelope local).
- Sem break-glass de impersonação tenant por platform (change futura).
- `activeMembership()` legada até UI de troca — documentado; testes de switch existem para o caminho novo.
- Smoke real e fatura SERPRO dependem de evidência comercial e cert — gates externos.

## Revisão

| Data | Resultado |
|------|-----------|
| 2026-07-15 | Modelo inicial documentado (task 16.2). Revisar após primeiro smoke produtivo e após qualquer incidente de credencial. |
