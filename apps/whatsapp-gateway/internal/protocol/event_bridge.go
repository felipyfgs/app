package protocol

import (
	"context"
	"crypto/sha256"
	"encoding/hex"
	"encoding/json"
	"errors"
	"fmt"
	"os"
	"strings"
	"sync/atomic"
	"time"
	"unicode"

	"github.com/inovaicontabil/fiscal-hub/apps/whatsapp-gateway/internal/domain"
	"github.com/inovaicontabil/fiscal-hub/apps/whatsapp-gateway/internal/spool"
	"github.com/inovaicontabil/fiscal-hub/apps/whatsapp-gateway/internal/store"
	"go.mau.fi/whatsmeow"
	"go.mau.fi/whatsmeow/proto/waE2E"
	"go.mau.fi/whatsmeow/proto/waWeb"
	"go.mau.fi/whatsmeow/types"
	"go.mau.fi/whatsmeow/types/events"
	"golang.org/x/sys/unix"
)

const (
	chatPresenceTTLSeconds = 15
	contactPresenceTTL     = 60
	maxHistoryMessages     = 100
)

// eventBridgeClient is deliberately narrower than the full Client surface.
// It gives tests a fakeable seam and documents the only upstream primitives
// that may inspect encrypted/raw message material before it is normalized.
type eventBridgeClient interface {
	DecryptPollVote(context.Context, *events.Message) (*waE2E.PollVoteMessage, error)
	DecryptSecretEncryptedMessage(context.Context, *events.Message) (*waE2E.Message, error)
	ParseWebMessage(types.JID, *waWeb.WebMessageInfo) (*events.Message, error)
	DownloadToFile(context.Context, whatsmeow.DownloadableMessage, whatsmeow.File) error
}

var _ eventBridgeClient = (*whatsmeow.Client)(nil)

type EventBridge struct {
	store         store.Store
	spool         *spool.Store
	maxMediaBytes int64
	rejectedScope atomic.Uint64
}

type eventHandlingStatus struct {
	success bool
}

type eventHandlingStatusKey struct{}

type deferredEvent struct {
	eventID    string
	sessionID  string
	eventType  domain.EventType
	occurredAt time.Time
	payload    map[string]any
}

type deferredEventCollector struct {
	events []deferredEvent
}

type deferredEventCollectorKey struct{}

func NewEventBridge(persistence store.Store, mediaSpool *spool.Store, maxMediaBytes int64) *EventBridge {
	return &EventBridge{store: persistence, spool: mediaSpool, maxMediaBytes: maxMediaBytes}
}

func (b *EventBridge) Handle(sessionID string, client *whatsmeow.Client, raw any) {
	_ = b.HandleWithSuccess(sessionID, client, raw)
}

// HandleWithSuccess is suitable for AddEventHandlerWithSuccessStatus. It only
// returns true when every event selected for persistence was appended (or was
// an idempotent duplicate). This lets whatsmeow mark handler failure instead
// of silently acknowledging a lost durable event.
func (b *EventBridge) HandleWithSuccess(sessionID string, client *whatsmeow.Client, raw any) bool {
	ctx, cancel := context.WithTimeout(context.Background(), 2*time.Minute)
	defer cancel()
	status := &eventHandlingStatus{success: true}
	ctx = context.WithValue(ctx, eventHandlingStatusKey{}, status)
	var bridgeClient eventBridgeClient
	if client != nil {
		bridgeClient = client
	}
	b.handle(ctx, sessionID, bridgeClient, raw)
	return status.success
}

