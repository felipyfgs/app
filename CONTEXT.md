# CONTEXT — NFS-e ADN Capture

Vocabulário e decisões estáveis do domínio. Detalhes de implementação em `openspec/` e ADRs em `docs/adr/`.

## Produto

- Sistema **interno do escritório contábil** (não é portal do cliente final).
- Objetivo: capturar e organizar XMLs de NFS-e via **ADN** (Ambiente de Dados Nacional), autenticado com certificado **e-CNPJ A1**.
- Escopo MVP: distribuição ADN, consulta, download e exportação ZIP. Fora: emissão/cancelamento, DANFSe, scraping de portal, APIs municipais.

## Atores e perfis

| Perfil | Papel |
|--------|--------|
| `ADMIN` | Administração, certificados, usuários, bootstrap |
| `OPERATOR` | Cadastros operacionais, sincronização manual, exportações |
| `VIEWER` | Consulta e download conforme policy |

## Tenancy

- Toda tabela de negócio tem `office_id`.
- Escritório ativo vem da **associação autenticada**, nunca de `office_id` livre no body/query.
- MVP cria um escritório; o schema já isola multi-escritório.

## CNPJ

- Texto de **14 caracteres**, numérico **ou** alfanumérico.
- Armazenar **maiúsculo**, sem máscara; **nunca** como número.
- Um certificado A1 por **raiz** (8 primeiros caracteres do CNPJ).
- Estabelecimentos (matriz/filial) compartilham o A1 da raiz do cliente.

## ADN e NSU

- Distribuição por **NSU**, não por data.
- Cursor por **estabelecimento + ambiente**, início em `0`.
- Fluxo: persistir página completa → só então avançar NSU.
- Falha Base64/GZip: **não** avança; bloqueio após 5 falhas consecutivas de decodificação.
- Job: máx. 20 páginas e reenfileira; lock por estabelecimento; ~4 reqs concorrentes; rate limit ~4 rps.
- Ciclo horário com espalhamento determinístico no Scheduler.

## Documentos fiscais

| Entidade | Função |
|----------|--------|
| `dfe_documents` | Documento original imutável (bytes + SHA-256) |
| `document_interests` | NSU/papel por estabelecimento |
| `nfse_notes` / `nfse_events` | Projeções consultáveis |

- Papéis: emitente, tomador, intermediário.
- XSD/versão desconhecida: alerta de parse, XML bem-formado é mantido; cursor não bloqueia por isso.

## Segurança

- Envelope crypto (`SecureObjectStore`); `VAULT_MASTER_KEY` fora do DB e de backups comuns.
- PFX só em memória (libcurl BLOB); TLS ≥ 1.2 + verificação de hostname.
- **Nunca** expor PFX, senha, chave privada ou PEM via API, logs ou exportação.
- Sem rota de recuperação de certificado.
- Auth: Fortify + Sanctum cookie same-origin + CSRF + TOTP.

## Stack

| Camada | Escolha |
|--------|---------|
| Backend | Laravel 13 / PHP 8.4 em `backend/` |
| Frontend | Nuxt 4 / Nuxt UI 4 SPA em `frontend/` |
| Edge | Nginx same-origin (SPA + PHP-FPM) |
| Dados | PostgreSQL 17 (verdade), Redis 8 / Horizon (filas) |
| Ops | Docker Compose; Scheduler (não filas em DB) |

## ADRs relacionados

- `docs/adr/001-adn-api-client.md`
- `docs/adr/002-same-origin-architecture.md`
- `docs/adr/003-secure-object-vault.md`
