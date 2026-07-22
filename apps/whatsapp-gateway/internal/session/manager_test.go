package session

import (
	"context"
	"errors"
	"fmt"
	"sync"
	"testing"
	"time"

	"github.com/inovaicontabil/fiscal-hub/apps/whatsapp-gateway/internal/domain"
	"github.com/inovaicontabil/fiscal-hub/apps/whatsapp-gateway/internal/store"
)

type fakeConnector struct {
	mu               sync.Mutex
	failures         int
	connected        map[string]bool
	contextCalls     int
	resetCalls       int
	passiveCalls     int
	passive          bool
	beforeConnectEnd func()
}

func (c *fakeConnector) Connect(sessionID string) error {
	c.mu.Lock()
	defer c.mu.Unlock()
	if c.failures > 0 {
		c.failures--
		return fmt.Errorf("temporary connection failure")
	}
	c.connected[sessionID] = true
	return nil
}

func (c *fakeConnector) Disconnect(sessionID string) {
	c.mu.Lock()
	defer c.mu.Unlock()
	delete(c.connected, sessionID)
}

func (c *fakeConnector) ConnectContext(_ context.Context, sessionID string) error {
	c.mu.Lock()
	c.contextCalls++
	hook := c.beforeConnectEnd
	c.mu.Unlock()
	err := c.Connect(sessionID)
	if hook != nil {
		hook()
	}
	return err
}

func (c *fakeConnector) Reset(_ context.Context, sessionID string) error {
	c.mu.Lock()
	defer c.mu.Unlock()
	if !c.connected[sessionID] {
		return fmt.Errorf("not connected")
	}
	c.resetCalls++
	return nil
}

func (c *fakeConnector) SetPassive(_ context.Context, sessionID string, passive bool) error {
	c.mu.Lock()
	defer c.mu.Unlock()
	if !c.connected[sessionID] {
		return fmt.Errorf("not connected")
	}
	c.passiveCalls++
	c.passive = passive
	return nil
}

func TestLeaseContentionAndTakeoverUseHigherFencingToken(t *testing.T) {
	t.Parallel()
	persistence := store.NewMemory()
	now := time.Unix(1_785_000_000, 0)
	mustProvision(t, persistence, "session-fencing-0001")

	var leases [2][]domain.Lease
	var wait sync.WaitGroup
	for index, replica := range []string{"replica-a", "replica-b"} {
		wait.Add(1)
		go func(index int, replica string) {
			defer wait.Done()
			leases[index], _ = persistence.ClaimSessions(t.Context(), replica, 1, now, 30*time.Second)
		}(index, replica)
	}
	wait.Wait()
	if len(leases[0])+len(leases[1]) != 1 {
		t.Fatalf("exactly one replica must own the session: %+v", leases)
	}
	var first domain.Lease
	if len(leases[0]) == 1 {
		first = leases[0][0]
	} else {
		first = leases[1][0]
	}

	takeover, err := persistence.ClaimSessions(t.Context(), "replica-c", 1, now.Add(31*time.Second), 30*time.Second)
	if err != nil || len(takeover) != 1 {
		t.Fatalf("takeover failed: leases=%+v err=%v", takeover, err)
	}
	if takeover[0].FencingToken <= first.FencingToken {
		t.Fatalf("fencing token did not advance: first=%d takeover=%d", first.FencingToken, takeover[0].FencingToken)
	}
	valid, _ := persistence.ValidLease(t.Context(), first, now.Add(31*time.Second))
	if valid {
		t.Fatal("expired owner remained valid after takeover")
	}
}

func TestCapacityDistributesFiveThousandLogicalSessionsWithoutDuplicates(t *testing.T) {
	persistence := store.NewMemory()
	now := time.Unix(1_785_000_000, 0)
	for index := range 5_000 {
		mustProvision(t, persistence, fmt.Sprintf("session-load-%04d", index))
	}

	claimed := make(chan domain.Lease, 5_000)
	var wait sync.WaitGroup
	for replica := range 20 {
		wait.Add(1)
		go func(replica int) {
			defer wait.Done()
			leases, err := persistence.ClaimSessions(
				t.Context(), fmt.Sprintf("replica-load-%02d", replica), 250, now, time.Minute,
			)
			if err != nil {
				t.Errorf("claim sessions: %v", err)
				return
			}
			for _, lease := range leases {
				claimed <- lease
			}
		}(replica)
	}
	wait.Wait()
	close(claimed)

	unique := make(map[string]string, 5_000)
	for lease := range claimed {
		if owner, duplicate := unique[lease.SessionID]; duplicate {
			t.Fatalf("session %s claimed by %s and %s", lease.SessionID, owner, lease.ReplicaID)
		}
		unique[lease.SessionID] = lease.ReplicaID
	}
	if len(unique) != 5_000 {
		t.Fatalf("expected 5000 claimed sessions, got %d", len(unique))
	}
}

