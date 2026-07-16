# 1.8 Checklist automatizado de artefatos sensíveis

**Implementação:** `frontend/tests/security/scan-artifacts.mjs`  
**Comando:** `cd frontend && pnpm test:artifacts`

## Materiais rejeitados

| Material | ID no scanner | Padrão |
|----------|---------------|--------|
| Chave privada PEM | `PRIVATE_KEY_PEM` | `BEGIN … PRIVATE KEY` |
| Certificado PEM em bloco | `CERTIFICATE_PEM_BLOCK` | `BEGIN CERTIFICATE` multilinha |
| PFX / PKCS#12 | `PFX_BINARY_HINT`, `PFX_PASSWORD_FIELD`, nome de arquivo | base64+pfx, `pfx_password` |
| Senha | `PASSWORD_FIELD` | `password`/`senha` com valor alfanumérico longo |
| Consumer Secret | `CONSUMER_SECRET` | `consumer_secret` / `consumer-secret` |
| Token / Bearer | `BEARER_TOKEN`, `OPAQUE_TOKEN` | Authorization Bearer, access/refresh/api token |
| Cookie de sessão | `RAW_COOKIE` | `set-cookie` / `cookie=` com valor |
| Termo XML | `TERMO_XML` | tags/termos de autorização + XML |
| XML fiscal real | `FISCAL_XML_REAL` | `<?xml` + NFe/CTe/NFSe/proc* |
| `vault_object_id` | `VAULT_OBJECT_ID` | campo com valor |
| Caminho de storage | `STORAGE_PATH_SECRET` | `storage_path` |
| Bytes de evidência | `EVIDENCE_BYTES` | `evidence_bytes` |
| Resposta externa bruta | `SERPRO_RAW_RESPONSE` | `serpro_raw` / `raw_response` / `integra_raw` |

## Raízes varridas

- `.output/public` (build SPA)
- `test-results`, `playwright-report`
- `tests/e2e/__screenshots__`
- `tests/e2e/support` e specs e2e
- `tests/unit`

## Gate de CI / entrega

1. Rodar `pnpm test:artifacts` após Playwright e após `pnpm build`.
2. Qualquer exit ≠ 0 **bloqueia** aceite da change (tarefas 12.9 e 13.2).
3. Fixtures sintéticas devem usar placeholders que **não** casem com os regex (ex.: senhas só letras, tokens curtos de demo, sem PEM).

## Checklist manual complementar (screenshots PNG)

- [ ] Nenhum QR de 2FA de produção
- [ ] Nenhum CNPJ/CPF real de cliente de produção
- [ ] Nenhum print de Network com header Cookie/Authorization
- [ ] Overlay de certificado sem senha/arquivo PFX legível
