## Why

O Atendimento só vive em `/communication` sem ID na URL: não dá para compartilhar, favoritar ou recarregar a conversa aberta. O operador espera deep-link no estilo Chatwoot (`…/conversations/{id}`).

## What Changes

- Rotas Nuxt: `/communication` (lista) e `/communication/conversations/{id}` (conversa selecionada).
- Selecionar conversa atualiza a URL; abrir a URL seleciona/carrega a conversa.
- Fechar seleção (mobile/escape) volta para `/communication`.
- Nav “Atendimento” ativa em qualquer subcaminho `/communication…`.

## Capabilities

### New Capabilities

- (nenhuma)

### Modified Capabilities

- `communication-inbox`: deep-link canônico de conversa na superfície `/communication`.

## Impact

- Web: páginas `communication/`, `navigation.ts`, sync seleção↔rota, testes unitários/contrato.
- API: nenhuma.
- Non-goals: contas na URL (tenant via CurrentOffice), redesign shell, realtime/WhatsApp.

### Dependências entre changes

- Nível: **C0**
- Bases estáveis: superfície Atendimento atual; padrão de deep-link de mailbox (`/monitoring/mailbox/[id]`).
- Depende de: **nenhuma**
- Capability/contrato: `communication-inbox`
- Marco exigido: `specs`
- Relação: coordenada com `corrigir-realtime-inbox-comunicacao` (não bloqueante)
- Desbloqueia: links compartilháveis de conversa
- Paralelismo: ok fora de ownership de páginas `communication*`
