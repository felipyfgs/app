## Context

A change `adicionar-orquestrador-portal-mei` introduz o sidecar Python (FastAPI + Celery + Playwright + Redis) e a persistência Laravel de tentativas. A revisão de stack concluiu que a divisão Nuxt / Laravel / MEI está correta, mas faltam regras normativas de fronteira: quem é SoT, o que cada fila pode executar, o que pode cruzar o HMAC e quando o artefato deixa o Redis. Esta change não reimplementa o orquestrador; ela aperta e verifica essas fronteiras.

Ownership a respeitar (não editar artefatos da change ativa peer):

| Área | Owner |
|---|---|
| Pacote `services/mei` ciclo de job/HMAC/Celery/Compose MEI | `adicionar-orquestrador-portal-mei` |
| Fronteiras SoT/redact/vault sync/proibições de stack | esta change |
| SPA / Sanctum / tenancy / SERPRO / vault | Laravel estável + esta change só nos pontos de orquestração MEI |

## Goals / Non-Goals

**Goals:**

- Fixar responsabilidades por tecnologia de forma testável.
- Garantir Postgres como SoT durável e Redis MEI como estado efêmero.
- Impedir vazamento de PII/CNPJ no sidecar e de prova fiscal fora do vault.
- Manter Horizon e Celery com papéis distintos e documentados.
- Coordenar com a upstream sem duplicar Dockerfile/Compose do MEI.

**Non-Goals:**

- Trocar FastAPI por outro framework, Celery por Arq/RQ, ou embutir Playwright no PHP.
- Unificar filas Horizon e Celery.
- Expor MEI na internet, colocar Sanctum no sidecar ou dar DB/vault ao Python.
- Habilitar portal live, captcha pago, sessão Gov.br ou mutações fiscais.
- Redis dedicado exclusivo ao MEI em produção (avaliar depois, se carga exigir).
- Parecer jurídico sobre automação de portais.

## Decisions

1. **Manter o triângulo Nuxt → Laravel → MEI.**  
   Alternativa rejeitada: SPA chamar MEI direto (quebra tenancy/HMAC e amplia superfície).  
   Alternativa rejeitada: Laravel fazer Playwright (mistura runtime, timeout e blast radius no Horizon).

2. **Horizon = domínio; Celery = browser.**  
   Jobs SERPRO/SEFAZ/fiscal continuam no Horizon. Sessões de portal ficam no Celery.  
   Alternativa rejeitada: um único worker PHP “chama Python subprocess” sem contrato HTTP — perde cancel/resume/artefato padronizado e health isolado.

3. **SoT = Postgres; Redis = efêmero.**  
   `mei_automation_attempts` (e projeções Laravel) são a verdade durável. Chaves `mei:job:*` / idempotency / replay no Redis do MEI expiram.  
   Laravel MUST sincronizar status/resultado da tentativa em intervalo de poll menor que o TTL e MUST marcar falha de sync se o job sumir do Redis antes da ingestão.  
   Alternativa rejeitada: Postgres no sidecar (segunda SoT e tenancy no Python).

4. **Allowlist + redact no Laravel, antes do HMAC.**  
   Só campos necessários à operação passam no `input`. CNPJ/identificadores sensíveis, se indispensáveis ao portal, ficam no mínimo necessário e nunca em metadata pública/logs do MEI.  
   Alternativa rejeitada: confiar só na redaction do Python (tarde demais; o payload já cruzou a fronteira).

5. **Artefato → vault é fronteira obrigatória.**  
   Fluxo: poll → download HMAC → validar tipo/tamanho/digest → `SecureObjectStore` / evidência fiscal → atualizar tentativa. Conteúdo bruto não permanece no Redis/disco MEI além do TTL.  
   Alternativa rejeitada: SPA ser a dona do download do artefato.

6. **Compose/rede do MEI permanece na upstream.**  
   Esta change apenas valida a fronteira: sem port publish, só rede interna, health/ready. Não reescreve o Dockerfile da peer change.

7. **Exceção transversal justificada (1 capability).**  
   `mei-stack-boundaries` corta Nuxt/API/MEI/ops, mas o resultado é um único contrato de fronteira — não cabe dividir sem fragmentar requisitos que precisam ser verificados juntos.

## Risks / Trade-offs

- [Duplicar trabalho com a upstream] → ownership explícito; esta change não toca ciclo Celery/HMAC base nem Compose MEI; só client-side Laravel (redact/sync/vault) e asserts de fronteira.
- [TTL Redis estoura antes do poll] → `poll_interval` e budget de sync < TTL; tentativa fica `SYNC_LOST` / reenqueue só se não houve submissão.
- [Allowlist demais rígida quebra operação futura] → allowlist por `operation` versionada; campos novos exigem change explícita.
- [Dois brokers no mesmo Redis host] → DBs lógicos distintos (`/4` vs `/0`/`1`); métricas/alertas separados; Redis dedicado fica fora do escopo.

## Migration Plan

1. Aplicar após (ou em coordenação com) `apply` da upstream para client/attempts existentes.
2. Ligar redact + sync + vault ingest com flags MEI ainda OFF.
3. Testes de contrato: payload rejeitado, sync antes do TTL, artefato no vault, ausência de cliente MEI no Nuxt.
4. Rollback: desligar flags; tentativas e evidências já gravadas permanecem auditáveis; Redis MEI pode ser flushado sem perda de SoT.

## Mapa de dependências

```text
adicionar-orquestrador-portal-mei (C0)
  specs ──coordenada──► alinhar-fronteiras-responsabilidades-stack (C1)
  apply ──bloqueante──► tasks de redact/sync/vault desta change
```

- Paralelismo: specs/design desta change após `specs` da upstream; implementação Laravel de fronteira após client+attempts da upstream.
- Rollout: upstream sobe containers MEI; esta change endurece o lado Laravel e os testes de fronteira.
- Rollback independente: desligar ingestão portal/flags; não remove o sidecar.

## Open Questions

Nenhuma bloqueante. Redis dedicado ao MEI fica como follow-up operacional se houver contenção.
