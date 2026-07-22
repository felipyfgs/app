## Context

O estado atual possui três camadas de teste que ainda não se conectam como um sistema:

| Camada | Estado observado | Limitação |
|---|---:|---|
| Superfície API | 455 rotas live; fixture ainda com 445 | gate confere total/amostra, não o conjunto exato nem a jornada |
| Superfície web | 97 páginas inventariadas; summary ainda marca 94 | páginas estão listadas, mas não ligadas a atores, endpoints e evidências |
| Testes | 93 PHPUnit, 57 Vitest/Playwright; 2 specs Playwright | quantidade de arquivos não demonstra cobertura de caso de uso |

O grafo necessário cruza `apps/web/app/pages|components|composables/api`, rotas/controllers/services/jobs em `apps/api`, integrações fail-closed e os testes em ambas as aplicações. O harness E2E existente já fornece Compose isolado, `FiscalMonitoringE2ESeeder`, atores operador/viewer, bloqueio de hosts externos e execução local fora do CI.

## Goals / Non-Goals

**Goals:**

- Classificar 100% das rotas e páginas atuais em jornadas de negócio, sem deixar superfícies órfãs.
- Tornar rastreável `jornada → ator → página → grupo/endpoint API → handler → integração → evidência L0–L3`.
- Fazer o gate distinguir “inventariado”, “tem smoke”, “tem teste de regra” e “tem navegador”, sem promover source-gate a E2E.
- Cobrir no navegador quatro jornadas críticas representativas: identidade/tenant, catálogo de clientes, trabalho operacional e monitoramento fiscal.
- Produzir relatório Markdown regenerável com cobertura e lacunas por jornada.

**Non-Goals:**

- Executar 455 chamadas HTTP ou abrir 97 páginas em cada gate.
- Tratar mera referência textual a uma rota como prova behavioral.
- Live SERPRO/Integra/SEFAZ, sidecar MEI, flags mutantes ON ou Playwright no CI.
- Alterar shell, regras fiscais ou contratos públicos sem um bug confirmado durante os testes.

## Decisions

1. **Catálogo explícito de jornadas + inventários gerados**  
   Um catálogo JSON pequeno define os casos de uso, atores, grupos API, seções/rotas web, integrações e evidências exigidas. O gerador combina esse catálogo com `api-routes.json` e `web-pages.json`, produzindo o grafo completo. Alternativa rejeitada: inferir jornadas apenas por imports; isso confunde estrutura técnica com intenção de negócio e quebra com auto-imports Nuxt.

2. **Grafo bipartido por superfícies e evidências**  
   Nós têm tipos estáveis (`journey`, `page`, `api-route`, `handler`, `integration`, `test`) e arestas tipadas (`enters`, `calls`, `handled-by`, `may-egress-to`, `proven-by`). Todas as 455 rotas e 97 páginas são classificadas; endpoints dinâmicos continuam identificados pelo template Laravel. O snapshot é duplicado nas fixtures API/web, seguindo o padrão atual, e ambos carregam o mesmo digest.

3. **Níveis não intercambiáveis**  
   `L0` prova inventário/classificação; `L1` prova contrato HTTP/auth/tenant; `L2` prova regra de domínio ou comportamento web; `L3` prova a jornada no navegador. Um smoke “status < 500” não satisfaz L1 quando a jornada exige isolamento ou permissão. O catálogo declara a evidência existente e o gate verifica arquivo, nível, jornada e âncora estável.

4. **Gate estrito no formato, seletivo na profundidade**  
   O gate MUST falhar para rota/página sem jornada, referência inexistente, digest divergente ou jornada crítica sem L1/L2/L3. Jornadas não críticas podem registrar `gap` explícito; assim o relatório é honesto sem exigir uma suíte de navegador impraticável para toda rota administrativa.

5. **Playwright reusa o harness local**  
   O seed ganha somente os dados determinísticos necessários. As specs usam atores existentes, `data-testid`, asserts de tenant/visibilidade e bloqueio de qualquer host além de `localhost`/`127.0.0.1`. Não há egress fiscal nem inclusão no workflow CI.

6. **Contextos de autorização permanecem separados**  
   Jornadas de escritório usam Sanctum + `CurrentOffice`; plataforma usa `PLATFORM_ADMIN` sem acesso fiscal implícito. O grafo registra o contexto esperado e os testes de tenant verificam ausência de dados cross-office.

## Mapa de dependências

```text
C0 mapear-grafo-testabilidade-e2e
 ├─ coordena arquivos de teste Sitfis com sitfis-historico-busca
 ├─ coordena fixtures Sitfis com corrigir-classificacao-pdf-sitfis
 └─ preserva histórico PGDAS-D de alinhar-historico-pgdasd-portal-simples

N0 inventário/catálogo
 └─ N1 gerador + grafo + gates
     ├─ N2 contratos API/Vitest das jornadas críticas
     └─ N2 seed + Playwright das jornadas críticas
         └─ N3 gates integrados e relatório final
```

Ownership desta change: novos artefatos de testabilidade, gates de inventário, seed/harness E2E e specs próprias. Arquivos de produto ou testes em edição pelas changes coordenadas só serão tocados se indispensáveis; nesse caso, o assert será aditivo e compatível com o contrato atual.

## Risks / Trade-offs

- [Grafo vira documentação decorativa] → gerador determinístico, digest e testes de paridade exata em API e web.
- [Falso positivo de cobertura] → evidência explícita por nível e âncoras; referência textual isolada não sobe de nível.
- [Vazamento entre offices] → jornadas críticas L1/L3 incluem troca de tenant e dados exclusivos por office.
- [Segredos em log/API] → fixtures sintéticas; nenhum PFX/token/XML bruto no catálogo ou relatório.
- [Bilhetagem SERPRO ou canal mutante] → `FISCAL_KILL_SWITCH=true`, providers OFF, `Http::fake/assertNothingSent` nos Features e bloqueio de hosts no browser.
- [E2E lento/flaky] → quatro jornadas focadas, um worker, seed idempotente, asserts por `data-testid`; Playwright segue fora do CI.
- [MEI reaparece no Compose] → nenhum serviço é criado; validação Compose existente continua sendo o gate.
- [Mudanças paralelas tornam baseline obsoleto] → regenerar a partir do working tree imediatamente antes dos gates e exigir paridade de conjunto, não apenas contagem.

## Migration Plan

1. Atualizar inventários atuais para 455 rotas/97 páginas e introduzir catálogo/gerador.
2. Gerar snapshots idênticos para API/web e relatório do levantamento.
3. Adicionar gates de grafo e contratos faltantes das quatro jornadas críticas.
4. Estender seed/specs Playwright e executar gates determinísticos; executar E2E local quando o ambiente Compose estiver disponível.
5. Rollback é a remoção dos novos artefatos/testes; não há migração de dados nem mudança de runtime.

## Open Questions

- Nenhuma bloqueante. Novas jornadas críticas futuras entram no catálogo e passam a exigir L1–L3 no mesmo PR.
