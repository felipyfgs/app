package session

import (
	"context"
	"sync/atomic"
	"testing"
	"time"

	"github.com/inovaicontabil/fiscal-hub/apps/whatsapp-gateway/internal/domain"
	"github.com/inovaicontabil/fiscal-hub/apps/whatsapp-gateway/internal/store"
)

type fakePairer struct{}

func (fakePairer) StartPairing(context.Context, string) (<-chan domain.PairingUpdate, error) {
	updates := make(chan domain.PairingUpdate, 2)
	updates <- domain.PairingUpdate{Event: "code", Code: "private-qr-code", ExpiresAt: time.Now().Add(time.Minute)}
	updates <- domain.PairingUpdate{Event: "success"}
	close(updates)
	return updates, nil
}

type fakeRecorder struct{ calls atomic.Int32 }

func (r *fakeRecorder) RecordDevice(context.Context, string) error {
	r.calls.Add(1)
	return nil
}

func TestPairingPersistsUpdatesBeforeDeliveryAndRecordsSuccessfulDevice(t *testing.T) {
	t.Parallel()
	persistence := store.NewMemory()
	mustProvision(t, persistence, "session-pairing-0001")
	recorder := &fakeRecorder{}
	coordinator := NewPairingCoordinator(persistence, fakePairer{}, recorder)
	if err := coordinator.Start(t.Context(), "session-pairing-0001"); err != nil {
		t.Fatalf("start pairing: %v", err)
	}

	deadline := time.Now().Add(time.Second)
	for time.Now().Before(deadline) {
		session, _ := persistence.GetSession(t.Context(), "session-pairing-0001")
		metrics, _ := persistence.Metrics(t.Context())
		if session.Status == domain.SessionConnected && metrics.PendingEvents == 2 && recorder.calls.Load() == 1 {
			return
		}
		time.Sleep(time.Millisecond)
	}
	t.Fatal("pairing updates were not durably materialized")
}
