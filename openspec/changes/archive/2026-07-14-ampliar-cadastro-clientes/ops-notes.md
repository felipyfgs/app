# Notas operacionais — ampliar-cadastro-clientes

## 7.1 Backup e ponto de retorno

Antes de aplicar a migração `2026_07_14_120000_expand_client_registration_schema` em ambiente com dados reais:

```bash
# Pré-migração (somente leitura)
docker compose exec php php artisan clients:preflight-registration-expand --json --fail-on-issues

# Backup PostgreSQL (exemplo)
docker compose exec -T postgres pg_dump -U nfse nfse > "backup-pre-ampliar-cadastro-$(date -u +%Y%m%dT%H%M%SZ).sql"
```

Após novos cadastros nos campos ampliado, **não** usar `migrate:rollback` destrutivo. Reversão = restaurar o dump ou manter código compatível com as colunas extras.

## 7.2 Seed sintético local

```bash
docker compose exec php php artisan migrate --force
docker compose exec php php artisan db:seed --class=DemoCatalogSeeder
```

Fluxo esperado: Cliente (legal_name + LEGACY) → estabelecimentos com `capture_enabled` → contatos (API) → A1 → sync.

## 7.3–7.5 Piloto

1. Poucas raízes numéricas: lookup CNPJ.ws, comparar prévia com RFB, validar rate limit (3/min) e cache 24h.
2. Um CNPJ alfanumérico: somente manual; API de lookup deve recusar com mensagem sanitizada.
3. Monitorar: falhas de lookup (503), conflitos 409 de raiz, entidades inelegíveis no scheduler, NSU estável.

## Arquétipos de UI (6.5)

Fontes fixadas em `.reference/nuxt-dashboard-template` @ `0f30c09`:

- `app/components/customers/AddModal.vue` → `ClientCreateModal.vue`
- `app/pages/settings.vue` → shell de `/clients/[id]`
- `app/pages/settings/index.vue` → cards da seção Cadastro (`ClientRegistration.vue`)
