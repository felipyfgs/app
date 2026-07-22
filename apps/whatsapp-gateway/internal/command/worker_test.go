package command

import (
	"context"
	"encoding/json"
	"testing"
	"time"

	"github.com/inovaicontabil/fiscal-hub/apps/whatsapp-gateway/internal/domain"
	"github.com/inovaicontabil/fiscal-hub/apps/whatsapp-gateway/internal/session"
	"github.com/inovaicontabil/fiscal-hub/apps/whatsapp-gateway/internal/store"
)

type fakeTransport struct {
	connected         bool
	providerMessageID string
	text              string
	media             []byte
	filename          string
	mimeType          string
}

func (f *fakeTransport) Connect(string) error { f.connected = true; return nil }
func (f *fakeTransport) Disconnect(string)    { f.connected = false }
func (f *fakeTransport) SendText(_ context.Context, _, _, text, providerMessageID string) error {
	f.text = text
	f.providerMessageID = providerMessageID
	return nil
}
func (f *fakeTransport) SendMedia(
	_ context.Context, _, _, caption, filename, mimeType string, providerMessageID string, content []byte,
) error {
	f.text = caption
	f.filename = filename
	f.mimeType = mimeType
	f.media = append([]byte(nil), content...)
	f.providerMessageID = providerMessageID
	return nil
}
func (f *fakeTransport) Logout(context.Context, string) error { f.connected = false; return nil }

type fakeMediaFetcher struct{ content []byte }

func (f fakeMediaFetcher) Fetch(context.Context, string, string, int64) ([]byte, error) {
	return append([]byte(nil), f.content...), nil
}

type fakeTypedTransport struct {
	fakeTransport
	calls   int
	payload domain.MessageSendPayload
}

type fakeActionTransport struct {
	fakeTransport
	action string
	target string
}

type fakePresenceTransport struct {
	fakeTransport
	presence string
}

func (f *fakePresenceTransport) SetPresence(
	_ context.Context, _ string, payload domain.PresencePayload,
) error {
	f.presence = payload.Presence
	return nil
}

func (f *fakePresenceTransport) SubscribeContactPresence(
	_ context.Context, _ string, payload domain.ContactPresencePayload,
) error {
	f.presence = "SUBSCRIBE:" + payload.To
	return nil
}

func (f *fakePresenceTransport) SetChatPresence(
	_ context.Context, _ string, payload domain.ChatPresencePayload,
) error {
	f.presence = payload.Presence
	return nil
}

func (f *fakeActionTransport) EditMessage(
	_ context.Context, _ string, payload domain.MessageEditPayload, _ string,
) error {
	f.action, f.target = "edit", payload.TargetMessageID
	return nil
}

func (f *fakeActionTransport) RevokeMessage(
	_ context.Context, _ string, payload domain.MessageTargetPayload, _ string,
) error {
	f.action, f.target = "revoke", payload.TargetMessageID
	return nil
}

func (f *fakeActionTransport) ReactMessage(
	_ context.Context, _ string, payload domain.MessageReactionPayload, _ string,
) error {
	f.action, f.target = "react:"+payload.Emoji, payload.TargetMessageID
	return nil
}

func (f *fakeActionTransport) VotePoll(
	_ context.Context, _ string, payload domain.PollVotePayload, _ string,
) error {
	f.action, f.target = "vote", payload.TargetMessageID
	return nil
}

func (f *fakeActionTransport) MarkMessage(
	_ context.Context, _ string, payload domain.MessageMarkPayload,
) error {
	f.action, f.target = "mark:"+payload.Receipt, payload.MessageIDs[0]
	return nil
}

func (f *fakeActionTransport) SetChatDisappearing(
	_ context.Context, _ string, payload domain.DisappearingPayload,
) error {
	f.action = "disappearing"
	return nil
}

func (f *fakeActionTransport) RequestUnavailableMessage(
	_ context.Context, _ string, payload domain.MessageTargetPayload,
) error {
	f.action, f.target = "unavailable", payload.TargetMessageID
	return nil
}

func (f *fakeTypedTransport) SendTypedMessage(
	_ context.Context,
	_ string,
	payload domain.MessageSendPayload,
	providerMessageID string,
	_ []byte,
) error {
	f.calls++
	f.payload = payload
	f.providerMessageID = providerMessageID
	return nil
}

