<?php

return [
    'master_key' => env('VAULT_MASTER_KEY'),
    'master_key_version' => (int) env('VAULT_MASTER_KEY_VERSION', 1),
    'disk_root' => env('VAULT_DISK_ROOT', storage_path('app/vault')),
];
