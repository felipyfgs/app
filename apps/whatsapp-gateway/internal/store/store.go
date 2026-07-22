package store

import (
	"context"
	"time"

	"github.com/inovaicontabil/fiscal-hub/apps/whatsapp-gateway/internal/domain"
)

type Store interface {
	AcceptCommand(context.Context, domain.Command) (duplicate bool, err error)
	NextCommands(context.Context, string, int, time.Time) ([]domain.PendingCommand, error)
	MarkCommandProcessed(context.Context, string, time.Time) error
	MarkCommandFailed(context.Context, string, time.Time, string, bool) error
	AppendEvent(context.Context, domain.Event) (duplicate bool, err error)
	NextEvents(context.Context, int, time.Time) ([]domain.PendingEvent, error)
	MarkEventDelivered(context.Context, string, time.Time) error
	MarkEventFailed(context.Context, string, time.Time, string, bool) error
	ClaimNonce(context.Context, string, time.Time) (bool, error)
	UpsertSession(context.Context, domain.Session) error
	GetSession(context.Context, string) (domain.Session, error)
	SetSessionStatus(context.Context, string, domain.SessionStatus, int, time.Time) error
	ClaimSessions(context.Context, string, int, time.Time, time.Duration) ([]domain.Lease, error)
	RenewLease(context.Context, domain.Lease, time.Time, time.Duration) (domain.Lease, error)
	ValidLease(context.Context, domain.Lease, time.Time) (bool, error)
	ReleaseLease(context.Context, domain.Lease) error
	Ping(context.Context) error
	Metrics(context.Context) (domain.Metrics, error)
	Close()
}
