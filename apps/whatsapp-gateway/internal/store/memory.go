package store

import (
	"context"
	"errors"
	"sort"
	"sync"
	"time"

	"github.com/inovaicontabil/fiscal-hub/apps/whatsapp-gateway/internal/domain"
)

type Memory struct {
	mu       sync.Mutex
	commands map[string]*memoryCommand
	events   map[string]*memoryEvent
	nonces   map[string]time.Time
	sessions map[string]domain.Session
	leases   map[string]domain.Lease
}

type memoryEvent struct {
	event       domain.Event
	status      string
	attempts    int
	availableAt time.Time
}

type memoryCommand struct {
	command     domain.Command
	status      string
	attempts    int
	availableAt time.Time
}

func NewMemory() *Memory {
	return &Memory{
		commands: make(map[string]*memoryCommand),
		events:   make(map[string]*memoryEvent),
		nonces:   make(map[string]time.Time),
		sessions: make(map[string]domain.Session),
		leases:   make(map[string]domain.Lease),
	}
}

func (s *Memory) AcceptCommand(_ context.Context, command domain.Command) (bool, error) {
	s.mu.Lock()
	defer s.mu.Unlock()
	if existing, ok := s.commands[command.CommandID]; ok {
		if existing.command.Digest != command.Digest {
			return false, domain.ErrDigestConflict
		}
		return true, nil
	}
	availableAt := command.AcceptedAt
	if availableAt.IsZero() {
		availableAt = time.Now()
	}
	s.commands[command.CommandID] = &memoryCommand{command: command, status: "PENDING", availableAt: availableAt}
	return false, nil
}

func (s *Memory) NextCommands(
	_ context.Context,
	replicaID string,
	limit int,
	now time.Time,
) ([]domain.PendingCommand, error) {
	s.mu.Lock()
	defer s.mu.Unlock()
	ids := make([]string, 0, len(s.commands))
	for id, command := range s.commands {
		lease := s.leases[command.command.SessionID]
		canProcess := command.command.Type == domain.CommandProvisionSession ||
			command.command.Type == domain.CommandPairSession ||
			(lease.ReplicaID == replicaID && lease.ExpiresAt.After(now))
		if canProcess && command.status != "PROCESSED" && !command.availableAt.After(now) {
			ids = append(ids, id)
		}
	}
	sort.Strings(ids)
	if len(ids) > limit {
		ids = ids[:limit]
	}
	result := make([]domain.PendingCommand, 0, len(ids))
	for _, id := range ids {
		command := s.commands[id]
		command.status = "PROCESSING"
		command.attempts++
		result = append(result, domain.PendingCommand{Command: command.command, Attempts: command.attempts})
	}
	return result, nil
}

func (s *Memory) MarkCommandProcessed(_ context.Context, commandID string, _ time.Time) error {
	s.mu.Lock()
	defer s.mu.Unlock()
	command, ok := s.commands[commandID]
	if !ok {
		return domain.ErrNotFound
	}
	command.status = "PROCESSED"
	return nil
}

func (s *Memory) MarkCommandFailed(_ context.Context, commandID string, availableAt time.Time, _ string, terminal bool) error {
	s.mu.Lock()
	defer s.mu.Unlock()
	command, ok := s.commands[commandID]
	if !ok {
		return domain.ErrNotFound
	}
	if terminal {
		command.status = "ERROR"
	} else {
		command.status = "RETRY"
	}
	command.availableAt = availableAt
	return nil
}

func (s *Memory) AppendEvent(_ context.Context, event domain.Event) (bool, error) {
	s.mu.Lock()
	defer s.mu.Unlock()
	if existing, ok := s.events[event.EventID]; ok {
		if existing.event.Digest != event.Digest {
			return false, domain.ErrDigestConflict
		}
		return true, nil
	}
	s.events[event.EventID] = &memoryEvent{event: event, status: "PENDING", availableAt: time.Now()}
	return false, nil
}

func (s *Memory) NextEvents(_ context.Context, limit int, now time.Time) ([]domain.PendingEvent, error) {
	s.mu.Lock()
	defer s.mu.Unlock()
	ids := make([]string, 0, len(s.events))
	for id, event := range s.events {
		if event.status != "DELIVERED" && !event.availableAt.After(now) {
			ids = append(ids, id)
		}
	}
	sort.Strings(ids)
	if len(ids) > limit {
		ids = ids[:limit]
	}
	result := make([]domain.PendingEvent, 0, len(ids))
	for _, id := range ids {
		event := s.events[id]
		event.status = "DISPATCHING"
		event.attempts++
		result = append(result, domain.PendingEvent{Event: event.event, Attempts: event.attempts})
	}
	return result, nil
}

func (s *Memory) MarkEventDelivered(_ context.Context, eventID string, _ time.Time) error {
	s.mu.Lock()
	defer s.mu.Unlock()
	event, ok := s.events[eventID]
	if !ok {
		return domain.ErrNotFound
	}
	event.status = "DELIVERED"
	return nil
}

func (s *Memory) MarkEventFailed(_ context.Context, eventID string, availableAt time.Time, _ string, terminal bool) error {
	s.mu.Lock()
	defer s.mu.Unlock()
	event, ok := s.events[eventID]
	if !ok {
		return domain.ErrNotFound
	}
	if terminal {
		event.status = "ERROR"
	} else {
		event.status = "RETRY"
	}
	event.availableAt = availableAt
	return nil
}

