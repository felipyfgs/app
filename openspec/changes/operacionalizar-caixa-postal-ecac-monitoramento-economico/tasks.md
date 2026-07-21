## 1. N0 — Contratos, dados e defaults fail-closed

- [ ] 1.1 Criar migrations/models para configuração por office, estado de sync por cliente, itens normalizados do one-shot e referências/digests do artefato privado; adicionar testes de constraints, casts, chaves únicas e isolamento por office
- [ ] 1.2 Extrair codec versionado de `SOLICEVENTOSPJ132`/`OBTEREVENTOSPJ134` e parser estrito da matriz `""|"x"|AAMMDD`; cobrir `evento`/`eventValue`, campo inválido sem egress, linhas malformadas e CNPJ alfanumérico em Unit tests
- [ ] 1.3 Implementar/registrar cliente e adapter `caixa_postal.indicador` para `INNOVAMSG63`, persistindo apenas diagnóstico; testar contrato, chave do registry e invariante de que zero não reconcilia a mailbox
- [ ] 1.4 Adicionar enums/config de modos `ECONOMICO`/`DIARIO_COMPLETO`, horário/fuso, reconciliação padrão de 30 dias, detalhe automático zero e flags OFF; testar defaults e validação fail-closed

## 2. N1 — Captura one-shot, portfólio e custo

- [ ] 2.1 Refatorar `EventosAtualizacaoFlowService` para salvar o `dados` em `SecureObjectStore` antes do parsing, separar consumo remoto de processamento local e normalizar itens transacionalmente; testar crash após HTTP 200, retry exclusivamente local, deduplicação e retenção sanitizada
  - Depende de: 1.1, 1.2
- [ ] 2.2 Implementar builder office-scoped de contribuintes elegíveis usando CNPJ completo de `establishments`, ordenação/chunking determinístico até 1.000 e mapeamento NI→client; testar procuração, cliente inativo, NI desconhecido, alfanumérico e ausência de vazamento entre offices
  - Depende de: 1.1, 1.4
- [ ] 2.3 Implementar guard de orçamento e política LISTAR/DETALHE por modo, preservando fonte `OFFICIAL|SHADOW|UNKNOWN` e bloqueando antes do egress; testar orçamento insuficiente, custo desconhecido, aviso shadow e cap de detalhe zero no econômico
  - Depende de: 1.4

## 3. N2 — Direcionamento, scheduler e reconciliação

- [ ] 3.1 Implementar processor E0601 que classifica vazio/`x`/data, mantém eventos do dia corrente pendentes, direciona uma LISTAR idempotente após fechamento do dia e só avança a data reconciliada após sucesso; testar várias mensagens na mesma data, repetição, falha LISTAR e item negado isolado
  - Depende de: 2.1, 2.2, 2.3
- [ ] 3.2 Implementar scheduler e jobs Horizon por escritório/lote com locks, ETA/TTL, polling sem `sleep`, cooldown 429, retomada e métricas sanitizadas; testar no-overlap, still-processing, protocolo expirado, limite diário e fuso `America/Sao_Paulo`
  - Depende de: 2.1, 2.2
- [ ] 3.3 Implementar preview/serviços de bootstrap e reconciliação periódica: todos os clientes não inicializados, 30 dias no econômico e todos diariamente no completo; testar idempotência, catch-up após indisponibilidade, páginas previstas e orçamento
  - Depende de: 2.2, 2.3

## 4. N3 — APIs tenant-scoped e consulta sob demanda

- [ ] 4.1 Criar endpoints Sanctum office-scoped de configuração, estado, preview e confirmação de sync com idempotency key e jobs assíncronos; adicionar Feature tests de permissões, ausência de `office_id`, cliente estrangeiro, bootstrap confirmado e resposta sem payload/PII
  - Depende de: 3.1, 3.2, 3.3
- [ ] 4.2 Integrar DETALHE sob demanda para mensagem sem corpo e ampliar o estado da mailbox com cobertura, última verificação gratuita/paga, próxima execução, reconciliação e bloqueios; testar preview/confirmação de custo, uma run por ISN e empty states da API
  - Depende de: 2.3, 3.3
- [ ] 4.3 Adicionar recuperação local de runs com resultado remoto recebido e processamento pendente, acionável por job/comando seguro sem egress; testar replay do artefato, item parcialmente processado e erro quando o artefato privado não está disponível
  - Depende de: 2.1, 3.1

## 5. N4 — Inbox operacional e testes Web

- [ ] 5.1 Adicionar tipos/composable e card compacto em `/monitoring/mailbox` com modo, cobertura, última/próxima verificação, reconciliação, custo e ação visível “Atualizar agora”; implementar modal preview→confirmação e Vitest dos estados nunca sincronizada, vazia após sucesso, saudável, atrasada, bloqueada e falha
  - Depende de: 4.1
- [ ] 5.2 Integrar corpo sob demanda, aviso de preço `SHADOW|UNKNOWN`, lista de clientes com `x` e copy do indicador como diagnóstico; adicionar Vitest de cap zero automático, confirmação DETALHE, orçamento bloqueado e indicador zero sem promessa de completude
  - Depende de: 4.1, 4.2

## 6. N5 — Gates API e Web

- [ ] 6.1 Rodar gates API completos no Compose: `composer validate --strict --no-check-publish`, `vendor/bin/pint --test` e `php artisan test`, registrando zero falhas na área mailbox/eventos
  - Depende de: 1.1, 1.2, 1.3, 1.4, 2.1, 2.2, 2.3, 3.1, 3.2, 3.3, 4.1, 4.2, 4.3
- [ ] 6.2 Rodar gates Web completos: `pnpm run lint`, `pnpm run typecheck`, `pnpm run generate`, `pnpm run test`, `pnpm run test:fidelity` e `pnpm run test:artifacts`
  - Depende de: 5.1, 5.2
## 7. N6 — Gate final de prontidão

- [ ] 7.1 Validar `docker compose -f docker-compose.yml config --quiet`, specs canônicas e esta change com OpenSpec strict; confirmar que não surgiram serviços `mei`/`mei-worker`, flags ON, segredo ou egress live nos testes
  - Depende de: 6.1, 6.2