func (b *EventBridge) handle(ctx context.Context, sessionID string, client eventBridgeClient, raw any) {
	switch event := raw.(type) {
	case *events.Message:
		b.handleMessage(ctx, sessionID, client, event, false)
	case *events.Receipt:
		b.handleReceipt(ctx, sessionID, event)
	case *events.HistorySync:
		b.handleHistorySync(ctx, sessionID, client, event)
	case *events.UndecryptableMessage:
		b.handleUndecryptable(ctx, sessionID, event)
	case *events.MediaRetry:
		b.handleMediaRetry(ctx, sessionID, event)
	case *events.MediaRetryError:
		// MediaRetryError is embedded in MediaRetry and has no message ID on
		// its own, so it cannot satisfy the normalized retry contract.
	case *events.OfflineSyncPreview:
		b.append(ctx, stableID("sync-preview", sessionID, event.Total, event.Messages), sessionID,
			domain.EventSyncStatusChanged, time.Now(), map[string]any{
				"component": "OFFLINE", "status": "STARTED",
			})
	case *events.OfflineSyncCompleted:
		b.append(ctx, stableID("sync-complete", sessionID, event.Count), sessionID,
			domain.EventSyncStatusChanged, time.Now(), map[string]any{
				"component": "OFFLINE", "status": "COMPLETED",
			})
	case *events.ChatPresence:
		b.handleChatPresence(ctx, sessionID, event)
	case *events.Presence:
		b.handlePresence(ctx, sessionID, event)
	case *events.Picture:
		b.handlePicture(ctx, sessionID, event)
	case *events.UserAbout:
		b.handleUserAbout(ctx, sessionID, event)
	case *events.IdentityChange:
		b.handleIdentityChange(ctx, sessionID, event)
	case *events.PrivacySettings:
		b.handlePrivacy(ctx, sessionID, event)
	case *events.Blocklist:
		b.handleBlocklist(ctx, sessionID, event)
	case *events.BlocklistChange:
		b.handleBlocklistChange(ctx, sessionID, event, time.Now())
	case *events.Contact:
		b.handleContact(ctx, sessionID, event)
	case *events.PushName:
		b.handlePushName(ctx, sessionID, event)
	case *events.BusinessName:
		b.handleBusinessName(ctx, sessionID, event)
	case *events.Pin:
		b.handleChatState(ctx, sessionID, event.JID, event.Timestamp, "PIN", map[string]any{
			"value": event.Action.GetPinned(),
		})
	case *events.Star:
		b.handleChatState(ctx, sessionID, event.ChatJID, event.Timestamp, "STAR", map[string]any{
			"value": event.Action.GetStarred(), "target_message_id": event.MessageID,
		})
	case *events.DeleteForMe:
		b.handleChatState(ctx, sessionID, event.ChatJID, event.Timestamp, "DELETE_FOR_ME", map[string]any{
			"target_message_id": event.MessageID,
		})
	case *events.Mute:
		b.handleChatState(ctx, sessionID, event.JID, event.Timestamp, "MUTE", map[string]any{
			"value": event.Action.GetMuted(),
		})
	case *events.Archive:
		b.handleChatState(ctx, sessionID, event.JID, event.Timestamp, "ARCHIVE", map[string]any{
			"value": event.Action.GetArchived(),
		})
	case *events.MarkChatAsRead:
		b.handleChatState(ctx, sessionID, event.JID, event.Timestamp, "MARK_READ", map[string]any{
			"value": event.Action.GetRead(),
		})
	case *events.ClearChat:
		b.handleChatState(ctx, sessionID, event.JID, event.Timestamp, "CLEAR_CHAT", nil)
	case *events.DeleteChat:
		b.handleChatState(ctx, sessionID, event.JID, event.Timestamp, "DELETE_CHAT", nil)
	case *events.LabelAssociationChat:
		b.handleChatState(ctx, sessionID, event.JID, event.Timestamp, "LABEL_CHAT", map[string]any{
			"label_id": event.LabelID, "value": event.Action.GetLabeled(),
		})
	case *events.LabelAssociationMessage:
		b.handleChatState(ctx, sessionID, event.JID, event.Timestamp, "LABEL_MESSAGE", map[string]any{
			"label_id": event.LabelID, "target_message_id": event.MessageID,
			"value": event.Action.GetLabeled(),
		})
	case *events.LabelEdit:
		// Label definitions are account-level and have no 1:1 target. Chat and
		// message label associations are projected by the scoped cases above.
	case *events.PushNameSetting:
		// This is an account profile setting without a contact target.
	case *events.UnarchiveChatsSetting:
		// This is an account-wide setting without a 1:1 target.
	case *events.AppStateSyncComplete:
		b.append(ctx, stableID("app-state-complete", sessionID, event.Name, event.Version), sessionID,
			domain.EventSyncStatusChanged, time.Now(), map[string]any{
				"component": "APP_STATE", "status": "COMPLETED",
			})
	case *events.AppStateSyncError:
		b.append(ctx, stableID("app-state-error", sessionID, event.Name, event.FullSync), sessionID,
			domain.EventSyncStatusChanged, time.Now(), map[string]any{
				"component": "APP_STATE", "status": "FAILED", "error_code": "APP_STATE_SYNC_ERROR",
			})
	case *events.AppState:
		// Raw app-state actions are intentionally never serialized. The higher
		// level allowlisted events above are the supported projection.
	case *events.UserStatusMute:
		// Status/broadcast is outside the 1:1 conversation scope.
		b.rejectedScope.Add(1)
	case *events.JoinedGroup, *events.GroupInfo,
		*events.NewsletterMessageMeta, *events.NewsletterJoin, *events.NewsletterLeave,
		*events.NewsletterMuteChange, *events.NewsletterLiveUpdate,
		*events.CallOffer, *events.CallAccept, *events.CallPreAccept, *events.CallTransport,
		*events.CallOfferNotice, *events.CallRelayLatency, *events.CallTerminate,
		*events.CallReject, *events.UnknownCallEvent,
		*events.FBMessage, *events.MexNotificationData, *events.NotifyAccountReachoutTimelock:
		b.rejectedScope.Add(1)
	case *events.Connected:
		b.handleSessionStatus(ctx, sessionID, "CONNECTED")
	case *events.Disconnected:
		b.handleSessionStatus(ctx, sessionID, "DEGRADED")
	case *events.LoggedOut:
		b.handleSessionStatus(ctx, sessionID, "REVOKED")
	case *events.QR:
		b.handlePairing(ctx, sessionID, "QR_AVAILABLE")
	case *events.PairSuccess:
		b.handlePairing(ctx, sessionID, "PAIRED")
	case *events.PairError:
		b.handlePairing(ctx, sessionID, "PAIR_FAILED")
	case *events.PairPasskeyRequest:
		b.handlePairing(ctx, sessionID, "PASSKEY_REQUIRED")
	case *events.PairPasskeyError:
		b.handlePairing(ctx, sessionID, "PASSKEY_FAILED")
	case *events.PairPasskeyConfirmation:
		b.handlePairing(ctx, sessionID, "PASSKEY_CONFIRMATION_REQUIRED")
	case *events.QRScannedWithoutMultidevice:
		b.handlePairing(ctx, sessionID, "MULTIDEVICE_REQUIRED")
	case *events.KeepAliveTimeout:
		b.handleGatewayAlert(ctx, sessionID, "KEEPALIVE_TIMEOUT", "WARNING", true, 0)
	case *events.KeepAliveRestored:
		b.handleGatewayAlert(ctx, sessionID, "KEEPALIVE_RESTORED", "INFO", false, 0)
	case *events.StreamReplaced:
		b.handleGatewayAlert(ctx, sessionID, "STREAM_REPLACED", "CRITICAL", false, 0)
	case *events.ManualLoginReconnect:
		b.handleGatewayAlert(ctx, sessionID, "MANUAL_LOGIN_RECONNECT", "INFO", true, 0)
	case *events.TemporaryBan:
		b.handleGatewayAlert(ctx, sessionID, "TEMPORARY_BAN", "CRITICAL", true, durationSeconds(event.Expire))
	case *events.ConnectFailure:
		b.handleGatewayAlert(ctx, sessionID, "CONNECT_FAILURE_"+event.Reason.NumberString(), "ERROR", true, 0)
	case *events.ClientOutdated:
		b.handleGatewayAlert(ctx, sessionID, "CLIENT_OUTDATED", "CRITICAL", false, 0)
	case *events.CATRefreshError:
		b.handleGatewayAlert(ctx, sessionID, "AUTH_REFRESH_FAILED", "ERROR", true, 0)
	case *events.StreamError:
		b.handleGatewayAlert(ctx, sessionID, "STREAM_ERROR_"+sanitizeCode(event.Code), "ERROR", true, 0)
		// Group, newsletter, calls, FB/bots and account reachout events are
		// intentionally absent: they cannot enter the 1:1 ledger.
	}
}

