package session

import (
	"context"
	"crypto/rand"
	"crypto/sha256"
	"encoding/hex"
	"encoding/json"
	"errors"
	"time"

	"github.com/inovaicontabil/fiscal-hub/apps/whatsapp-gateway/internal/domain"
	"github.com/inovaicontabil/fiscal-hub/apps/whatsapp-gateway/internal/store"
)

type Pairer interface {
	StartPairing(context.Context, string) (<-chan domain.PairingUpdate, error)
}

type advancedPairer interface {
	StartPhonePairing(context.Context, string, string, bool) (<-chan domain.PairingUpdate, error)
	RespondPasskey(context.Context, string, domain.PasskeyResponsePayload) error
	ConfirmPasskey(context.Context, string, bool) error
}

var ErrAdvancedPairingUnsupported = errors.New("advanced pairing is unsupported")

type DeviceRecorder interface {
	RecordDevice(context.Context, string) error
}

type PairingCoordinator struct {
	store    store.Store
	pairer   Pairer
	recorder DeviceRecorder
}

func NewPairingCoordinator(persistence store.Store, pairer Pairer, recorder DeviceRecorder) *PairingCoordinator {
	return &PairingCoordinator{store: persistence, pairer: pairer, recorder: recorder}
}

func (c *PairingCoordinator) Start(ctx context.Context, sessionID string) error {
	updates, err := c.pairer.StartPairing(ctx, sessionID)
	if err != nil {
		return err
	}
	if err := c.store.SetSessionStatus(ctx, sessionID, domain.SessionPairing, 0, time.Time{}); err != nil {
		return err
	}
	go c.consume(ctx, sessionID, updates)
	return nil
}

func (c *PairingCoordinator) StartPhone(
	ctx context.Context,
	sessionID, phone string,
	showPushNotification bool,
) error {
	pairer, ok := c.pairer.(advancedPairer)
	if !ok {
		return ErrAdvancedPairingUnsupported
	}
	updates, err := pairer.StartPhonePairing(ctx, sessionID, phone, showPushNotification)
	if err != nil {
		return err
	}
	if err := c.store.SetSessionStatus(ctx, sessionID, domain.SessionPairing, 0, time.Time{}); err != nil {
		return err
	}
	go c.consume(ctx, sessionID, updates)
	return nil
}

func (c *PairingCoordinator) RespondPasskey(
	ctx context.Context,
	sessionID string,
	payload domain.PasskeyResponsePayload,
) error {
	pairer, ok := c.pairer.(advancedPairer)
	if !ok {
		return ErrAdvancedPairingUnsupported
	}
	return pairer.RespondPasskey(ctx, sessionID, payload)
}

func (c *PairingCoordinator) ConfirmPasskey(ctx context.Context, sessionID string, confirm bool) error {
	pairer, ok := c.pairer.(advancedPairer)
	if !ok {
		return ErrAdvancedPairingUnsupported
	}
	return pairer.ConfirmPasskey(ctx, sessionID, confirm)
}

func (c *PairingCoordinator) consume(ctx context.Context, sessionID string, updates <-chan domain.PairingUpdate) {
	for update := range updates {
		payload, _ := json.Marshal(map[string]any{
			"event": update.Event, "code": update.Code,
			"expires_at": update.ExpiresAt, "error_code": update.ErrorCode,
			"passkey_request": update.PasskeyRequest,
		})
		digest := sha256.Sum256(payload)
		event := domain.Event{
			ContractVersion: "v1", EventID: randomID("pairing"), SessionID: sessionID,
			Type: domain.EventPairingUpdated, OccurredAt: time.Now().UTC(), Payload: payload,
			Digest: hex.EncodeToString(digest[:]),
		}
		_, _ = c.store.AppendEvent(ctx, event)
		switch update.Event {
		case "success":
			if c.recorder != nil {
				_ = c.recorder.RecordDevice(ctx, sessionID)
			}
			_ = c.store.SetSessionStatus(ctx, sessionID, domain.SessionConnected, 0, time.Time{})
		case "timeout", "error", "err-unexpected-state", "err-client-outdated", "err-scanned-without-multidevice":
			_ = c.store.SetSessionStatus(ctx, sessionID, domain.SessionDegraded, 1, time.Now().Add(time.Minute))
		}
	}
}

func randomID(prefix string) string {
	random := make([]byte, 16)
	_, _ = rand.Read(random)
	return prefix + "-" + hex.EncodeToString(random)
}
