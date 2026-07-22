package protocol

import (
	"context"
	"crypto/tls"
	"encoding/base64"
	"errors"
	"fmt"
	"net/http"
	"net/url"
	"strings"
	"testing"
	"time"

	"github.com/inovaicontabil/fiscal-hub/apps/whatsapp-gateway/internal/domain"
	"go.mau.fi/whatsmeow"
	waStore "go.mau.fi/whatsmeow/store"
	"go.mau.fi/whatsmeow/types"
	"go.mau.fi/whatsmeow/types/events"
)

func (c *fakeClient) ConnectContext(ctx context.Context) error {
	c.connectContextCalls++
	if c.connectWait != nil {
		select {
		case <-c.connectWait:
		case <-ctx.Done():
			return ctx.Err()
		}
	}
	if c.connectErr != nil {
		return c.connectErr
	}
	c.connected = true
	if c.loginOnConnect {
		c.loggedIn = true
	}
	return nil
}

func (c *fakeClient) WaitForConnection(time.Duration) bool {
	c.waitForConnectionCall++
	return c.ready
}

func (c *fakeClient) IsLoggedIn() bool { return c.loggedIn }

func (c *fakeClient) ResetConnection() { c.resetCalls++ }

func (c *fakeClient) PairPhone(
	_ context.Context,
	phone string,
	showPush bool,
	_ whatsmeow.PairClientType,
	_ string,
) (string, error) {
	c.pairedPhone = phone
	c.showPairingPush = showPush
	return c.pairPhoneCode, c.pairPhoneError
}

func (c *fakeClient) SendPasskeyResponse(_ context.Context, response *types.WebAuthnResponse) error {
	c.passkeyResponse = response
	return c.passkeyResponseError
}

func (c *fakeClient) SendPasskeyConfirmation(context.Context) error {
	c.passkeyConfirmCalls++
	return c.passkeyConfirmError
}

func (c *fakeClient) SetPassive(_ context.Context, passive bool) error {
	c.passive = passive
	return c.passiveError
}

func TestSessionConnectWaitsForAuthenticatedReadiness(t *testing.T) {
	t.Parallel()
	client := &fakeClient{loginOnConnect: true, ready: true}
	adapter := NewWhatsMeowAdapter(fakeResolver{client: client}, ClientSettings{
		ConnectTimeout: time.Second, ReadyTimeout: time.Second, HTTPTimeout: time.Second,
		MaxParallelRetryHandlers: 1,
	})
	if err := adapter.ConnectContext(t.Context(), "session-ready-0001"); err != nil {
		t.Fatalf("connect ready session: %v", err)
	}
	state, err := adapter.State("session-ready-0001")
	if err != nil {
		t.Fatalf("read state: %v", err)
	}
	if !state.Connected || !state.LoggedIn || !state.Ready || state.Passive {
		t.Fatalf("unexpected sanitized state: %+v", state)
	}
	if client.connectContextCalls != 1 || client.waitForConnectionCall != 1 {
		t.Fatalf("connect/readiness primitives not used: connect=%d wait=%d", client.connectContextCalls, client.waitForConnectionCall)
	}
}

func TestSessionConnectIsCancelableAndRedactsUpstreamFailure(t *testing.T) {
	t.Parallel()
	never := make(chan struct{})
	client := &fakeClient{connectWait: never}
	adapter := NewWhatsMeowAdapter(fakeResolver{client: client}, ClientSettings{
		ConnectTimeout: 10 * time.Millisecond, ReadyTimeout: time.Second, HTTPTimeout: time.Second,
		MaxParallelRetryHandlers: 1,
	})
	ctx, cancel := context.WithTimeout(t.Context(), 5*time.Millisecond)
	defer cancel()
	err := adapter.ConnectContext(ctx, "session-cancel-0001")
	if !errors.Is(err, ErrSessionConnect) {
		t.Fatalf("expected sanitized connect error, got %v", err)
	}
	if client.connected {
		t.Fatal("canceled connection remained connected")
	}
}

func TestSessionReadinessFailureDisconnectsSocket(t *testing.T) {
	t.Parallel()
	client := &fakeClient{loginOnConnect: true, ready: false}
	adapter := NewWhatsMeowAdapter(fakeResolver{client: client}, ClientSettings{
		ConnectTimeout: time.Second, ReadyTimeout: time.Millisecond, HTTPTimeout: time.Second,
		MaxParallelRetryHandlers: 1,
	})
	if err := adapter.ConnectContext(t.Context(), "session-not-ready-0001"); !errors.Is(err, ErrSessionNotReady) {
		t.Fatalf("expected readiness error, got %v", err)
	}
	if client.connected {
		t.Fatal("not-ready socket was not disconnected")
	}
}

