# Playwright MCP no Codex e no Grok

O repositório configura o servidor oficial `@playwright/mcp` em dois clientes:

- Codex: `.codex/config.toml`;
- Grok: `.grok/config.toml`.

A versão é fixada para tornar a automação reproduzível. Atualizações devem alterar os dois arquivos juntas e repetir os smokes abaixo.

## Segurança e escopo

- navegador headless e perfil isolado;
- navegação permitida somente para `localhost` e `127.0.0.1`, portas `3000` e `8080`;
- respostas de imagem habilitadas para inspeção visual;
- snapshots e logs transitórios em `.playwright-mcp/` não são versionados;
- proibido usar o MCP para scraping, Gov.br, portais fiscais reais, PFX ou credenciais SERPRO.

## Ativação

O Codex lê `.codex/config.toml` somente em um repositório confiável. Inicie uma nova tarefa após mudar a configuração.

O Grok exige confiança explícita antes de executar MCPs definidos pelo projeto:

```bash
grok --trust
```

Na interface do Grok, `/hooks-trust` concede a mesma confiança para MCPs locais.

## Diagnóstico

Verificar descoberta no Codex:

```bash
codex mcp list
```

Verificar configuração, processo e handshake no Grok:

```bash
grok mcp doctor playwright --json
```

O diagnóstico esperado contém `healthy: true`, handshake MCP válido e ferramentas `browser_*` descobertas.

## Smoke interativo

Com o frontend disponível em `http://127.0.0.1:3000`, solicitar ao agente:

> Use somente o MCP Playwright para abrir `http://127.0.0.1:3000`, informar a URL final e o título da página.

O agente deve chamar `browser_navigate` e, quando necessário, `browser_evaluate`. A suíte Playwright em `frontend/tests/e2e/` continua sendo o gate automatizado; o MCP complementa a suíte com exploração interativa.

Referência oficial: <https://github.com/microsoft/playwright-mcp>.
