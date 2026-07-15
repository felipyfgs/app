## Por que

O escritório não precisa concentrar a captura das NF-e 55 e NFC-e 65 imediatamente após a descoberta: o objetivo operacional informado é disponibilizar os XMLs até o dia 1 do mês seguinte. Distribuir o trabalho ao longo do mês reduz pressão sobre a SVRS, evita rajadas perto do fechamento e cria tempo para `autXML`, upload e pacotes oficiais resolverem a maior parte das pendências.

## O que muda

- Adicionar um agendador mensal orientado a prazo para documentos de saída, calculando `due_at` no fim do dia 1 do mês seguinte no fuso do escritório.
- Definir uma meta interna anterior ao prazo final, com margem para contingência, sem tratar o dia 1 como prazo legal presumido.
- Espalhar as capturas de forma determinística e justa entre escritórios, raízes, modelos e competências, sempre subordinadas aos budgets do canal.
- Aguardar uma janela de acomodação para que vault, `autXML`, emissão, XML/ZIP ou pacote oficial satisfaçam a chave antes de consumir o portal SVRS.
- Criar faixas de urgência por tempo restante e capacidade prevista, aumentando alertas e contingência — nunca a taxa automática da SVRS.
- Limitar novas tentativas SVRS da mesma chave e eliminar retries rápidos que desperdiçam exchanges.
- Prever se o backlog cabe na capacidade segura restante até a meta interna; excesso será sinalizado cedo e encaminhado à importação assistida.
- Produzir visão mensal de completude, pendências, risco de prazo, capacidade e fontes de captura para operação do escritório.
- **BREAKING operacional:** substituir o backoff SVRS de 15 minutos/1 hora/6 horas/12 horas e cinco tentativas por cadência diária orientada ao prazo, com no máximo duas transações remotas por chave antes da contingência.

## Capacidades

### Novas capacidades

- `outbound-xml-deadline-scheduling`: cálculo de prazo, faixas de urgência, janela de acomodação, fila gradual e política de tentativas por chave.
- `outbound-xml-capture-capacity`: previsão de capacidade segura, distribuição justa do budget e escalonamento antecipado para contingência.

### Capacidades modificadas

- `outbound-xml-ingestion`: reconciliar a satisfação do prazo por qualquer fonte válida e cancelar trabalho remoto ainda não iniciado.
- `operations-dashboard`: exibir completude mensal, risco de prazo, capacidade restante e necessidade de contingência.
- `frontend-dashboard-experience`: apresentar calendário/faixas de urgência e impedir que urgência gere retry ou aumento de taxa indevido.
- `xml-delivery`: permitir preparar a entrega/exportação mensal somente com indicação explícita de completude e pendências.

## Impacto

- **Backend:** scheduler Laravel, jobs Horizon, cálculo de prazo/capacidade, campos de agenda e agregações mensais em PostgreSQL/Redis.
- **Frontend:** visão de fechamento mensal, filtros por competência/cliente/modelo, risco e ações assistidas usando o dashboard existente.
- **Operação:** captura automática contínua e calma; aumento de urgência aciona pessoas/fontes alternativas, não rajada remota.
- **Integrações:** respeita o governador compartilhado de `add-resilient-svrs-nfe55-outbound-xml-retrieval` e os canais de `add-svrs-nfce-outbound-xml-retrieval` e `add-office-autxml-and-bulk-xml-import`.

## Não-objetivos

- Declarar o dia 1 como obrigação legal nacional; ele será SLA operacional configurado do escritório.
- Aumentar automaticamente limite, concorrência ou frequência da SVRS quando o prazo se aproximar.
- Garantir completude apenas com o portal; ignorar bloqueio/circuit breaker; ou usar IP/certificado alternativo como contingência.
- Criar portal para cliente final, enviar documentos externamente ou alterar emissão/cancelamento fiscal.
- Consultar chaves inexistentes, varrer numeração ou repetir indefinidamente a mesma chave.
