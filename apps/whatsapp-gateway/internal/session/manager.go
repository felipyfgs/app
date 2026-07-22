package session

import (
	"context"
	"errors"
	"sync"
	"time"

	"github.com/inovaicontabil/fiscal-hub/apps/whatsapp-gateway/internal/domain"
	"github.com/inovaicontabil/fiscal-hub/apps/whatsapp-gateway/internal/store"
)

type Connector interface {
	Connect(sessionID string) error
	Disconnect(sessionID string)
}

type contextConnector interface {
	ConnectContext(context.Context, string) error
}

type connectionResetter interface {
	Reset(context.Context, string) error
}

type passiveSetter interface {
	SetPassive(context.Context, string, bool) error
}

var (
	ErrLeaseNotOwned        = errors.New("session lease is not owned by this replica")
	ErrOperationUnsupported = errors.New("session connector operation is unsupported")
)

type Manager struct {
	store     store.Store
	connector Connector
	replicaID string
	capacity  int
	leaseTTL  time.Duration
	heartbeat time.Duration
	now       func() time.Time
	mu        sync.Mutex
	owned     map[string]domain.Lease
}

func NewManager(
	persistence store.Store,
	connector Connector,
	replicaID string,
	capacity int,
	leaseTTL, heartbeat time.Duration,
) *Manager {
	return &Manager{
		store: persistence, connector: connector, replicaID: replicaID,
		capacity: capacity, leaseTTL: leaseTTL, heartbeat: heartbeat,
		now: time.Now, owned: make(map[string]domain.Lease),
	}
}

func (m *Manager) Run(ctx context.Context) {
	ticker := time.NewTicker(m.heartbeat)
	defer ticker.Stop()
	_ = m.Reconcile(ctx)
	for {
		select {
		case <-ctx.Done():
			m.Stop(context.Background())
			return
		case <-ticker.C:
			_ = m.Reconcile(ctx)
		}
	}
}

func (m *Manager) Reconcile(ctx context.Context) error {
	now := m.now()
	m.mu.Lock()
	for sessionID, lease := range m.owned {
		renewed, err := m.store.RenewLease(ctx, lease, now, m.leaseTTL)
		if err != nil {
			m.connector.Disconnect(sessionID)
			delete(m.owned, sessionID)
			continue
		}
		m.owned[sessionID] = renewed
	}
	m.mu.Unlock()

	leases, err := m.store.ClaimSessions(ctx, m.replicaID, m.capacity, now, m.leaseTTL)
	if err != nil {
		return err
	}
	for _, lease := range leases {
		if err := m.connectClaim(ctx, lease, now); err != nil {
			continue
		}
	}
	return nil
}

func (m *Manager) Owns(ctx context.Context, sessionID string) (domain.Lease, bool) {
	m.mu.Lock()
	lease, ok := m.owned[sessionID]
	m.mu.Unlock()
	if !ok {
		return domain.Lease{}, false
	}
	valid, err := m.store.ValidLease(ctx, lease, m.now())
	return lease, err == nil && valid
}

func (m *Manager) Stop(ctx context.Context) {
	m.mu.Lock()
	owned := m.owned
	m.owned = make(map[string]domain.Lease)
	m.mu.Unlock()
	for sessionID, lease := range owned {
		m.connector.Disconnect(sessionID)
		_ = m.store.ReleaseLease(ctx, lease)
	}
}

func (m *Manager) Release(ctx context.Context, sessionID string) {
	m.mu.Lock()
	lease, ok := m.owned[sessionID]
	if ok {
		delete(m.owned, sessionID)
	}
	m.mu.Unlock()
	if ok {
		m.connector.Disconnect(sessionID)
		_ = m.store.ReleaseLease(ctx, lease)
	}
}

// ConnectOwned explicitly connects a session only while this replica owns a
// valid fencing lease. Reconcile remains the normal background entrypoint.
func (m *Manager) ConnectOwned(ctx context.Context, sessionID string) error {
	lease, ok := m.Owns(ctx, sessionID)
	if !ok {
		return ErrLeaseNotOwned
	}
	session, err := m.store.GetSession(ctx, sessionID)
	if err != nil {
		return err
	}
	session.DesiredConnected = true
	if err := m.store.UpsertSession(ctx, session); err != nil {
		return err
	}
	if err := m.connect(ctx, sessionID); err != nil {
		reconnectCount := session.ReconnectCount + 1
		_ = m.store.SetSessionStatus(
			ctx, sessionID, domain.SessionDegraded, reconnectCount, m.now().Add(reconnectDelay(reconnectCount)),
		)
		return err
	}
	if valid, err := m.store.ValidLease(ctx, lease, m.now()); err != nil || !valid {
		m.connector.Disconnect(sessionID)
		return ErrLeaseNotOwned
	}
	return m.store.SetSessionStatus(ctx, sessionID, domain.SessionConnected, 0, time.Time{})
}