func (b *EventBridge) handleMessage(
	ctx context.Context,
	sessionID string,
	client eventBridgeClient,
	event *events.Message,
	history bool,
) {
	if event == nil || event.Message == nil {
		return
	}
	peer, err := normalizeMessageSource(event.Info.MessageSource)
	if err != nil {
		b.rejectScope(err)
		return
	}

	message, handled := b.prepareMessage(ctx, sessionID, client, event, peer, history)
	if handled {
		return
	}

	payload := normalizedMessageContent(message)
	payload["provider_message_id"] = event.Info.ID
	payload["from"] = peer.Normalized
	payload["direction"] = messageDirection(event.Info.IsFromMe)
	if history {
		payload["history"] = true
	}
	payload["occurred_at"] = eventTimestamp(event.Info.Timestamp).Format(time.RFC3339Nano)
	if contextInfo := inboundMessageContext(message); contextInfo != nil && contextInfo.GetStanzaID() != "" {
		replyTo := map[string]any{"provider_message_id": contextInfo.GetStanzaID()}
		if sender, senderErr := normalizeOptionalJID(contextInfo.GetParticipant()); senderErr == nil && sender != "" {
			replyTo["sender"] = sender
		}
		payload["reply_to"] = replyTo
	}
	if media := downloadableMessage(message); media != nil {
		if !history && (client == nil || b.spool == nil) {
			payload["media_error_code"] = "MEDIA_SPOOL_UNAVAILABLE"
		} else if !history {
			record, downloadErr := b.downloadToSpool(ctx, client, stableID("media", sessionID, event.Info.ID), media)
			if downloadErr != nil {
				payload["media_error_code"] = "MEDIA_DOWNLOAD_FAILED"
			} else {
				payload["spool_id"] = record.ID
				payload["media_size_bytes"] = record.SizeBytes
				payload["media_sha256"] = record.SHA256
				payload["mime_type"] = mediaMIME(media)
			}
		}
	}
	b.append(ctx, stableID(messageEventPrefix(history), sessionID, event.Info.ID), sessionID,
		domain.EventMessageReceived, event.Info.Timestamp, payload)
}

func (b *EventBridge) prepareMessage(
	ctx context.Context,
	sessionID string,
	client eventBridgeClient,
	event *events.Message,
	peer OneToOneAddress,
	history bool,
) (*waE2E.Message, bool) {
	message := event.Message
	targetID := ""
	if encrypted := message.GetSecretEncryptedMessage(); encrypted != nil {
		targetID = encrypted.GetTargetMessageKey().GetID()
		if client == nil {
			b.handleMessageDecryptFailure(ctx, sessionID, event, "SECRET_MESSAGE_DECRYPT_UNAVAILABLE")
			return nil, true
		}
		decrypted, decryptErr := client.DecryptSecretEncryptedMessage(ctx, event)
		if decryptErr != nil || decrypted == nil {
			b.handleMessageDecryptFailure(ctx, sessionID, event, "SECRET_MESSAGE_DECRYPT_FAILED")
			return nil, true
		}
		message = decrypted
	}

	if pollUpdate := message.GetPollUpdateMessage(); pollUpdate != nil {
		b.handlePollVote(ctx, sessionID, client, event, peer, pollUpdate, history)
		return nil, true
	}
	if protocolMessage := message.GetProtocolMessage(); protocolMessage != nil {
		switch protocolMessage.GetType() {
		case waE2E.ProtocolMessage_REVOKE:
			details := map[string]any{}
			if history {
				details["history"] = true
			}
			b.appendMessageAction(ctx, sessionID, event.Info, "REVOKE", protocolMessage.GetKey().GetID(), peer,
				details)
			return nil, true
		case waE2E.ProtocolMessage_MESSAGE_EDIT:
			targetID = protocolMessage.GetKey().GetID()
			message = protocolMessage.GetEditedMessage()
		}
	}
	if reaction := message.GetReactionMessage(); reaction != nil {
		details := map[string]any{"emoji": reaction.GetText()}
		if history {
			details["history"] = true
		}
		b.appendMessageAction(ctx, sessionID, event.Info, "REACTION", reaction.GetKey().GetID(), peer,
			details)
		return nil, true
	}
	if event.IsEdit || targetID != "" {
		if targetID == "" {
			targetID = event.Info.ID
		}
		details := map[string]any{"text": messageText(message)}
		if history {
			details["history"] = true
		}
		b.appendMessageAction(ctx, sessionID, event.Info, "EDIT", targetID, peer, details)
		return nil, true
	}
	return message, false
}

func (b *EventBridge) handlePollVote(
	ctx context.Context,
	sessionID string,
	client eventBridgeClient,
	event *events.Message,
	peer OneToOneAddress,
	update *waE2E.PollUpdateMessage,
	history bool,
) {
	targetID := update.GetPollCreationMessageKey().GetID()
	if client == nil {
		b.handleMessageDecryptFailure(ctx, sessionID, event, "POLL_VOTE_DECRYPT_UNAVAILABLE")
		return
	}
	vote, err := client.DecryptPollVote(ctx, event)
	if err != nil || vote == nil {
		b.handleMessageDecryptFailure(ctx, sessionID, event, "POLL_VOTE_DECRYPT_FAILED")
		return
	}
	hashes := make([]string, 0, len(vote.GetSelectedOptions()))
	for _, hash := range vote.GetSelectedOptions() {
		if len(hash) == sha256.Size {
			hashes = append(hashes, hex.EncodeToString(hash))
		}
	}
	details := map[string]any{"option_hashes": hashes}
	if history {
		details["history"] = true
	}
	b.appendMessageAction(ctx, sessionID, event.Info, "POLL_VOTE", targetID, peer, details)
}

