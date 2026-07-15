# Matriz de habilitação — SVRS NFC-e XML retrieval

**Change:** `add-svrs-nfce-outbound-xml-retrieval` · task 1.4  
**Defaults:** todos os gates **desligados** até decisão operacional registrada.

## Dimensões

| Dimensão | Controle | Default | Quem altera |
|----------|----------|---------|------------|
| Instância | `SEFAZ_SVRS_NFCE_XML_RETRIEVAL_ENABLED` | `false` | Ops (env/config) |
| Instância | `SEFAZ_SVRS_NFCE_XML_AUTO_QUEUE_ENABLED` | `false` | Ops |
| Instância | `SEFAZ_SVRS_NFCE_XML_PILOT_ALLOWLIST_ONLY` | `false`* | Ops |
| Instância | Kill switch runtime / env | off | ADMIN+2FA / env |
| Escritório | Tenancy (`office_id` do servidor) | — | Nunca do cliente API |
| Raiz (cliente) | A1 disponível + allowlist de perfil | — | ADMIN+2FA |
| Estabelecimento | UF MA, perfil outbound NFC-e 65 | — | Cadastro + perfil |
| Ambiente | `production` \| `homologation` no perfil | — | Perfil |
| Perfil | `ACTIVE`, allowlisted (se pilot-only), não kill-switched | — | ADMIN+2FA |
| Modelo/UF | Somente modelo `65` e `cUF=21` na chave | — | Código (hard) |

\* Flag existe e default off; quando `true`, apenas perfis allowlisted disparam recovery.

## Matriz de decisão (chamada remota GET/POST)

Uma chamada SVRS só ocorre se **todas** forem verdadeiras:

1. Master `retrieval_enabled = true`
2. Kill switch SVRS **inativo**
3. Breaker global **fechado** (ou half-open com chave allowlisted de prova)
4. Breaker da raiz **fechado** (idem)
5. Número em `KEY_DISCOVERED` ou `XML_PENDING` com chave válida 44
6. Chave com `cUF=21`, modelo `65`, direção OUT
7. Perfil `ACTIVE`, estabelecimento MA, ambiente conhecido
8. Se `pilot_allowlist_only`: perfil `allowlisted = true`
9. A1 da raiz materializável (referência vault, sem export)
10. Rate limit global/raiz disponíveis
11. Origem da ação: job auto-queue (exige flag auto-queue) **ou** OPERATOR/ADMIN enfileirando manualmente

## Auto-queue vs manual

| Ação | Master | Auto-queue | Allowlist (se pilot-only) | Papel |
|------|--------|------------|---------------------------|-------|
| Smoke/manual de uma chave | on | off OK | perfil allowlisted se pilot-only | OPERATOR/ADMIN |
| Scheduler enfileira | on | **on** | idem | sistema |
| Fallback upload XML/ZIP | irrelevante | irrelevante | irrelevante | OPERATOR/ADMIN |

## Ambientes

| Ambiente app | Ambiente fiscal no perfil | Host SVRS (config tipada) |
|--------------|---------------------------|---------------------------|
| local/CI | homologation ou production | hosts allowlisted em config; CI **nunca** usa A1 real |
| staging/piloto | production (típico) | `dfe-portal.svrs.rs.gov.br` |
| produção | production | idem; flags e allowlist controlam blast radius |

## Proibições (qualquer dimensão)

- URL/host/header/cookie/certificado fornecidos pelo cliente da API
- NF-e modelo 55 por este canal
- `cUF` ≠ 21
- RPA / Selenium / execução de JavaScript remoto
- Persistência de PFX, senha, PEM ou cookie
