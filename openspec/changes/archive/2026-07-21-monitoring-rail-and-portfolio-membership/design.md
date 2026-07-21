## Context

Hoje a carteira Simples/MEI filtra por `clients.tax_regime` (PGDASD ↔ SN, PGMEI ↔ MEI). Qualquer cliente ativo com o regime certo entra na lista — não há opt-out. O modal da foto (“Associar clientes” / DAS do Simples) ainda não existe no código; o mais próximo é “Associar categorias” (`AssociateCategoriesModal`) e `PortfolioActions` (morto). O rail do detalhe lista todas as seções de `FISCAL_SECTIONS`, inclusive CCMEI sem gate de MEI.

Esta change é transversal (rail UI + membership API + redirect cadastro): duas capabilities no proposal, com ownership separado (rail vs carteira).

## Goals / Non-Goals

**Goals:**

- Rail enxuto: ocultar Execuções, Achados, Cadastro e Vínculos, Renúncias, Processos Fiscais.
- CCMEI só quando `clientIsMei(client)`.
- Pós-create SN → `/monitoring/simples-mei` (PGDASD); MEI → aba PGMEI.
- Opt-out por módulo + UI de incluir/excluir (modal + dropdown “Excluir” em todas as carteiras).

**Non-Goals:**

- Soft-delete do cliente CRM; apagar evidências/snapshots.
- Trocar `tax_regime` como forma de “sair da carteira”.
- SERPRO / mutações fiscais / flags ON.
- Usar `office_fiscal_category_links` como filtro primário (já rejeitado em `simples-mei-portfolio-regime-scope`).

## Decisions

1. **Opt-out explícito (não membership positiva)**  
   Tabela `office_monitoring_module_exclusions` (`office_id`, `client_id`, `module_key`, timestamps, actor). Carteira = regra atual de elegibilidade (`tax_regime` / módulo) **MINUS** exclusões ativas do módulo.  
   - *Alternativa rejeitada:* exigir link ativo em `office_fiscal_category_links` — conflita com regime canônico e quebra quem já está na carteira sem link.  
   - *Alternativa rejeitada:* `is_active=false` no cliente — remove do CRM.

2. **`module_key` alinhado ao portfolio**  
   Valores do hub (`simples_mei`, `dctfweb`, `fgts`, `installments`, `sitfis`, `mailbox`, `declarations`, `guides`, `registrations`, `tax_processes`). Submódulo SN/MEI: exclusão em `simples_mei` cobre ambas as abas **ou** chaves `simples_mei:PGDASD` / `simples_mei:PGMEI` se o produto exigir granularidade — **decidir implementação: granular por submodule** para “DAS do Simples” ≠ MEI (foto = DAS do Simples). Preferir `module_key` + `submodule` nullable (`PGDASD`|`PGMEI`|null).

3. **API (office context, Sanctum)**  
   - `GET /api/v1/fiscal/monitoring/membership?module=&submodule=` — elegíveis / excluídos (ou dois endpoints).  
   - `POST .../membership/exclude` `{ client_ids, module, submodule? }`  
   - `POST .../membership/include` `{ client_ids, module, submodule? }` (remove exclusão; não inventa regime).  
   Include de cliente fora do regime do módulo: **400** honesto (não muda `tax_regime`).

4. **Modal “Associar clientes”**  
   Novo componente (não reusar “Associar categorias”): lista elegíveis do módulo + estado “na carteira” / “excluído”; `+` inclui; ação excluir / lote; busca nome/CNPJ. Entrada na toolbar das carteiras (onde hoje faltava o fluxo da foto).

5. **Dropdown “Excluir”**  
   Em builders de ações (PGDASD, PGMEI, DCTFWeb, e padrão compartilhado nas demais carteiras Module*): confirmação curta → exclude → refresh da lista. Sem SERPRO.

6. **Rail**  
   Filtrar `FISCAL_SECTIONS` / overview cards; deep-link para seção oculta → redirect overview. CCMEI: `clientIsMei` após `loadClient`.

7. **Redirect pós-cadastro**  
   Em `onFormSaved` (create): se `tax_regime` família MEI → `monitoringModuleHref('simples_mei', 'PGMEI')`; família SN → PGDASD; senão ficha CRM atual.

## Mapa de dependências

```text
C0 monitoring-rail-and-portfolio-membership
  ├─ client-fiscal-rail (web navigation/overview)
  └─ monitoring-portfolio-membership (API exclusions + web modal/actions/redirect)
coordenada apply: company-first / slim (mesmo [clientId].vue) — merge cuidadoso
```

Ownership: API exclusions + portfolio filter sob `ModulePortfolio*`; web rail sob `client-fiscal-detail-navigation` + overview; membership UI sob `components/monitoring` + table utils.

## Risks / Trade-offs

- [Cliente SN excluído some da lista mas ainda tem regime] → Mitigação: copy “Removido do monitoramento”; modal permite reincluir.
- [Exclusão sem tenancy] → Mitigação: sempre `CurrentOffice`; never office_id do body.
- [Scheduler continua consultando excluídos] → Mitigação: aplicar o mesmo filtro de exclusão no escopo do scheduler/jobs do módulo (fase 1 mínima: portfolio UI + query; scheduler na mesma query scoped se compartilhada).
- [Merge com company-first] → Mitigação: overview cards espelham rail filtrado.

## Migration Plan

1. Migrar tabela de exclusões (vazia = comportamento atual).  
2. Deploy API + web.  
3. Rollback: dropar filtro de exclusão / feature flag off (default fail-closed = tabela vazia já é compatível).

## Open Questions

- Exclusão em módulos sem submodule (DCTFWeb etc.): só `module_key`. Confirmado.  
- Pendências permanece no rail (não riscada na UI do usuário) — confirmado no proposal.