func (b *EventBridge) appendMessageAction(
	ctx context.Context,
	sessionID string,
	info types.MessageInfo,
	action, targetID string,
	peer OneToOneAddress,
	details map[string]any,
) {
	if targetID == "" {
		return
	}
	payload := map[string]any{
		"action": action, "provider_message_id": info.ID,
		"target_message_id": targetID, "from": peer.Normalized,
	}
	for key, value := range details {
		payload[key] = value
	}
	eventID := stableID("message-action", sessionID, action, info.ID, targetID)
	if collector, ok := ctx.Value(deferredEventCollectorKey{}).(*deferredEventCollector); ok {
		collector.events = append(collector.events, deferredEvent{
			eventID: eventID, sessionID: sessionID, eventType: domain.EventMessageActionReceived,
			occurredAt: info.Timestamp, payload: payload,
		})
		return
	}
	b.append(ctx, eventID, sessionID, domain.EventMessageActionReceived, info.Timestamp, payload)
}

func (b *EventBridge) handleMessageDecryptFailure(
	ctx context.Context,
	sessionID string,
	event *events.Message,
	code string,
) {
	b.append(ctx, stableID("decrypt-failed", sessionID, event.Info.ID, code), sessionID,
		domain.EventGatewayAlert, event.Info.Timestamp, map[string]any{
			"code": code, "severity": "WARNING", "retryable": true,
		})
}

func (b *EventBridge) handleReceipt(ctx context.Context, sessionID string, event *events.Receipt) {
	if event == nil {
		return
	}
	_, err := normalizeMessageSource(event.MessageSource)
	if err != nil {
		b.rejectScope(err)
		return
	}
	status := ""
	switch event.Type {
	case types.ReceiptTypeDelivered:
		status = "DELIVERED"
	case types.ReceiptTypeRead, types.ReceiptTypeReadSelf:
		status = "READ"
	case types.ReceiptTypePlayed, types.ReceiptTypePlayedSelf:
		status = "READ"
	case types.ReceiptTypeRetry:
		status = "UNKNOWN"
	case types.ReceiptTypeServerError:
		status = "UNKNOWN"
	default:
		return
	}
	for _, messageID := range event.MessageIDs {
		payload := map[string]any{"provider_message_id": messageID, "status": status}
		b.append(ctx, stableID("receipt-"+strings.ToLower(status), sessionID, messageID), sessionID,
			domain.EventMessageStatusChanged, event.Timestamp, payload)
	}
}

func (b *EventBridge) handleHistorySync(
	ctx context.Context,
	sessionID string,
	client eventBridgeClient,
	event *events.HistorySync,
) {
	if event == nil || event.Data == nil {
		return
	}
	messages := make([]map[string]any, 0)
	messageIDs := make([]string, 0)
	actions := &deferredEventCollector{}
	historyContext := context.WithValue(ctx, deferredEventCollectorKey{}, actions)
	seen := make(map[string]struct{})
	rejected := 0
	for _, conversation := range event.Data.GetConversations() {
		if len(messages) >= maxHistoryMessages {
			break
		}
		chat, err := types.ParseJID(conversation.GetID())
		if err != nil {
			rejected++
			continue
		}
		address, err := NormalizeOneToOneJID(chat)
		if err != nil {
			b.rejectScope(err)
			rejected++
			continue
		}
		for _, historyMessage := range conversation.GetMessages() {
			if len(messages) >= maxHistoryMessages {
				break
			}
			webMessage := historyMessage.GetMessage()
			messageID := webMessage.GetKey().GetID()
			if messageID == "" {
				rejected++
				continue
			}
			if _, duplicate := seen[messageID]; duplicate {
				continue
			}
			seen[messageID] = struct{}{}
			messageIDs = append(messageIDs, messageID)
			if client == nil {
				rejected++
				continue
			}
			parsed, parseErr := client.ParseWebMessage(address.JID, webMessage)
			if parseErr != nil || parsed == nil || parsed.Message == nil {
				rejected++
				continue
			}
			peer, sourceErr := normalizeMessageSource(parsed.Info.MessageSource)
			if sourceErr != nil {
				b.rejectScope(sourceErr)
				rejected++
				continue
			}
			normalizedMessage, actionHandled := b.prepareMessage(historyContext, sessionID, client, parsed, peer, true)
			if actionHandled {
				continue
			}
			parsedCopy := *parsed
			parsedCopy.Message = normalizedMessage
			payload := normalizedHistoryMessage(&parsedCopy, peer)
			messages = append(messages, payload)
		}
	}
	batchSignature := strings.Join(messageIDs, "\n")
	batchID := stableID("history-batch", sessionID, event.Data.GetSyncType(), event.Data.GetChunkOrder(), batchSignature)
	payload := map[string]any{
		"batch_id":  batchID,
		"sync_type": event.Data.GetSyncType().String(), "chunk_order": event.Data.GetChunkOrder(),
		"progress": event.Data.GetProgress(), "messages": messages, "message_count": len(messages),
		"rejected_count": rejected, "truncated": len(messages) == maxHistoryMessages,
		"complete": event.Data.GetProgress() >= 100,
	}
	b.append(ctx, stableID("history", sessionID, event.Data.GetSyncType(), event.Data.GetChunkOrder(), batchSignature), sessionID,
		domain.EventHistorySynced, time.Now(), payload)
	if !eventHandlingSucceeded(ctx) {
		return
	}
	for _, deferred := range actions.events {
		b.append(ctx, deferred.eventID, deferred.sessionID, deferred.eventType, deferred.occurredAt, deferred.payload)
	}
}

