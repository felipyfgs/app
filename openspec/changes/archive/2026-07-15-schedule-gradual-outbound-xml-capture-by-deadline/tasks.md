## 1. Dependências e preparação

- [x] 1.1 Confirmar que o governador compartilhado da SVRS está implementado e testado antes de habilitar dispatch desta change.
- [x] 1.2 Inventariar scheduler, orquestrador, retries e estados existentes nas changes NF-e/NFC-e/autXML.
- [x] 1.3 Registrar o dia 1 como SLA operacional informado pelo escritório, sem qualificá-lo como prazo legal.
- [x] 1.4 Definir ordem de aplicação/sincronização das changes ativas para não manter duas políticas de retry SVRS.
- [x] 1.5 Manter auto-queue e tráfego real desligados durante migrations, backfill e modo sombra.
- [x] 1.6 Executar backup/restore antes das migrations em ambiente com dados fiscais reais.

## 2. Configuração e modelo de dados

- [x] 2.1 Criar configuração tipada de timezone, dia/hora do SLA, buffer interno, acomodação e utilização automática de 60%.
- [x] 2.2 Impedir buffer inferior a 24 horas e prazo posterior ao fim do dia 1 no MVP.
- [x] 2.3 Adicionar `due_at`, `target_at`, `deadline_source`, `urgency_band` e `deadline_status` às pendências/recuperações adequadas.
- [x] 2.4 Adicionar `next_attempt_at`, contagem de transações por chave e timestamps de planejamento/dispatch.
- [x] 2.5 Adicionar projeção/snapshot de demanda, capacidade, folga, conclusão estimada e risco por coorte/competência.
- [x] 2.6 Adicionar estado de prontidão mensal e referência do manifesto à exportação.
- [x] 2.7 Criar índices para seleção por `office_id`, competência, `due_at`, faixa, raiz, modelo e agenda.
- [x] 2.8 Criar uniques que impeçam slots/jobs duplicados para a mesma chave/tentativa.
- [x] 2.9 Garantir timestamps UTC no banco e timezone do escritório somente na regra/exibição.
- [x] 2.10 Implementar rollback não destrutivo preservando aquisições, XMLs, tentativas e exports existentes.

## 3. Domínio de prazo e competência

- [x] 3.1 Implementar value objects para competência, SLA operacional, `due_at`, `target_at` e faixa.
- [x] 3.2 Calcular prazo definitivo pela data de autorização fiscal validada.
- [x] 3.3 Calcular prazo provisório pelo ano/mês da chave quando o XML ainda não existe.
- [x] 3.4 Recalcular prazo provisório após ingestão sem mover documento para outro tenant.
- [x] 3.5 Marcar como `OVERDUE` documento descoberto depois de `due_at` sem criar rajada.
- [x] 3.6 Implementar faixas `PLANNED`, `ATTENTION`, `CONTINGENCY`, `OVERDUE` e `CAPTURED`.
- [x] 3.7 Promover a `ATTENTION` quando faltarem sete dias ou a folga cair abaixo do limiar.
- [x] 3.8 Promover a `CONTINGENCY` por meta alcançada, janela final ou capacidade insuficiente.
- [x] 3.9 Testar virada de mês, ano, fevereiro, horário de verão histórico e timezone inválido com relógio falso.
- [x] 3.10 Implementar backfill de prazos históricos em lotes idempotentes e sem acesso externo.

## 4. Janela de acomodação e roteamento de fontes

- [x] 4.1 Implementar acomodação padrão de 24 horas após `XML_PENDING`.
- [x] 4.2 Encurtar acomodação até 6 horas quando faltarem menos de sete dias para a meta.
- [x] 4.3 Dispensar acomodação em contingência/vencido sem dispensar governor ou breaker.
- [x] 4.4 Consultar vault, catálogo, emissão/importação, `autXML`, XML/ZIP e pacote oficial antes da SVRS.
- [x] 4.5 Cancelar slot e job SVRS não iniciado quando qualquer fonte válida satisfizer a chave.
- [x] 4.6 Preservar proveniência separada e resultado de prazo para cada aquisição.
- [x] 4.7 Impedir que XML divergente conte como concluído ou sobrescreva canônico.
- [x] 4.8 Gerar lote de contingência por escritório/raiz/modelo sem expor outro tenant.

## 5. Planejador de capacidade segura

