# CONTEXT — Hub fiscal multi-escritório

Vocabulário e decisões estáveis do domínio. Regras operacionais para agentes: `AGENTS.md`. Detalhes em `openspec/` e ADRs em `docs/adr/`.

## Produto

- SaaS multi-escritório para **escritórios contábeis** (não é portal do contribuinte final).
- Captura de DF-e: NFS-e ADN, NF-e DistDFe, CT-e, autXML, import XML/ZIP, canais regionais (quando liberados por flag).
- Monitoramento fiscal via Integra Contador / SERPRO (contrato global da software house).
- Fora do escopo: emissão/cancelamento, DANFSe como produto, scraping de portal, APIs municipais genéricas, sublicença de credenciais SERPRO/PFX.

## Atores e perfis

| Perfil | Papel |
|--------|--------|
| `ADMIN` | Administração do escritório, certificados, usuários, cofre (com 2FA quando exigido) |
| `OPERATOR` | Cadastros operacionais, sincronização, import, exportações |
| `VIEWER` | Consulta e download conforme policy |
| `PLATFORM_ADMIN` | Operação da plataforma (memberships, catálogo); **não** herda conteúdo fiscal de tenants |

## Tenancy

- Toda tabela de negócio de tenant tem `office_id`.
- Escritório ativo vem da **membership autenticada**, nunca de `office_id` livre no body/query.
- Mesmo CNPJ pode existir em escritórios distintos; jobs/locks/exports **nunca** misturam tenants.
- Usuário pode ter várias memberships; troca de tenant é explícita.

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

## SaaS multi-escritório e Integra Contador

### Escritório cliente
Tenant comercial e de segurança da plataforma: o escritório contábil assinante que opera o painel e isola seus contribuintes por `office_id`.
_Avoid_: tenant genérico, customer, conta, firm (sem qualificar), "cliente da plataforma" (ambíguo com contribuinte)

### Operação de domínio
Identidade estável e interna de uma capacidade Integra Contador no produto (`operation_key`), independente de códigos SERPRO mutáveis.
_Avoid_: serviço SERPRO, endpoint, rota, idServico (como identidade de domínio)

### Coordenadas SERPRO
Tupla oficial versionada de transporte: rota funcional (`/Apoiar`, `/Consultar`, `/Declarar`, `/Emitir`, `/Monitorar`), `idSistema`, `idServico`, `versaoSistema` e poder e-CAC quando aplicável.
_Avoid_: operation_key, código interno, solution/service/operation legados como fonte de verdade de fio

### Proveniência fiscal
Origem verificável de runs, evidências e snapshots: `SIMULATED`, `SERPRO_REAL` ou `UNVERIFIED`. Definida pelo driver no início da execução; nunca promovida por payload ou frontend.
_Avoid_: data_origin de demo UI como prova SERPRO, "fonte real" sem estado de verificação, origem inferida do body
