## Context

O schema atual foi construído por várias changes incrementais. O levantamento realizado antes desta proposta encontrou 49 migrations, 103 enums PHP e, no PostgreSQL local, 108 tabelas, 280 chaves estrangeiras, 85 constraints únicas e nenhuma `CHECK constraint`. Há 170 relações com `CASCADE`, inclusive em áreas de evidência fiscal, enquanto parte relevante das regras de tenant, estado e cardinalidade existe apenas na aplicação.

Os dados atuais também expõem uma divergência concreta: o domínio e a spec cadastral descrevem um Cliente por raiz com vários Estabelecimentos, e a base já contém uma raiz com dois estabelecimentos, mas um serviço ainda declara relação 1:1 e mantém `matrix_client_id` como segunda autoridade. Padrões equivalentes aparecem no catálogo documental, cursores, recuperação outbound, monitoramento, guias e SERPRO.

A refatoração atravessa dados fiscais imutáveis, ledger de consumo, autenticação multi-tenant e segredos. Portanto, ela não pode depender de uma migration destrutiva única nem de testes SQLite. O PostgreSQL é a fonte de verdade e o apply precisa preservar contratos funcionais enquanto troca a autoridade interna.

Esta change deve ser aplicada após a estabilização e sincronização de `build-complete-fiscal-monitoring-hub`. Se a change ativa alterar o schema antes do apply, o inventário e o mapa origem-destino deverão ser regenerados.

## Goals / Non-Goals

**Goals:**

- Definir uma única autoridade para cada conceito de domínio e remover duplicidade semântica.
- Garantir isolamento de escritório e cardinalidades críticas também no PostgreSQL.
- Preservar integralmente XML, hashes, NSUs, aquisições, evidências, auditoria, ledger e segredos.
- Separar plano de controle global e plano de dados por tenant de forma estrutural.
- Tornar estados e transições verificáveis com política uniforme de enums e constraints.
- Fazer migração aditiva, observável, idempotente e reversível até a aprovação final.
- Demonstrar, após o apply, que jornadas, contratos e resultados continuam íntegros.

**Non-Goals:**

- Alterar o stack Laravel/Nuxt/PostgreSQL/Redis ou introduzir novo cliente oficial externo.
- Criar funcionalidades fiscais, canais, mutações, portal de contribuinte ou cobrança não previstos nas specs existentes.
- Reinterpretar ou normalizar bytes XML já armazenados.
- Expor segredos, criar rota de recuperação de certificado ou materializar PFX/PEM em disco.
- Usar remoção em cascata como mecanismo de limpeza de evidência fiscal.
- Retirar estruturas legadas no mesmo deploy que cria e preenche o modelo-alvo.

## Decisions

### 1. Cada conceito terá uma autoridade canônica explícita

O modelo-alvo será organizado pelas autoridades abaixo. Tabelas de compatibilidade poderão existir durante a transição, mas não poderão continuar aceitando escrita independente após o corte.

| Área | Autoridade canônica | Estruturas derivadas ou subordinadas |
|---|---|---|
| Cadastro | Cliente por raiz no escritório | estabelecimentos por CNPJ completo, contatos, atributos e A1 da raiz |
| Tenant | membership ativa selecionada | contexto de requisição e referências compostas por `office_id` |
| Documento | documento fiscal imutável | aquisições, interesses, eventos e projeções tipadas |
| Sincronização | cursor por stream, estabelecimento e ambiente | execuções, páginas e falhas sem autoridade sobre o NSU |
| Outbound | caso de recuperação | tentativas por fonte, pacotes e aquisição que satisfez o caso |
| SERPRO | operação estável + versão oficial | preço/regra de cobrança, ledger append-only e agregados separados |
| Monitoramento | período + obrigação do cliente | execução operacional e snapshot fiscal versionado |
| Guia | guia lógica + versão vigente | verificações de pagamento e operações mutantes auditáveis |

Alternativa rejeitada: manter tabelas paralelas e definir precedência apenas em serviços. Isso deixa jobs, SQL operacional e futuras rotas livres para escolher autoridades diferentes.

### 2. Cliente representa a raiz; estabelecimento representa o CNPJ completo

`clients` manterá `office_id` e `root_cnpj` normalizado, único por escritório. `establishments` manterá `office_id`, `client_id`, CNPJ completo imutável e indicador de matriz com unicidade parcial para no máximo uma matriz ativa. A raiz do estabelecimento deverá coincidir com o Cliente. O A1 continuará pertencendo ao Cliente e será reutilizado somente em memória pelos canais elegíveis.