func TestManagerReconnectsWithBackoffAndChecksFence(t *testing.T) {
	t.Parallel()
	persistence := store.NewMemory()
	mustProvision(t, persistence, "session-reconnect-0001")
	if err := persistence.SetSessionStatus(
		t.Context(), "session-reconnect-0001", domain.SessionDegraded, 0, time.Time{},
	); err != nil {
		t.Fatalf("prepare reconnectable session: %v", err)
	}
	connector := &fakeConnector{failures: 1, connected: make(map[string]bool)}
	manager := NewManager(persistence, connector, "replica-reconnect", 1, 30*time.Second, 10*time.Second)
	now := time.Unix(1_785_000_000, 0)
	manager.now = func() time.Time { return now }

	if err := manager.Reconcile(t.Context()); err != nil {
		t.Fatalf("first reconcile: %v", err)
	}
	session, _ := persistence.GetSession(t.Context(), "session-reconnect-0001")
	if session.Status != domain.SessionDegraded || session.ReconnectCount != 1 {
		t.Fatalf("failed connection did not degrade session: %+v", session)
	}

	now = now.Add(2 * time.Second)
	if err := manager.Reconcile(t.Context()); err != nil {
		t.Fatalf("retry reconcile: %v", err)
	}
	session, _ = persistence.GetSession(t.Context(), "session-reconnect-0001")
	if session.Status != domain.SessionConnected {
		t.Fatalf("session did not reconnect: %+v", session)
	}
	if _, owns := manager.Owns(t.Context(), session.SessionID); !owns {
		t.Fatal("manager connected session without a valid fence")
	}
}

func TestManagerUsesCancelableConnectorAndChecksLeaseAfterConnect(t *testing.T) {
	t.Parallel()
	persistence := store.NewMemory()
	mustProvision(t, persistence, "session-stale-connect-0001")
	if err := persistence.SetSessionStatus(
		t.Context(), "session-stale-connect-0001", domain.SessionDegraded, 0, time.Time{},
	); err != nil {
		t.Fatalf("prepare reconnectable session: %v", err)
	}
	now := time.Unix(1_785_000_000, 0)
	connector := &fakeConnector{connected: make(map[string]bool)}
	manager := NewManager(persistence, connector, "replica-stale", 1, 30*time.Second, 10*time.Second)
	manager.now = func() time.Time { return now }
	connector.beforeConnectEnd = func() { now = now.Add(31 * time.Second) }

	if err := manager.Reconcile(t.Context()); err != nil {
		t.Fatalf("reconcile stale connect: %v", err)
	}
	connector.mu.Lock()
	connected := connector.connected["session-stale-connect-0001"]
	contextCalls := connector.contextCalls
	connector.mu.Unlock()
	if connected || contextCalls != 1 {
		t.Fatalf("stale owner kept socket or context path was skipped: connected=%v calls=%d", connected, contextCalls)
	}
	if _, owns := manager.Owns(t.Context(), "session-stale-connect-0001"); owns {
		t.Fatal("manager retained ownership after lease expired during connect")
	}
}

func TestOwnedSessionOperationsRequireCurrentFenceAndPersistTransitions(t *testing.T) {
	t.Parallel()
	persistence := store.NewMemory()
	mustProvision(t, persistence, "session-owned-ops-0001")
	if err := persistence.SetSessionStatus(
		t.Context(), "session-owned-ops-0001", domain.SessionDegraded, 0, time.Time{},
	); err != nil {
		t.Fatalf("prepare session: %v", err)
	}
	now := time.Unix(1_785_000_000, 0)
	connector := &fakeConnector{connected: make(map[string]bool)}
	manager := NewManager(persistence, connector, "replica-owner", 1, 30*time.Second, 10*time.Second)
	manager.now = func() time.Time { return now }
	if err := manager.Reconcile(t.Context()); err != nil {
		t.Fatalf("claim and connect: %v", err)
	}
	if err := manager.Reset(t.Context(), "session-owned-ops-0001"); err != nil {
		t.Fatalf("owned reset: %v", err)
	}
	if err := manager.SetPassive(t.Context(), "session-owned-ops-0001", true); err != nil {
		t.Fatalf("owned passive: %v", err)
	}
	connector.mu.Lock()
	resetCalls, passiveCalls, passive := connector.resetCalls, connector.passiveCalls, connector.passive
	connector.mu.Unlock()
	if resetCalls != 1 || passiveCalls != 1 || !passive {
		t.Fatalf("owned primitives not called: reset=%d passive_calls=%d passive=%v", resetCalls, passiveCalls, passive)
	}
	if err := manager.DisconnectOwned(t.Context(), "session-owned-ops-0001"); err != nil {
		t.Fatalf("owned disconnect: %v", err)
	}
	session, _ := persistence.GetSession(t.Context(), "session-owned-ops-0001")
	if session.Status != domain.SessionDisabled || session.DesiredConnected {
		t.Fatalf("disconnect transition not persisted: %+v", session)
	}

	now = now.Add(31 * time.Second)
	if err := manager.Reset(t.Context(), "session-owned-ops-0001"); !errors.Is(err, ErrLeaseNotOwned) {
		t.Fatalf("stale reset was not fenced: %v", err)
	}
	connector.mu.Lock()
	resetCalls = connector.resetCalls
	connector.mu.Unlock()
	if resetCalls != 1 {
		t.Fatalf("stale owner caused remote reset: calls=%d", resetCalls)
	}
}

func mustProvision(t *testing.T, persistence store.Store, sessionID string) {
	t.Helper()
	err := persistence.UpsertSession(context.Background(), domain.Session{
		SessionID: sessionID, Status: domain.SessionProvisioned, DesiredConnected: true,
	})
	if err != nil {
		t.Fatalf("provision %s: %v", sessionID, err)
	}
}
