package config

import (
	"encoding/base64"
	"errors"
	"fmt"
	"os"
	"strconv"
	"strings"
	"time"
)

type Config struct {
	Enabled          bool
	HTTPAddress      string
	DatabaseURL      string
	LaravelEventsURL string
	LaravelMediaURL  string
	CurrentKeyID     string
	CurrentSecret    string
	PreviousKeyID    string
	PreviousSecret   string
	HMACWindow       time.Duration
	NonceTTL         time.Duration
	DataKey          []byte
	MaxBodyBytes     int64
	MaxMediaBytes    int64
	ReplicaID        string
	SessionCapacity  int
	LeaseTTL         time.Duration
	HeartbeatEvery   time.Duration
	SpoolDirectory   string
	WAConnectTimeout time.Duration
	WAReadyTimeout   time.Duration
	WAHTTPTimeout    time.Duration
	WAProxyURL       string
	WARetryHandlers  int64
}

func Load() (Config, error) {
	cfg := Config{
		Enabled:          envBool("WHATSAPP_GATEWAY_ENABLED", false),
		HTTPAddress:      env("WHATSAPP_GATEWAY_HTTP_ADDRESS", ":8080"),
		DatabaseURL:      strings.TrimSpace(os.Getenv("WHATSAPP_GATEWAY_DATABASE_URL")),
		LaravelEventsURL: strings.TrimSpace(os.Getenv("WHATSAPP_GATEWAY_EVENTS_URL")),
		LaravelMediaURL:  strings.TrimSpace(os.Getenv("WHATSAPP_GATEWAY_MEDIA_URL")),
		CurrentKeyID:     strings.TrimSpace(os.Getenv("WHATSAPP_GATEWAY_HMAC_KEY_ID")),
		CurrentSecret:    os.Getenv("WHATSAPP_GATEWAY_HMAC_SECRET"),
		PreviousKeyID:    strings.TrimSpace(os.Getenv("WHATSAPP_GATEWAY_HMAC_PREVIOUS_KEY_ID")),
		PreviousSecret:   os.Getenv("WHATSAPP_GATEWAY_HMAC_PREVIOUS_SECRET"),
		HMACWindow:       envDuration("WHATSAPP_GATEWAY_HMAC_WINDOW", 5*time.Minute),
		NonceTTL:         envDuration("WHATSAPP_GATEWAY_NONCE_TTL", 10*time.Minute),
		MaxBodyBytes:     envInt64("WHATSAPP_GATEWAY_MAX_BODY_BYTES", 1<<20),
		MaxMediaBytes:    envInt64("WHATSAPP_GATEWAY_MEDIA_MAX_BYTES", 20<<20),
		ReplicaID:        env("WHATSAPP_GATEWAY_REPLICA_ID", hostname()),
		SessionCapacity:  envInt("WHATSAPP_GATEWAY_SESSION_CAPACITY", 250),
		LeaseTTL:         envDuration("WHATSAPP_GATEWAY_LEASE_TTL", 30*time.Second),
		HeartbeatEvery:   envDuration("WHATSAPP_GATEWAY_HEARTBEAT_EVERY", 10*time.Second),
		SpoolDirectory:   env("WHATSAPP_GATEWAY_SPOOL_DIR", "/var/lib/whatsapp-gateway/spool"),
		WAConnectTimeout: envDuration("WHATSAPP_GATEWAY_WA_CONNECT_TIMEOUT", 20*time.Second),
		WAReadyTimeout:   envDuration("WHATSAPP_GATEWAY_WA_READY_TIMEOUT", 30*time.Second),
		WAHTTPTimeout:    envDuration("WHATSAPP_GATEWAY_WA_HTTP_TIMEOUT", 45*time.Second),
		WAProxyURL:       strings.TrimSpace(os.Getenv("WHATSAPP_GATEWAY_WA_PROXY_URL")),
		WARetryHandlers:  envInt64("WHATSAPP_GATEWAY_WA_RETRY_HANDLERS", 4),
	}

	if raw := strings.TrimSpace(os.Getenv("WHATSAPP_GATEWAY_DATA_KEY")); raw != "" {
		decoded, err := base64.StdEncoding.DecodeString(raw)
		if err != nil || len(decoded) != 32 {
			return Config{}, errors.New("WHATSAPP_GATEWAY_DATA_KEY must be 32 bytes encoded as base64")
		}
		cfg.DataKey = decoded
	}

	if cfg.Enabled {
		if cfg.DatabaseURL == "" || cfg.CurrentKeyID == "" || cfg.CurrentSecret == "" || len(cfg.DataKey) != 32 {
			return Config{}, errors.New("enabled gateway requires database, current HMAC key and 32-byte data key")
		}
		if cfg.LaravelEventsURL == "" {
			return Config{}, errors.New("enabled gateway requires Laravel events URL")
		}
		if cfg.LaravelMediaURL == "" {
			return Config{}, errors.New("enabled gateway requires Laravel media URL")
		}
	}
	if cfg.SessionCapacity < 1 || cfg.HMACWindow <= 0 || cfg.NonceTTL < cfg.HMACWindow {
		return Config{}, errors.New("invalid capacity or HMAC timing configuration")
	}
	if cfg.WAConnectTimeout <= 0 || cfg.WAConnectTimeout > 2*time.Minute ||
		cfg.WAReadyTimeout <= 0 || cfg.WAReadyTimeout > 2*time.Minute ||
		cfg.WAHTTPTimeout <= 0 || cfg.WAHTTPTimeout > 5*time.Minute ||
		cfg.WARetryHandlers < 1 || cfg.WARetryHandlers > 32 {
		return Config{}, errors.New("invalid WhatsApp runtime limits")
	}

	return cfg, nil
}

func env(name, fallback string) string {
	if value := strings.TrimSpace(os.Getenv(name)); value != "" {
		return value
	}
	return fallback
}

func envBool(name string, fallback bool) bool {
	value := strings.TrimSpace(os.Getenv(name))
	if value == "" {
		return fallback
	}
	parsed, err := strconv.ParseBool(value)
	return err == nil && parsed
}

func envInt(name string, fallback int) int {
	value, err := strconv.Atoi(strings.TrimSpace(os.Getenv(name)))
	if err != nil {
		return fallback
	}
	return value
}

func envInt64(name string, fallback int64) int64 {
	value, err := strconv.ParseInt(strings.TrimSpace(os.Getenv(name)), 10, 64)
	if err != nil {
		return fallback
	}
	return value
}

func envDuration(name string, fallback time.Duration) time.Duration {
	value := strings.TrimSpace(os.Getenv(name))
	if value == "" {
		return fallback
	}
	parsed, err := time.ParseDuration(value)
	if err != nil {
		return fallback
	}
	return parsed
}

func hostname() string {
	name, err := os.Hostname()
	if err != nil || strings.TrimSpace(name) == "" {
		return fmt.Sprintf("gateway-%d", os.Getpid())
	}
	return name
}
