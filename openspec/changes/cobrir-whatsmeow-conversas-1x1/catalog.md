# Catálogo whatsmeow para conversas 1:1

## Fontes e regra de fechamento

- API primária: `tulir/whatsmeow@8b4a8ba0d31877b19331748d75d96f338c7982d3`.
- Referência de handlers: `asternic/wuzapi@70642149a0e8a81d49caa640f557217e03e09729`.
- Universo: 135 métodos públicos únicos de `*whatsmeow.Client` e 74 structs públicas em `types/events`.
- `BASELINE`: já chamado pelo gateway atual; ainda pode ganhar cobertura adicional.
- `IMPLEMENTED`: aplicável a 1:1, implementado e ligado a código/teste reais no manifesto executável.
- `INTERNAL`: primitiva encapsulada, alias, configuração de bootstrap ou detalhe que não vira operação pública; o manifesto final deve apontar seu owner/teste ou a composição que a cobre.
- `EXCLUDED`: fora do recorte solicitado, com motivo explícito.
- `DEPRECATED`: não usar em código novo; mapear para a alternativa suportada.

A change só estará completa quando não houver `PENDING`, toda entrada `INTERNAL` tiver disposição verificável e o teste de deriva confirmar exatamente o method set pinado.

## Métodos públicos de Client (135/135)

### App state e status

| Método | Estado | Mapeamento 1:1 | WuzAPI |
|---|---|---|---|
| `FetchAppState` | IMPLEMENTED | sync/recovery interno allowlisted | não |
| `MarkNotDirty` | IMPLEMENTED | conclusão de recovery interno | não |
| `SendAppState` | IMPLEMENTED | archive/mute/pin/star/delete-for-me/mark-read por builder | sim, archive |
| `GetStatusPrivacy` | EXCLUDED | status/broadcast | não |
| `SetStatusMessage` | EXCLUDED | status/broadcast | sim |

### Sessão, conexão, handlers e transporte

| Método | Estado | Mapeamento 1:1 | WuzAPI |
|---|---|---|---|
| `AddEventHandler` | BASELINE | registro da event bridge | sim |
| `AddEventHandlerWithSuccessStatus` | IMPLEMENTED | handler com resultado explícito para retry/ack | não |
| `Connect` | BASELINE | compatibilidade do connector | sim |
| `ConnectContext` | IMPLEMENTED | conexão cancelável com deadline | não |
| `Disconnect` | BASELINE | disconnect local sem revogar device | sim |
| `IsConnected` | BASELINE | estado técnico | sim |
| `IsLoggedIn` | IMPLEMENTED | estado sanitizado da sessão | sim |
| `Logout` | BASELINE | revogação explícita | sim |
| `ParseWebMessage` | IMPLEMENTED | normalização de history sync | não |
| `RemoveEventHandler` | IMPLEMENTED | cleanup do handler registrado | não |
| `RemoveEventHandlers` | INTERNAL | alternativa global não usada em client compartilhado | não |
| `ResetConnection` | IMPLEMENTED | recuperação de sessão degradada | não |
| `SetMaxParallelRetryReceiptHandling` | IMPLEMENTED | limite de bootstrap configurável | não |
| `SetMediaHTTPClient` | IMPLEMENTED | HTTP client com timeout/proxy seguro | não |
| `SetPreLoginHTTPClient` | IMPLEMENTED | HTTP pre-login com timeout/proxy seguro | não |
| `SetWebsocketHTTPClient` | IMPLEMENTED | handshake/websocket com transporte seguro | não |
| `SetProxy` | INTERNAL | base dos setters allowlisted | sim |
| `SetProxyAddress` | IMPLEMENTED | configuração administrativa fail-closed | sim |
| `SetSOCKSProxy` | IMPLEMENTED | configuração administrativa fail-closed | sim |
| `StoreLIDPNMapping` | INTERNAL | cache/store alimentado pelo protocolo; não exposto | não |
| `WaitForConnection` | IMPLEMENTED | readiness após connect/reset | não |
| `SetPassive` | IMPLEMENTED | modo companion administrativo | não |
| `DangerousInternals` | DEPRECATED | API perigosa proibida | não |

