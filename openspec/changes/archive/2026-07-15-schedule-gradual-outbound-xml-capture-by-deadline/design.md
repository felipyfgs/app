## Contexto

As changes de captura de saída já fornecem descoberta de chave, `XML_PENDING`, recuperação SVRS, `autXML`, upload XML/ZIP, pacote oficial, vault e catálogo. O agendamento atual é técnico: cria jobs elegíveis e, em falhas transitórias, usa backoff curto. Ele não considera o fechamento mensal nem responde se o backlog cabe na capacidade conservadora do portal.

O negócio informou que o XML pode ser disponibilizado ao escritório até o dia 1 do mês seguinte. Este design trata isso como SLA operacional interno, não como afirmação de prazo legal. A estratégia é usar o tempo disponível: fontes sem custo de egress primeiro, fila SVRS diluída durante o mês e contingência humana antecipada quando a capacidade não for suficiente.

Esta change depende da governança de `add-resilient-svrs-nfe55-outbound-xml-retrieval`. Ela não pode aumentar os budgets do portal, ignorar breaker nem ativar um canal antes de seus gates de segurança. Também substitui, no comportamento efetivo, os retries rápidos definidos inicialmente em `add-svrs-nfce-outbound-xml-retrieval`.

## Objetivos / Não-objetivos

**Objetivos:**

- Capturar NF-e 55 e NFC-e 65 automaticamente, em ritmo contínuo e baixo, antes do fechamento mensal.
- Priorizar `autXML`, vault e importações antes de consumir a SVRS.
- Distribuir capacidade de forma justa sem uma raiz ou escritório monopolizar o egress.
- Detectar cedo quando o backlog não cabe e iniciar contingência sem rajada.
- Tornar completude, prazo e fonte de resolução auditáveis por competência.

**Não-objetivos:**

- Definir obrigação fiscal externa, enviar arquivos ao cliente final ou criar portal de cliente.
- Aumentar taxa por urgência, burlar cooldown ou garantir completude apenas com a SVRS.
- Repetir indefinidamente uma chave, varrer numeração ou consultar sem chave conhecida.
- Alterar os budgets, breaker, mTLS, cofre ou validações criptográficas das changes de transporte.

## Decisões

### D1 — Prazo por mês de autorização e fuso do escritório

Para documento autorizado no mês `M`, `due_at` será `23:59:59` do dia 1 do mês seguinte no timezone do escritório, inicialmente `America/Sao_Paulo`. A meta interna `target_at` será 48 horas antes de `due_at`; esse buffer será configuração operacional somente de deploy, nunca menor que 24 horas no MVP.

O prazo será derivado da data de autorização fiscal validada. Enquanto houver somente chave, ano/mês da chave poderá inicializar uma estimativa, marcada como provisória e recalculada após ingestão. Documento descoberto após `due_at` nasce `OVERDUE`, sem gerar rajada.

Alternativa rejeitada: prazo contado da descoberta, pois esconderia notas descobertas tarde e distorceria o fechamento da competência.

### D2 — Janela de acomodação antes da SVRS

Após a chave entrar em `XML_PENDING`, o roteador aguardará inicialmente 24 horas para fontes preferenciais: objeto já existente, emissão/importação, `autXML`, XML/ZIP ou pacote oficial. A janela termina antes se uma fonte satisfizer a chave.

Se faltarem menos de 7 dias para `target_at`, a janela poderá ser encurtada até 6 horas. Se o documento já estiver em `CONTINGENCY` ou `OVERDUE`, não haverá espera obrigatória, mas qualquer tentativa SVRS continuará sujeita ao slot normal; o fallback abre em paralelo.

### D3 — Faixas de urgência mudam ações, não taxa

Cada pendência terá uma faixa recalculada:

- `PLANNED`: mais de 7 dias até `target_at`;
- `ATTENTION`: até 7 dias de `target_at` ou capacidade projetada abaixo de 150% da demanda;
- `CONTINGENCY`: `target_at` alcançado, até 72 horas para `due_at`, ou demanda maior que a capacidade segura restante;
- `OVERDUE`: `due_at` expirou sem XML válido;
- `CAPTURED`: qualquer fonte válida concluiu ingestão.

