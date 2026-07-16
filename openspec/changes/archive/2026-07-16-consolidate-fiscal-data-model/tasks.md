## 1. Pré-condições e baseline verificável

- [x] 1.1 Confirmar que `build-complete-fiscal-monitoring-hub` está estável, com specs sincronizadas, e atualizar o mapa físico desta change se o schema tiver mudado.
- [x] 1.2 Inventariar migrations aplicadas e pendentes em cada ambiente e resolver migrations redundantes ou condicionais antes da refatoração.
- [x] 1.3 Gerar dicionário do PostgreSQL efetivo com tabelas, colunas, tipos, FKs, ações de delete, uniques, índices, checks, sequências e volume por tabela.
- [x] 1.4 Produzir matriz origem-destino para cadastro, tenancy, documentos, cursores, outbound, SERPRO, monitoramento e guias, indicando a autoridade canônica e o legado correspondente.
- [x] 1.5 Produzir matriz de funcionalidades e contratos existentes: auth/TOTP, memberships/troca de escritório, clientes, contatos, A1, capturas, import, catálogo/download/export, outbound, monitoramentos, guias, dashboards, settings e plataforma.
- [x] 1.6 Capturar baseline sanitizado de contagens, órfãos, duplicidades, referências cruzadas, hashes, NSUs, versões correntes, totais monetários e consumo por período.
- [x] 1.7 Capturar snapshots de contratos HTTP e resultados das jornadas críticas sem incluir PFX, senhas, PEM, tokens, Consumer Secret ou Termo XML.
- [x] 1.8 Executar backup consistente de banco e objetos do cofre e comprovar restore coordenado em instância isolada, mantendo `VAULT_MASTER_KEY` fora do backup comum.
- [x] 1.9 Definir responsáveis, janela de shadow verification, critérios de tolerância e formato do relatório que aprovará ou reprovará o gate final.

## 2. Harness de migrations, reconciliação e PostgreSQL

- [x] 2.1 Criar testes PostgreSQL para migration do zero e upgrade a partir de uma cópia sanitizada do schema anterior.
- [x] 2.2 Criar biblioteca/comandos de backfill com checkpoint, idempotência, correlação origem-destino, dry-run e relatório de rejeições sanitizado.
- [x] 2.3 Criar comando de reconciliação que compare baseline e modelo-alvo por agregado e saia com erro em divergência não aprovada.
- [x] 2.4 Criar testes de schema para FKs compostas, uniques parciais, checks, tipos `timestamptz` e ações `RESTRICT/NO ACTION`.
- [x] 2.5 Remover das novas migrations tolerância silenciosa por `hasTable`, `hasColumn` ou `try/catch` e implementar pré-condições explícitas com diagnóstico.
- [x] 2.6 Implementar feature flags/adapters de corte por agregado e rollback lógico sem apagar novas escritas.
- [x] 2.7 Versionar payloads de jobs afetados e criar estratégia para drenar ou adaptar jobs enfileirados antes de cada corte.

## 3. Tenancy e agregado Cliente–Estabelecimento

- [x] 3.1 Criar chaves candidatas e FKs compostas necessárias para garantir `office_id` coerente nas relações de tenant prioritárias.
- [x] 3.2 Alterar o contexto `BelongsToOffice`/`CurrentOffice` para fail-closed e criar contexto privilegiado tipado e auditado para rotinas globais.
- [x] 3.3 Vincular a seleção de escritório à membership ativa do usuário e invalidá-la quando a membership for revogada.
- [x] 3.4 Adicionar testes de acesso sem tenant, ID de outro tenant, mesmo CNPJ em tenants distintos, job global e `PLATFORM_ADMIN` sem leitura fiscal herdada.
- [x] 3.5 Implementar `Client` canônico por `(office_id, root_cnpj)` e validar CNPJ textual numérico ou alfanumérico normalizado.
- [x] 3.6 Implementar Estabelecimentos múltiplos por Cliente, raiz coerente e unicidade parcial de uma matriz ativa.
- [x] 3.7 Migrar cadastros legados por escritório e raiz com mapa origem-destino, preservando contatos, atributos, estados, histórico e referências.
- [x] 3.8 Consolidar credenciais A1 na raiz, detectar conflitos de credencial ativa e provar que nenhum material secreto foi copiado ou exposto.
- [x] 3.9 Adaptar serviços, DTOs, policies, jobs e frontend ao agregado canônico sem quebrar criação atômica, listagem, detalhe, edição e elegibilidade.
- [x] 3.10 Reconciliar clientes, estabelecimentos, matrizes, contatos, atributos e credenciais e aprovar o gate local antes do corte cadastral.

## 4. Catálogo documental e cursores fiscais

