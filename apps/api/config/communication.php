<?php

return [
    'enabled' => filter_var(env('COMMUNICATION_ENABLED', false), FILTER_VALIDATE_BOOL),
    'gateway' => [
        'enabled' => filter_var(env('WHATSAPP_GATEWAY_ENABLED', false), FILTER_VALIDATE_BOOL),
        'base_url' => env('WHATSAPP_GATEWAY_URL', 'http://whatsapp-gateway:8080'),
        'timeout_seconds' => (int) env('WHATSAPP_GATEWAY_TIMEOUT_SECONDS', 10),
    ],
    'hmac' => [
        'current_key_id' => env('WHATSAPP_GATEWAY_HMAC_KEY_ID', ''),
        'current_secret' => env('WHATSAPP_GATEWAY_HMAC_SECRET', ''),
        'previous_key_id' => env('WHATSAPP_GATEWAY_HMAC_PREVIOUS_KEY_ID', ''),
        'previous_secret' => env('WHATSAPP_GATEWAY_HMAC_PREVIOUS_SECRET', ''),
        'window_seconds' => (int) env('WHATSAPP_GATEWAY_HMAC_WINDOW_SECONDS', 300),
        'nonce_ttl_seconds' => (int) env('WHATSAPP_GATEWAY_HMAC_NONCE_TTL_SECONDS', 600),
    ],
    'media' => [
        'max_bytes' => (int) env('COMMUNICATION_MEDIA_MAX_BYTES', 20_971_520),
        'disk_root' => env('COMMUNICATION_MEDIA_DISK_ROOT', '/var/vault/communication'),
    ],
];
