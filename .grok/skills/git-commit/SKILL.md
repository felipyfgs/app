---
name: git-commit
description: >
  Cria commits Git de alta qualidade: inspeciona diff, stage inteligente,
  commits atômicos e mensagens Conventional Commits em pt-BR. Use quando o
  usuário pedir commit, "commitar", "faça o commit", mensagem de commit,
  stage, dividir commits, ou rodar /git-commit ou /commit.
license: MIT
metadata:
  author: project
  version: "1.0"
  adaptedFor: grok
  inspiredBy:
    - softaworks/agent-toolkit commit-work
    - github/awesome-copilot git-commit
    - berserkdisruptors contextual-commits
---

# Git Commit (pt-BR)

Cria commits fáceis de revisar e seguros de entregar:

- só entram mudanças pretendidas
- escopo lógico (dividir quando necessário)
- mensagem descreve **o quê** e **por quê** em português brasileiro
- formato Conventional Commits (types/scopes em inglês; subject/body em pt-BR)

## Objetivo

Entregar um ou mais commits limpos no working tree, sem segredos e sem ruído.

## Idioma e estilo do monorepo

- **Mensagens de commit em pt-BR** (subject e body) — alinhado a `AGENTS.md`.
- Types e scopes em inglês (`feat`, `fix`, `backend`, …).
- Subject: imperativo, sem ponto final, ~50–72 caracteres.
- Body: explicar **por quê**, não narrar o diff.
- Preferir commits pequenos e focados a um monólito.

### Formato

```
<type>[optional scope]: <descrição em pt-BR>

[corpo opcional em pt-BR]

[rodapé opcional]
```

### Types

| Type | Quando usar |
|------|-------------|
| `feat` | Nova funcionalidade para o usuário/domínio |
| `fix` | Correção de bug |
| `docs` | Só documentação |
| `style` | Formatação/estilo sem mudança de lógica |
| `refactor` | Refatoração sem feat nem fix |
| `perf` | Melhoria de performance |
| `test` | Testes |
| `build` | Build, deps de empacotamento |
| `ci` | CI/CD |
| `chore` | Manutenção, tooling, config |
| `revert` | Reversão |

### Scopes deste monorepo (sugestão)

Use o módulo principal afetado; omita se for genérico.

| Scope | Área |
|-------|------|
| `backend` | Laravel / PHP / API |
| `frontend` | Nuxt / UI |
| `docker` | Compose, Nginx, imagens |
| `openspec` | Specs, changes, design |
| `auth` | Fortify, Sanctum, TOTP, papéis |
| `vault` | SecureObjectStore, certificados |
| `adn` | Cliente ADN, mTLS, sync NSU |
| `nfse` | Documentos, projeções, eventos |
| `horizon` | Filas, jobs, rate limit |
| `dx` | Skills, agent tooling, editor |

Breaking change: `feat(auth)!: ...` ou footer `BREAKING CHANGE: ...`.

## Workflow (checklist)

### 1. Inspecionar antes de stage

```bash
git status
git diff
git diff --stat
git log --oneline -8
```

- Se já houver stage: `git diff --cached` e `git diff --cached --stat`.
- Alinhar estilo ao histórico recente (comprimento, scopes).

### 2. Decidir fronteiras de commit

Dividir quando misturar:

- feature vs refactor
- backend vs frontend
- formatação vs lógica
- testes vs código de produção
- bump de deps vs mudança de comportamento
- artefatos OpenSpec vs código de app

Se um arquivo misturar intenções: planejar `git add -p`.

**Default:** vários commits pequenos se houver mudanças não relacionadas.

### 3. Stage só o que entra no próximo commit

```bash
git add caminho/arquivo1 caminho/arquivo2
# ou
git add -p
```

- Prefira paths explícitos a `git add -A` / `git add .`.
- Unstage: `git restore --staged <path>` ou `git restore --staged -p`.

### 4. Revisar o que será commitado

```bash
git diff --cached
git status
```

Sanidade obrigatória:

- [ ] sem segredos (ver abaixo)
- [ ] sem debug acidental (`dd()`, `console.log` de rascunho, dumps)
- [ ] sem formatação/churn não relacionado
- [ ] sem PFX, PEM, chaves, senhas, `.env` com credenciais

### 5. Descrever em 1–2 frases (antes da mensagem)

Responder mentalmente: **o que mudou?** + **por quê?**

Se não der para descrever limpo → commit grande/misto demais → voltar ao passo 2.

### 6. Escrever e executar o commit

HEREDOC (sempre, para multi-linha e acentos):

```bash
git commit -m "$(cat <<'EOF'
type(scope): descrição curta em pt-BR

Corpo opcional explicando o porquê.

EOF
)"
```

### 7. Contexto opcional no body (quando há raciocínio da sessão)

Para mudanças não triviais, pode acrescentar *action lines* no body (compatível com Conventional Commits):

```
intent(scope): o que o usuário/negócio pedia
decision(scope): abordagem escolhida e por quê
rejected(scope): alternativa descartada e motivo
constraint(scope): limite que moldou a solução
learned(scope): gotcha útil para sessões futuras
```

Regras:

- Subject continua Conventional Commit puro.
- Só linhas com sinal; zero padding.
- Commit trivial (typo, format): só o subject.
- **Não inventar** intent/decision se não houver contexto da sessão — subject limpo é melhor que ficção.

### 8. Verificar e repetir

- Rodar o check mais barato e relevante do repo (lint/test do pacote tocado), se fizer sentido e for rápido.
- `git status` — se ainda houver mudanças, próximo commit (passo 2).
- **Não** fazer push a menos que o usuário peça.

## Protocolo de segurança Git

- **NUNCA** alterar `git config`.
- **NUNCA** comandos destrutivos (`--force`, `reset --hard`, limpar untracked) sem pedido explícito.
- **NUNCA** `--no-verify` salvo pedido explícito do usuário.
- **NUNCA** force-push em `main`/`master`.
- **NUNCA** commitar se o único conteúdo for segredo ou material sensível fiscal/certificado.
- Se hook falhar: corrigir o problema e criar **novo** commit (não amend automático).
- **Amend** só se o usuário pedir **e** o commit for local (não pushed) **e** criado por você nesta sessão.

### Segredos e material proibido neste produto

Recusar stage/commit se aparecer:

- `.env`, `.env.*` com valores reais
- `VAULT_MASTER_KEY`, master keys, tokens
- PFX/P12, PEM de chave privada, senhas de certificado
- dumps de XML com dados fiscais sensíveis em path errado (exports acidentais)
- credenciais em logs ou fixtures de produção

Ver também domínio em `AGENTS.md`: nunca expor PFX, senha, chave privada ou PEM.

## Entregável ao usuário

Após commit(s):

1. Hash curto + mensagem de cada commit
2. Resumo de 1 linha por commit (o quê/por quê)
3. `git status` final (working tree limpa ou o que restou)

Exemplo:

```
## Commits criados

1. a1b2c3d  feat(adn): limitar job a 20 páginas e reenfileirar
   — evita travar o worker em sync longo; requeue controlado

2. d4e5f6a  test(adn): cobrir cursor NSU e falha de decode
   — trava avanço de NSU após 5 falhas consecutivas

Working tree limpa.
```

## Anti-padrões

| Evitar | Preferir |
|--------|----------|
| `git commit -m "fix"` | `fix(backend): corrigir filtro de office_id na listagem` |
| Um commit com backend+frontend+docs misturados | 2–3 commits por área |
| Subject em inglês se o repo pede pt-BR | Subject em pt-BR |
| Body que repete o diff | Body com motivação/restrição |
| `git add .` cego | Paths ou `-p` |
| Inventar `intent(...)` sem contexto | Só Conventional subject |

## Referências

- Tipos e exemplos: [references/conventional-commits.md](references/conventional-commits.md)
- Spec: https://www.conventionalcommits.org/