func normalizedHistoryMessage(event *events.Message, peer OneToOneAddress) map[string]any {
	payload := normalizedMessageContent(event.Message)
	payload["provider_message_id"] = event.Info.ID
	payload["from"] = peer.Normalized
	payload["direction"] = messageDirection(event.Info.IsFromMe)
	payload["history"] = true
	payload["occurred_at"] = eventTimestamp(event.Info.Timestamp).Format(time.RFC3339Nano)
	if contextInfo := inboundMessageContext(event.Message); contextInfo != nil && contextInfo.GetStanzaID() != "" {
		payload["reply_to"] = map[string]any{"provider_message_id": contextInfo.GetStanzaID()}
	}
	return payload
}

func (b *EventBridge) handleUndecryptable(ctx context.Context, sessionID string, event *events.UndecryptableMessage) {
	if event == nil {
		return
	}
	_, err := normalizeMessageSource(event.Info.MessageSource)
	if err != nil {
		b.rejectScope(err)
		return
	}
	b.append(ctx, stableID("undecryptable", sessionID, event.Info.ID), sessionID,
		domain.EventMediaRetryUpdated, event.Info.Timestamp, map[string]any{
			"provider_message_id": event.Info.ID, "status": "REQUESTED",
		})
}

func (b *EventBridge) handleMediaRetry(ctx context.Context, sessionID string, event *events.MediaRetry) {
	if event == nil {
		return
	}
	_, err := NormalizeOneToOneJID(event.ChatID)
	if err != nil {
		b.rejectScope(err)
		return
	}
	status := "READY"
	errorCode := ""
	if event.Error != nil {
		status = "FAILED"
		errorCode = fmt.Sprintf("MEDIA_RETRY_%d", event.Error.Code)
	}
	payload := map[string]any{"provider_message_id": event.MessageID, "status": status}
	if errorCode != "" {
		payload["error_code"] = errorCode
	}
	b.append(ctx, stableID("media-retry", sessionID, event.MessageID, status), sessionID,
		domain.EventMediaRetryUpdated, event.Timestamp, payload)
}

func (b *EventBridge) handleChatPresence(ctx context.Context, sessionID string, event *events.ChatPresence) {
	if event == nil {
		return
	}
	peer, err := normalizeMessageSource(event.MessageSource)
	if err != nil {
		b.rejectScope(err)
		return
	}
	b.append(ctx, stableID("chat-presence", sessionID, peer.Normalized, event.State, event.Media, time.Now().Unix()/5),
		sessionID, domain.EventChatPresenceChanged, time.Now(), map[string]any{
			"from": peer.Normalized, "presence": chatPresenceValue(event.State, event.Media),
			"media": chatPresenceMedia(event.Media), "ttl_seconds": chatPresenceTTLSeconds,
		})
}

func (b *EventBridge) handlePresence(ctx context.Context, sessionID string, event *events.Presence) {
	if event == nil {
		return
	}
	peer, err := NormalizeOneToOneJID(event.From)
	if err != nil {
		b.rejectScope(err)
		return
	}
	payload := map[string]any{
		"from": peer.Normalized, "available": true, "ttl_seconds": contactPresenceTTL,
	}
	if event.Unavailable {
		payload["available"] = false
		if !event.LastSeen.IsZero() {
			payload["last_seen"] = event.LastSeen.UTC().Format(time.RFC3339Nano)
		}
	}
	b.append(ctx, stableID("presence", sessionID, peer.Normalized, event.Unavailable, event.LastSeen.Unix()),
		sessionID, domain.EventPresenceChanged, time.Now(), payload)
}

func (b *EventBridge) handlePicture(ctx context.Context, sessionID string, event *events.Picture) {
	peer, err := NormalizeOneToOneJID(event.JID)
	if err != nil {
		b.rejectScope(err)
		return
	}
	payload := map[string]any{
		"user": peer.Normalized,
	}
	if !event.Remove {
		payload["picture_id"] = event.PictureID
	}
	b.append(ctx, stableID("picture", sessionID, peer.Normalized, event.PictureID), sessionID,
		domain.EventContactProfileChanged, event.Timestamp, payload)
}

func (b *EventBridge) handleUserAbout(ctx context.Context, sessionID string, event *events.UserAbout) {
	peer, err := NormalizeOneToOneJID(event.JID)
	if err != nil {
		b.rejectScope(err)
		return
	}
	b.append(ctx, stableID("about", sessionID, peer.Normalized, event.Timestamp.UnixMilli()), sessionID,
		domain.EventContactProfileChanged, event.Timestamp, map[string]any{
			"user": peer.Normalized, "about": event.Status,
		})
}

func (b *EventBridge) handleIdentityChange(ctx context.Context, sessionID string, event *events.IdentityChange) {
	peer, err := NormalizeOneToOneJID(event.JID)
	if err != nil {
		b.rejectScope(err)
		return
	}
	b.append(ctx, stableID("identity", sessionID, peer.Normalized, event.Timestamp.UnixMilli()), sessionID,
		domain.EventIdentityChanged, event.Timestamp, map[string]any{
			"user": peer.Normalized, "change": "IDENTITY_CHANGED",
		})
}

