package dispatcher

import (
	"crypto/sha256"
	"encoding/hex"
	"encoding/json"
	"net/http"
	"net/http/httptest"
	"sync/atomic"
	"testing"
	"time"

	"github.com/inovaicontabil/fiscal-hub/apps/whatsapp-gateway/internal/domain"
	"github.com/inovaicontabil/fiscal-hub/apps/whatsapp-gateway/internal/security"
	"github.com/inovaicontabil/fiscal-hub/apps/whatsapp-gateway/internal/store"
)

func TestDispatcherRetriesPersistedEventUntilLaravelAcknowledges(t *testing.T) {
	t.Parallel()
	var calls atomic.Int32
	endpoint := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, request *http.Request) {
		body := make([]byte, request.ContentLength)
		_, _ = request.Body.Read(body)
		if request.Header.Get(security.HeaderSignature) == "" || request.Header.Get(security.HeaderNonce) == "" {
			t.Error("missing HMAC headers")
		}
		if calls.Add(1) == 1 {
			w.WriteHeader(http.StatusServiceUnavailable)
			return
		}
		w.WriteHeader(http.StatusNoContent)
	}))
	defer endpoint.Close()

	persistence := store.NewMemory()
	payload, _ := json.Marshal(map[string]string{"status": "CONNECTED"})
	digest := sha256.Sum256(payload)
	_, err := persistence.AppendEvent(t.Context(), domain.Event{
		ContractVersion: "v1", EventID: "event-retry-0001", SessionID: "session-retry-0001",
		Type: "SESSION_STATUS_CHANGED", OccurredAt: time.Now(), Payload: payload,
		Digest: hex.EncodeToString(digest[:]),
	})
	if err != nil {
		t.Fatalf("append event: %v", err)
	}

	now := time.Now().UTC()
	dispatcher := New(persistence, endpoint.URL+"/api/internal/v1/whatsapp/events", "gateway-v1", "secret", endpoint.Client())
	dispatcher.now = func() time.Time { return now }
	if err := dispatcher.DispatchOnce(t.Context()); err != nil {
		t.Fatalf("first dispatch: %v", err)
	}
	metrics, _ := persistence.Metrics(t.Context())
	if metrics.PendingEvents != 1 {
		t.Fatalf("failed delivery was lost: %+v", metrics)
	}

	now = now.Add(time.Minute)
	if err := dispatcher.DispatchOnce(t.Context()); err != nil {
		t.Fatalf("second dispatch: %v", err)
	}
	metrics, _ = persistence.Metrics(t.Context())
	if metrics.PendingEvents != 0 || calls.Load() != 2 {
		t.Fatalf("event not acknowledged exactly after retry: metrics=%+v calls=%d", metrics, calls.Load())
	}
}