A transição para `ATTENTION` antecipa alertas e preparação de lote assistido. `CONTINGENCY` abre importação/pacote oficial como ação primária e continua usando apenas slots SVRS normais. `OVERDUE` escala a inbox, mas não altera budgets.

### D4 — Só 60% da capacidade nominal para auto-queue

`OutboundXmlCaptureCapacityPlanner` lerá os budgets do `SvrsPortalEgressGovernor` e reservará no máximo 60% da capacidade nominal futura para captura automática. Os 40% restantes protegem canários, transações já reservadas, variações e operação restrita; não constituem convite para retry manual.

Capacidade segura será calculada em exchanges, não em chaves. Uma transação GET+POST custa dois exchanges; reservas, cooldowns, limites por raiz e janelas horária/diária reduzem a projeção. O planner não presume que capacidade não usada hoje acumule para amanhã.

Com o default de 50 exchanges/dia da coorte, o auto-queue planeja até 30 exchanges/dia, isto é, no máximo 15 transações completas, ainda limitado por intervalos, raízes e breaker. A taxa efetiva pode ser menor.

### D5 — Earliest-deadline-first com justiça entre raízes

O planner ordenará primeiro por `due_at` e faixa, mas selecionará no máximo um item por raiz a cada rodada, alternando `office_id`, raiz e modelo. Dentro da mesma raiz, usa autorização mais antiga e depois chave como desempate estável. O spread será determinístico para que reinício não compacte slots.

Itens novos não ultrapassam itens antigos da mesma competência sem motivo registrado. Nenhum tenant poderá consumir toda a capacidade compartilhada.

### D6 — Previsão de risco e contingência antecipada

Para cada competência/coorte, o sistema calculará:

- demanda: exchanges das primeiras tentativas elegíveis e segundas tentativas ainda justificadas;
- capacidade segura até `target_at`, considerando 60%, breaker, cooldown, intervalos e limites por raiz;
- folga absoluta e percentual;
- data estimada de conclusão;
- quantidade que exige outra fonte.

Quando demanda superar capacidade, o sistema não sobrecarrega a fila. Ele marca os itens menos atendíveis como `CAPACITY_AT_RISK`, gera lista por escritório/raiz e recomenda `autXML`, XML/ZIP ou pacote oficial. A previsão é recalculada após ingestão, reserva, falha, bloqueio e mudança de prazo.

### D7 — No máximo duas transações SVRS por chave

A primeira tentativa é posicionada após a janela de acomodação. Uma segunda tentativa só poderá ocorrer:

- pelo menos 24 horas depois;
- se o resultado anterior for explicitamente recuperável;
- se houver capacidade segura sem prejudicar primeiras tentativas;
- se o breaker estiver fechado;
- e sem ultrapassar duas transações externas totais para a chave.

Bloqueio, autenticação/identidade, assinatura, contrato alterado ou resposta definitiva não geram segunda tentativa. Falha antes de resposta ainda consome o exchange efetivamente enviado. Após o limite, a pendência fica disponível às demais fontes e à contingência.

Esta política substitui para SVRS o backoff rápido de 15 min/1 h/6 h/12 h e a quinta tentativa terminal. DistDFe conserva suas regras oficiais e cursor próprio.

### D8 — Scheduler em duas fases

Um planner periódico, sem PFX, recalcula prazos, faixas, capacidade e `next_attempt_at`. Um dispatcher frequente enfileira somente slots já planejados e revalida governor, breaker, fonte e tenancy antes do job. Planejar não reserva egress indefinidamente; a reserva atômica continua imediatamente antes da rede.

Se a janela planejada passar por indisponibilidade, o item volta ao próximo ciclo, sem fila de compensação. Jobs de uma chave permanecem idempotentes.

### D9 — Satisfação por qualquer fonte e trilha mensal