func (b *EventBridge) handlePrivacy(ctx context.Context, sessionID string, event *events.PrivacySettings) {
	if event == nil {
		return
	}
	settings := make([]map[string]any, 0, 4)
	appendSetting := func(name string, value types.PrivacySetting, allowed ...types.PrivacySetting) {
		for _, candidate := range allowed {
			if value == candidate {
				settings = append(settings, map[string]any{"name": name, "value": string(value)})
				return
			}
		}
	}
	appendSetting("last", event.NewSettings.LastSeen, types.PrivacySettingAll, types.PrivacySettingContacts,
		types.PrivacySettingContactBlacklist, types.PrivacySettingNone)
	appendSetting("profile", event.NewSettings.Profile, types.PrivacySettingAll, types.PrivacySettingContacts,
		types.PrivacySettingContactBlacklist, types.PrivacySettingNone)
	appendSetting("readreceipts", event.NewSettings.ReadReceipts, types.PrivacySettingAll, types.PrivacySettingNone)
	appendSetting("online", event.NewSettings.Online, types.PrivacySettingAll, types.PrivacySettingMatchLastSeen)
	b.append(ctx, stableID("privacy", sessionID, event.NewSettings.LastSeen, event.NewSettings.Profile,
		event.NewSettings.ReadReceipts, event.NewSettings.Online), sessionID, domain.EventPrivacyChanged, time.Now(),
		map[string]any{"settings": settings})
}

func (b *EventBridge) handleBlocklist(ctx context.Context, sessionID string, event *events.Blocklist) {
	if event == nil {
		return
	}
	if event.Action == events.BlocklistActionModify {
		b.append(ctx, stableID("blocklist", sessionID, event.Action), sessionID,
			domain.EventBlocklistChanged, time.Now(), map[string]any{"action": "SET", "users": []string{}})
		return
	}
	for _, change := range event.Changes {
		changeCopy := change
		b.handleBlocklistChange(ctx, sessionID, &changeCopy, time.Now())
	}
}

func (b *EventBridge) handleBlocklistChange(
	ctx context.Context,
	sessionID string,
	event *events.BlocklistChange,
	occurredAt time.Time,
) {
	if event == nil {
		return
	}
	peer, err := NormalizeOneToOneJID(event.JID)
	if err != nil {
		b.rejectScope(err)
		return
	}
	b.append(ctx, stableID("blocklist-change", sessionID, peer.Normalized, event.Action), sessionID,
		domain.EventBlocklistChanged, occurredAt, map[string]any{
			"action": strings.ToUpper(string(event.Action)), "users": []string{peer.Normalized},
		})
}

func (b *EventBridge) handleContact(ctx context.Context, sessionID string, event *events.Contact) {
	peer, err := NormalizeOneToOneJID(event.JID)
	if err != nil {
		b.rejectScope(err)
		return
	}
	b.append(ctx, stableID("contact", sessionID, peer.Normalized, event.Timestamp.UnixMilli()), sessionID,
		domain.EventContactProfileChanged, event.Timestamp, map[string]any{
			"user": peer.Normalized, "display_name": event.Action.GetFullName(),
		})
}

func (b *EventBridge) handlePushName(ctx context.Context, sessionID string, event *events.PushName) {
	peer, err := NormalizeOneToOneJID(event.JID)
	if err != nil {
		if alternate, alternateErr := NormalizeOneToOneJID(event.JIDAlt); alternateErr == nil {
			peer = alternate
		} else {
			b.rejectScope(err)
			return
		}
	}
	b.append(ctx, stableID("push-name", sessionID, peer.Normalized, event.NewPushName), sessionID,
		domain.EventContactProfileChanged, messageInfoTimestamp(event.Message), map[string]any{
			"user": peer.Normalized, "display_name": event.NewPushName,
		})
}

func (b *EventBridge) handleBusinessName(ctx context.Context, sessionID string, event *events.BusinessName) {
	peer, err := NormalizeOneToOneJID(event.JID)
	if err != nil {
		b.rejectScope(err)
		return
	}
	b.append(ctx, stableID("business-name", sessionID, peer.Normalized, event.NewBusinessName), sessionID,
		domain.EventContactProfileChanged, messageInfoTimestamp(event.Message), map[string]any{
			"user": peer.Normalized, "business_name": event.NewBusinessName,
		})
}

func (b *EventBridge) handleChatState(
	ctx context.Context,
	sessionID string,
	jid types.JID,
	timestamp time.Time,
	action string,
	details map[string]any,
) {
	peer, err := NormalizeOneToOneJID(jid)
	if err != nil {
		b.rejectScope(err)
		return
	}
	payload := map[string]any{"to": peer.Normalized, "action": action}
	for key, value := range details {
		if value != nil {
			payload[key] = value
		}
	}
	b.append(ctx, stableID("chat-state", sessionID, peer.Normalized, action, timestamp.UnixMilli()), sessionID,
		domain.EventChatStateChanged, timestamp, payload)
}

func (b *EventBridge) handleSessionStatus(ctx context.Context, sessionID, status string) {
	now := time.Now().UTC()
	b.append(ctx, stableID("session-"+strings.ToLower(status), sessionID, now.Unix()/10), sessionID,
		domain.EventSessionStatusChanged, now, map[string]any{"status": status})
}

func (b *EventBridge) handlePairing(ctx context.Context, sessionID, state string) {
	now := time.Now().UTC()
	b.append(ctx, stableID("pairing", sessionID, state, now.Unix()/10), sessionID,
		domain.EventPairingUpdated, now, map[string]any{"event": state})
}

func (b *EventBridge) handleGatewayAlert(
	ctx context.Context,
	sessionID, code, severity string,
	retryable bool,
	retryAfterSeconds int64,
) {
	now := time.Now().UTC()
	payload := map[string]any{"code": code, "severity": severity, "retryable": retryable}
	if retryAfterSeconds > 0 {
		payload["retry_after_seconds"] = retryAfterSeconds
	}
	b.append(ctx, stableID("gateway-alert", sessionID, code, now.Unix()/10), sessionID,
		domain.EventGatewayAlert, now, payload)
}

// RejectedScopeCount exposes only an aggregate counter. No server, user or JID
// is used as a label, so rejected group/channel identifiers cannot leak.
func (b *EventBridge) RejectedScopeCount() uint64 {
	return b.rejectedScope.Load()
}

func (b *EventBridge) rejectScope(err error) {
	if errors.Is(err, ErrRecipientScopeNotAllowed) || errors.Is(err, ErrRecipientInvalid) {
		b.rejectedScope.Add(1)
	}
}

