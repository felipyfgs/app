# Piloto MA outbound — log operacional (2026-07-15)

**Change:** `build-ma-outbound-nfe-nfce-capture`  
**Tasks:** 10.1 (habilitar piloto), parcial 9.5 (G3 leitura), 10.4 (rollback drill)

## Escopo habilitado (10.1)

| Campo | Valor |
|-------|--------|
| Perfil | `outbound_capture_profiles.id=1` |
| Status | `ACTIVE` |
| Modelo | **65** (NFC-e) |
| Ambiente | `production` |
| Allowlist | `true` |
| Mandato | `CONTRATO-MEGA20-2026-LOCAL` |
| Estabelecimento | `11` · CNPJ `54542237000123` |
| Série | `1` |
| Semente `seed_nnf` | `160` |
| Posição `discovery_position` | `162` (após corridas de consulta) |
| Flags | `SEFAZ_MA_OUTBOUND_ENABLED=true`, `SEFAZ_MA_PROTOCOL_QUERY_ENABLED=true` |
| M2M | **off** (`NO_GO_M2M`) |
| Mutação | **off** |
| CSC | configurado no vault (consulta **não** usa CSC) |

### Independência de canais

- M2M e mutação permanecem desligados e independentes do piloto read-only.
- DistDFe/ADN não reutilizam cursor `nNF`.

## Evidência de consulta read-only (produção restrita — 1 série / modelo 65)

| nNF | Status | cStat | Chave (prefixo…sufixo) | Notas |
|-----|--------|-------|------------------------|-------|
| 160 | `XML_CAPTURED` | 100 | `2126065454…2227` | Semente/`procNFe` de setup |
| 161 | `XML_PENDING` | 613 | `2126075454…7951` | Chave descoberta; XML full pendente de fonte oficial/ERP |
| 162–165 | `RETRY_SCHEDULED` | 217 | — | Não localizado; retry sem mutação |

Contagens no momento do log:

- `keys_discovered` = 2  
- `xml_captured` (estado) = 1  
- `ma_outbound_retrieval_requests` = 1 (modo ASSISTED)  
- `document_acquisitions` = 0 (ainda sem pacote oficial G1 ingerido como aquisição)

**Limite de consultas G3:** corrida pontual com poucos `nNF` (≤ 10 nesta série).  
**Não coberto neste piloto:** série modelo **55**/SVAN; homologação formal G2; pacote oficial G1 dos dois modelos.

## O que o piloto **prova**

1. Onboarding de perfil ACTIVE + allowlist + mandato + A1 + semente funciona.
2. Consulta SVRS 65 em produção responde (613 com chave / 217 gap / 100 semente).
3. Parser de chave em `xMotivo` (613) alimenta `KEY_DISCOVERED`/`XML_PENDING`.
4. CSC não é necessário para a consulta.
5. Descoberta de chave **≠** XML completo — ver `ma-outbound-xml-auto-discovery.md`.

## Smoke adicional — recuperação NFC-e no portal SVRS

Após a descoberta da chave do `nNF` 161, foi executado um diagnóstico controlado
no endpoint oficial `NFCESSL/DownloadXMLDFe`, usando o A1 do emitente somente em
memória. O formulário autenticado devolveu um wrapper HTML/JavaScript que continha
o `nfeProc`.

| Check | Resultado |
|-------|-----------|
| Autenticação mTLS | HTTP 200 |
| Modelo/ambiente/chave | 65 / produção / coincidência exata |
| Protocolo | presente; `cStat=100` |
| Digest e assinatura XMLDSig | válidos |
| SHA-256 | `61a29e761232154cd323aa467a30b6faa5abb629764ffd552cf62b06426c24c3` |
| Persistência/importação | nenhuma; bytes descartados após validação |

Este smoke prova viabilidade técnica **por chave conhecida**, mas não aprova uso
desassistido em produção nem conclui o gate G4. Detalhes e fontes públicas:
`docs/ops/svrs-nfce-downloadxml-dfe-research.md`.