- [x] 4.1 Criar ou ajustar a entidade canônica de documento fiscal imutável com bytes no cofre, SHA-256 e identidades oficiais protegidas.
- [x] 4.2 Implementar `document_acquisitions` para cada chegada, com correlação de fonte, método, execução/importação, cursor/página/NSU e validação.
- [x] 4.3 Substituir a unicidade genérica de aquisição por chaves idempotentes específicas de ADN, DistDFe, upload, pacote, SVRS e demais fontes suportadas.
- [x] 4.4 Separar `document_interests` semânticos da proveniência e implementar junção aquisição–interesse quando uma chegada comprovar múltiplos papéis.
- [x] 4.5 Garantir uma projeção tipada por documento/família, registrar versão do parser e remover autoridade de campos escalares legados após compatibilidade.
- [x] 4.6 Backfillar documentos, aquisições comprováveis, interesses, eventos e projeções sem modificar bytes, hashes ou datas; registrar limitações de proveniência.
- [x] 4.7 Preservar em custódia artefatos com mesma identidade e hashes diferentes e impedir que divergência conte como documento concluído.
- [x] 4.8 Consolidar cursor ADN por escritório, estabelecimento e ambiente, preservando NSU, estado, falhas, bloqueio e agendamento.
- [x] 4.9 Consolidar cursor DistDFe separadamente, preservando NSU/maxNSU, backoff, falhas e bloqueio e impedindo escolha automática entre duplicados ambíguos.
- [x] 4.10 Manter streams do autor/escritório e sequenciamento outbound separados dos cursores de distribuição do contribuinte.
- [x] 4.11 Adaptar jobs ADN/DistDFe para confirmar página, aquisições, interesses e cursor na mesma transação, sem avanço em decode ou persistência parcial.
- [x] 4.12 Testar reprocessamento, 20 páginas/requeue, locks, rate limit, quinta falha de decode, documento existente, hash divergente e independência entre canais.
- [x] 4.13 Reconciliar 100% dos bytes/hashes, chaves, NSUs, aquisições, interesses e projeções e aprovar o gate local do catálogo/capturas.

## 5. Recuperação outbound e operações fiscais

- [x] 5.1 Implementar caso de recuperação outbound como autoridade de identidade fiscal, prazo, urgência e resultado de completude.
- [x] 5.2 Implementar tentativas por fonte com request tag, decisão de roteamento, timestamps, custo e erro sanitizado.
- [x] 5.3 Subordinar solicitações de pacote e exchanges à tentativa correspondente e vincular o sucesso à aquisição documental validada.
- [x] 5.4 Migrar `ma_outbound_retrieval_requests` e estruturas relacionadas para casos/tentativas sem perder prazos, fontes, pacotes, divergências ou resultado temporal.
- [x] 5.5 Remover resultado `CAPTURED` da linguagem de urgência e adaptar enums, mappers, DTOs e filtros com compatibilidade temporária.
- [x] 5.6 Convergir tentativas mutantes específicas em `fiscal_mutation_operations` sem liberar operação bloqueada, ampliar allowlist ou reduzir gates de 2FA/aprovação.
- [x] 5.7 Testar fontes concorrentes, satisfação pelo primeiro XML válido, cancelamento idempotente de slots, divergência, histórico insuficiente e fluxo somente leitura.
- [x] 5.8 Reconciliar casos, tentativas, aquisições, prazos e resultados e aprovar o gate local outbound.

## 6. Plano de controle e ledger SERPRO

- [x] 6.1 Consolidar o catálogo em operação estável por `operation_key`, versões oficiais com vigência e regras de cobrança/preço versionadas separadamente.
- [x] 6.2 Migrar os dois catálogos SERPRO atuais para a autoridade canônica com mapa de chaves e bloqueio de vigências ou tiers sobrepostos.
- [x] 6.3 Padronizar `SerproConsumptionClass` e manter mapper compatível para valores persistidos pelo enum duplicado até o corte.
- [x] 6.4 Garantir ledger append-only e identidade idempotente para reserva, consumo, estorno, expiração e reconciliação.
- [x] 6.5 Separar agregados mensais globais e por escritório, tornando `office_id` obrigatório somente no plano de dados e proibindo linha híbrida por nulidade.
- [x] 6.6 Separar auditoria de plataforma e tenant ou impor identidade explícita de plano sem permitir acesso fiscal implícito ao `PLATFORM_ADMIN`.
- [x] 6.7 Adaptar serviços, jobs, dashboards e exports internos aos catálogos/agregados canônicos sem expor contrato ou credenciais SERPRO aos tenants.
- [x] 6.8 Reconciliar por período chamadas, unidades, reservas, estornos, valores, fatura global e atribuição tenant e aprovar o gate local SERPRO.

## 7. Monitoramento, guias, estados e retenção