func normalizeMessageSource(source types.MessageSource) (OneToOneAddress, error) {
	if source.IsGroup {
		return OneToOneAddress{}, ErrRecipientScopeNotAllowed
	}
	chat, err := NormalizeOneToOneJID(source.Chat)
	if err != nil {
		return OneToOneAddress{}, err
	}
	if chat.Kind == AddressLID {
		if alternate, alternateErr := NormalizeOneToOneJID(source.SenderAlt); alternateErr == nil &&
			alternate.Kind == AddressPN {
			return alternate, nil
		}
	}
	return chat, nil
}

func (b *EventBridge) append(
	ctx context.Context,
	eventID, sessionID string,
	eventType domain.EventType,
	occurredAt time.Time,
	payloadValue map[string]any,
) {
	payload, err := json.Marshal(payloadValue)
	if err != nil {
		markEventHandlingFailure(ctx)
		return
	}
	digest := sha256.Sum256(payload)
	_, err = b.store.AppendEvent(ctx, domain.Event{
		ContractVersion: "v1", EventID: eventID, SessionID: sessionID, Type: eventType,
		OccurredAt: eventTimestamp(occurredAt), Payload: payload, Digest: hex.EncodeToString(digest[:]),
	})
	if err != nil {
		markEventHandlingFailure(ctx)
	}
}

func markEventHandlingFailure(ctx context.Context) {
	if status, ok := ctx.Value(eventHandlingStatusKey{}).(*eventHandlingStatus); ok {
		status.success = false
	}
}

func eventHandlingSucceeded(ctx context.Context) bool {
	status, ok := ctx.Value(eventHandlingStatusKey{}).(*eventHandlingStatus)
	return !ok || status.success
}

func (b *EventBridge) downloadToSpool(
	ctx context.Context,
	client eventBridgeClient,
	spoolID string,
	media whatsmeow.DownloadableMessage,
) (spool.Record, error) {
	if length, ok := media.(interface{ GetFileLength() uint64 }); ok &&
		length.GetFileLength() > uint64(b.maxMediaBytes) {
		return spool.Record{}, errors.New("media exceeds configured limit")
	}
	fd, err := unix.MemfdCreate("whatsapp-media", unix.MFD_CLOEXEC)
	if err != nil {
		return spool.Record{}, err
	}
	file := os.NewFile(uintptr(fd), "whatsapp-media")
	defer file.Close()
	if err := client.DownloadToFile(ctx, media, file); err != nil {
		return spool.Record{}, err
	}
	info, err := file.Stat()
	if err != nil {
		return spool.Record{}, err
	}
	if info.Size() > b.maxMediaBytes {
		return spool.Record{}, errors.New("media exceeds configured limit")
	}
	if _, err := file.Seek(0, 0); err != nil {
		return spool.Record{}, err
	}
	return b.spool.Put(ctx, spoolID, file)
}

func normalizedMessageContent(message *waE2E.Message) map[string]any {
	payload := map[string]any{"kind": messageKind(message)}
	if text := messageText(message); text != "" {
		payload["text"] = text
	}
	switch {
	case message.GetLocationMessage() != nil:
		location := message.GetLocationMessage()
		payload["location"] = map[string]any{
			"latitude": location.GetDegreesLatitude(), "longitude": location.GetDegreesLongitude(),
			"name": location.GetName(), "address": location.GetAddress(),
		}
	case message.GetLiveLocationMessage() != nil:
		location := message.GetLiveLocationMessage()
		payload["location"] = map[string]any{
			"latitude": location.GetDegreesLatitude(), "longitude": location.GetDegreesLongitude(),
			"name": location.GetCaption(),
		}
	case message.GetContactMessage() != nil:
		payload["contact"] = map[string]any{
			"display_name": message.GetContactMessage().GetDisplayName(),
			"vcard":        message.GetContactMessage().GetVcard(),
		}
	case message.GetPollCreationMessage() != nil:
		addPoll(payload, message.GetPollCreationMessage())
	case message.GetPollCreationMessageV2() != nil:
		addPoll(payload, message.GetPollCreationMessageV2())
	case message.GetPollCreationMessageV3() != nil:
		addPoll(payload, message.GetPollCreationMessageV3())
	case message.GetButtonsResponseMessage() != nil:
		payload["text"] = firstNonEmpty(message.GetButtonsResponseMessage().GetSelectedDisplayText(),
			message.GetButtonsResponseMessage().GetSelectedButtonID())
	case message.GetListResponseMessage() != nil:
		payload["text"] = firstNonEmpty(message.GetListResponseMessage().GetTitle(),
			message.GetListResponseMessage().GetSingleSelectReply().GetSelectedRowID())
	case message.GetTemplateButtonReplyMessage() != nil:
		payload["text"] = firstNonEmpty(message.GetTemplateButtonReplyMessage().GetSelectedDisplayText(),
			message.GetTemplateButtonReplyMessage().GetSelectedID())
	case message.GetInteractiveResponseMessage() != nil:
		payload["text"] = firstNonEmpty(message.GetInteractiveResponseMessage().GetBody().GetText(),
			message.GetInteractiveResponseMessage().GetNativeFlowResponseMessage().GetName())
	}
	if document := message.GetDocumentMessage(); document != nil && document.GetFileName() != "" {
		payload["filename"] = document.GetFileName()
	}
	return payload
}

func addPoll(payload map[string]any, poll *waE2E.PollCreationMessage) {
	options := make([]string, 0, len(poll.GetOptions()))
	for _, option := range poll.GetOptions() {
		options = append(options, option.GetOptionName())
	}
	payload["poll"] = map[string]any{
		"name": poll.GetName(), "options": options, "selectable_options": poll.GetSelectableOptionsCount(),
	}
}