- [x] 5.1 Definir interface `OutboundXmlCaptureCapacityPlanner` sem dependência de PFX.
- [x] 5.2 Ler budgets efetivos, breaker, cooldown, reservas e limites por raiz do governador.
- [x] 5.3 Calcular em exchanges o custo das primeiras e segundas tentativas elegíveis.
- [x] 5.4 Limitar auto-queue a 60% da capacidade nominal de cada janela.
- [x] 5.5 Não acumular capacidade ociosa como burst para janela futura.
- [x] 5.6 Descontar canário e transações já reservadas antes de planejar captura automática.
- [x] 5.7 Projetar capacidade até `target_at` por coorte, competência e raiz.
- [x] 5.8 Calcular folga absoluta/percentual, conclusão estimada e quantidade fora da capacidade.
- [x] 5.9 Marcar `CAPACITY_AT_RISK` sem aumentar taxa quando demanda exceder capacidade.
- [x] 5.10 Recalcular previsão após ingestão, reserva, falha, bloqueio, mudança de SLA ou relógio.
- [x] 5.11 Testar capacidade zero, breaker longo, limite por raiz e backlog maior que o mês.

## 6. Fila justa e agenda determinística

- [x] 6.1 Implementar ordenação por prazo, faixa, autorização e desempate estável.
- [x] 6.2 Alternar `office_id`, raiz e modelo selecionando uma chave por raiz por rodada.
- [x] 6.3 Garantir que uma raiz volumosa não monopolize a coorte.
- [x] 6.4 Priorizar primeiras tentativas sobre segundas tentativas.
- [x] 6.5 Gerar `next_attempt_at` com spread determinístico dentro dos slots seguros.
- [x] 6.6 Garantir que reinício com mesmo estado produza agenda equivalente.
- [x] 6.7 Replanejar slot perdido sem compactar fila nem criar compensação imediata.
- [x] 6.8 Impedir alteração de prioridade remota por request/usuário.
- [x] 6.9 Testar justiça com múltiplos escritórios, raízes, modelos e competências.

## 7. Política de tentativas SVRS

- [x] 7.1 Substituir backoff SVRS de 15 min/1 h/6 h/12 h pela política orientada ao prazo.
- [x] 7.2 Limitar cada chave a no máximo duas transações externas totais.
- [x] 7.3 Exigir intervalo mínimo de 24 horas entre primeira e segunda transação.
- [x] 7.4 Permitir segunda tentativa somente para resultado tipado recuperável e com capacidade sobrando.
- [x] 7.5 Impedir segunda tentativa após bloqueio, autenticação, identidade, assinatura, contrato ou resultado definitivo.
- [x] 7.6 Contabilizar exchanges efetivamente enviados mesmo quando a resposta não chega.
- [x] 7.7 Encaminhar a chave à contingência após esgotar tentativas sem marcá-la capturada/inexistente.
- [x] 7.8 Preservar regras próprias do DistDFe e impedir que esta política altere seus cursores/retries.
- [x] 7.9 Atualizar testes e documentação da recuperação NFC-e para remover a quinta tentativa.

## 8. Planner, dispatcher e Horizon

- [x] 8.1 Criar job periódico de planejamento que não materializa certificado nem reserva egress.
- [x] 8.2 Criar dispatcher frequente que seleciona apenas slots vencidos.
- [x] 8.3 Revalidar tenant, fonte, estado, flags, allowlist, breaker e orçamento antes de enfileirar.
- [x] 8.4 Manter job remoto de uma chave e payload mínimo derivado do servidor.
- [x] 8.5 Adquirir lock idempotente por chave/tentativa antes do dispatch.
- [x] 8.6 Reservar egress antes de materializar A1 pelo `SecureObjectStore`.
- [x] 8.7 Falhar fechado sem PFX em memória quando governor/coordenador estiver indisponível.
- [x] 8.8 Cancelar job pendente quando ingestão concorrente satisfizer a chave.
- [x] 8.9 Testar crash entre planejamento, dispatch, reserva, mTLS, vault e commit.
- [x] 8.10 Impedir retries automáticos do Horizon fora da agenda calculada.

## 9. Ingestão, completude e auditoria

- [x] 9.1 Registrar `captured_at`, fonte, prazo/meta e resultado no prazo após ingestão canônica completa.
- [x] 9.2 Marcar `CAPTURED` somente depois de vault, aquisição, documento e projeção confirmados.
- [x] 9.3 Reconciliar upload/autXML/pacote/SVRS com slots e jobs existentes pela chave/hash.
- [x] 9.4 Calcular completude somente sobre documentos conhecidos do escritório/competência.
- [x] 9.5 Impedir que resumo, XML inválido ou divergente conte como full capturado quando `nfeProc` for exigido.
- [x] 9.6 Auditar mudança de SLA, faixa, planejamento, fallback e resolução sem XML/chave completa em logs.
- [x] 9.7 Garantir isolamento por `office_id` em agregações, jobs, exports e manifestos.

## 10. API e autorização

