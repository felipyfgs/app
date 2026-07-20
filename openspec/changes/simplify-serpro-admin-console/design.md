## Context

Após `unify-serpro-admin-nav`, o Admin tem um destino SERPRO e o shell usa `SectionNavigation` com Operação / Integração / Canário. Cada hub ainda tem `ShellScrollableTabs` locais e embeds de páginas de ops. O Proprietário precisa de saúde + credenciais/limites; canário, conciliação, catálogo e histórico são raros.

## Goals / Non-Goals

**Goals:**

- Dois itens no shell: Visão geral + Configuração.
- Visão geral = Status sem tabs; Configuração = Acesso essencial sem tabs.
- Deep-links intactos; links secundários nas páginas.

**Non-Goals:**

- Deletar arquivos de deep-link ou APIs.
- Mudar comportamento de kill switch/credenciais além da superfície UI.
- Redesign amplo do shell Admin.

## Decisions

### 1. Labels Visão geral / Configuração

- **Escolha:** renomear Operação→Visão geral e Integração→Configuração no catálogo.
- **Por quê:** espelha o corte de produto acordado.
- **Alternativa:** manter labels antigos — rejeitada (menos claras).

### 2. Canário só deep-link

- **Escolha:** remover do `SERPRO_NAV_ITEMS`; link secundário na Visão geral.
- **Por quê:** wizard raro; não é gestão diária.

### 3. Remover tabs locais e embeds

- **Escolha:** apagar `ShellScrollableTabs` + embeds em `index.vue`/`configuration.vue`; páginas filhas continuam via redirect.
- **Por quê:** elimina segunda camada de IA no fluxo diário.

### 4. Cortar pending offices e histórico longo

- **Escolha:** remover da Configuração primária.
- **Por quê:** auditoria/suporte, não gestão diária de credenciais.

## Risks / Trade-offs

- [Deep-links `/usage` etc. sem tab ativa] → Mitigação: `isActive` de Visão geral/Configuração cobre esses paths; redirect pages continuam.
- [Conflito de delta com `unify-serpro-admin-nav`] → Mitigação: C1 bloqueante; esta change sobrescreve o catálogo do shell.

## Migration Plan

1. Deploy frontend.
2. Rollback: reverter nav + duas páginas + testes.
3. Sem flag/DB.

## Open Questions

- Nenhuma.
