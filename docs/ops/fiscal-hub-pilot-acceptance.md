# Template de aceite — piloto produtivo restrito (1 office)

**Change:** `build-complete-fiscal-monitoring-hub` · task **16.5**  
**Atualizado:** 2026-07-15  
**Status do aceite:** **NÃO ASSINADO** (template)

## Propósito

Documentar o acordo operacional entre **software house (plataforma)** e **escritório piloto** para uso **somente leitura** do hub fiscal via Integra Contador, com escopo mínimo e orçamento controlado.

> Preencher uma cópia por piloto (ticket/GDrive). **Não** colar PFX, senhas, tokens ou Termo completo neste repositório.

## 1. Identificação

| Campo | Valor |
|-------|--------|
| Nome do escritório (Office) | |
| `office_id` interno | |
| CNPJ do escritório (máscara ok: `****`) | |
| Ambiente | HOMOLOGATION / PRODUCTION (piloto) |
| Período do piloto | de ____/____/____ a ____/____/____ |
| Responsável escritório (nome/e-mail) | |
| Responsável plataforma (nome/e-mail) | |
| Ticket / ID do aceite | |

## 2. Escopo aprovado

### 2.1 Contribuintes

| # | CNPJ (máscara) | Apelido interno | Módulos RO | Poderes/procuração confirmados |
|---|----------------|-----------------|------------|--------------------------------|
| 1 | | | | |
| 2 | | | | |
| 3 | | | | |

- Máximo recomendado no primeiro piloto: **1–5 contribuintes**.
- Mesmo CNPJ **não** deve ser monitorado em paralelo por outro office na mesma instância sem alinhamento.

### 2.2 Módulos somente leitura liberados

Marcar apenas o que a allowlist habilitará:

- [ ] Situação fiscal / Sitfis  
- [ ] Simples / MEI (consultas)  
- [ ] DCTFWeb / MIT (consultas)  
- [ ] Parcelamentos (consultas)  
- [ ] Caixa postal  
- [ ] Declarações (monitoramento)  
- [ ] Guias — **somente listagem/consulta** (sem emissão)  
- [ ] FGTS/eSocial — **cobertura parcial** (sem portal FGTS Digital)

### 2.3 Explicitamente **fora** do piloto

- [x] Operações mutantes (transmitir, aderir, encerrar, emitir guia)
- [x] Portal do contribuinte final
- [x] Scraping / Gov.br / sessão de navegador
- [x] Cobrança bancária da assinatura
- [x] Escala para outros offices / coortes GA

## 3. Autorizações e procurações

| Item | Status | Evidência (id/hash, sem XML) |
|------|--------|------------------------------|
| Contrato SERPRO software house ACTIVE | | |
| Evidência comercial SaaS (gate 1.3) | GO / NO-GO | |
| Autor do Pedido configurado | | |
| Termo de Autorização válido (sha256) | | |
| Token de procurador renovável / processo A3 | | |
| Procuração por serviço/contribuinte | | |
| Modo de certificado Autor | A1 gerenciado / A3 interativo / externo | |

## 4. Orçamento e limites

| Limite | Valor acordado |
|--------|----------------|
| Orçamento global mensal plataforma (unidades/R$) | *baixo — preencher* |
| Franquia do office piloto | |
| Cap de chamadas/dia (soft) | |
| `SERPRO_USAGE_SHADOW_MODE` | `true` até conciliação estável |
| `SERPRO_USAGE_COMMERCIAL_BLOCKING` | `false` no início do piloto |
| Allowlist de modules | `FEATURE_*_OFFICE_ALLOWLIST=<office_id>` |

**Compromisso:** divergência material ledger × fatura SERPRO **bloqueia escala** (doc 16.7).

## 5. Segurança e privacidade

O escritório declara que:

- [ ] Possui mandato/procuração válida dos contribuintes listados para os serviços liberados  
- [ ] Usuários do painel terão papéis mínimos (`VIEWER` quando possível)  
- [ ] Não solicitará à plataforma export de PFX, Termo completo ou tokens  
- [ ] Incidentes de acesso indevido serão reportados em até 24h úteis  

A plataforma declara que:

- [ ] Dados do piloto ficam sob `office_id` do escritório  
- [ ] `PLATFORM_ADMIN` não acessa conteúdo fiscal do tenant  
- [ ] Kill switch e rollback preservam evidências e ledger  
- [ ] Backup de instância existe; restore drill conforme política  

## 6. Critérios de aceite operacional

| # | Critério | Pass/Fail |
|---|----------|-----------|
| A1 | Onboarding Autor+Termo+poderes sem erro de cadeia | |
| A2 | Smoke RO Contratante/Autor/Contribuinte (16.6) | |
| A3 | Ao menos 1 ciclo de monitoramento com snapshot+evidência | |
| A4 | Zero mutação acidental (flags off) | |
| A5 | Consumo visível só do office; platform vê consolidado sanitizado | |
| A6 | Sem vazamento de segredo em logs amostrados | |
| A7 | Procedimento de suporte e escalonamento combinado | |

## 7. Rollback e saída

- Kill switch global ou remoção da allowlist encerra novas chamadas em minutos.  
- Dados e evidências **não** são apagados no rollback.  
- Escritório pode solicitar export permitido e encerramento (`SUSPENDED`/`CANCELED`).  

## 8. Assinaturas

| Papel | Nome | Data | Assinatura / OK |
|-------|------|------|-----------------|
| Plataforma (ops) | | | |
| Plataforma (produto/comercial) | | | |
| Escritório piloto (admin) | | | |

**Resultado do aceite:** ☐ APROVADO · ☐ APROVADO COM RESSALVAS · ☐ REPROVADO  

Ressalvas: _______________________________________________