`matrix_client_id` e qualquer convenção “um Client por CNPJ completo” serão tratados como legado. O backfill agrupará registros pela raiz dentro do mesmo escritório, sem agrupar CNPJs iguais entre escritórios.

Alternativa rejeitada: manter um Cliente por filial. Ela duplica certificado, contato e estado da raiz e conflita com a spec e com dados já existentes.

### 3. Tenancy será fail-closed e reforçada por referências compostas

Consultas de modelos de tenant sem `CurrentOffice` válido deverão falhar, salvo contexto privilegiado explicitamente tipado para jobs de plataforma. Relações entre tabelas de tenant usarão chave candidata `(office_id, id)` no pai e FK composta no filho quando houver risco de associação cruzada.

A seleção ativa será vinculada a uma membership ativa do usuário, e não apenas a um `office_id`. Um `PLATFORM_ADMIN` não obterá leitura de dados fiscais por ausência de filtro; acesso de plataforma continuará em serviços próprios do plano de controle.

Alternativa rejeitada: confiar apenas em global scopes permissivos. Um contexto nulo transforma erro de programação em leitura ampla.

### 4. Planos de controle e de dados não compartilharão linhas polimórficas por nulidade

Catálogos, contrato e consolidação global SERPRO ficarão em tabelas globais sem `office_id`. Ledger e agregados atribuídos ao tenant ficarão em tabelas com `office_id NOT NULL`. Agregados mensais globais e por escritório serão relações distintas, ainda que alimentadas pelo mesmo ledger. Auditoria de plataforma e auditoria de tenant terão identidade de plano explícita.

Alternativa rejeitada: `scope=GLOBAL|TENANT` combinado com `office_id` anulável. O desenho é fácil de consultar incorretamente e viola a separação de planos definida no ADR 005.

### 5. Documento, chegada e interesse serão fatos diferentes

O documento canônico preservará bytes originais, SHA-256, identidade oficial e imutabilidade. Cada recepção será uma `document_acquisition`, mesmo quando os bytes já existirem, contendo fonte, método, cursor/página, execução ou item de importação, NSU quando aplicável e resultado de validação. A idempotência será específica da fonte; não haverá unicidade genérica que colapse chegadas legítimas iguais.

`document_interests` representará apenas a relação semântica estável entre documento, estabelecimento, papel fiscal e direção. Se uma aquisição provar mais de um interesse, uma junção associará ambos. Projeções NFS-e/NF-e/NFC-e/CT-e/MDF-e terão FK única para o documento canônico e nunca substituirão os bytes originais.

Alternativa rejeitada: manter proveniência dentro de `document_interests`. Isso mistura fato de transporte com significado fiscal e produz regras frágeis quando `role` ou NSU são nulos.

### 6. Cursores serão separados por principal e stream fiscal

ADN e DistDFe do contribuinte usarão um cursor canônico por `(office_id, establishment_id, environment, channel)`, com lock, falhas e NSU confirmados no mesmo agregado. A distribuição em nome do autor/escritório continuará separada porque usa outra identidade e outra cadeia de autorização. O sequenciamento outbound por número fiscal também continuará separado porque não é distribuição por NSU.

O cursor legado não será avançado depois do corte. A persistência da página, das aquisições e do novo NSU continuará atômica; falha de Base64/GZip impede avanço e o bloqueio após cinco falhas permanece.

Alternativa rejeitada: uma tabela universal de cursores com colunas anuláveis para todos os protocolos. Ela reduz tabelas, mas mistura locks, identidade, unidade de progresso e políticas incompatíveis.

### 7. Recuperação outbound será modelada como caso, tentativa e aquisição

O prazo e a necessidade pertencem ao caso de recuperação. Cada fonte consultada gera uma tentativa com request tag, decisão de roteamento, resultado sanitizado e custo. Solicitações de pacote ficam subordinadas à tentativa/fonte. O sucesso só ocorre quando uma aquisição canônica e validada satisfaz o caso; divergência permanece em quarentena e não conta como completude.

Operações mutantes específicas convergirão para `fiscal_mutation_operations` e suas tentativas, mantendo allowlist, aprovação, 2FA e flags. `urgency` não conterá resultados como “capturado”.

