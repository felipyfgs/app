## ADDED Requirements

### Requirement: Autoridade canônica única por conceito
O sistema MUST manter uma única autoridade gravável para cada identidade, estado corrente, cursor, versão ou saldo de domínio e SHALL tratar estruturas temporárias de compatibilidade como derivadas e reconciliáveis.

#### Scenario: Escrita durante a transição
- **WHEN** uma operação ocorre enquanto estruturas antiga e nova coexistem
- **THEN** um único serviço canônico realiza a escrita e a estrutura derivada recebe o mesmo identificador de correlação sem aceitar alteração independente

#### Scenario: Autoridades divergentes
- **WHEN** a reconciliação encontra dois registros que reivindicam ser o estado ou a versão corrente da mesma entidade
- **THEN** o sistema bloqueia o corte daquele agregado, preserva ambas as evidências e exige resolução explícita

### Requirement: Integridade de tenant no banco e na aplicação
Toda relação entre dados do tenant MUST garantir que pai e filho pertencem ao mesmo `office_id`, e toda leitura de tenant MUST falhar quando não houver membership ativa e escritório explícito, salvo operação privilegiada tipada e auditada.

#### Scenario: Associação cruzada entre escritórios
- **WHEN** uma escrita tenta referenciar um pai de outro escritório usando um identificador válido
- **THEN** a aplicação rejeita a operação e a constraint do PostgreSQL impede a persistência mesmo se a validação da aplicação for contornada

#### Scenario: Contexto de escritório ausente
- **WHEN** um código de tenant consulta dados sem escritório ativo
- **THEN** a consulta falha de forma segura e não retorna linhas de todos os escritórios

#### Scenario: Mesmo CNPJ em escritórios distintos
- **WHEN** dois escritórios autorizados cadastram a mesma raiz ou o mesmo CNPJ completo
- **THEN** os registros permanecem distintos e nenhuma consulta, job, lock, exportação ou reconciliação mistura os tenants

### Requirement: Separação estrutural dos planos de controle e dados
O sistema MUST manter entidades globais de plataforma sem `office_id` e entidades atribuídas ao tenant com `office_id NOT NULL`, sem usar nulidade de `office_id` para alternar o plano da mesma linha.

#### Scenario: Agregação global e por tenant
- **WHEN** consumo SERPRO é consolidado no mês
- **THEN** a consolidação global e a atribuição por escritório são gravadas em autoridades separadas e reconciliáveis com o mesmo ledger append-only

#### Scenario: Administrador da plataforma
- **WHEN** um `PLATFORM_ADMIN` acessa uma função do plano de controle sem membership no tenant
- **THEN** o sistema permite somente os dados globais autorizados e não amplia a consulta para conteúdo fiscal dos escritórios

### Requirement: Restrições estruturais para estados e cardinalidades
O PostgreSQL MUST impor chaves, cardinalidades, intervalos e estados internos críticos por FKs, índices únicos, índices parciais e `CHECK constraints`; o PHP MUST usar enums ou value objects correspondentes sem transformar códigos oficiais evolutivos em listas fechadas destrutivas.

#### Scenario: Estado interno inválido
- **WHEN** uma escrita tenta persistir valor fora do conjunto fechado de um estado interno
- **THEN** a validação de domínio rejeita o valor e a `CHECK constraint` impede sua persistência direta

#### Scenario: Código oficial ainda desconhecido
- **WHEN** um provedor oficial retorna código novo em conteúdo bem-formado
- **THEN** o sistema preserva o valor bruto, mapeia o estado normalizado para `UNKNOWN` e não perde a evidência

#### Scenario: Duas versões correntes
- **WHEN** uma operação concorrente tenta marcar duas versões ou snapshots como correntes para a mesma identidade
- **THEN** a transação garante no máximo uma versão corrente

### Requirement: Retenção de evidência fiscal por exclusão restrita
Documentos, aquisições, eventos, ledger, snapshots, operações, auditoria e evidências MUST NOT ser removidos por cascata a partir de Cliente, Estabelecimento ou configuração; eventual expurgo SHALL exigir fluxo explícito, autorizado, auditado e reconciliado.

#### Scenario: Exclusão de cadastro com histórico
- **WHEN** um usuário tenta excluir cadastro que possui evidência fiscal ou financeira
- **THEN** o sistema preserva o histórico, rejeita a exclusão física e oferece somente a transição de ciclo de vida permitida

#### Scenario: Expurgo autorizado
- **WHEN** uma política de retenção aprovada executar expurgo elegível
- **THEN** o sistema registra escopo, autorização, contagens, hashes de controle e resultado sem apagar dados fora do escopo

### Requirement: Evolução de schema aditiva e reconciliável
A refatoração MUST criar o modelo-alvo antes de aposentar o legado, executar backfills idempotentes e reiniciáveis e produzir diagnóstico explícito para toda linha que não puder ser migrada.

#### Scenario: Reexecução do backfill
- **WHEN** um backfill é interrompido e executado novamente
- **THEN** linhas já confirmadas não são duplicadas, linhas pendentes continuam e os mesmos identificadores de origem apontam para o mesmo destino

#### Scenario: Linha ambígua
- **WHEN** uma linha de origem admite mais de um destino válido
- **THEN** o backfill não escolhe silenciosamente, registra a ambiguidade sem dado sensível e bloqueia o gate do agregado

#### Scenario: Pré-condição de migration ausente
- **WHEN** o schema efetivo não corresponde à pré-condição declarada
- **THEN** a migration falha com mensagem acionável em vez de ignorar a operação por `hasTable`, `hasColumn` ou exceção silenciosa

### Requirement: Gate final pós-apply
O sistema MUST concluir uma verificação final pós-apply antes de retirar estruturas legadas, cobrindo reconciliação de dados, isolamento multi-tenant, contratos de API, regressão funcional, invariantes fiscais, segredos, ledger, filas e restauração.

#### Scenario: Verificação integral aprovada
- **WHEN** contagens, identidades, hashes, NSUs, totais, versões correntes e jornadas funcionais coincidem com o baseline ou possuem exceção formal aprovada
- **THEN** o relatório final registra evidências e autoriza planejar a retirada posterior das estruturas legadas

#### Scenario: Divergência não explicada
- **WHEN** qualquer reconciliação ou jornada crítica diverge sem exceção aprovada
- **THEN** a retirada é bloqueada, a leitura retorna à autoridade compatível anterior e nenhuma evidência nova é descartada

#### Scenario: Restore não demonstrado
- **WHEN** backup e restore coordenados do banco e objetos protegidos não foram ensaiados com sucesso
- **THEN** o gate final permanece reprovado mesmo que os testes funcionais passem

### Requirement: Verificação no PostgreSQL de produção lógica
Constraints, índices parciais, concorrência e migrations MUST ser testados em PostgreSQL compatível com o ambiente do produto; testes SQLite MAY complementar unidades, mas MUST NOT ser a única evidência estrutural.

#### Scenario: Suite estrutural
- **WHEN** a change é validada antes do corte
- **THEN** migrations do zero, upgrade de uma cópia do schema anterior e testes de constraints são executados no PostgreSQL

#### Scenario: Concorrência em autoridade corrente
- **WHEN** transações concorrentes tentam criar matriz, credencial, cursor, snapshot ou versão corrente duplicada
- **THEN** somente uma transação obtém a autoridade e a outra recebe conflito tratável sem corrupção

