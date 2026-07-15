# Operação, suporte, onboarding, limites de cobertura e comunicação comercial

**Change:** `build-complete-fiscal-monitoring-hub` · task **16.11**  
**Atualizado:** 2026-07-15

## 1. Modelo do produto (comunicação canônica)

| Afirmação correta | Evitar |
|-------------------|--------|
| SaaS para **escritórios contábeis** monitorarem contribuintes | “Portal do contribuinte” / app do cliente final |
| Software house é **contratante** SERPRO; escritório é **Autor**; cliente é **contribuinte** | Dizer que o escritório “tem o contrato Integra” da plataforma |
| Monitoramento e consultas com base em API oficial | Scraping, robô de portal, Gov.br automatizado |
| FGTS: **cobertura parcial** via eSocial quando aplicável | “Integração completa FGTS Digital” |
| Custo: plano da plataforma ≠ fatura SERPRO linha a linha na UI do tenant | Mostrar fatura global SERPRO ou custo interno ao tenant |
| Mutações só com aprovação e 2FA | “O sistema já transmite tudo sozinho” no GA RO |

Gate comercial formal: `docs/ops/serpro-integra-contador-commercial-legal-evidence.md`.

## 2. Papéis e responsabilidades

| Papel | Responsabilidades |
|-------|-------------------|
| **Platform ops** | Contrato SERPRO, kill switches, backups, coortes, conciliação, incidentes de plataforma |
| **Platform admin (produto)** | Lifecycle de offices, planos, allowlists, comunicação de incidente |
| **Suporte N1** | Acesso tenant, senha/2FA, “por que está BLOCKED”, navegação do painel |
| **Suporte N2 fiscal** | Cadeia Autor/Termo/procuração, findings, evidências, estados de situação |
| **Escritório ADMIN** | Onboarding Autor, usuários do office, procurações, aceite de mutação |
| **Escritório OPERATOR** | Operação diária RO / mutações se autorizado |
| **Escritório VIEWER** | Leitura |

`PLATFORM_ADMIN` **não** substitui o contador do tenant e **não** lê conteúdo fiscal alheio.

## 3. Onboarding de um escritório (runbook curto)

1. Criar `Office` + subscription (`TRIAL`/`ACTIVE`) com limites.  
2. Convidar `ADMIN` com 2FA obrigatório.  
3. Seleção de tenant ativo (membership) — não aceitar `office_id` do client.  
4. Configurar **Autor do Pedido** + upload de **Termo** + modo de certificado.  
5. Validar token de procurador (A1/A3/externo).  
6. Cadastrar contribuintes (CNPJ texto 14, uppercase, sem máscara).  
7. Registrar **poderes/procurações** por serviço necessário.  
8. Entrar na **allowlist** dos módulos contratados (coorte).  
9. Treinar leitura de status: `UP_TO_DATE`, `PENDING`, `ATTENTION`, `ERROR`, `UNKNOWN`, `UNSUPPORTED`, `BLOCKED`, etc.  
10. Mostrar painel de **consumo do office** (sem fatura global).  
11. Checklist de segurança: sem compartilhar PFX; suporte nunca pede senha de cert por e-mail aberto.

### Estados de assinatura e efeito

| Estado | Novas chamadas externas | Mutações | Leitura histórica |
|--------|-------------------------|----------|-------------------|
| TRIAL | Conforme flags/allowlist | Off salvo aceite | Sim |
| ACTIVE | Sim (limites) | Se aprovado | Sim |
| PAST_DUE | Política comercial (pode degradar) | Off recomendado | Sim |
| SUSPENDED | Não | Não | Sim (export permitido) |
| CANCELED | Não | Não | Conforme retenção |

## 4. Suporte — árvore de decisão rápida

| Sintoma | Verificações | Ação típica |
|---------|--------------|-------------|
| “Nada atualiza” | Kill switch, allowlist, subscription, scheduler/horizon | Ops platform |
| `BLOCKED` cadeia | Termo, token, poder, contrato health | N2 + office ADMIN |
| `UNSUPPORTED` | Módulo/UF/regime fora de cobertura | Explicar limite; não inventar status |
| `UNKNOWN` / evidência fraca | Não tratar como “em dia” | Orientar nova coleta / fonte |
| Erro 401/403 SERPRO | Cert/token global ou Autor | Kill se massivo; runbook rotação |
| Custo alto no mês | Painel consumo; polling agressivo; tenant ruidoso | Ajustar intervalos; caps |
| Pedido de mutação | Flag e aceite 16.10 | Recusar se NO-GO |
| Pedido de cert/Termo por e-mail | — | **Recusar**; canal seguro + política |
| Cross-tenant suspeito | Preflight, logs | Incidente P1; isolar |

### Severidades