### Pairing

| Método | Estado | Mapeamento 1:1 | WuzAPI |
|---|---|---|---|
| `GetQRChannel` | BASELINE | pairing QR efêmero | sim |
| `PairPhone` | IMPLEMENTED | pairing por código efêmero | sim |
| `SendPasskeyResponse` | IMPLEMENTED | resposta WebAuthn allowlisted | não |
| `SendPasskeyConfirmation` | IMPLEMENTED | confirmação do handoff de passkey | não |

### Envio, builders e ações

| Método | Estado | Mapeamento 1:1 | WuzAPI |
|---|---|---|---|
| `GenerateMessageID` | INTERNAL | Laravel fornece `provider_message_id`; usado só em fluxos sem mensagem de produto | sim |
| `SendMessage` | BASELINE | transporte comum de todos os DTOs de mensagem | sim |
| `BuildMessageKey` | IMPLEMENTED | quote/reaction/revoke | não diretamente |
| `BuildReaction` | IMPLEMENTED | reação/remover reação | não; WuzAPI monta protobuf |
| `BuildRevoke` | IMPLEMENTED | revogação para todos | sim |
| `BuildEdit` | IMPLEMENTED | edição dentro da janela suportada | sim |
| `BuildUnavailableMessageRequest` | IMPLEMENTED | recovery de mensagem indecriptável | sim |
| `BuildHistorySyncRequest` | IMPLEMENTED | paginação de history 1:1 | sim |
| `SendPeerMessage` | IMPLEMENTED | history/unavailable para próprios devices | não diretamente |
| `SetDisappearingTimer` | IMPLEMENTED | timer 0/24h/7d/90d para chat individual | sim, rota nomeada group |
| `RevokeMessage` | DEPRECATED | usar `BuildRevoke` + `SendMessage` | não |
| `SendFBMessage` | EXCLUDED | FB/Armadillo/bot | não |

### Polls e mensagens secret-encrypted

| Método | Estado | Mapeamento 1:1 | WuzAPI |
|---|---|---|---|
| `BuildPollCreation` | IMPLEMENTED | criar poll 1:1 | sim |
| `BuildPollVote` | IMPLEMENTED | votar em poll 1:1 | não |
| `DecryptPollVote` | IMPLEMENTED | projetar voto inbound | sim |
| `EncryptPollVote` | INTERNAL | composição usada por `BuildPollVote` | não |
| `DecryptSecretEncryptedMessage` | IMPLEMENTED | edição/event response inbound | sim |
| `DecryptReaction` | EXCLUDED | reaction cifrada de community announcement | não |
| `EncryptReaction` | EXCLUDED | reaction cifrada de community announcement | não |
| `DecryptComment` | EXCLUDED | comentário de community announcement | não |
| `EncryptComment` | EXCLUDED | comentário de community announcement | não |

### Presença e receipts

| Método | Estado | Mapeamento 1:1 | WuzAPI |
|---|---|---|---|
| `SendPresence` | IMPLEMENTED | available/unavailable global | sim |
| `SubscribePresence` | IMPLEMENTED | online/last seen de contato individual | sim |
| `SendChatPresence` | IMPLEMENTED | composing/paused/recording | sim |
| `MarkRead` | IMPLEMENTED | read/played por IDs e chat 1:1 | sim |
| `SetForceActiveDeliveryReceipts` | IMPLEMENTED | política interna de delivery receipts | não |
| `SendProtocolMessageReceipt` | IMPLEMENTED | ack explícito de protocolo quando requerido | não |

### Upload, download, thumbnail e retry

