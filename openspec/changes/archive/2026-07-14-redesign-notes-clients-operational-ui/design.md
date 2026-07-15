## Context

### Estado atual

- Backend de captura ADN JSON operacional; projeção `nfse_notes` enriquecida (`number`, nomes, locais, `official_status_code`).
- Frontend: Notas em mestre–detalhe estilo **inbox** (`default-size` ~28%), linhas ainda centrais em chave/CNPJ; Clientes em cards/detalhe Settings, certificado e captura no subfluxo.
- Restrição de produto: painel interno do escritório; papéis ADMIN/OPERATOR/VIEWER; template Nuxt UI Dashboard fixado.

### Referência de UX (inspiração, não cópia)

Análise de SaaS fiscal (ex.: lista “Meus Clientes” com KPIs, chips de certificado, busca única, tabela densa):

- KPIs respondem saúde da carteira em 1 vista.
- Status operacional na linha (não só no detalhe).
- Busca ampla e export/ações de lote quando existirem no domínio.

Nossa tradução de domínio:

| Referência | Nosso equivalente |
|------------|-------------------|
| Certificado digital chip | A1 ACTIVE / ausente / vencendo / vencido |
| “Não integrado” | Captura off / sem cursor / inelegível |
| Tabela razão + CNPJ + regime | Cliente + CNPJ + chips A1/captura/sync |
| Lista densa de clientes | Idem |
| (Notas não na ref) | Catálogo fiscal: nº, partes, valor, papel |

## Goals / Non-Goals

**Goals:**

- Operador tria clientes “prontos para capturar” e notas “deste mês / deste cliente” sem abrir XML.
- Notas e Clientes escaneáveis em desktop e usáveis em mobile (colunas prioritárias).
- Manter shell, permissões, URL de filtros e ausência de mocks de métricas.

**Non-Goals:**

- Dashboard analítico geográfico ou séries inventadas.
- Multi-produto / novos hubs de menu.
- Reescrever cofre, ADN ou export ZIP.

## Decisions

### 1. Ordem de entrega: Notas → Clientes

**Decisão:** Implementar Notas primeiro (dados de projeção já no banco), depois Clientes (pode exigir agregação leve na API de listagem).

**Por quê:** valor imediato no piloto com 100+ notas; Clientes exige contrato de resumo (A1 + sync) mais cuidadoso.

### 2. Padrão de layout — Notas

**Decisão (preferida): tabela administrativa full-width + detalhe em painel direito redimensionável ou drawer**, alinhada ao arquétipo de tabela do template, **não** inbox estreito de 22–28%.

Alternativa aceita se a tabela conflitar fortemente com fidelidade visual já aceita: **inbox alargado** (`default-size` 38–42%, `min` 32%) com linhas de 3 níveis de informação (nº+papel · nomes · valor/competência).

**Não usar:** lista monoespaçada de chave como título principal.

Colunas / campos da linha (desktop):

| Prioridade | Campo |
|------------|--------|
| P0 | `number` (ou fallback chave curta), `issued_at`, `competence` |
| P0 | contraparte (nome; CNPJ mono secundário), `fiscal_role` |
| P0 | `service_amount`, `status` |
| P1 | `issue_location` |
| Detalhe | chave completa, intermediário, cStat, XML meta, eventos |

Mobile: manter identidade (nº/contraparte), valor, status; resto no detalhe/slideover.

### 3. Padrão de layout — Clientes

**Decisão:** lista em **tabela densa** (como Exportações/template customers), não grade de cards como único modo.

KPIs (somente contagens reais do escritório ativo):

1. Total de clientes (ativos ou todos visíveis — documentar: **ativos**).
2. Com A1 ACTIVE.
3. Sem A1 ACTIVE.
4. A1 a vencer (≤ 30 dias, reutilizar regra de alerta).
5. Com captura problemática (cursor BLOCKED/ERROR ou elegibilidade crítica) — se custo alto, derivar da inbox ops.

Colunas:

| Coluna | Fonte |
|--------|--------|
| Razão / nome interno | client |
| CNPJ raiz ou matriz | root / establishment matrix |
| A1 | credential_summary (status, valid_to) |
| Captura | establishments capture_enabled + elegibilidade agregada |
| Sync | pior status de cursor entre est. do cliente, ou último sucesso |
| Ações | abrir; sync se policy; certificado se ADMIN |

Busca única: legal_name, display_name, root_cnpj, CNPJ de estabelecimento (backend já ou estender `q`).

### 4. API

**Notas:** listagem já devolve projeção; garantir campos novos no JSON (já no model). Sem mudança breaking.

**Clientes:** se o `index` não expõe `credential_summary` + resumo de sync por linha, **estender DTO de listagem** (não N+1 no frontend). Preferir eager load / subquery no `ClientController@index`.

### 5. Filtros e URL

- Manter query string para filtros e `accessKey` de nota.
- Notas: busca principal por texto (chave **ou** número **ou** nome de parte) se API permitir; senão manter filtros atuais + destaque visual dos campos P0.
- Evitar seletor de colunas cosmético sem preferência persistida (spec atual).

### 6. Visual / tokens

- Chips: success = A1 ok / nota ACTIVE; warning = a vencer / REVIEW; error = vencido / BLOCKED / CANCELLED; neutral = off.
- `formatCnpj` + nomes em texto normal; mono só CNPJ/chave.
- Navbar: uma ação primária (ex. Exportar notas se autorizado; Novo cliente em Clientes).

### 7. Fidelidade ao template

- Continuar `UDashboardPanel`, `UTable` / padrões de lista do template, `UBadge`, empty states.
- Divergências permitidas apenas por domínio (colunas fiscais, chips A1).
- Registrar em TRACE/comentário se algum tamanho de painel divergir do inbox de referência.

## Risks / Trade-offs

| Risco | Mitigação |
|-------|-----------|
| Tabela Notas vs mestre–detalhe já aceito no archive UX | Preferir tabela; se regressão visual forte, fallback inbox largo |
| N+1 ao montar status de sync por cliente | Agregar no backend |
| KPI “captura problemática” caro | Reusar OperationsInboxBuilder contagens ou query indexada |
| Operador confunde status nota ACTIVE com cStat | Mostrar “Ativa” + cStat no detalhe; chip simples na lista |
| Escopo expandir para analytics | Explicit non-goal |

## Migration Plan

1. Frontend Notas (sem migration DB se campos já existem).
2. API clientes list enrichment se necessário + UI Clientes.
3. Ajuste testes de componente/e2e de rotas `/notes` e `/clients`.
4. Sem migração destrutiva; rollback = reverter frontend/API.

## Open Questions

Nenhuma bloqueante. Se a listagem de clientes já trouxer `credential_summary` suficiente, a task de API vira no-op.