- [x] 7.1 Criar identidade canônica de período e relação cliente–obrigação, separando-a de execução operacional e snapshot fiscal.
- [x] 7.2 Migrar runs para lifecycle operacional e snapshots versionados para situação/coverage, garantindo no máximo um snapshot corrente por identidade.
- [x] 7.3 Consolidar guia lógica, versões imutáveis e uma única autoridade de versão vigente sem ponteiros concorrentes ou ciclos.
- [x] 7.4 Consolidar estado de pagamento normalizado e evidências/verificações, preservando códigos oficiais brutos e histórico.
- [x] 7.5 Migrar stubs, competências, runs, snapshots, guias, pagamentos e mutações com mapa origem-destino e relatório de ambiguidades.
- [x] 7.6 Classificar os 103 enums em estado interno, código oficial, catálogo configurável ou motivo/erro e documentar owner, persistência e transições.
- [x] 7.7 Implementar `varchar + CHECK` para estados internos críticos, mapper `raw -> normalized/UNKNOWN` para externos e tabelas para catálogos editáveis.
- [x] 7.8 Padronizar grafia/casing dos valores internos e remover leitura de HTTP/configuração de enums puros por services/policies explícitos.
- [x] 7.9 Substituir `CASCADE` por `RESTRICT/NO ACTION` em documentos, aquisições, eventos, ledger, snapshots, operações, auditoria e evidências.
- [x] 7.10 Implementar inativação e fluxo explícito de retenção/expurgo; testar que exclusão de cadastro não elimina histórico fiscal ou financeiro.
- [x] 7.11 Adaptar módulos de monitoramento, guias, dashboards e exports e aprovar gates locais por contagem, estado atual, histórico e resultado funcional.

## 8. Corte progressivo e compatibilidade

- [x] 8.1 Centralizar cada escrita na autoridade canônica e impedir escrita independente nas estruturas de compatibilidade.
- [x] 8.2 Executar shadow verification por agregado durante a janela definida, registrando divergência sem payload ou segredo sensível.
- [x] 8.3 Cortar leitura de tenancy/cadastro após gate local e monitorar erros, latência, cardinalidades e isolamento.
- [x] 8.4 Cortar leitura de catálogo/capturas/outbound após gates locais e monitorar NSU, filas, locks, aquisições e divergências.
- [x] 8.5 Cortar leitura de SERPRO/monitoramento/guias após gates locais e monitorar totais, versões correntes e estados.
- [x] 8.6 Drenar ou adaptar jobs legados, provar que nenhum consumidor continua gravando no modelo antigo e manter rollback lógico disponível.
- [x] 8.7 Executar reconciliação incremental depois de cada corte e reverter o agregado em caso de diferença não aprovada.

## 9. Regressão funcional e segurança

- [x] 9.1 Executar suite backend completa no PostgreSQL, incluindo unit, feature, integração, concorrência, policies e contratos dos clientes oficiais com fixtures sanitizadas.
- [x] 9.2 Executar contratos HTTP comparando baseline e versão refatorada, documentando e aprovando qualquer mudança intencional.
- [x] 9.3 Executar testes de autenticação, CSRF, TOTP, papéis, memberships, troca/revogação de escritório e isolamento entre dois tenants com o mesmo CNPJ.
- [x] 9.4 Executar jornadas de clientes, estabelecimentos, contatos, campos adicionais, A1, elegibilidade e alertas sem rota ou payload de recuperação de segredo.
- [x] 9.5 Executar jornadas ADN, DistDFe, import, catálogo, filtros, download e export, comprovando bytes/hash imutáveis e ausência de salto de NSU.
- [x] 9.6 Executar jornadas outbound, monitoramentos fiscais, guias, caixa postal, dashboards e settings com os mesmos resultados do baseline.
- [x] 9.7 Executar jornadas de administração de plataforma comprovando separação do plano de controle e ausência de leitura fiscal herdada.
- [x] 9.8 Executar lint, análise estática, typecheck, testes unitários frontend e E2E das jornadas críticas afetadas.
- [x] 9.9 Executar varredura de respostas, logs, auditoria e exports para PFX, senha, chave privada, PEM, tokens, Consumer Secret, vault IDs indevidos e Termo XML.
- [x] 9.10 Executar teste de performance dos índices/queries críticos e verificar que locks de migration e backfills cabem na janela operacional.

## 10. Gate final pós-apply e retirada controlada

- [x] 10.1 Reexecutar o reconciliador integral e comparar contagens, chaves, hashes, NSUs, órfãos, referências cross-office, versões correntes, totais e consumo com o baseline.
- [x] 10.2 Confirmar que todas as funcionalidades da matriz possuem evidência de teste aprovada ou exceção formal com risco e responsável.
- [x] 10.3 Repetir backup e restore pós-apply em instância isolada e validar banco, objetos do cofre, referências e execução das jornadas mínimas restauradas.
- [x] 10.4 Gerar relatório final sanitizado com versão, migrations, período de shadow, resultados por agregado, divergências, exceções e decisão `APROVADO|REPROVADO`.
- [x] 10.5 Bloquear automaticamente a remoção do legado e retornar agregados afetados ao adapter anterior se o relatório for `REPROVADO` ou o restore não for comprovado.
- [x] 10.6 Validar esta change com `openspec validate consolidate-fiscal-data-model --json` e registrar o resultado junto ao relatório final.
- [x] 10.7 Após aprovação explícita, criar change separada para retirar leitores, writers, enums, colunas e tabelas legadas, com novo backup e sem apagar evidência fiscal/auditoria.