func (s *Memory) ClaimNonce(_ context.Context, digest string, expiresAt time.Time) (bool, error) {
	s.mu.Lock()
	defer s.mu.Unlock()
	now := time.Now()
	for key, expiry := range s.nonces {
		if !expiry.After(now) {
			delete(s.nonces, key)
		}
	}
	if expiry, ok := s.nonces[digest]; ok && expiry.After(now) {
		return false, nil
	}
	s.nonces[digest] = expiresAt
	return true, nil
}

func (s *Memory) UpsertSession(_ context.Context, session domain.Session) error {
	if !session.Valid() {
		return errors.New("invalid session")
	}
	s.mu.Lock()
	defer s.mu.Unlock()
	if existing, ok := s.sessions[session.SessionID]; ok {
		session.FencingToken = existing.FencingToken
	}
	s.sessions[session.SessionID] = session
	return nil
}

func (s *Memory) GetSession(_ context.Context, sessionID string) (domain.Session, error) {
	s.mu.Lock()
	defer s.mu.Unlock()
	session, ok := s.sessions[sessionID]
	if !ok {
		return domain.Session{}, domain.ErrNotFound
	}
	return session, nil
}

func (s *Memory) SetSessionStatus(
	_ context.Context,
	sessionID string,
	status domain.SessionStatus,
	reconnectCount int,
	nextReconnectAt time.Time,
) error {
	s.mu.Lock()
	defer s.mu.Unlock()
	session, ok := s.sessions[sessionID]
	if !ok {
		return domain.ErrNotFound
	}
	session.Status = status
	session.ReconnectCount = reconnectCount
	session.NextReconnectAt = nextReconnectAt
	session.UpdatedAt = time.Now()
	s.sessions[sessionID] = session
	return nil
}

func (s *Memory) ClaimSessions(
	_ context.Context,
	replicaID string,
	capacity int,
	now time.Time,
	ttl time.Duration,
) ([]domain.Lease, error) {
	s.mu.Lock()
	defer s.mu.Unlock()
	owned := 0
	for _, lease := range s.leases {
		if lease.ReplicaID == replicaID && lease.ExpiresAt.After(now) {
			owned++
		}
	}
	remaining := capacity - owned
	if remaining <= 0 {
		return nil, nil
	}
	ids := make([]string, 0, len(s.sessions))
	for id, session := range s.sessions {
		lease, leased := s.leases[id]
		if session.DesiredConnected && session.Status != domain.SessionRevoked &&
			(!leased || !lease.ExpiresAt.After(now)) && !session.NextReconnectAt.After(now) {
			ids = append(ids, id)
		}
	}
	sort.Strings(ids)
	if len(ids) > remaining {
		ids = ids[:remaining]
	}
	result := make([]domain.Lease, 0, len(ids))
	for _, id := range ids {
		session := s.sessions[id]
		session.FencingToken++
		session.UpdatedAt = now
		s.sessions[id] = session
		lease := domain.Lease{
			SessionID: id, ReplicaID: replicaID, FencingToken: session.FencingToken, ExpiresAt: now.Add(ttl),
		}
		s.leases[id] = lease
		result = append(result, lease)
	}
	return result, nil
}

func (s *Memory) RenewLease(
	_ context.Context,
	lease domain.Lease,
	now time.Time,
	ttl time.Duration,
) (domain.Lease, error) {
	s.mu.Lock()
	defer s.mu.Unlock()
	current, ok := s.leases[lease.SessionID]
	if !ok || current.ReplicaID != lease.ReplicaID || current.FencingToken != lease.FencingToken || !current.ExpiresAt.After(now) {
		return domain.Lease{}, domain.ErrNotFound
	}
	current.ExpiresAt = now.Add(ttl)
	s.leases[lease.SessionID] = current
	return current, nil
}

func (s *Memory) ValidLease(_ context.Context, lease domain.Lease, now time.Time) (bool, error) {
	s.mu.Lock()
	defer s.mu.Unlock()
	current, ok := s.leases[lease.SessionID]
	return ok && current.ReplicaID == lease.ReplicaID && current.FencingToken == lease.FencingToken && current.ExpiresAt.After(now), nil
}

func (s *Memory) ReleaseLease(_ context.Context, lease domain.Lease) error {
	s.mu.Lock()
	defer s.mu.Unlock()
	current, ok := s.leases[lease.SessionID]
	if ok && current.ReplicaID == lease.ReplicaID && current.FencingToken == lease.FencingToken {
		delete(s.leases, lease.SessionID)
	}
	return nil
}

func (s *Memory) Ping(context.Context) error { return nil }

func (s *Memory) Metrics(context.Context) (domain.Metrics, error) {
	s.mu.Lock()
	defer s.mu.Unlock()
	metrics := domain.Metrics{}
	for _, command := range s.commands {
		if command.status != "PROCESSED" {
			metrics.PendingCommands++
		}
	}
	now := time.Now()
	for _, event := range s.events {
		if event.status != "DELIVERED" {
			metrics.PendingEvents++
		}
		if event.status == "ERROR" {
			metrics.FailedEvents++
		}
	}
	for _, session := range s.sessions {
		if session.Status == domain.SessionConnected {
			metrics.ActiveSessions++
		}
	}
	for _, lease := range s.leases {
		if lease.ExpiresAt.After(now) {
			metrics.ActiveLeases++
		}
	}
	return metrics, nil
}

func (s *Memory) Close() {}
