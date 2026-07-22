package security

import (
	"context"
	"net/http"
	"testing"
	"time"

	"github.com/inovaicontabil/fiscal-hub/apps/whatsapp-gateway/internal/store"
)

func TestVerifierAcceptsCurrentAndPreviousKeysAndRejectsReplay(t *testing.T) {
	t.Parallel()
	now := time.Unix(1_785_000_000, 0)
	persistence := store.NewMemory()
	verifier := NewVerifier(map[string]string{
		"gateway-v2": "current-secret",
		"gateway-v1": "previous-secret",
	}, 5*time.Minute, 10*time.Minute, persistence)
	verifier.now = func() time.Time { return now }

	body := []byte(`{"command_id":"command-0001"}`)
	headers := http.Header{}
	headers.Set(HeaderKeyID, "gateway-v1")
	headers.Set(HeaderTimestamp, "1785000000")
	headers.Set(HeaderNonce, "4d84e89c-843d-47a5-96ad-d5776646182a")
	headers.Set(HeaderSignature, Sign("previous-secret", "POST", "/internal/v1/commands", body, now.Unix(), headers.Get(HeaderNonce)))

	if err := verifier.Verify(context.Background(), "POST", "/internal/v1/commands", body, headers); err != nil {
		t.Fatalf("expected valid signature: %v", err)
	}
	if err := verifier.Verify(context.Background(), "POST", "/internal/v1/commands", body, headers); err != ErrReplay {
		t.Fatalf("expected replay, got %v", err)
	}
}

func TestVerifierRejectsStaleTimestampAndTamperedBody(t *testing.T) {
	t.Parallel()
	now := time.Unix(1_785_000_000, 0)
	verifier := NewVerifier(map[string]string{"gateway-v1": "secret"}, 5*time.Minute, 10*time.Minute, store.NewMemory())
	verifier.now = func() time.Time { return now }

	headers := http.Header{}
	headers.Set(HeaderKeyID, "gateway-v1")
	headers.Set(HeaderTimestamp, "1784999699")
	headers.Set(HeaderNonce, "67d8b893-4615-4fe3-a509-3382984463fa")
	headers.Set(HeaderSignature, Sign("secret", "POST", "/internal/v1/commands", []byte(`{}`), 1_784_999_699, headers.Get(HeaderNonce)))
	if err := verifier.Verify(context.Background(), "POST", "/internal/v1/commands", []byte(`{}`), headers); err != ErrStaleTimestamp {
		t.Fatalf("expected stale timestamp, got %v", err)
	}

	headers.Set(HeaderTimestamp, "1785000000")
	headers.Set(HeaderSignature, Sign("secret", "POST", "/internal/v1/commands", []byte(`{}`), now.Unix(), headers.Get(HeaderNonce)))
	if err := verifier.Verify(context.Background(), "POST", "/internal/v1/commands", []byte(`{"changed":true}`), headers); err != ErrInvalidSignature {
		t.Fatalf("expected invalid signature, got %v", err)
	}
}