| Método | Estado | Mapeamento 1:1 | WuzAPI |
|---|---|---|---|
| `Upload` | BASELINE | upload em memória legado do baseline | sim |
| `UploadReader` | IMPLEMENTED | upload streaming preferencial | não |
| `UploadNewsletter` | EXCLUDED | channel/newsletter | não |
| `UploadNewsletterReader` | EXCLUDED | channel/newsletter | não |
| `DeleteMedia` | IMPLEMENTED | cleanup de blob transitório de history sync | não |
| `Download` | INTERNAL | variante em memória evitada pelo spool | sim |
| `DownloadToFile` | BASELINE | inbound para spool privado | não |
| `DownloadAny` | DEPRECATED | selecionar tipo e usar `Download`/`DownloadToFile` | não |
| `DownloadThumbnail` | IMPLEMENTED | preview sanitizado quando não houver mídia completa | não |
| `FetchStickerPack` | IMPLEMENTED | metadados allowlisted do sticker pack | não |
| `DownloadMediaWithOnlyPath` | INTERNAL | low-level sem hash; não exposto | não |
| `DownloadMediaWithOnlyPathToFile` | INTERNAL | low-level sem hash; não exposto | não |
| `DownloadMediaWithPath` | INTERNAL | implementação de `Download`; direct path proibido no contrato | não |
| `DownloadMediaWithPathToFile` | INTERNAL | implementação de `DownloadToFile`; direct path proibido no contrato | não |
| `DownloadFB` | EXCLUDED | mídia FB/Armadillo | não |
| `DownloadFBToFile` | EXCLUDED | mídia FB/Armadillo | não |
| `SendMediaRetryReceipt` | IMPLEMENTED | retry controlado de mídia inbound | não |

### Histórico e sync protocolar

| Método | Estado | Mapeamento 1:1 | WuzAPI |
|---|---|---|---|
| `DownloadHistorySync` | IMPLEMENTED | decriptar/importar batch 1:1 | não |
| `SendHistorySyncServerErrorReceipt` | IMPLEMENTED | reportar falha verificável de history mídia | não |

### Descoberta, perfis, links e devices

| Método | Estado | Mapeamento 1:1 | WuzAPI |
|---|---|---|---|
| `IsOnWhatsApp` | IMPLEMENTED | disponibilidade por lote limitado | sim |
| `GetUserInfo` | IMPLEMENTED | info sanitizada de contato | sim |
| `GetBusinessProfile` | IMPLEMENTED | perfil business sanitizado | não |
| `GetProfilePictureInfo` | IMPLEMENTED | avatar/preview | sim |
| `GetUserDevices` | INTERNAL | fanout E2E interno; não expor device JIDs | não |
| `GetUserDevicesContext` | INTERNAL | alias de `GetUserDevices` | não |
| `GetContactQRLink` | IMPLEMENTED | link próprio com revogação administrativa | não |
| `ResolveContactQRLink` | IMPLEMENTED | resolver link de contato | não |
| `ResolveBusinessMessageLink` | IMPLEMENTED | resolver link `wa.me/message` | não |
| `GetBotListV2` | EXCLUDED | bots | não |
| `GetBotProfiles` | EXCLUDED | bots | não |

### Blocklist e privacy

| Método | Estado | Mapeamento 1:1 | WuzAPI |
|---|---|---|---|
| `GetBlocklist` | IMPLEMENTED | query administrativa sanitizada | sim |
| `UpdateBlocklist` | IMPLEMENTED | block/unblock com LID→PN | sim |
| `TryFetchPrivacySettings` | IMPLEMENTED | fetch remoto/cache | sim |
| `GetPrivacySettings` | IMPLEMENTED | snapshot cached sanitizado | indireto |
| `SetPrivacySetting` | IMPLEMENTED | matriz fechada name/value | sim |
| `SetDefaultDisappearingTimer` | IMPLEMENTED | default administrativo 0/24h/7d/90d | não |

### Push notifications

| Método | Estado | Mapeamento 1:1 | WuzAPI |
|---|---|---|---|
| `GetServerPushNotificationConfig` | EXCLUDED | desnecessário para gateway long-lived | não |
| `RegisterForPushNotifications` | EXCLUDED | push móvel fora do produto | não |

### Grupos e communities (24 excluídos)