func (m *Manager) DisconnectOwned(ctx context.Context, sessionID string) error {
	if _, ok := m.Owns(ctx, sessionID); !ok {
		return ErrLeaseNotOwned
	}
	session, err := m.store.GetSession(ctx, sessionID)
	if err != nil {
		return err
	}
	m.connector.Disconnect(sessionID)
	session.DesiredConnected = false
	session.Status = domain.SessionDisabled
	session.ReconnectCount = 0
	session.NextReconnectAt = time.Time{}
	session.UpdatedAt = m.now()
	return m.store.UpsertSession(ctx, session)
}

func (m *Manager) Reset(ctx context.Context, sessionID string) error {
	lease, ok := m.Owns(ctx, sessionID)
	if !ok {
		return ErrLeaseNotOwned
	}
	resetter, ok := m.connector.(connectionResetter)
	if !ok {
		return ErrOperationUnsupported
	}
	if err := resetter.Reset(ctx, sessionID); err != nil {
		session, getErr := m.store.GetSession(ctx, sessionID)
		if getErr == nil {
			reconnectCount := session.ReconnectCount + 1
			_ = m.store.SetSessionStatus(
				ctx, sessionID, domain.SessionDegraded, reconnectCount, m.now().Add(reconnectDelay(reconnectCount)),
			)
		}
		return err
	}
	if valid, err := m.store.ValidLease(ctx, lease, m.now()); err != nil || !valid {
		m.connector.Disconnect(sessionID)
		return ErrLeaseNotOwned
	}
	return m.store.SetSessionStatus(ctx, sessionID, domain.SessionConnected, 0, time.Time{})
}

func (m *Manager) SetPassive(ctx context.Context, sessionID string, passive bool) error {
	lease, ok := m.Owns(ctx, sessionID)
	if !ok {
		return ErrLeaseNotOwned
	}
	setter, ok := m.connector.(passiveSetter)
	if !ok {
		return ErrOperationUnsupported
	}
	if err := setter.SetPassive(ctx, sessionID, passive); err != nil {
		return err
	}
	if valid, err := m.store.ValidLease(ctx, lease, m.now()); err != nil || !valid {
		m.connector.Disconnect(sessionID)
		return ErrLeaseNotOwned
	}
	return nil
}

func (m *Manager) connectClaim(ctx context.Context, lease domain.Lease, now time.Time) error {
	valid, err := m.store.ValidLease(ctx, lease, now)
	if err != nil || !valid {
		return domain.ErrNotFound
	}
	session, err := m.store.GetSession(ctx, lease.SessionID)
	if err != nil {
		return err
	}
	// Sessões ainda não pareadas ficam sob lease sem abrir o socket WhatsMeow;
	// o pairing faz GetQRChannel antes do Connect.
	if session.Status == domain.SessionProvisioned || session.Status == domain.SessionPairing {
		m.mu.Lock()
		m.owned[lease.SessionID] = lease
		m.mu.Unlock()
		return nil
	}
	if err := m.connect(ctx, lease.SessionID); err != nil {
		reconnectCount := session.ReconnectCount + 1
		delay := reconnectDelay(reconnectCount)
		_ = m.store.SetSessionStatus(ctx, lease.SessionID, domain.SessionDegraded, reconnectCount, now.Add(delay))
		_ = m.store.ReleaseLease(ctx, lease)
		return err
	}
	if valid, err := m.store.ValidLease(ctx, lease, m.now()); err != nil || !valid {
		m.connector.Disconnect(lease.SessionID)
		_ = m.store.ReleaseLease(ctx, lease)
		return ErrLeaseNotOwned
	}
	if err := m.store.SetSessionStatus(ctx, lease.SessionID, domain.SessionConnected, 0, time.Time{}); err != nil {
		m.connector.Disconnect(lease.SessionID)
		_ = m.store.ReleaseLease(ctx, lease)
		return err
	}
	m.mu.Lock()
	m.owned[lease.SessionID] = lease
	m.mu.Unlock()
	return nil
}

func (m *Manager) connect(ctx context.Context, sessionID string) error {
	if connector, ok := m.connector.(contextConnector); ok {
		return connector.ConnectContext(ctx, sessionID)
	}
	return m.connector.Connect(sessionID)
}

func reconnectDelay(attempt int) time.Duration {
	if attempt < 1 {
		attempt = 1
	}
	delay := time.Second * time.Duration(1<<min(attempt-1, 8))
	return min(delay, 5*time.Minute)
}
