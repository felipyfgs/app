package httpapi

import (
	"bytes"
	"context"
	"io"
	"net/http"
	"net/http/httptest"
	"strconv"
	"strings"
	"testing"
	"time"

	"github.com/inovaicontabil/fiscal-hub/apps/whatsapp-gateway/internal/domain"
	"github.com/inovaicontabil/fiscal-hub/apps/whatsapp-gateway/internal/security"
	"github.com/inovaicontabil/fiscal-hub/apps/whatsapp-gateway/internal/store"
)

type testMediaStore struct{ content string }

func (s testMediaStore) Reader(context.Context, string) (io.ReadCloser, error) {
	return io.NopCloser(strings.NewReader(s.content)), nil
}

type testQueryExecutor struct {
	calls int
}

func (e *testQueryExecutor) Execute(_ context.Context, query domain.Query) (any, error) {
	e.calls++
	return map[string]any{"available": query.Type == domain.QueryIsOnWhatsApp}, nil
}

const testSecret = "gateway-test-secret"

func TestCommandEndpointIsDurableIdempotentAndDetectsDigestConflict(t *testing.T) {
	t.Parallel()
	persistence := store.NewMemory()
	server := newTestServer(persistence)
	body := []byte(`{"contract_version":"v1","command_id":"command-0001","session_id":"session-0001","type":"MESSAGE_SEND","provider_message_id":"message-0001","payload":{"to":"+5511999991234","text":"Olá"}}`)

	first := performCommand(t, server, body, "nonce-command-0001")
	if first.Code != http.StatusAccepted || !strings.Contains(first.Body.String(), `"duplicate":false`) {
		t.Fatalf("unexpected first response: %d %s", first.Code, first.Body.String())
	}
	duplicate := performCommand(t, server, body, "nonce-command-0002")
	if duplicate.Code != http.StatusAccepted || !strings.Contains(duplicate.Body.String(), `"duplicate":true`) {
		t.Fatalf("unexpected duplicate response: %d %s", duplicate.Code, duplicate.Body.String())
	}

	changed := bytes.Replace(body, []byte(`"Olá"`), []byte(`"Outro"`), 1)
	conflict := performCommand(t, server, changed, "nonce-command-0003")
	if conflict.Code != http.StatusConflict {
		t.Fatalf("expected conflict, got %d %s", conflict.Code, conflict.Body.String())
	}
}

func TestCommandEndpointRejectsReplayBeforeMutation(t *testing.T) {
	t.Parallel()
	persistence := store.NewMemory()
	server := newTestServer(persistence)
	body := []byte(`{"contract_version":"v1","command_id":"command-replay","session_id":"session-replay","type":"SESSION_PROVISION","payload":{}}`)

	first := performCommand(t, server, body, "nonce-replay-0001")
	second := performCommand(t, server, body, "nonce-replay-0001")
	if first.Code != http.StatusAccepted || second.Code != http.StatusUnauthorized {
		t.Fatalf("expected accepted then unauthorized, got %d and %d", first.Code, second.Code)
	}
	metrics, err := persistence.Metrics(t.Context())
	if err != nil || metrics.PendingCommands != 1 {
		t.Fatalf("replay mutated store: metrics=%+v err=%v", metrics, err)
	}
}

func TestCommandEndpointRejectsUnknownNestedPayloadFields(t *testing.T) {
	t.Parallel()
	persistence := store.NewMemory()
	server := newTestServer(persistence)
	body := []byte(`{"contract_version":"v1","command_id":"command-strict-0001","session_id":"session-strict-0001","type":"MESSAGE_SEND","provider_message_id":"message-strict-0001","payload":{"to":"+5511999991234","text":"Olá","raw_proto":{"secret":"forbidden"}}}`)

	response := performCommand(t, server, body, "nonce-command-strict-0001")
	if response.Code != http.StatusUnprocessableEntity {
		t.Fatalf("expected strict payload rejection, got %d %s", response.Code, response.Body.String())
	}
	metrics, _ := persistence.Metrics(t.Context())
	if metrics.PendingCommands != 0 {
		t.Fatal("invalid nested payload was persisted")
	}
}

func TestQueryEndpointIsStrictSignedAndReplayProtected(t *testing.T) {
	t.Parallel()
	persistence := store.NewMemory()
	executor := &testQueryExecutor{}
	server := newTestServer(persistence).WithQueryExecutor(executor)
	body := []byte(`{"contract_version":"v1","query_id":"query-user-check-0001","session_id":"session-query-0001","type":"USER_CHECK","payload":{"users":["+5511999991234"]}}`)

	first := performQuery(t, server, body, "nonce-query-user-check-0001")
	if first.Code != http.StatusOK || !strings.Contains(first.Body.String(), `"available":true`) {
		t.Fatalf("unexpected query response: %d %s", first.Code, first.Body.String())
	}
	replay := performQuery(t, server, body, "nonce-query-user-check-0001")
	if replay.Code != http.StatusUnauthorized || executor.calls != 1 {
		t.Fatalf("query replay reached executor: status=%d calls=%d", replay.Code, executor.calls)
	}

	unknownBody := bytes.Replace(body, []byte(`"users":["+5511999991234"]`), []byte(`"users":["+5511999991234"],"raw":true`), 1)
	unknown := performQuery(t, server, unknownBody, "nonce-query-user-check-0002")
	if unknown.Code != http.StatusUnprocessableEntity || executor.calls != 1 {
		t.Fatalf("strict query validation failed: status=%d calls=%d", unknown.Code, executor.calls)
	}
}