| Método | Estado | Motivo |
|---|---|---|
| `CreateGroup` | EXCLUDED | grupo/community |
| `GetGroupInfo` | EXCLUDED | grupo/community |
| `GetGroupInfoFromInvite` | EXCLUDED | grupo/community |
| `GetGroupInfoFromLink` | EXCLUDED | grupo/community |
| `GetGroupInviteLink` | EXCLUDED | grupo/community |
| `GetGroupRequestParticipants` | EXCLUDED | grupo/community |
| `GetJoinedGroups` | EXCLUDED | grupo/community |
| `GetLinkedGroupsParticipants` | EXCLUDED | grupo/community |
| `GetSubGroups` | EXCLUDED | grupo/community |
| `JoinGroupWithInvite` | EXCLUDED | grupo/community |
| `JoinGroupWithLink` | EXCLUDED | grupo/community |
| `LeaveGroup` | EXCLUDED | grupo/community |
| `LinkGroup` | EXCLUDED | grupo/community |
| `UnlinkGroup` | EXCLUDED | grupo/community |
| `SetGroupAnnounce` | EXCLUDED | grupo/community |
| `SetGroupDescription` | EXCLUDED | grupo/community |
| `SetGroupJoinApprovalMode` | EXCLUDED | grupo/community |
| `SetGroupLocked` | EXCLUDED | grupo/community |
| `SetGroupMemberAddMode` | EXCLUDED | grupo/community |
| `SetGroupName` | EXCLUDED | grupo/community |
| `SetGroupPhoto` | EXCLUDED | grupo/community |
| `SetGroupTopic` | EXCLUDED | grupo/community |
| `UpdateGroupParticipants` | EXCLUDED | grupo/community |
| `UpdateGroupRequestParticipants` | EXCLUDED | grupo/community |

### Newsletters/channels (13 métodos próprios + 2 uploads já catalogados)

| Método | Estado | Motivo |
|---|---|---|
| `AcceptTOSNotice` | EXCLUDED | channel/newsletter |
| `CreateNewsletter` | EXCLUDED | channel/newsletter |
| `FollowNewsletter` | EXCLUDED | channel/newsletter |
| `UnfollowNewsletter` | EXCLUDED | channel/newsletter |
| `GetNewsletterInfo` | EXCLUDED | channel/newsletter |
| `GetNewsletterInfoWithInvite` | EXCLUDED | channel/newsletter |
| `GetNewsletterMessageUpdates` | EXCLUDED | channel/newsletter |
| `GetNewsletterMessages` | EXCLUDED | channel/newsletter |
| `GetSubscribedNewsletters` | EXCLUDED | channel/newsletter |
| `NewsletterMarkViewed` | EXCLUDED | channel/newsletter |
| `NewsletterSendReaction` | EXCLUDED | channel/newsletter |
| `NewsletterSubscribeLiveUpdates` | EXCLUDED | channel/newsletter |
| `NewsletterToggleMute` | EXCLUDED | channel/newsletter |

### Calls (fora do recorte)

| Método | Estado | Motivo |
|---|---|---|
| `RejectCall` | EXCLUDED | chamadas não são mensagens 1:1 e o upstream não implementa call media |

## Evidência por família aplicável

O manifesto executável em `internal/protocol/catalog` é a fonte por símbolo. Esta matriz resume os paths reais compartilhados por cada conjunto:

| Família | Implementação | Testes |
|---|---|---|
| Contrato e escopo PN/LID | `internal/domain/contract.go`; `internal/protocol/jid.go`; `internal/protocol/jid_scope.go` | `internal/domain/contract_test.go`; `internal/protocol/jid_test.go`; `internal/httpapi/recipient_scope_test.go` |
| Sessão, pairing, handlers e transporte | `internal/protocol/session.go`; `internal/protocol/client_settings.go`; `internal/protocol/device_resolver.go` | `internal/protocol/session_test.go`; `internal/protocol/event_bridge_test.go` |
| Mensagens tipadas e upload streaming | `internal/protocol/typed_messages.go` | `internal/protocol/typed_messages_test.go` |
| Ações, polls, receipts e disappearing | `internal/protocol/actions.go` | `internal/protocol/actions_test.go` |
| Presença | `internal/protocol/presence.go` | `internal/protocol/presence_test.go` |
| Queries, perfis, links e QR | `internal/protocol/queries.go` | `internal/protocol/queries_test.go` |
| Privacidade, blocklist e app-state | `internal/protocol/account_policy.go` | `internal/protocol/account_policy_test.go` |
| Histórico, mídia e recovery | `internal/protocol/recovery.go` | `internal/protocol/recovery_test.go` |
| Eventos normalizados | `internal/protocol/event_bridge.go`; `internal/protocol/device_resolver.go` | `internal/protocol/event_bridge_test.go` |
| Projeção/API/UI | `apps/api/app/Services/Communication/Events/GatewayEventIngestor.php`; `apps/api/app/Http/Controllers/Api/V1/Communication`; `apps/web/app/components/communication` | `CommunicationGatewayProjectionTest.php`; `CommunicationGatewayActionApiTest.php`; `communication.test.ts`; `communication-workspace-ui-gate.test.ts` |