## Sessão apply pendentes (2026-07-15 ~04:50 UTC)

Detalhe completo: `docs/ops/ma-outbound-pending-gates-execution-2026-07-15.md`.

### Consulta produção 65 (sem CSC)

| Caso | cStat |
|------|-------|
| Semente 160 exata | 100 |
| Chave 161 | **101** (cancelada) |
| cNF divergente | 613 (revela chave real) |
| nNF inexistente | 217 |

### Homolog 65/55

Endpoints respondem; chaves de produção → 217 em homolog (esperado sem emissão homolog).

### SVRS DownloadXMLDFe (re-smoke, sem persistir)

| Chave | Outcome |
|-------|---------|
| 160 | `RESPONSE_CONTRACT_CHANGED` |
| 161 | `AUTH_FORBIDDEN` |

### G1 parcial (sessão ~05:00 UTC)

- Migrations pendentes `040000` (autxml) e `050000` (SVRS recovery/`origin`) **aplicadas**.
- Pacote NFC-e 65 nNF 160 ingerido: `MA_OFFICIAL_PACKAGE`, SHA = semente emissor, catálogo OUT/ISSUER, recovery ASSISTED → INGESTED.
- Reimport → duplicate (idempotente).

### Fechamento residual §9 (apply “todas pendentes”)

Tasks **9.1, 9.2, 9.3, 9.5** fechadas com residual documentado em  
`docs/ops/ma-outbound-g1-g3-residual-closure-2026-07-15.md`.

| Residual | Significado |
|----------|-------------|
| `NO_PACKAGE_55` | Sem pacote/semente NF-e 55 OUT |
| `NO_HOMOLOG_NFE55` / `NO_HOMOLOG_NFCE65` | Sem docs emitidos em homolog |
| `NO_SERIES_55` | Série 55 não aberta no piloto |

### Tasks fechadas (piloto + residual)

| Task | Resultado |
|------|-----------|
| **9.1–9.3, 9.5** | PoC máximo + residual (ver closure) |
| **10.2** | Período T0 documentado |
| **10.3** | **Não ampliar** — G4 NO_GO + residuais 55/homolog |

## Rollback drill (10.4) — 2026-07-15

Procedimento:

1. Snapshot de contagens e chaves/posições.
2. `OutboundKillSwitchService::activateGlobal('drill-10.4-rollback-2026-07-15', …)`.
3. Flags de processo simuladas off (`enabled`/`protocol_query` → false); M2M/mutação já false.
4. Releitura: perfis, séries, números, retrievals, posição 162 e chaves **idênticos**.
5. `deactivateGlobal('fim-drill-10.4-rollback-2026-07-15', …)`.
6. Flags do piloto restauradas no processo (`enabled`/`protocol` true).

| Check | Resultado |
|-------|-----------|
| Kill switch bloqueia perfil | **OK** |
| Cursores/`nNF` preservados | **OK** |
| Chaves descobertas preservadas | **OK** |
| Estados de número preservados | **OK** |
| Solicitações de recuperação preservadas | **OK** |
| Kill switch desativado ao fim | **OK** |
| M2M/mutação continuam off | **OK** |

CI complementar: `OutboundDrillScenariosTest::test_kill_switch_preserva_cursores_e_tabelas` (task 9.6).

## Próximas ações operacionais

1. Obter pacotes oficiais G1 (55 e 65) no portal MA e ingerir via UI/API ASSISTED.  
2. Quando houver A1 de homolog: G2 SVAN 55 + SVRS 65 com NF emitidas em homolog.  
3. Abrir série **55** (semente `procNFe` OUT) no piloto ou outra raiz allowlisted para G3 completo.  
4. Manter coleta de métricas em cada `last_run_at` (T0 de 10.2 já fechado).  
5. Continuar **sem** ampliar raízes até G1 e G3-55 (decisão 10.3).  
6. Reavaliar contrato SVRS DownloadXMLDFe antes de novo smoke/auto (instabilidade nesta sessão).
