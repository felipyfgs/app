# ADR 005 — Plano de controle global vs plano de dados com `office_id`

## Status

Aceito (change `build-complete-fiscal-monitoring-hub`)

## Contexto

A base atual já persiste `office_id` em tabelas de negócio e resolve o escritório via membership (`CurrentOffice`, middleware `EnsureOfficeContext`), porém o produto foi operado mentalmente como painel de **um** escritório. O modelo de negócio confirmado é **SaaS multi-escritório**: a software house opera a plataforma; vários escritórios contábeis contratam o serviço e monitoram apenas seus contribuintes.

No Integra Contador existem três identidades:

1. **Contratante da API** — software house (contrato SERPRO, e-CNPJ mTLS, Consumer Key/Secret).
2. **Autor do Pedido de Dados** — escritório/procurador (Termo de Autorização, poderes).
3. **Contribuinte** — cliente final do escritório.

Misturar credenciais e fatura SERPRO no mesmo espaço de dados dos tenants, ou conceder a administradores da plataforma leitura fiscal implícita, viola sigilo fiscal, LGPD e o desenho comercial (uma fatura SERPRO agregada, rateio interno por tenant).

Relacionado: [ADR 002](./002-same-origin-architecture.md) (edge same-origin), [ADR 003](./003-secure-object-vault.md) (cofre de segredos).

## Decisão

### 1. Dois planos explícitos

| Plano | Escopo | `office_id` | Exemplos |
|-------|--------|-------------|----------|
| **Controle (global)** | Plataforma / software house | **Ausente** — o tipo da tabela expressa o escopo; **não** usar `office_id` opcional como atalho | `serpro_contracts`, `platform_memberships`, catálogo/versionamento de preços SERPRO, consolidação de fatura da plataforma, feature flags globais, health sanitizada do contrato |
| **Dados (tenant)** | Escritório contábil assinante | **Obrigatório** em toda linha de negócio | assinatura/plano do escritório, Autor do Pedido, Termo, tokens de procurador, procurações/poderes, clientes, execuções, evidências, consumo **atribuído**, documentos e projeções fiscais |

### 2. Recursos globais permitidos (lista fechada para o MVP desta change)

Somente os seguintes tipos de recurso podem existir sem `office_id` de tenant:

- **`serpro_contracts`** — identidade contratante, ambiente, estado, vigência, identificadores não secretos, referências ao `SecureObjectStore` (PFX/OAuth/tokens cifrados).
- **`platform_memberships`** (ou equivalente) — associação usuário ↔ papel de plataforma (`PLATFORM_ADMIN`).
- **Catálogo / preços SERPRO versionados** — soluções, serviços, classes faturáveis, vigências de preço da plataforma.
- **Consolidação de fatura** — agregados e conciliações da fatura SERPRO recebida pela software house (ledger original de uso por tenant permanece com `office_id`).
- **Flags e limites globais** — kill switches de plataforma, rate limits do contrato, circuit breakers por solução, feature flags de ambiente.
- **Metadados operacionais de instância** já existentes sem tenant (ex.: `instance_backup_runs`) — continuam de escopo de instância, não de escritório.

Qualquer novo recurso global exige ADR ou atualização desta decisão; **default = tenant-scoped com `office_id`**.

### 3. Recursos estritamente de tenant

Permanecem (ou passam a ser) com `office_id` obrigatório:

- assinatura / plano / estado comercial do escritório (`office_subscriptions` ou agregado);
- autorização SERPRO do escritório (Autor, Termo, token de procurador);
- procurações e poderes por contribuinte;
- consumo atribuído (`serpro_api_usage_entries` e reservas);
- clientes, estabelecimentos, cursores, documentos, exports, jobs de domínio fiscal, evidências e snapshots.

### 4. Papéis: tenant ≠ plataforma

| Papel | Escopo | Acesso fiscal |
|-------|--------|----------------|
| `ADMIN` / `OPERATOR` / `VIEWER` | Membership no `Office` | Conforme matriz do escritório ativo |
| `PLATFORM_ADMIN` | Membership de plataforma | **Não** herda leitura/escrita de conteúdo fiscal de tenants; apenas operações de controle (tenants lifecycle sanitizado, contrato metadados, fatura consolidada, flags) |

Impersonação genérica de tenant por `PLATFORM_ADMIN` **não** faz parte desta decisão. Qualquer break-glass futuro exige change própria (consentimento, prazo, auditoria reforçada).

### 5. Autoridade de tenant

- Nunca confiar em `office_id` do body/query/header do cliente HTTP.
- O escritório ativo deriva de membership válida (e, quando implementado, de seleção explícita auditada).
- Jobs e filas carregam identificadores internos e **revalidam** `office_id` antes de efeitos externos ou de mutação.
- Policies e global scopes de tenant aplicam-se ao plano de dados; services de plano de controle usam guards/policies próprios de plataforma.

### 6. Alternativas rejeitadas

- **Duplicar contrato SERPRO por escritório** — multiplica segredos, contraria fatura única e o modelo comercial.
- **Deploy isolado por escritório** — impede escala do contrato e operação central.
- **`office_id` nullable em tabelas mistas** — esconde escopo e facilita vazamento por query incompleta.

## Consequências

- Migrations e models novos devem declarar o plano (global vs tenant) na revisão; testes negativos de isolamento para o mesmo CNPJ em dois offices.
- Cofre: finalidades distintas para objetos globais (contratante SERPRO) e de tenant (A1 do autor opcional, Termo, credenciais SEFAZ do cliente).
- UI: shell tenant-aware; dados do contrato SERPRO não aparecem crus no painel do escritório.
- Operação: kill switch e rate limit existem em **dois níveis** (global do contrato e por tenant).
- Evidência comercial SERPRO permanece gate externo — ver `docs/ops/serpro-integra-contador-commercial-legal-evidence.md`.
- Checklist de isolamento do código legado: `docs/ops/multi-tenant-isolation-checklist.md`.
