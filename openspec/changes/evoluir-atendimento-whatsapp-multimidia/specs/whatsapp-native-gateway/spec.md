## ADDED Requirements

### Requirement: Envelope tipado preserva mídia, PTT e citação 1x1
O gateway SHALL receber e validar `kind`, `caption`, `reply_to` e `media.ptt` allowlisted, escolher o media type correspondente e montar o protobuf sem alterar o significado solicitado. Citação SHALL referenciar o provider ID original; participante remoto MUST ser preenchido somente quando conhecido e validado como usuário 1:1.

#### Scenario: Texto cita mensagem inbound
- **WHEN** comando de texto contém `reply_to.message_id` e sender remoto individual válido
- **THEN** o gateway monta `ContextInfo` com stanza, remote JID e participante correspondentes antes de enviar

#### Scenario: Texto cita mensagem outbound
- **WHEN** comando cita provider ID de mensagem enviada pelo próprio device e não contém sender remoto
- **THEN** o gateway preserva stanza/remote JID sem atribuir incorretamente a mensagem ao destinatário

#### Scenario: Áudio PTT é enviado
- **WHEN** comando `AUDIO` contém mídia de áudio válida e `ptt=true`
- **THEN** o gateway faz upload como áudio e monta `AudioMessage.PTT=true` com o provider ID idempotente do Laravel

#### Scenario: Sticker WebP é enviado
- **WHEN** comando `STICKER` contém stream `image/webp` com tamanho e digest válidos
- **THEN** o gateway faz upload como imagem, monta `StickerMessage` e não faz fallback para imagem ou documento

#### Scenario: Tipo não corresponde ao MIME
- **WHEN** comando de mídia declara combinação incompatível
- **THEN** o gateway falha antes de `SendMessage` e emite resultado sanitizado sem tentar outro tipo

### Requirement: Eventos ricos convergem sem perder nome ou ação
Eventos inbound e OUTBOUND live SHALL preservar tipo, filename sanitizado, quote e provider IDs necessários à projeção Laravel. Edit, revoke e reaction originados de qualquer participante válido SHALL referenciar a mensagem original, sem expor protobuf, media key, direct path ou JID de device.

#### Scenario: Mídia inbound possui nome
- **WHEN** o WhatsApp entrega documento, imagem, áudio, vídeo ou sticker 1:1 com filename disponível
- **THEN** o evento multipart inclui somente filename sanitizado, MIME, tamanho, digest, spool ID e metadados allowlisted

#### Scenario: Cliente revoga mensagem
- **WHEN** chega revoke válido de mensagem inbound conhecida
- **THEN** o evento de ação referencia o provider ID alvo e permite ao Laravel atualizar a mensagem original

#### Scenario: Payload tenta vazar segredo de mídia
- **WHEN** qualquer evento rico é serializado para Laravel
- **THEN** media key, direct path, node bruto e credenciais permanecem ausentes do envelope
