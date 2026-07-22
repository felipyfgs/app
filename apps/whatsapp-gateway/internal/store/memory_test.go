package store

import (
	"context"
	"errors"
	"testing"
	"time"

	"github.com/inovaicontabil/fiscal-hub/apps/whatsapp-gateway/internal/domain"
)

func TestMemoryStorePersistsCommandsAndEventsIdempotently(t *testing.T) {
	t.Parallel()
	ctx := context.Background()
	persistence := NewMemory()
	command := domain.Command{CommandID: "command-0001", Digest: "digest-a"}
	if duplicate, err := persistence.AcceptCommand(ctx, command); err != nil || duplicate {
		t.Fatalf("first command must be persisted: duplicate=%v err=%v", duplicate, err)
	}
	if duplicate, err := persistence.AcceptCommand(ctx, command); err != nil || !duplicate {
		t.Fatalf("same command must be duplicate: duplicate=%v err=%v", duplicate, err)
	}
	command.Digest = "digest-b"
	if _, err := persistence.AcceptCommand(ctx, command); !errors.Is(err, domain.ErrDigestConflict) {
		t.Fatalf("expected command digest conflict, got %v", err)
	}

	event := domain.Event{EventID: "event-id-0001", Digest: "event-digest", OccurredAt: time.Now()}
	if duplicate, err := persistence.AppendEvent(ctx, event); err != nil || duplicate {
		t.Fatalf("first event must be persisted: duplicate=%v err=%v", duplicate, err)
	}
	if duplicate, err := persistence.AppendEvent(ctx, event); err != nil || !duplicate {
		t.Fatalf("same event must be duplicate: duplicate=%v err=%v", duplicate, err)
	}
}