O XML é considerado disponível no prazo somente depois da ingestão canônica completa. A aquisição registra fonte e `captured_at`; a pendência mantém `due_at`, `target_at`, faixa final e se foi concluída antes do prazo. Upload, `autXML`, pacote oficial e SVRS têm o mesmo efeito de satisfação, preservando proveniências distintas.

Uma fonte válida cancela slots e jobs SVRS ainda não iniciados. Documento com hash divergente permanece em revisão e não conta como concluído.

### D10 — Fechamento e exportação com completude explícita

O dashboard apresentará por competência: total esperado conhecido, capturado, pendente, em risco, vencido e origem. Como o sistema conhece somente chaves descobertas/importadas, a métrica será chamada “completude sobre documentos conhecidos”, nunca garantia de total fiscal absoluto.

Uma exportação mensal poderá ser:

- `COMPLETE_KNOWN`: todos os documentos conhecidos elegíveis têm XML canônico;
- `PARTIAL_CONFIRMED`: operador confirmou exportar com pendências e recebe manifesto das ausências;
- `NOT_READY`: ainda processando ou com divergência crítica.

O ZIP não inventa XML e não bloqueia exportações parciais explicitamente confirmadas. O manifesto fica restrito ao escritório e é auditado.

### D11 — Autorização e alterações de SLA

ADMIN com 2FA recente poderá configurar o SLA operacional do escritório dentro dos limites de deploy, antecipar `target_at` e habilitar auto-queue após gates. Não poderá postergar além do fim do dia 1, aumentar budgets, furar breaker ou forçar prioridade remota. OPERATOR atua em contingência e confirma exportação parcial; VIEWER somente consulta.

Mudança de SLA recalcula agenda, mas não inicia rajada nem altera tentativas já consumidas.

## Riscos / Trade-offs

- **SLA informado pode não refletir obrigação legal aplicável** → rotular como política interna e permitir configuração/registro por escritório.
- **Backlog excede 60% da capacidade SVRS** → detectar cedo e encaminhar a `autXML`/importação, sem elevar taxa.
- **Chaves são descobertas tarde** → marcar risco/vencido imediatamente e abrir contingência em paralelo.
- **Completude conhecida não prova universo fiscal total** → nomenclatura explícita e reconciliação com sequência/perfis/fontes.
- **Duas tentativas podem deixar falhas transitórias sem nova prova** → priorizar fontes alternativas e preservar retry somente quando cabe; segurança do egress prevalece.
- **Fila justa pode atrasar uma raiz volumosa** → previsão por raiz e contingência antecipada, sem permitir monopolização.
- **Timezone/configuração incorreta altera prazo** → timezone validado, timestamps UTC no banco e exibição local auditável.

## Plano de migração

1. Manter auto-queue SVRS desligado e concluir o governador compartilhado.
2. Adicionar campos de prazo/agenda/faixa com migration compatível e backfill sem rede.
3. Calcular prazos históricos em modo sombra e comparar com estados existentes.
4. Implementar planner/capacidade/fair queue usando fake clock e fixtures, ainda sem dispatch.
5. Substituir retries rápidos do orquestrador SVRS por no máximo duas tentativas diárias orientadas ao prazo.
6. Integrar cancelamento por qualquer fonte e visão mensal/API/UI.
7. Executar um ciclo mensal em modo sombra, medindo demanda versus 60% da capacidade.
8. Habilitar dispatch automático somente para allowlist já aprovada nas changes de transporte.
9. Ampliar por coorte/escritório após ciclo sem bloqueio e com contingência testada.

Rollback: desligar planner/dispatcher novos, preservar `due_at`, tentativas, aquisições e XMLs e manter ingestão assistida. Não restaurar retries rápidos automaticamente.

## Questões em aberto

- O SLA operacional deve usar sempre 23:59 do dia 1 ou horário comercial específico por escritório?
- A margem interna de 48 horas deve aumentar quando o dia 1 cair em fim de semana/feriado?
- Qual volume mensal conhecido por raiz exige antecipar `autXML`/pacote oficial antes do primeiro ciclo?