Alternativa rejeitada: continuar acrescentando colunas a `ma_outbound_retrieval_requests`. Isso transforma um pedido em prazo, tentativa, pacote, roteamento e resultado simultaneamente.

### 8. Catálogo SERPRO separará identidade, versão de wire e cobrança

Uma operação terá `operation_key` estável. Versões oficiais guardarão coordinates de API e vigência. Regras de cobrança/preço serão versionadas separadamente, sem dois catálogos competindo pela mesma chave. O ledger permanecerá append-only e cada reserva, consumo, estorno ou reconciliação terá identidade idempotente explícita. `SerproConsumptionClass` será a linguagem canônica; enum duplicado será aposentado após compatibilidade.

Alternativa rejeitada: fundir versão técnica e preço na mesma linha. Eles mudam em calendários diferentes e precisam de auditoria independente.

### 9. Monitoramento separará período, execução e resultado fiscal

`tax_periods` identificará competência/período. A relação do cliente com a obrigação guardará elegibilidade e estado corrente. A execução guardará somente lifecycle operacional; o snapshot versionado guardará situação fiscal, cobertura, payload sanitizado e ponteiro corrente protegido por unicidade parcial. Mutabilidade pertence ao catálogo da operação, não à execução.

Guias usarão uma autoridade de versão vigente, com histórico imutável. Estado de pagamento normalizado e evidência/verificação serão conceitos distintos. O modelo não apagará stubs ou versões até que cada linha tenha destino reconciliado.

Alternativa rejeitada: manter `is_current`, ponteiros em ambas as direções e estado duplicado. Autoridades múltiplas permitem ciclos e mais de uma versão corrente.

### 10. Política de enums será orientada pela natureza do valor

- Estado interno fechado: enum PHP com nome de domínio, coluna `varchar` e `CHECK` PostgreSQL.
- Código oficial evolutivo: valor bruto preservado e mapper para enum normalizado com fallback `UNKNOWN`.
- Catálogo configurável ou versionado: tabela, não enum.
- Código de erro/motivo interno: enum tipado e sanitizado.
- Estados de agregados diferentes não serão fundidos apenas porque compartilham valores.

Será padronizada uma grafia canônica para valores internos, com adapters temporários para valores legados. Enums puros não lerão configuração nem farão decisão HTTP; transições críticas terão policies testadas.

Alternativa rejeitada: enum nativo do PostgreSQL. Alterações operacionais e rollback são mais difíceis do que `varchar + CHECK`, sem benefício suficiente neste estágio.

### 11. Evidência fiscal usará retenção explícita e deleção restrita

Documentos, aquisições, eventos, ledger, snapshots, operações, auditoria e evidências não serão eliminados por cascata a partir de cadastro. FKs usarão `RESTRICT/NO ACTION`, salvo filhos puramente efêmeros ou de composição comprovada. Exclusão comercial será inativação/soft delete; eventual expurgo terá política, autorização, relatório e limites próprios.

Alternativa rejeitada: `CASCADE` generalizado. É conveniente em testes, mas transforma uma exclusão cadastral em perda silenciosa de evidência.

### 12. Migrations serão determinísticas e verificadas no PostgreSQL

Migrations novas não usarão `hasTable`/`hasColumn` ou `try/catch` silencioso para esconder divergência esperada. Pré-condições críticas falharão com diagnóstico. Índices parciais, FKs compostas e `CHECK constraints` terão testes de schema em PostgreSQL real. Timestamps novos usarão `timestamptz`; identificadores CNPJ continuarão texto normalizado.

O histórico já aplicado não será reescrito. A futura consolidação em baseline só poderá ocorrer depois que ambientes implantados estiverem inventariados e houver procedimento explícito de bootstrap.

### 13. Compatibilidade funcional será um gate, não uma expectativa

Antes do corte será gerada uma matriz entre funcionalidades e autoridades de dados: autenticação/TOTP, memberships e troca de escritório, clientes/estabelecimentos/contatos, A1 e elegibilidade, ADN/DistDFe, imports, catálogo/download/export, outbound, módulos de monitoramento, guias, caixa postal, dashboards, configurações tenant e administração de plataforma.

Para cada jornada haverá contrato de API e teste de regressão. A verificação final também reconciliará contagens, chaves, hashes, NSUs, totais monetários/consumo, versões correntes, órfãos e referências entre escritórios. O relatório deverá registrar resultado, evidência e exceções aprovadas. Sem aprovação, o rollback lógico mantém a leitura na autoridade anterior e estruturas legadas não são removidas.