func TestHealthAndMetricsNeverExposePayloadOrIdentifiers(t *testing.T) {
	t.Parallel()
	persistence := store.NewMemory()
	server := newTestServer(persistence)
	body := []byte(`{"contract_version":"v1","command_id":"command-private","session_id":"session-private","type":"MESSAGE_SEND","provider_message_id":"message-private","payload":{"to":"+5511988887777","text":"conteúdo sigiloso"}}`)
	if response := performCommand(t, server, body, "nonce-private-0001"); response.Code != http.StatusAccepted {
		t.Fatalf("failed to seed command: %d %s", response.Code, response.Body.String())
	}

	for _, path := range []string{"/healthz", "/metrics"} {
		request := httptest.NewRequest(http.MethodGet, path, nil)
		response := httptest.NewRecorder()
		server.Handler().ServeHTTP(response, request)
		if response.Code != http.StatusOK {
			t.Fatalf("%s returned %d", path, response.Code)
		}
		for _, forbidden := range []string{"session-private", "+5511988887777", "conteúdo sigiloso", testSecret} {
			if strings.Contains(response.Body.String(), forbidden) {
				t.Fatalf("%s leaked %q in %s", path, forbidden, response.Body.String())
			}
		}
	}
}

func TestDisabledGatewayRefusesCommandsWithoutPersisting(t *testing.T) {
	t.Parallel()
	persistence := store.NewMemory()
	verifier := security.NewVerifier(map[string]string{"test-v1": testSecret}, 5*time.Minute, 10*time.Minute, persistence)
	server := New(false, 1<<20, persistence, verifier)
	request := httptest.NewRequest(http.MethodPost, "/internal/v1/commands", strings.NewReader(`{}`))
	response := httptest.NewRecorder()
	server.Handler().ServeHTTP(response, request)
	if response.Code != http.StatusServiceUnavailable {
		t.Fatalf("expected 503, got %d", response.Code)
	}
	metrics, _ := persistence.Metrics(t.Context())
	if metrics.PendingCommands != 0 {
		t.Fatal("disabled gateway persisted a command")
	}
}

func TestMediaEndpointRequiresSignatureAndStreamsPlaintextOnlyAfterAuthentication(t *testing.T) {
	t.Parallel()
	persistence := store.NewMemory()
	server := newTestServer(persistence).WithMediaStore(testMediaStore{content: "media-bytes"})

	unsigned := httptest.NewRequest(http.MethodGet, "/internal/v1/media/media-0001", nil)
	unsignedResponse := httptest.NewRecorder()
	server.Handler().ServeHTTP(unsignedResponse, unsigned)
	if unsignedResponse.Code != http.StatusUnauthorized {
		t.Fatalf("expected unsigned media request to fail, got %d", unsignedResponse.Code)
	}

	timestamp := time.Now().Unix()
	nonce := "nonce-media-download-0001"
	signed := httptest.NewRequest(http.MethodGet, "/internal/v1/media/media-0001", nil)
	signed.Header.Set(security.HeaderKeyID, "test-v1")
	signed.Header.Set(security.HeaderTimestamp, strconv.FormatInt(timestamp, 10))
	signed.Header.Set(security.HeaderNonce, nonce)
	signed.Header.Set(security.HeaderSignature, security.Sign(
		testSecret, signed.Method, signed.URL.EscapedPath(), nil, timestamp, nonce,
	))
	signedResponse := httptest.NewRecorder()
	server.Handler().ServeHTTP(signedResponse, signed)
	if signedResponse.Code != http.StatusOK || signedResponse.Body.String() != "media-bytes" {
		t.Fatalf("unexpected media response: %d %q", signedResponse.Code, signedResponse.Body.String())
	}
}

func newTestServer(persistence *store.Memory) *Server {
	verifier := security.NewVerifier(map[string]string{"test-v1": testSecret}, 5*time.Minute, 10*time.Minute, persistence)
	return New(true, 1<<20, persistence, verifier)
}

func performCommand(t *testing.T, server *Server, body []byte, nonce string) *httptest.ResponseRecorder {
	t.Helper()
	timestamp := time.Now().Unix()
	request := httptest.NewRequest(http.MethodPost, "/internal/v1/commands", bytes.NewReader(body))
	request.Header.Set(security.HeaderKeyID, "test-v1")
	request.Header.Set(security.HeaderTimestamp, strconv.FormatInt(timestamp, 10))
	request.Header.Set(security.HeaderNonce, nonce)
	request.Header.Set(security.HeaderSignature, security.Sign(testSecret, request.Method, request.URL.EscapedPath(), body, timestamp, nonce))
	response := httptest.NewRecorder()
	server.Handler().ServeHTTP(response, request)
	return response
}

func performQuery(t *testing.T, server *Server, body []byte, nonce string) *httptest.ResponseRecorder {
	t.Helper()
	timestamp := time.Now().Unix()
	request := httptest.NewRequest(http.MethodPost, "/internal/v1/queries", bytes.NewReader(body))
	request.Header.Set(security.HeaderKeyID, "test-v1")
	request.Header.Set(security.HeaderTimestamp, strconv.FormatInt(timestamp, 10))
	request.Header.Set(security.HeaderNonce, nonce)
	request.Header.Set(security.HeaderSignature, security.Sign(testSecret, request.Method, request.URL.EscapedPath(), body, timestamp, nonce))
	response := httptest.NewRecorder()
	server.Handler().ServeHTTP(response, request)
	return response
}