## Eventos públicos (74/74)

### Aplicáveis e implementados

- [x] Sessão/pairing: `QR`, `PairSuccess`, `PairError`, `PairPasskeyRequest`, `PairPasskeyError`, `PairPasskeyConfirmation`, `QRScannedWithoutMultidevice`, `Connected`, `KeepAliveTimeout`, `KeepAliveRestored`, `LoggedOut`, `StreamReplaced`, `ManualLoginReconnect`, `TemporaryBan`, `ConnectFailure`, `ClientOutdated`, `CATRefreshError`, `StreamError`, `Disconnected`.
- [x] Mensagem/sync: `Message`, `Receipt`, `HistorySync`, `UndecryptableMessage`, `OfflineSyncPreview`, `OfflineSyncCompleted`, `MediaRetry`, `MediaRetryError`.
- [x] Presença/perfil/segurança: `ChatPresence`, `Presence`, `Picture`, `UserAbout`, `IdentityChange`, `PrivacySettings`, `Blocklist`, `BlocklistChange`.
- [x] App-state de conversa/contato: projeção 1:1 allowlisted e consumo explícito, sem serialização raw, para `Contact`, `PushName`, `BusinessName`, `Pin`, `Star`, `DeleteForMe`, `Mute`, `Archive`, `MarkChatAsRead`, `ClearChat`, `DeleteChat`, `PushNameSetting`, `UnarchiveChatsSetting`, `UserStatusMute`, `LabelEdit`, `LabelAssociationChat`, `LabelAssociationMessage`, `AppState`, `AppStateSyncComplete`, `AppStateSyncError`.

### Excluídos por grupo/channel/call/FB ou evento interno sem projeção de produto

- [x] Grupos: `JoinedGroup`, `GroupInfo`.
- [x] Channels/newsletters: `NewsletterMessageMeta`, `NewsletterJoin`, `NewsletterLeave`, `NewsletterMuteChange`, `NewsletterLiveUpdate`.
- [x] Calls: `CallOffer`, `CallAccept`, `CallPreAccept`, `CallTransport`, `CallOfferNotice`, `CallRelayLatency`, `CallTerminate`, `CallReject`, `UnknownCallEvent`.
- [x] FB/bots/outros fora do recorte: `FBMessage`, `MexNotificationData`, `NotifyAccountReachoutTimelock`.

## Checklist resumido de implementação

- [x] C0 Catálogo executável e gate de deriva para 135 métodos/74 eventos.
- [x] C1 Allowlist central 1:1, normalização PN/LID e rejeição de grupo/channel/broadcast.
- [x] C2 Sessão: context, readiness, reset, pair phone/passkey, passive e clients seguros.
- [x] C3 Mensagens: text/quote/preview e todos os tipos de mídia/conteúdo 1:1.
- [x] C4 Ações: edit, revoke, reaction, poll/vote, receipts e disappearing timer.
- [x] C5 Presença global/chat e subscriptions com sinais efêmeros.
- [x] C6 User/profile/business/avatar/links/contact QR e queries sanitizadas.
- [x] C7 Blocklist/privacy/app-state com matrizes e builders allowlisted.
- [x] C8 History/unavailable/media retry/thumbnail/sticker pack e streaming.
- [x] C9 Event bridge completa, sem evento raw nem dados sensíveis.
- [x] C10 Projeções Laravel e UI somente para operações úteis ao atendimento.
- [x] C11 Auditoria final, gates e reconciliação do catálogo sem `PENDING`.