func TestWorkerProvisionsAndSendsOnlyWithOwnedLease(t *testing.T) {
	t.Parallel()
	persistence := store.NewMemory()
	transport := &fakeTransport{}
	manager := session.NewManager(persistence, transport, "replica-worker", 10, time.Minute, 10*time.Second)
	worker := New(persistence, manager, nil, transport, "replica-worker")
	now := time.Now().UTC()
	worker.now = func() time.Time { return now }

	provisionPayload, _ := json.Marshal(map[string]bool{"desired_connected": true})
	_, err := persistence.AcceptCommand(t.Context(), domain.Command{
		ContractVersion: "v1", CommandID: "command-provision-0001", SessionID: "session-worker-0001",
		Type: domain.CommandProvisionSession, Payload: provisionPayload, Digest: "provision-digest", AcceptedAt: now,
	})
	if err != nil {
		t.Fatalf("accept provision: %v", err)
	}
	if err := worker.ProcessOnce(t.Context()); err != nil {
		t.Fatalf("process provision: %v", err)
	}
	if err := manager.Reconcile(t.Context()); err != nil {
		t.Fatalf("claim session: %v", err)
	}

	messagePayload, _ := json.Marshal(map[string]string{"to": "+5511999991234", "text": "Olá"})
	_, err = persistence.AcceptCommand(t.Context(), domain.Command{
		ContractVersion: "v1", CommandID: "command-message-0001", SessionID: "session-worker-0001",
		Type: domain.CommandSendMessage, ProviderMessageID: "provider-message-0001",
		Payload: messagePayload, Digest: "message-digest", AcceptedAt: now,
	})
	if err != nil {
		t.Fatalf("accept message: %v", err)
	}
	if err := worker.ProcessOnce(t.Context()); err != nil {
		t.Fatalf("process message: %v", err)
	}
	if transport.providerMessageID != "provider-message-0001" || transport.text != "Olá" {
		t.Fatalf("transport identity changed: id=%q text=%q", transport.providerMessageID, transport.text)
	}
	metrics, _ := persistence.Metrics(t.Context())
	if metrics.PendingCommands != 0 || metrics.PendingEvents != 1 {
		t.Fatalf("unexpected ledger state: %+v", metrics)
	}
}

func TestWorkerFetchesAndSendsDocumentForMediaCommand(t *testing.T) {
	t.Parallel()
	persistence := store.NewMemory()
	transport := &fakeTransport{}
	manager := session.NewManager(persistence, transport, "replica-media", 10, time.Minute, 10*time.Second)
	worker := New(persistence, manager, nil, transport, "replica-media").WithMediaFetcher(
		fakeMediaFetcher{content: []byte("%PDF-document")},
	)
	now := time.Now().UTC()
	worker.now = func() time.Time { return now }
	if err := persistence.UpsertSession(t.Context(), domain.Session{
		SessionID: "session-media-0001", Status: domain.SessionProvisioned, DesiredConnected: true,
	}); err != nil {
		t.Fatalf("provision session: %v", err)
	}
	if err := manager.Reconcile(t.Context()); err != nil {
		t.Fatalf("claim session: %v", err)
	}
	payload, _ := json.Marshal(map[string]any{
		"to": "+5511999991234", "text": "Segue a guia",
		"media": map[string]any{
			"filename": "guia.pdf", "mime_type": "application/pdf", "size_bytes": 13,
			"sha256": "aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa",
		},
	})
	_, err := persistence.AcceptCommand(t.Context(), domain.Command{
		ContractVersion: "v1", CommandID: "command-media-0001", SessionID: "session-media-0001",
		Type: domain.CommandSendMessage, ProviderMessageID: "provider-media-0001",
		Payload: payload, Digest: "media-digest", AcceptedAt: now,
	})
	if err != nil {
		t.Fatalf("accept media command: %v", err)
	}
	if err := worker.ProcessOnce(t.Context()); err != nil {
		t.Fatalf("process media command: %v", err)
	}
	if transport.filename != "guia.pdf" || transport.mimeType != "application/pdf" || string(transport.media) != "%PDF-document" {
		t.Fatalf("media was not sent: filename=%q mime=%q bytes=%q", transport.filename, transport.mimeType, transport.media)
	}
}

