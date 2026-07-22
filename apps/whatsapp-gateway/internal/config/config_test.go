package config

import (
	"encoding/base64"
	"strings"
	"testing"
)

func TestLoadIsFailClosedByDefault(t *testing.T) {
	t.Setenv("WHATSAPP_GATEWAY_ENABLED", "")
	t.Setenv("WHATSAPP_GATEWAY_DATABASE_URL", "")
	t.Setenv("WHATSAPP_GATEWAY_HMAC_KEY_ID", "")
	t.Setenv("WHATSAPP_GATEWAY_HMAC_SECRET", "")
	t.Setenv("WHATSAPP_GATEWAY_DATA_KEY", "")

	cfg, err := Load()
	if err != nil {
		t.Fatalf("load disabled defaults: %v", err)
	}
	if cfg.Enabled {
		t.Fatal("gateway must default to disabled")
	}
}

func TestLoadRejectsEnabledGatewayWithoutSecrets(t *testing.T) {
	t.Setenv("WHATSAPP_GATEWAY_ENABLED", "true")
	t.Setenv("WHATSAPP_GATEWAY_DATABASE_URL", "")
	t.Setenv("WHATSAPP_GATEWAY_HMAC_KEY_ID", "")
	t.Setenv("WHATSAPP_GATEWAY_HMAC_SECRET", "")
	t.Setenv("WHATSAPP_GATEWAY_DATA_KEY", "")

	if _, err := Load(); err == nil {
		t.Fatal("expected enabled configuration to fail closed")
	}
}

func TestLoadAcceptsCompleteEnabledConfiguration(t *testing.T) {
	t.Setenv("WHATSAPP_GATEWAY_ENABLED", "true")
	t.Setenv("WHATSAPP_GATEWAY_DATABASE_URL", "postgres://gateway@postgres/nfse")
	t.Setenv("WHATSAPP_GATEWAY_EVENTS_URL", "http://php/api/internal/v1/whatsapp/events")
	t.Setenv("WHATSAPP_GATEWAY_MEDIA_URL", "http://php/api/internal/v1/communication/gateway/media")
	t.Setenv("WHATSAPP_GATEWAY_HMAC_KEY_ID", "gateway-v1")
	t.Setenv("WHATSAPP_GATEWAY_HMAC_SECRET", strings.Repeat("s", 32))
	t.Setenv("WHATSAPP_GATEWAY_DATA_KEY", base64.StdEncoding.EncodeToString([]byte(strings.Repeat("k", 32))))

	cfg, err := Load()
	if err != nil {
		t.Fatalf("load enabled config: %v", err)
	}
	if !cfg.Enabled || len(cfg.DataKey) != 32 {
		t.Fatalf("unexpected config: enabled=%v key_length=%d", cfg.Enabled, len(cfg.DataKey))
	}
}
