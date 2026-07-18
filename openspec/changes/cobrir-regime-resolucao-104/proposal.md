## Por quê

A operação oficial `regimeapuracao.consultarresolucao`
(`CONSULTARRESOLUCAO104`) é uma consulta produtiva, não mutante e potencialmente
bilhetável, mas não tem alias, contrato, evidência nem tela próprios. O hub não
captura a resolução de Regime de Caixa por ano-calendário.

## Mudanças

- Implementar a 104 com `anoCalendario` validado e decodificação Base64
  fail-closed.
- Guardar o texto retornado somente como evidência autorizada e expor à UI
  apenas descritor sanitizado e download same-origin.
- Criar consulta explícita e histórico local tenant-scoped na superfície
  Simples Nacional, cobertos por fake/simulated.

Não são objetivos executar SERPRO real, alterar a opção de regime 101 ou a
consulta 103, expor Base64/bytes do cofre, nem habilitar mutações.

## Evidência de orquestração

- Grok CLI — sessão `019f7387-c5eb-7932-a4c7-db2a5f0c4fcc`: revisão
  independente do contrato e dos riscos da 104.
- Grok CLI — sessão `019f7389-9916-7571-904a-462387b3e34a`: delegação de
  implementação backend e auto-revisão; o resultado será registrado nas tasks.
- Grok CLI — **sessão responsável** `019f739b-3694-7051-bb7b-31d1838e0284`
  (2026-07-18; precursor `019f7396-1b6e-7c60-bca0-3c8e1aa893e1`): pesquisa
  **exclusiva** no user-guide local `~/.grok/docs/user-guide/` (01, 04, 05,
  14, 15, 16, 17, 20) sobre sessões paralelas, worktrees e subagentes.
  Checklist operacional em `docs/ops/integra-contador-matriz-cobertura.md`
  (seção final): fontes locais, estratégia de três camadas (sessão /
  worktree / capability) + partição de paths, anti-padrões e session id
  writer. Resultado: **PASS** — orquestrador sem edição de código; workers
  com `isolation: worktree` ou lote exclusivo de paths; subagente default
  `isolation: none` (não herda worktree do pai; `cwd` × worktree
  mutuamente exclusivos); `explore`/`plan` ≠ `read-only` (shell). Escrita
  **somente** nos dois docs permitidos; sem segredos.

## Impacto

- Backend: catálogo, DTO/codec, adapter, evidência segura, rotas e testes.
- Frontend: tipos, composable, modal e ação explícita no PGDAS-D.
- Segurança: `CurrentOffice`, cofre, logs sanitizados e nenhum HTTP real em
  testes.