| Sev | Exemplo | SLA interno sugerido |
|-----|---------|----------------------|
| P1 | Vazamento; cert comprometido; cross-tenant | Imediato; kill switch |
| P2 | Integra fora para todos os tenants | &lt; 1h resposta |
| P3 | Um office com Termo inválido | &lt; 1 dia útil |
| P4 | Dúvida de UI / treinamento | Backlog suporte |

## 5. Limites de cobertura (produto)

Documentar no comercial e no onboarding:

| Área | Coberto no hub | Não coberto / parcial |
|------|----------------|------------------------|
| NFS-e ADN / DF-e SEFAZ legados | Canais documentais existentes | — (fora do núcleo Integra, mas no monorepo) |
| Situação / Sitfis | Consultas e projeções com evidência | Certidão negativa inventada sem item |
| Simples/MEI | Consultas e monitoramento RO | Transmissão sem 16.10 |
| DCTFWeb/MIT | Monitoramento RO | Encerrar/transmitir sem aprovação |
| Parcelamentos | Consultas | Adesão sem aprovação |
| Caixa postal | Mensagens via API | E-mail humano de fiscalização não estruturado |
| Guias | Consulta; emissão só com GO | Pagamento bancário da guia no produto |
| FGTS | Parcial eSocial | FGTS Digital portal completo |
| Municípios genéricos | Não | APIs municipais ad hoc |
| Contribuinte final | Não | Login de cliente do escritório |

Matriz documental legada: `docs/ops/document-coverage-matrix.md` (DF-e). Manter este doc alinhado ao marketing.

## 6. Comunicação comercial — frases aprovadas / proibidas

### Aprovadas (ajustar branding)

- “Plataforma multi-escritório para contabilidades acompanharem obrigações e pendências com base em APIs oficiais.”  
- “A software house opera o contrato técnico com o SERPRO; o escritório atua como autor/procurador com Termo e procurações.”  
- “O consumo é rateado internamente; a fatura SERPRO é da plataforma.”  
- “FGTS possui cobertura parcial; não substituímos o FGTS Digital completo.”  
- “Emissões e transmissões, quando disponíveis, exigem confirmação e segundo fator.”  

### Proibidas

- “Somos revenda credenciada SERPRO para o seu CNPJ” (salvo se contrato disser isso — hoje gate pendente).  
- “Garantimos 100% das obrigações fiscais em dia automaticamente.”  
- “Integração total FGTS Digital / todos os municípios / portal Gov.br.”  
- “Armazenamos seu certificado para você baixar depois.”  
- Qualquer número de preço SERPRO copiado de fatura interna em proposta sem validação financeira.

## 7. Operação contínua (cadência)

| Cadência | Atividade |
|----------|-----------|
| Diária | Filas, breaker, kill switches, alertas de cert (&lt; 30 dias) |
| Semanal | Amostra de logs sanitizados; tickets P2/P3; allowlists |
| Mensal | Conciliação 16.7; revisão de coortes; backup restore drill periódico |
| Trimestral | Revisão threat model; tabela de retenção; treino suporte |
| Incidente | Runbooks 16.8 + rotação cert + comunicação tenant sem segredos |

## 8. Artefatos de ops do hub (índice 16.x)

| Doc | Uso |
|-----|-----|
| [fiscal-hub-verification-2026-07-15.md](./fiscal-hub-verification-2026-07-15.md) | Evidência de testes |
| [fiscal-hub-threat-model.md](./fiscal-hub-threat-model.md) | Ameaças e controles |
| [fiscal-hub-retention-backup.md](./fiscal-hub-retention-backup.md) | Retenção/DR/exclusão |
| [fiscal-hub-trial-shadow-checklist.md](./fiscal-hub-trial-shadow-checklist.md) | Trial mock+shadow |
| [fiscal-hub-pilot-acceptance.md](./fiscal-hub-pilot-acceptance.md) | Aceite 1 office |
| [fiscal-hub-prod-smoke-readonly.md](./fiscal-hub-prod-smoke-readonly.md) | Smoke RO real |
| [fiscal-hub-usage-vs-invoice.md](./fiscal-hub-usage-vs-invoice.md) | Conciliação e bloqueio escala |
| [fiscal-hub-resilience-drills.md](./fiscal-hub-resilience-drills.md) | Drills emergência |
| [fiscal-hub-cohort-rollout.md](./fiscal-hub-cohort-rollout.md) | Coortes RO |
| [fiscal-hub-mutating-approval.md](./fiscal-hub-mutating-approval.md) | GO/NO-GO mutantes |
| Este documento | Ops/suporte/comercial |

## 9. Estado para disponibilidade geral (GA)

GA **somente leitura** requer:

- [ ] Coorte C3 estável  
- [ ] Dois ciclos de conciliação MATCHED/ADJUSTED  
- [ ] On-call e N1/N2 treinados  
- [ ] Docs comerciais alinhados a este arquivo  
- [ ] Kill switches ensaiados  
- [ ] Evidência comercial SERPRO em GO  

GA **mutante** é **posterior** e por operação (16.10), nunca implícito no GA RO.