func TestWorkerDispatchesTypedMessageExactlyOnceForDuplicateCommand(t *testing.T) {
	t.Parallel()
	persistence := store.NewMemory()
	transport := &fakeTypedTransport{}
	manager := session.NewManager(persistence, transport, "replica-typed", 10, time.Minute, 10*time.Second)
	worker := New(persistence, manager, nil, transport, "replica-typed")
	now := time.Now().UTC()
	worker.now = func() time.Time { return now }
	if err := persistence.UpsertSession(t.Context(), domain.Session{
		SessionID: "session-typed-0001", Status: domain.SessionProvisioned, DesiredConnected: true,
	}); err != nil {
		t.Fatalf("provision typed session: %v", err)
	}
	if err := manager.Reconcile(t.Context()); err != nil {
		t.Fatalf("claim typed session: %v", err)
	}
	payload, _ := json.Marshal(domain.MessageSendPayload{
		To: "+5511999991234", Kind: domain.MessageLocation,
		Location: &domain.LocationPayload{Latitude: -23.55, Longitude: -46.63, Name: "São Paulo"},
	})
	command := domain.Command{
		ContractVersion: "v1", CommandID: "command-typed-0001", SessionID: "session-typed-0001",
		Type: domain.CommandSendMessage, ProviderMessageID: "provider-typed-0001",
		Payload: payload, Digest: "typed-digest", AcceptedAt: now,
	}
	if duplicate, err := persistence.AcceptCommand(t.Context(), command); err != nil || duplicate {
		t.Fatalf("accept typed command: duplicate=%v err=%v", duplicate, err)
	}
	if duplicate, err := persistence.AcceptCommand(t.Context(), command); err != nil || !duplicate {
		t.Fatalf("same typed command must deduplicate: duplicate=%v err=%v", duplicate, err)
	}
	if err := worker.ProcessOnce(t.Context()); err != nil {
		t.Fatalf("process typed command: %v", err)
	}
	if transport.calls != 1 || transport.payload.Location == nil ||
		transport.providerMessageID != "provider-typed-0001" {
		t.Fatalf("typed command dispatch changed: calls=%d payload=%+v id=%q",
			transport.calls, transport.payload, transport.providerMessageID)
	}
}

func TestWorkerRoutesMessageActionThroughOwnedSession(t *testing.T) {
	t.Parallel()
	persistence := store.NewMemory()
	transport := &fakeActionTransport{}
	manager := session.NewManager(persistence, transport, "replica-action", 10, time.Minute, 10*time.Second)
	worker := New(persistence, manager, nil, transport, "replica-action")
	now := time.Now().UTC()
	worker.now = func() time.Time { return now }
	if err := persistence.UpsertSession(t.Context(), domain.Session{
		SessionID: "session-action-0001", Status: domain.SessionProvisioned, DesiredConnected: true,
	}); err != nil {
		t.Fatalf("provision action session: %v", err)
	}
	if err := manager.Reconcile(t.Context()); err != nil {
		t.Fatalf("claim action session: %v", err)
	}
	payload, _ := json.Marshal(domain.MessageReactionPayload{
		MessageTargetPayload: domain.MessageTargetPayload{
			To: "+5511999991234", TargetMessageID: "target-message-0001", Sender: "+5511999991234",
		},
		Emoji: "✅",
	})
	_, err := persistence.AcceptCommand(t.Context(), domain.Command{
		ContractVersion: "v1", CommandID: "command-action-0001", SessionID: "session-action-0001",
		Type: domain.CommandReactMessage, ProviderMessageID: "provider-action-0001",
		Payload: payload, Digest: "action-digest", AcceptedAt: now,
	})
	if err != nil {
		t.Fatalf("accept action command: %v", err)
	}
	if err := worker.ProcessOnce(t.Context()); err != nil {
		t.Fatalf("process action command: %v", err)
	}
	if transport.action != "react:✅" || transport.target != "target-message-0001" {
		t.Fatalf("action route changed: action=%q target=%q", transport.action, transport.target)
	}
}

func TestWorkerProcessesPresenceWithoutCreatingDurableMessageEvent(t *testing.T) {
	t.Parallel()
	persistence := store.NewMemory()
	transport := &fakePresenceTransport{}
	manager := session.NewManager(persistence, transport, "replica-presence", 10, time.Minute, 10*time.Second)
	worker := New(persistence, manager, nil, transport, "replica-presence")
	now := time.Now().UTC()
	worker.now = func() time.Time { return now }
	if err := persistence.UpsertSession(t.Context(), domain.Session{
		SessionID: "session-presence-0001", Status: domain.SessionProvisioned, DesiredConnected: true,
	}); err != nil {
		t.Fatalf("provision presence session: %v", err)
	}
	if err := manager.Reconcile(t.Context()); err != nil {
		t.Fatalf("claim presence session: %v", err)
	}
	payload, _ := json.Marshal(domain.ChatPresencePayload{
		To: "+5511999991234", Presence: "COMPOSING", Media: "TEXT",
	})
	_, err := persistence.AcceptCommand(t.Context(), domain.Command{
		ContractVersion: "v1", CommandID: "command-presence-0001", SessionID: "session-presence-0001",
		Type: domain.CommandSetChatPresence, Payload: payload, Digest: "presence-digest", AcceptedAt: now,
	})
	if err != nil {
		t.Fatalf("accept presence command: %v", err)
	}
	if err := worker.ProcessOnce(t.Context()); err != nil {
		t.Fatalf("process presence command: %v", err)
	}
	metrics, err := persistence.Metrics(t.Context())
	if err != nil || metrics.PendingEvents != 0 || transport.presence != "COMPOSING" {
		t.Fatalf("presence became durable product event: metrics=%+v value=%q err=%v", metrics, transport.presence, err)
	}
}
