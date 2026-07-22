package protocol

import (
	"context"
	"database/sql"
	"errors"
	"fmt"
	"sync"

	"github.com/inovaicontabil/fiscal-hub/apps/whatsapp-gateway/internal/cryptobox"
	_ "github.com/jackc/pgx/v5/stdlib"
	"go.mau.fi/whatsmeow"
	"go.mau.fi/whatsmeow/store/sqlstore"
	"go.mau.fi/whatsmeow/types"
)

type DeviceResolver struct {
	mu        sync.Mutex
	db        *sql.DB
	container *sqlstore.Container
	box       *cryptobox.Box
	clients   map[string]*whatsmeow.Client
	handlers  map[string]uint32
	settings  ClientSettings
	ctx       context.Context
	eventSink func(string, *whatsmeow.Client, any) bool
}

func OpenDeviceResolver(
	ctx context.Context,
	databaseURL string,
	box *cryptobox.Box,
	clientSettings ...ClientSettings,
) (*DeviceResolver, error) {
	settings := ClientSettings{}.normalized()
	if len(clientSettings) > 0 {
		settings = clientSettings[0].normalized()
	}
	if err := settings.validate(); err != nil {
		return nil, err
	}
	db, err := sql.Open("pgx", databaseURL)
	if err != nil {
		return nil, fmt.Errorf("open WhatsMeow device database: %w", err)
	}
	container := sqlstore.NewWithDB(db, "postgres", nil)
	if err := container.Upgrade(ctx); err != nil {
		_ = db.Close()
		return nil, fmt.Errorf("upgrade WhatsMeow device database: %w", err)
	}
	return &DeviceResolver{
		db: db, container: container, box: box, clients: make(map[string]*whatsmeow.Client),
		handlers: make(map[string]uint32), settings: settings, ctx: ctx,
	}, nil
}

func (r *DeviceResolver) Resolve(sessionID string) (WhatsMeowClient, error) {
	r.mu.Lock()
	defer r.mu.Unlock()
	if client, ok := r.clients[sessionID]; ok {
		return client, nil
	}

	var ciphertext, nonce []byte
	err := r.db.QueryRow(`
SELECT device_jid_cipher, device_jid_nonce
FROM whatsapp_gateway.session_devices
WHERE session_id = $1`, sessionID).Scan(&ciphertext, &nonce)
	var device = r.container.NewDevice()
	if err == nil {
		plain, decryptErr := r.box.Open(ciphertext, nonce, []byte(sessionID))
		if decryptErr != nil {
			return nil, fmt.Errorf("decrypt session device mapping: %w", decryptErr)
		}
		jid, parseErr := types.ParseJID(string(plain))
		if parseErr != nil {
			return nil, fmt.Errorf("parse stored device JID: %w", parseErr)
		}
		device, err = r.container.GetDevice(context.Background(), jid)
		if err != nil {
			return nil, fmt.Errorf("load WhatsMeow device: %w", err)
		}
		if device == nil {
			return nil, errors.New("mapped WhatsMeow device does not exist")
		}
	} else if !errors.Is(err, sql.ErrNoRows) {
		return nil, fmt.Errorf("read session device mapping: %w", err)
	}

	client := whatsmeow.NewClient(device, nil)
	if err := configureWhatsMeowClient(client, r.ctx, r.settings); err != nil {
		return nil, err
	}
	if r.eventSink != nil {
		sink := r.eventSink
		r.handlers[sessionID] = client.AddEventHandlerWithSuccessStatus(func(event any) bool {
			return sink(sessionID, client, event)
		})
	}
	r.clients[sessionID] = client
	return client, nil
}

func (r *DeviceResolver) SetEventSink(sink func(string, *whatsmeow.Client, any) bool) {
	r.mu.Lock()
	defer r.mu.Unlock()
	for sessionID, handlerID := range r.handlers {
		if client := r.clients[sessionID]; client != nil {
			client.RemoveEventHandler(handlerID)
		}
		delete(r.handlers, sessionID)
	}
	r.eventSink = sink
	if sink == nil {
		return
	}
	for sessionID, client := range r.clients {
		resolvedSessionID := sessionID
		resolvedClient := client
		r.handlers[sessionID] = client.AddEventHandlerWithSuccessStatus(func(event any) bool {
			return sink(resolvedSessionID, resolvedClient, event)
		})
	}
}

// Release evicts one client after logout and removes its event handler. A
// subsequent Resolve creates a fresh client from the current device mapping.
func (r *DeviceResolver) Release(sessionID string) {
	r.mu.Lock()
	client := r.clients[sessionID]
	handlerID, hasHandler := r.handlers[sessionID]
	delete(r.handlers, sessionID)
	delete(r.clients, sessionID)
	r.mu.Unlock()
	if client == nil {
		return
	}
	if hasHandler {
		client.RemoveEventHandler(handlerID)
	}
	client.Disconnect()
}

func (r *DeviceResolver) RecordDevice(ctx context.Context, sessionID string) error {
	r.mu.Lock()
	defer r.mu.Unlock()
	client, ok := r.clients[sessionID]
	if !ok || client.Store.ID == nil {
		return errors.New("paired device is not available")
	}
	plain := []byte(client.Store.ID.String())
	ciphertext, nonce, err := r.box.Seal(plain, []byte(sessionID))
	if err != nil {
		return err
	}
	_, err = r.db.ExecContext(ctx, `
INSERT INTO whatsapp_gateway.session_devices (
    session_id, device_jid_cipher, device_jid_nonce, updated_at
) VALUES ($1, $2, $3, now())
ON CONFLICT (session_id) DO UPDATE SET
    device_jid_cipher = EXCLUDED.device_jid_cipher,
    device_jid_nonce = EXCLUDED.device_jid_nonce,
    updated_at = now()`, sessionID, ciphertext, nonce)
	if err != nil {
		return fmt.Errorf("persist session device mapping: %w", err)
	}
	return nil
}

func (r *DeviceResolver) Close() {
	r.mu.Lock()
	clients := r.clients
	handlers := r.handlers
	r.clients = make(map[string]*whatsmeow.Client)
	r.handlers = make(map[string]uint32)
	r.mu.Unlock()
	for sessionID, client := range clients {
		if handlerID, ok := handlers[sessionID]; ok {
			client.RemoveEventHandler(handlerID)
		}
		client.Disconnect()
	}
	_ = r.container.Close()
}
