## Context

A implementação existente já separa corretamente o domínio Laravel do transporte Go/WhatsMeow e já projeta mensagens/action events no Nuxt. A análise do fluxo mostrou que os DTOs do gateway suportam `kind`, `reply_to`, `MediaReference.PTT`, sticker e actions, mas o endpoint público de envio descarta parte desses metadados. No frontend, o composer só anexa arquivos, exige texto artificial, envia apenas com Ctrl/Cmd+Enter e oferece duas reações fixas; a timeline baixa toda mídia como documento e a identidade principal ignora `conversation.clients`.

A mudança atravessa API, gateway e Web porque uma interação só é válida quando o mesmo significado é preservado do gesto do operador até o protobuf WhatsApp e de volta ao ledger. Laravel continua sendo a fonte de verdade, o gateway não recebe IDs locais arbitrários nem ownership de cliente, e o Nuxt continua dentro do shell master-detail existente.

## Goals / Non-Goals

**Goals:**

- Preservar tipo, citação, remetente citado e PTT no caminho Nuxt→Laravel→gateway→WhatsApp.
- Tornar áudio gravado, sticker WebP e mídia anexada fluxos reais, com validação consistente e feedback de fila/falha.
- Renderizar mídia inbound/outbound por endpoint privado inline, mantendo download explícito, nome e isolamento por Office/inbox.
- Permitir Enter para enviar, Shift+Enter para nova linha e composição segura durante IME.
- Oferecer reações com conjunto amplo de emojis e refletir edit/revoke/reaction de ambos os participantes pela projeção de eventos existente.
- Priorizar nome do cliente fiscal vinculado no cabeçalho e na lista, mantendo contato/telefone como contexto secundário.
- Refinar densidade, responsividade e affordances sem redesenhar o shell do dashboard.

**Non-Goals:**

- Grupos, campanhas, catálogo remoto de stickers, GIF search, chatbot/IA ou Meta Cloud API.
- Editar mensagem inbound em nome do destinatário; “dos dois lados” significa emitir ações permitidas para mensagens do hub e projetar ações originadas pelo cliente.
- Transcodificação multimídia pesada; o recorder seleciona um MIME suportado pelo navegador e aceito pelo contrato de áudio.
- URLs públicas, base64 de mídia no JSON, storage fora do vault, flags ON, SERPRO live, mutações fiscais, SEFAZ, `mei`/`mei-worker` ou novos targets de ops.

## Decisions

### 1. O endpoint público de envio aceitará intenção tipada mínima

`SendMessageRequest` aceitará `kind` apenas para `TEXT|IMAGE|AUDIO|VIDEO|DOCUMENT|STICKER` e `ptt` booleano. Texto será obrigatório somente sem arquivo. O servidor detectará o MIME real e validará a combinação: sticker exige WebP; PTT exige áudio; tipo omitido continuará inferido para compatibilidade.

O controller buscará a mensagem citada na mesma conversa, exigirá `provider_message_id` remoto e montará `reply_to.message_id`; `sender` será incluído somente ao citar inbound. O payload usará `text` para texto, `caption` para mídia compatível, `kind` explícito e `media.ptt`. O digest idempotente incluirá tipo, corpo, arquivo, quote e PTT.

Alternativa rejeitada: confiar no `File.type` do navegador ou enviar apenas o ID local. O MIME deve ser validado server-side e o gateway só consegue citar com a identidade remota.

### 2. Contexto de quote 1:1 não inventará participante

O gateway montará `ContextInfo` com `StanzaID` e `RemoteJID`; `Participant` será preenchido somente quando Laravel fornecer o remetente remoto validado. Ao citar mensagem outbound, o participante ficará ausente em vez de ser incorretamente preenchido com o destinatário.

Alternativa rejeitada: enviar o endereço do contato para toda citação, pois isso atribui ao cliente uma mensagem originalmente enviada pelo próprio device.

### 3. Gravação usa MediaRecorder com fallback de MIME, limites e cleanup

O composer escolherá, em ordem, `audio/ogg;codecs=opus`, `audio/mp4` e `audio/webm;codecs=opus` conforme `MediaRecorder.isTypeSupported`. A gravação terá limite curto, interromperá tracks em cancelamento/unmount e produzirá um `File` enviado como `AUDIO` com `ptt=true`. Permissão negada ou browser incompatível será informada sem perder o rascunho.

Alternativa rejeitada: introduzir FFmpeg no gateway nesta change. Isso aumentaria imagem, CPU e superfície de arquivos temporários sem ser necessário para anexos de áudio já suportados pelo protocolo.

### 4. Sticker será um envio de arquivo WebP explicitamente tipado

Um input dedicado aceitará `image/webp`, marcará `kind=STICKER` e não enviará filename como texto/caption. O input geral continuará tratando WebP como imagem, evitando que toda imagem WebP vire sticker por acidente.

Alternativa rejeitada: catálogo público/terceiro de stickers, que adicionaria egress e licença fora do escopo.

### 5. Mídia inline terá endpoint same-origin separado do download