func downloadableMessage(message *waE2E.Message) whatsmeow.DownloadableMessage {
	switch {
	case message.GetImageMessage() != nil:
		return message.GetImageMessage()
	case message.GetAudioMessage() != nil:
		return message.GetAudioMessage()
	case message.GetVideoMessage() != nil:
		return message.GetVideoMessage()
	case message.GetDocumentMessage() != nil:
		return message.GetDocumentMessage()
	case message.GetStickerMessage() != nil:
		return message.GetStickerMessage()
	default:
		return nil
	}
}

func messageKind(message *waE2E.Message) string {
	switch {
	case message.GetImageMessage() != nil:
		return "IMAGE"
	case message.GetAudioMessage() != nil:
		return "AUDIO"
	case message.GetVideoMessage() != nil:
		return "VIDEO"
	case message.GetDocumentMessage() != nil:
		return "DOCUMENT"
	case message.GetStickerMessage() != nil:
		return "STICKER"
	case message.GetLocationMessage() != nil || message.GetLiveLocationMessage() != nil:
		return "LOCATION"
	case message.GetContactMessage() != nil || message.GetContactsArrayMessage() != nil:
		return "CONTACT"
	case pollCreation(message) != nil:
		return "POLL"
	case message.GetButtonsResponseMessage() != nil || message.GetListResponseMessage() != nil ||
		message.GetTemplateButtonReplyMessage() != nil || message.GetInteractiveResponseMessage() != nil:
		return "INTERACTIVE"
	default:
		return "TEXT"
	}
}

func pollCreation(message *waE2E.Message) *waE2E.PollCreationMessage {
	if message.GetPollCreationMessage() != nil {
		return message.GetPollCreationMessage()
	}
	if message.GetPollCreationMessageV2() != nil {
		return message.GetPollCreationMessageV2()
	}
	return message.GetPollCreationMessageV3()
}

func messageText(message *waE2E.Message) string {
	if message.GetConversation() != "" {
		return message.GetConversation()
	}
	if message.GetExtendedTextMessage() != nil {
		return message.GetExtendedTextMessage().GetText()
	}
	if message.GetImageMessage() != nil {
		return message.GetImageMessage().GetCaption()
	}
	if message.GetVideoMessage() != nil {
		return message.GetVideoMessage().GetCaption()
	}
	if message.GetDocumentMessage() != nil {
		return message.GetDocumentMessage().GetCaption()
	}
	return ""
}

func inboundMessageContext(message *waE2E.Message) *waE2E.ContextInfo {
	switch {
	case message.GetExtendedTextMessage() != nil:
		return message.GetExtendedTextMessage().GetContextInfo()
	case message.GetImageMessage() != nil:
		return message.GetImageMessage().GetContextInfo()
	case message.GetAudioMessage() != nil:
		return message.GetAudioMessage().GetContextInfo()
	case message.GetVideoMessage() != nil:
		return message.GetVideoMessage().GetContextInfo()
	case message.GetDocumentMessage() != nil:
		return message.GetDocumentMessage().GetContextInfo()
	case message.GetStickerMessage() != nil:
		return message.GetStickerMessage().GetContextInfo()
	case message.GetLocationMessage() != nil:
		return message.GetLocationMessage().GetContextInfo()
	case message.GetContactMessage() != nil:
		return message.GetContactMessage().GetContextInfo()
	case pollCreation(message) != nil:
		return pollCreation(message).GetContextInfo()
	default:
		return nil
	}
}

func mediaMIME(media whatsmeow.DownloadableMessage) string {
	if typed, ok := media.(interface{ GetMimetype() string }); ok {
		return typed.GetMimetype()
	}
	return "application/octet-stream"
}

func normalizeOptionalJID(raw string) (string, error) {
	if raw == "" {
		return "", nil
	}
	jid, err := types.ParseJID(raw)
	if err != nil {
		return "", err
	}
	peer, err := NormalizeOneToOneJID(jid)
	if err != nil {
		return "", err
	}
	return peer.Normalized, nil
}

func messageInfoTimestamp(info *types.MessageInfo) time.Time {
	if info == nil {
		return time.Now().UTC()
	}
	return eventTimestamp(info.Timestamp)
}

func eventTimestamp(timestamp time.Time) time.Time {
	if timestamp.IsZero() {
		return time.Now().UTC()
	}
	return timestamp.UTC()
}

func messageEventPrefix(history bool) string {
	if history {
		return "history-message"
	}
	return "inbound"
}

func messageDirection(fromMe bool) string {
	if fromMe {
		return "OUTBOUND"
	}
	return "INBOUND"
}

func chatPresenceMedia(media types.ChatPresenceMedia) string {
	if media == types.ChatPresenceMediaAudio {
		return "AUDIO"
	}
	return "TEXT"
}

func chatPresenceValue(state types.ChatPresence, media types.ChatPresenceMedia) string {
	if state == types.ChatPresenceComposing && media == types.ChatPresenceMediaAudio {
		return "RECORDING"
	}
	return strings.ToUpper(string(state))
}

func durationSeconds(value time.Duration) int64 {
	if value <= 0 {
		return 0
	}
	seconds := int64(value / time.Second)
	if seconds > 86400 {
		return 86400
	}
	return seconds
}

func firstNonEmpty(values ...string) string {
	for _, value := range values {
		if value != "" {
			return value
		}
	}
	return ""
}

func sanitizeCode(value string) string {
	if value == "" {
		return "UNKNOWN"
	}
	var result strings.Builder
	for _, char := range value {
		if result.Len() >= 64 {
			break
		}
		if char >= 'a' && char <= 'z' || char >= 'A' && char <= 'Z' || char >= '0' && char <= '9' || char == '_' {
			result.WriteRune(unicode.ToUpper(char))
		} else {
			result.WriteByte('_')
		}
	}
	return result.String()
}

func stableID(prefix string, parts ...any) string {
	hasher := sha256.New()
	for _, part := range parts {
		_, _ = fmt.Fprintln(hasher, part)
	}
	return prefix + "-" + hex.EncodeToString(hasher.Sum(nil)[:16])
}
