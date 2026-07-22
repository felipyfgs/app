## Why

O gateway e a inbox já possuem primitives para mídia e ações 1:1, porém a experiência atual não fecha o fluxo real: citações não chegam ao WhatsApp remoto, WebP não é classificado como sticker, não há gravação de voz nem envio com Enter, as reações são restritas e anexos recebidos aparecem apenas como downloads genéricos. Além disso, o cliente fiscal vinculado não é usado como identidade principal na lista e no cabeçalho da conversa.

## What Changes

- Completar o contrato outbound Laravel→gateway com `kind`, `reply_to` remoto e flag PTT, preservando IDs idempotentes e validação 1:1.
- Permitir envio real de imagem, áudio/voz, vídeo, documento e sticker WebP; manter o fluxo inbound privado com nome do arquivo e preview/player autenticado.
- Acrescentar gravação de áudio no navegador, seleção de sticker, seletor compacto de emojis, citação de mensagens anteriores e envio por Enter com Shift+Enter para quebra de linha.
- Refinar as ações de mensagem para reagir/remover reação, editar e apagar para todos, refletindo também edições, reações e revogações iniciadas no aparelho do cliente.
- Refatorar a UI/UX de `/communication` no mesmo shell clean: lista mais informativa, timeline com agrupamento visual e mídia inline, composer compacto e responsivo, estados de ação claros e nomes de clientes no cabeçalho e na lista.
- Adicionar testes Laravel, Go e Vitest para provar o envelope remoto, tipos de mídia, atalhos de teclado, identidade visual e projeções bidirecionais.

Non-goals: grupos, campanhas, chatbot/IA, Meta Cloud API, transcodificação pesada ou catálogo público de stickers, habilitar flags em produção, chamadas SERPRO live, parecer jurídico, mutações fiscais, canais SEFAZ, serviços `mei`/`mei-worker` no Compose ou targets indisponíveis de backup/restore.

## Capabilities

### New Capabilities

- (nenhuma)

### Modified Capabilities

- `communication-inbox`: completar composer, timeline, mídia privada inline, citações reais, ações bidirecionais e identificação do cliente na superfície de atendimento.
- `whatsapp-native-gateway`: garantir que mídia tipada, PTT, stickers, citações e ações usem o envelope allowlisted e produzam efeitos reais na conversa 1:1.

## Impact

- API Laravel: request/controller/resource de mensagens, ingestão e streaming privado de anexos, rotas e testes Feature de Comunicação.
- Gateway Go: validação/construção de contexto citado e testes de mensagem tipada, sem alterar ownership de domínio nem expor novas portas.
- Web Nuxt: tipos, API client, workspace, composer, lista, timeline, renderização de mídia e testes unitários/fidelity da superfície `/communication`.
- Contrato interno: OpenAPI do gateway permanece versionado e recebe somente campos allowlisted já previstos ou explicitamente tipados.
- Segurança: mídia continua cifrada em repouso, same-origin, tenant-scoped e sem URL pública permanente; switches permanecem OFF.

### Dependências entre changes

- Nível: **C3**.
- Bases estáveis: `Office`/`CurrentOffice`, RBAC de comunicação, vault privado, outbox Laravel, Reverb e shell Nuxt do dashboard.
- Depende de: `adicionar-comunicacao-whatsapp-nativa`, capabilities `communication-inbox` e `whatsapp-native-gateway`, marco `apply`, relação `bloqueante`; `cobrir-whatsmeow-conversas-1x1`, mesmas capabilities, marco `apply`, relação `bloqueante`; `sincronizar-outbound-celular-chat-silencioso`, contrato de OUTBOUND live e hydrate silencioso, marco `apply`, relação `coordenada`.
- Desbloqueia: operação cotidiana do atendimento com paridade prática entre hub e aparelho para mensagens ricas e ações 1:1.
- Paralelismo: não aplicar em paralelo mudanças nos mesmos arquivos de Comunicação/gateway das três upstreams; trabalhos alheios a essas áreas podem continuar, preservando o worktree existente.