## Risks / Trade-offs

- [Backfill associa registros ao agregado errado] → mapear por `office_id` + identidade fiscal, registrar tabela de correspondência e bloquear ambiguidades para revisão.
- [Dual-write produz divergência] → manter um único serviço escritor, transação comum e reconciliador contínuo; dual-write será temporário e observável.
- [Constraints bloqueiam dados legados válidos] → executar auditorias antes da constraint, corrigir explicitamente e usar `NOT VALID`/`VALIDATE CONSTRAINT` quando reduzir lock sem reduzir o gate.
- [Índices e FKs causam lock] → criar índices compatíveis com produção, medir plano/tempo e separar validação da criação quando aplicável.
- [Retirada prematura quebra jobs atrasados] → versionar payloads de job, drenar filas e aceitar leitura compatível durante a janela.
- [Regressão encoberta por SQLite] → executar suite estrutural e integração no PostgreSQL usado pelo produto.
- [Mudança SERPRO altera totais] → reconciliar ledger append-only, fatura global e atribuição tenant por período antes e depois.
- [Rollback depois de novas escritas perde dados] → rollback até o gate será lógico por feature flag/adapters; restauração física será apenas para incidente e será ensaiada.
- [Escopo muito amplo] → aplicar por agregados, cada um com gate local, mantendo o gate final transversal obrigatório.

## Migration Plan

1. **Pré-condição e congelamento do mapa:** sincronizar as specs da change fiscal ativa, inventariar migrations aplicadas/pendentes, gerar diagrama e dicionário do schema efetivo e congelar a matriz origem-destino.
2. **Baseline e recuperação:** produzir backup consistente, validar restore em instância isolada e capturar contagens, hashes, NSUs, órfãos, duplicidades, totais e contratos de API da versão anterior.
3. **Fundação aditiva:** criar chaves candidatas, tabelas canônicas, colunas de compatibilidade, `CHECK constraints` inicialmente validáveis e índices sem remover estruturas.
4. **Backfill idempotente por agregado:** migrar cadastro/tenant, documentos, cursores, outbound, SERPRO, monitoramento e guias em lotes reiniciáveis com relatório de rejeições.
5. **Compatibilidade e shadow verification:** centralizar escrita nos serviços canônicos, manter adapters de leitura legada quando necessários e comparar resultados novos/antigos em background.
6. **Corte progressivo de leitura:** ativar o modelo novo por agregado e ambiente, drenar jobs antigos e executar o gate local antes de avançar.
7. **Verificação final pós-apply:** rodar reconciliação completa, suite backend em PostgreSQL, contratos HTTP, isolamento multi-tenant, testes frontend e E2E das jornadas, invariantes de segredo, NSU, imutabilidade e ledger, além do ensaio de restore.
8. **Aprovação:** registrar relatório final. Qualquer divergência não explicada bloqueia aposentadoria e devolve a leitura ao adapter anterior.
9. **Retirada posterior:** somente em uma etapa/migration separada, remover escritas e estruturas legadas já sem leitores, preservando tabelas de mapeamento e evidência do processo pelo período definido.

### Rollback

Até a aprovação final, o rollback padrão será desativar o corte por feature flag e voltar a leitura para o adapter legado; migrations aditivas permanecem para não perder novas escritas. Cada backfill terá cursor próprio e poderá ser repetido. Em falha grave, o procedimento de restore validado recuperará banco e objetos do cofre de forma coordenada; o `VAULT_MASTER_KEY` continuará fora do backup comum. Não haverá `down()` destrutivo para tentar reconstruir evidência removida.

## Open Questions

- Definir a janela mínima de shadow verification e os responsáveis formais pela aprovação do relatório final antes do apply.
- Confirmar quais tabelas de custom fields representam definições reutilizáveis do escritório e quais são apenas atributos de um Cliente, para escolher entre definição+valor ou `client_attributes`.
- Confirmar o período de retenção de adapters/tabelas legadas após o gate e o procedimento de remoção em ambiente produtivo.
- Revisar, depois da sincronização de `build-complete-fiscal-monitoring-hub`, os nomes físicos finais de tabelas SERPRO, monitoramento e guias; as autoridades de domínio deste design permanecem estáveis mesmo que os nomes mudem.