func TestResetAndPassiveUseTypedPrimitives(t *testing.T) {
	t.Parallel()
	client := &fakeClient{connected: true, loggedIn: true, ready: true, loginOnConnect: true}
	adapter := NewWhatsMeowAdapter(fakeResolver{client: client}, ClientSettings{
		ConnectTimeout: time.Second, ReadyTimeout: time.Second, HTTPTimeout: time.Second,
		MaxParallelRetryHandlers: 1,
	})
	if err := adapter.Reset(t.Context(), "session-reset-0001"); err != nil {
		t.Fatalf("reset session: %v", err)
	}
	if client.resetCalls != 1 || client.connectContextCalls != 1 {
		t.Fatalf("reset did not reconnect synchronously: reset=%d connect=%d", client.resetCalls, client.connectContextCalls)
	}
	if err := adapter.SetPassive(t.Context(), "session-reset-0001", true); err != nil {
		t.Fatalf("set passive: %v", err)
	}
	state, _ := adapter.State("session-reset-0001")
	if !client.passive || !state.Passive || !state.Ready {
		t.Fatalf("passive state was not projected: client=%v state=%+v", client.passive, state)
	}
}

func TestPhonePairingWaitsForQRAndNeverEchoesPhoneInErrors(t *testing.T) {
	t.Parallel()
	const phone = "+5511999991234"
	updates := make(chan whatsmeow.QRChannelItem, 2)
	updates <- whatsmeow.QRChannelItem{Event: whatsmeow.QRChannelEventCode, Code: "qr-must-not-leak"}
	updates <- whatsmeow.QRChannelSuccess
	close(updates)
	client := &fakeClient{
		qrUpdates: updates, pairPhoneError: fmt.Errorf("remote rejected %s with secret-code", phone),
	}
	adapter := NewWhatsMeowAdapter(fakeResolver{client: client})
	result, err := adapter.StartPhonePairing(t.Context(), "session-phone-0001", phone, true)
	if err != nil {
		t.Fatalf("start phone pairing: %v", err)
	}
	first := <-result
	serialized := fmt.Sprintf("%+v", first)
	if first.Event != "error" || first.ErrorCode != "PAIRING_FAILED" ||
		strings.Contains(serialized, phone) || strings.Contains(serialized, "secret-code") ||
		strings.Contains(serialized, "qr-must-not-leak") {
		t.Fatalf("pairing failure was not redacted: %s", serialized)
	}
	if client.pairedPhone != "5511999991234" || !client.showPairingPush {
		t.Fatalf("phone was not normalized internally: phone=%q push=%v", client.pairedPhone, client.showPairingPush)
	}
}

func TestPhonePairingReturnsOnlyCodeAndExpiry(t *testing.T) {
	t.Parallel()
	updates := make(chan whatsmeow.QRChannelItem, 2)
	updates <- whatsmeow.QRChannelItem{Event: whatsmeow.QRChannelEventCode, Code: "private-qr"}
	updates <- whatsmeow.QRChannelSuccess
	close(updates)
	client := &fakeClient{qrUpdates: updates, pairPhoneCode: "ABCD-EFGH"}
	adapter := NewWhatsMeowAdapter(fakeResolver{client: client})
	result, err := adapter.StartPhonePairing(t.Context(), "session-phone-0002", "+5511988887777", false)
	if err != nil {
		t.Fatalf("start phone pairing: %v", err)
	}
	first := <-result
	if first.Event != "phone-code" || first.Code != "ABCD-EFGH" || first.ExpiresAt.IsZero() {
		t.Fatalf("unexpected phone pairing projection: %+v", first)
	}
	if strings.Contains(fmt.Sprintf("%+v", first), "private-qr") {
		t.Fatalf("QR code leaked into phone pairing event: %+v", first)
	}
	second := <-result
	if second.Event != "success" {
		t.Fatalf("pairing terminal event missing: %+v", second)
	}
}