- [x] 10.1 Expor resumo de competência com total conhecido, capturado, faixas, risco e fontes.
- [x] 10.2 Expor previsão de capacidade e conclusão sem dados de outro tenant.
- [x] 10.3 Expor detalhes de pendência e próximo passo com chave mascarada quando apropriado.
- [x] 10.4 Permitir a ADMIN com 2FA recente antecipar meta dentro dos limites configurados.
- [x] 10.5 Recusar postergação além do dia 1, buffer abaixo do mínimo e alteração de budget/coorte.
- [x] 10.6 Permitir a OPERATOR iniciar upload/pacote e confirmar exportação parcial.
- [x] 10.7 Manter VIEWER somente leitura.
- [x] 10.8 Aplicar sessão Sanctum, CSRF, policies e tenancy derivada em todos os endpoints.

## 11. Dashboard Nuxt

- [x] 11.1 Usar `/frontend-nuxt-stack` e o template fixado ao implementar a visão de fechamento.
- [x] 11.2 Criar filtros por competência, cliente, raiz, modelo, faixa e fonte.
- [x] 11.3 Exibir `target_at`, `due_at`, completude conhecida e conclusão estimada.
- [x] 11.4 Exibir capacidade automática de 60%, folga e quantidade em contingência.
- [x] 11.5 Distinguir urgência de falha técnica e breaker por texto, ícone e cor acessíveis.
- [x] 11.6 Em `ATTENTION`, preparar lote/ações sem retry remoto.
- [x] 11.7 Em `CONTINGENCY`/`OVERDUE`, tornar importação assistida a ação principal.
- [x] 11.8 Ocultar e bloquear alterações administrativas sem papel e 2FA recente.
- [x] 11.9 Impedir UI de aumentar frequência, antecipar cooldown ou postergar prazo indevidamente.
- [x] 11.10 Testar responsividade, teclado, foco, contraste, vazio, loading, erro e atualização.

## 12. Exportação mensal e manifesto

- [x] 12.1 Calcular estados `COMPLETE_KNOWN`, `PARTIAL_CONFIRMED` e `NOT_READY`.
- [x] 12.2 Integrar prontidão à exportação ZIP assíncrona existente.
- [x] 12.3 Exigir confirmação explícita de OPERATOR/ADMIN para exportar com pendências.
- [x] 12.4 Gerar manifesto auditado das ausências autorizadas sem inventar XML.
- [x] 12.5 Impedir VIEWER de confirmar entrega parcial.
- [x] 12.6 Manter ZIP/manifesto privados, temporários e restritos ao `office_id`.
- [x] 12.7 Não rotular `COMPLETE_KNOWN` como garantia de universo fiscal completo.

## 13. Observabilidade e testes

- [x] 13.1 Criar métricas de completude conhecida, prazo, faixas, capacidade, slots e fontes.
- [x] 13.2 Criar alertas antecipados de capacidade insuficiente e itens vencidos.
- [x] 13.3 Limitar cardinalidade e mascarar chave/CNPJ em logs, métricas e tracing.
- [x] 13.4 Testar cálculo mensal e faixas com relógio falso.
- [x] 13.5 Testar planner de 60%, justiça e determinismo com property/concurrency tests.
- [x] 13.6 Testar no máximo duas tentativas e intervalo de 24 horas.
- [x] 13.7 Testar que urgência nunca modifica governor ou breaker.
- [x] 13.8 Testar cancelamento por autXML/upload enquanto slot/job aguarda.
- [x] 13.9 Testar isolamento multi-tenant de dashboards, APIs, métricas e manifestos.
- [x] 13.10 Executar suíte backend/frontend, análise estática, migrations e rollback.
- [x] 13.11 Executar auditoria de segredos provando que planner/UI nunca acessam ou expõem PFX/PEM/senha.

## 14. Modo sombra, piloto e escala

- [x] 14.1 Executar backfill e planejamento em modo sombra sem dispatch externo.
- [x] 14.2 Comparar por um ciclo a demanda real com 60% da capacidade segura.
- [x] 14.3 Validar falsos riscos, justiça entre raízes e cancelamentos por fontes preferenciais.
- [x] 14.4 Testar contingência XML/ZIP/pacote e exportação parcial antes do auto-queue.
- [x] 14.5 Habilitar dispatch apenas para allowlist cujo transporte já passou pelos gates de segurança.
- [x] 14.6 Pilotar uma coorte/competência sem aumentar budgets existentes.
- [x] 14.7 Medir capturas por fonte, exchanges por XML, prazo, bloqueios e volume assistido.
- [x] 14.8 Ampliar escritórios/raízes gradualmente somente após ciclo sem bloqueio.
- [x] 14.9 Reavaliar a fração de 60% apenas por nova decisão/versionamento, nunca por auto-ramp.
- [x] 14.10 Documentar rollback: desligar planner/dispatcher, preservar estado e manter contingência.