O resource de anexo exporá nome sanitizado, `download_url` e `preview_url`, nunca `object_id`. O endpoint inline repetirá autorização de Office/inbox e permitirá somente imagem, áudio e vídeo, com `Content-Disposition: inline`, `no-store` e `nosniff`; documentos permanecem download. A ingestão preservará o filename allowlisted entregue pelo gateway.

Alternativa rejeitada: Blob URL criado por fetch no componente para cada mensagem, que duplica memória, dificulta cleanup e piora áudio/vídeo.

### 6. A UI continua master-detail e ganha controles progressivos

O shell `UDashboardPanel`/`UDashboardNavbar` e os slideovers móveis serão preservados. A lista destacará nome do cliente, contato e inbox; o header repetirá identidade e estado. Mensagens terão preview/player por tipo, quote clicável, toolbar de ações no hover/focus e popover de emojis. O composer será mais compacto, com Enter/Shift+Enter, attach, sticker, emoji e recorder próximos ao campo.

Alternativa rejeitada: trocar pelo `UChatMessages` orientado a AI, porque a tela possui semântica de atendimento, notas, receipts e ações WhatsApp próprias.

### 7. Ações remotas permanecem assíncronas e auditáveis

Edit, revoke e reaction continuam enfileirados pela outbox; a UI não declarará sucesso remoto antes do evento do gateway. A edição/revogação iniciada pelo cliente continuará sendo aplicada pelo `GatewayEventIngestor` à mensagem original. A UI só oferece editar/apagar para mensagens OUTBOUND não revogadas.

Alternativa rejeitada: mutação otimista do corpo/estado remoto, que poderia mascarar rejeição por janela do WhatsApp.

## Mapa de dependências

```text
adicionar-comunicacao-whatsapp-nativa (C0/apply)
          └─> cobrir-whatsmeow-conversas-1x1 (C1/apply)
                    └─> sincronizar-outbound-celular-chat-silencioso (C2/apply)
                              └─> evoluir-atendimento-whatsapp-multimidia (C3)

N0 contrato HTTP + resources + helpers puros
 ├─> N1 Laravel outbound/inbound + testes Feature
 ├─> N1 gateway quote/mídia + testes Go
 └─> N1 componentes Nuxt + testes Vitest
          └─> N2 integração visual/REST e regressões cruzadas
                    └─> N3 gates completos
```

- Ownership desta change: send request/controller/resource, preview privado, contexto typed do gateway e componentes/utilitários da superfície Comunicação.
- Compatibilidade: campos novos são opcionais; clientes antigos `{body,file}` continuam válidos e o envelope legado `{to,text,media}` continua inferível.
- Arquivos compartilhados das upstreams não terão seus artefatos OpenSpec editados; somente implementação atual e delta desta change serão alterados.
- API, gateway e helpers Web podem avançar no mesmo nível lógico, mas o gate integrado só ocorre após os três caminhos preservarem o contrato.
- Rollout mantém switches OFF; rollback remove os controles novos sem migration destrutiva e sem apagar mensagens/anexos já aceitos.

## Risks / Trade-offs

- [MIME gravado varia por browser] → feature detection, allowlist server-side, mensagem de incompatibilidade e testes das escolhas de fallback.
- [Preview privado vaza entre Offices] → mesma policy de download, lookup tenant-scoped, sem `object_id`, `no-store` e teste negativo cross-tenant.
- [Quote aponta para mensagem errada] → lookup pela conversa atual e uso obrigatório do provider ID persistido.
- [WhatsApp rejeita edit/revoke fora da janela] → outbox assíncrona, falha auditável e copy que não promete conclusão antecipada.
- [Arquivo WebP mal classificado] → sticker somente por intenção explícita + MIME detectado; WebP no anexo comum permanece imagem.
- [Recorder deixa microfone ativo] → stop de todas as tracks em enviar, cancelar, erro e unmount; limite de duração/tamanho.
- [UI densa em mobile] → controles secundários em popovers/menus, composer compacto e manutenção dos slideovers existentes.
- [Vazamento de segredo/PII em API ou log] → nenhuma media key/direct path/QR/object ID em resources; payloads HMAC continuam allowlisted.
- [Kill switches ou canais sensíveis abertos] → nenhum default é alterado e nenhum egress SERPRO/SEFAZ é chamado nos testes.
- [Serviço proibido no Compose] → não tocar `mei`/`mei-worker`; validação Compose confirma a ausência.

## Migration Plan

1. Aplicar contrato opcional e resources Laravel, mantendo consumidores atuais compatíveis.
2. Aplicar correção de quote no gateway e rodar testes Go sem sessão live.
3. Liberar UI e testes Nuxt; validar manualmente com número controlado somente quando o operador habilitar flags fora desta change.
4. Rodar gates completos de API, gateway, Web, OpenSpec e Compose.

Rollback: desligar comunicação, reverter UI/rotas/campos opcionais e preservar ledger/anexos. Não há migration destrutiva nem necessidade de apagar comandos aceitos; resultados pendentes continuam reconciliáveis pelos IDs atuais.

## Open Questions

Nenhuma bloqueante. A compatibilidade real de cada MIME de gravação será observada no piloto; formatos rejeitados permanecem erro explícito e não sofrem fallback silencioso para documento.