func TestPasskeyRequestAndResponseAreAllowlisted(t *testing.T) {
	t.Parallel()
	request := &types.WebAuthnPublicKey{
		Challenge:        []byte("challenge"),
		Timeout:          30_000,
		RelyingPartID:    "web.whatsapp.com",
		UserVerification: "preferred",
		AllowCredentials: []types.AllowedCredential{{
			ID: []byte("credential"), Type: "public-key", Transports: []string{"internal", "unsupported"},
		}},
		Extensions: map[string]any{"raw_should_not_escape": "secret"},
	}
	mapped := sanitizePairingUpdate(whatsmeow.QRChannelItem{
		Event:          whatsmeow.QRChannelEventPasskeyRequest,
		PasskeyRequest: &events.PairPasskeyRequest{PublicKey: request},
	})
	if mapped.PasskeyRequest == nil || mapped.PasskeyRequest.RequestID == "" ||
		mapped.PasskeyRequest.Challenge != base64.RawURLEncoding.EncodeToString([]byte("challenge")) ||
		len(mapped.PasskeyRequest.AllowedCredential) != 1 ||
		len(mapped.PasskeyRequest.AllowedCredential[0].Transports) != 1 {
		t.Fatalf("unexpected passkey projection: %+v", mapped.PasskeyRequest)
	}
	if strings.Contains(fmt.Sprintf("%+v", mapped), "raw_should_not_escape") {
		t.Fatalf("passkey extensions escaped allowlist: %+v", mapped)
	}

	client := &fakeClient{connected: true}
	adapter := NewWhatsMeowAdapter(fakeResolver{client: client})
	payload := domain.PasskeyResponsePayload{
		ID:             base64.RawURLEncoding.EncodeToString([]byte("credential")),
		ClientDataJSON: base64.RawURLEncoding.EncodeToString([]byte("client-data")),
		Authenticator:  base64.RawURLEncoding.EncodeToString([]byte("authenticator")),
		Signature:      base64.RawURLEncoding.EncodeToString([]byte("signature")),
	}
	if err := adapter.RespondPasskey(t.Context(), "session-passkey-0001", payload); err != nil {
		t.Fatalf("respond passkey: %v", err)
	}
	if client.passkeyResponse == nil || string(client.passkeyResponse.Response.Signature) != "signature" {
		t.Fatalf("passkey response not decoded: %+v", client.passkeyResponse)
	}
	if err := adapter.ConfirmPasskey(t.Context(), "session-passkey-0001", true); err != nil {
		t.Fatalf("confirm passkey: %v", err)
	}
	if client.passkeyConfirmCalls != 1 {
		t.Fatalf("passkey confirmation primitive was not called")
	}
}

func TestClientSettingsAreFailClosedAndRedacted(t *testing.T) {
	t.Parallel()
	const secretProxy = "https://user:proxy-secret@"
	err := ClientSettings{ProxyAddress: secretProxy}.validate()
	if !errors.Is(err, ErrInvalidClientSettings) || strings.Contains(err.Error(), "proxy-secret") {
		t.Fatalf("proxy error was not fail-closed and redacted: %v", err)
	}
	if err := (ClientSettings{ProxyAddress: "http://proxy.internal"}).validate(); !errors.Is(err, ErrInvalidClientSettings) {
		t.Fatalf("plaintext proxy was accepted: %v", err)
	}

	client := newSafeHTTPClient(3 * time.Second)
	transport := client.Transport.(*http.Transport)
	if client.Timeout != 3*time.Second || transport.Proxy != nil ||
		transport.TLSClientConfig.MinVersion < tls.VersionTLS12 {
		t.Fatalf(
			"unsafe HTTP client settings: timeout=%s proxy_set=%t tls=%d",
			client.Timeout, transport.Proxy != nil, transport.TLSClientConfig.MinVersion,
		)
	}
	redirect := &http.Request{URL: &url.URL{Scheme: "http", Host: "unsafe.invalid"}}
	if err := client.CheckRedirect(redirect, nil); err == nil {
		t.Fatal("HTTPS downgrade redirect was accepted")
	}
}

func TestConfigureClientDisablesUnfencedReconnectAndAmbientFeatures(t *testing.T) {
	t.Parallel()
	client := whatsmeow.NewClient(&waStore.Device{}, nil)
	if err := configureWhatsMeowClient(client, t.Context(), ClientSettings{}); err != nil {
		t.Fatalf("configure client: %v", err)
	}
	if client.EnableAutoReconnect || client.InitialAutoReconnect || !client.DisableLoginAutoReconnect ||
		client.AutoTrustIdentity || client.AutomaticMessageRerequestFromPhone ||
		client.UseRetryMessageStore || !client.SynchronousAck || client.SendReportingTokens {
		t.Fatalf("unsafe runtime flags remained enabled: %+v", client)
	}
}

func TestDeviceResolverRemovesHandlersOnSinkReplacementAndRelease(t *testing.T) {
	t.Parallel()
	client := whatsmeow.NewClient(&waStore.Device{}, nil)
	oldID := client.AddEventHandler(func(any) {})
	resolver := &DeviceResolver{
		clients:  map[string]*whatsmeow.Client{"session-handler-0001": client},
		handlers: map[string]uint32{"session-handler-0001": oldID},
	}
	resolver.SetEventSink(func(string, *whatsmeow.Client, any) bool { return true })
	newID := resolver.handlers["session-handler-0001"]
	if newID == oldID || client.RemoveEventHandler(oldID) {
		t.Fatal("old handler remained registered after sink replacement")
	}
	resolver.Release("session-handler-0001")
	if len(resolver.clients) != 0 || len(resolver.handlers) != 0 || client.RemoveEventHandler(newID) {
		t.Fatal("handler or cached client remained after release")
	}
}
