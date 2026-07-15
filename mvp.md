# MVP — Captura de XML (NFC-e / NF-e saídas e contexto)

Documento de produto/técnico a partir do explore (2026-07).  
Alinha OpenSpec, canais oficiais SEFAZ e o que o monorepo já prevê.

---

## Objetivo do MVP

O escritório contábil precisa dos **XML de guarda** dos clientes, classificados por **entrada / saída**, sem depender só de e-mail manual.

| Kind | Entrada (IN) | Saída (OUT) |
|------|--------------|-------------|
| **NFS-e** | ADN (tomador) — já no desenho | ADN (prestador) — já no desenho |
| **NF-e 55** | DistDFe + ciência (full) — já no desenho | **Import** (universal) |
| **NFC-e 65** | Raro / não foco | **Import** (universal) + **SAE-SP** (só SP, automático) |

**Não-objetivo do MVP:** emitir nota, scraping de portal, paridade Hubstrom multi-UF (23 estados), CSC como canal de captura.

---

## Princípios (não negociar)

1. **Captura ≠ emissão** — só armazenar XML já autorizado.
2. **Cliente próprio mTLS** (PFX só em memória) — padrão ADN/DistDFe; sem lib comunitária de emissão como transporte de produção.
3. **Vault imutável** — bytes originais + SHA-256; idempotência por hash / chave de acesso.
4. **Tenancy** — `office_id` do contexto; nunca confiar office vindo do client.
5. **Segredos** — nunca expor PFX, senha, chave privada via API/logs.
6. **Feature flags default off** — smoke restrito antes de piloto amplo.
7. **Um dono de DistDFe por A1** — evitar 656 por consumo indevido.

---

## Canais oficiais (verdade fiscal)

### O que funciona

| Canal | O que entrega | Papel no MVP |
|-------|---------------|--------------|
| **ADN NFS-e** | Prestador / tomador / intermediário | Já (entrada + saída serviço) |
| **NFeDistribuicaoDFe** | Docs de **interesse** (não gerados pelo consultante) | Entradas NF-e 55 |
| **Ciência do destinatário** | Libera `procNFe` full na entrada | Unlock de entrega, não política fiscal |
| **Import XML/ZIP** | Qualquer UF, 55 e 65 de saída | **Primário de saídas** |
| **SAE-SP NFC-e** | Listagem de chaves + download XML do **emitente** (SP) | **Automático NFC-e saída só SP** |

### O que **não** funciona (e não prometer)

| Mito | Realidade |
|------|-----------|
| DistDFe devolve a própria NF-e ao emitente | **Não** (MOC / rejeição 641) |
| CSC + série “puxa tudo da SEFAZ” | CSC = emissão/QR; não listagem nacional |
| Lib open source tipo Hubstrom | **Não existe** no GitHub |
| Um WS nacional de saídas NFC-e | **Não**; é **por UF** |
| Consulta pública / QR = XML de guarda | Muitas vezes HTML/resumo ou XML remontado **sem** assinatura original |

### Maranhão (MA) — captura de saídas (change ativa)

OpenSpec: `build-ma-outbound-nfe-nfce-capture`.

| Peça | Comportamento |
|------|----------------|
| Posição | **nNF** por série (nunca `last_nsu`) |
| Pacote oficial | Modo **ASSISTED** (upload ZIP/XML) — primário |
| Consulta | `NFeConsultaProtocolo` + cStat **562** (SVAN/55, SVRS/65) — flag off por default |
| M2M | **`NO_GO_M2M`** até contrato formal SEFAZ-MA |
| CSC | Só cofre + fallback mutante modelo **65**; **não** para consulta/download |
| 539 / inutilização | Saga isolada, flag mutante **off**, gates G5 |
| RPA | **Proibido** (Gov.br, SEFAZNET, CAPTCHA, portal) |

Flags: `SEFAZ_MA_OUTBOUND_*` em `backend/config/sefaz.php` · runbooks em `docs/ops/ma-outbound-*.md`.

---

## Escopo MVP (entregas)

### P0 — Já coberto ou obrigatório para fechar o ciclo contábil

| # | Entrega | Status no monorepo |
|---|---------|-------------------|
| 1 | DistDFe NF-e entradas + cursor NSU + rate limit | Spec/código em andamento |
| 2 | Ciência técnica para full XML de entrada | Spec (`nfe-xml-unlock-via-ciencia`) |
| 3 | ADN NFS-e (IN/OUT por papel) | Spec/código |
| 4 | Catálogo unificado + `direction` IN/OUT/UNKNOWN | Spec |
| 5 | **Import saídas** NF-e 55 + NFC-e 65 (ZIP/XML) | Spec `outbound-xml-ingestion` |

### P1 — NFC-e saída automática (oficial)

| # | Entrega | Notas |
|---|---------|-------|
| 6 | Canal `NFCE_SAE_SP` | Só estabelecimento **UF=SP** + A1 |
| 7 | Cliente `NfceSaeClient` (listKeys + downloadXml) | SOAP + mTLS próprio |
| 8 | Job Horizon + cursor por **janela de data** (não NSU) | ~100 dias; ≤2000 chaves; cStat 101 → fatiar |
| 9 | Flag `SEFAZ_NFCE_ENABLED` (já existe env) | Default **off** |
| 10 | Projeção `kind=NFCE`, `direction=OUT` | Mesmo vault do import |

**URLs de referência SAE-SP (confirmar na NT vigente):**

- Produção listagem: `https://nfce.fazenda.sp.gov.br/ws/NFCeListagemChaves.asmx`
- Produção download: `https://nfce.fazenda.sp.gov.br/ws/NFCeDownloadXML.asmx`
- Portal: https://portal.fazenda.sp.gov.br/servicos/nfce/Paginas/saenfce.aspx  
- NT PDF: https://portal.fazenda.sp.gov.br/servicos/nfce/Documents/SAE-NFC-e%20v1.0.0.pdf  

**CSC não é parâmetro do SAE.**

### P2 — Fora do MVP (backlog)

- Adapters oficiais por UF (MA, SVRS, etc.) com contrato documentado  
- `autXML` do escritório para NF-e 55 de saída via DistDFe do contador  
- Multi-UF estilo Hubstrom (semente + CSC)  
- Scraping / RPA de portais  
- Emissão de NFC-e/NF-e pelo painel  

---

## Arquitetura alvo (saídas NFC-e)

```
                    e-CNPJ A1 (vault / mTLS)
                              │
          ┌───────────────────┼───────────────────┐
          ▼                   ▼                   ▼
   DistDFe NFE          SAE-SP NFC-e           IMPORT
   (entradas)           (saídas SP)            (saídas qualquer UF)
          │                   │                   │
          └───────────────────┼───────────────────┘
                              ▼
                 dfe_documents (SHA-256, imutável)
                 direction IN | OUT
                 kind NFE | NFCE | NFSE | CTE …
                              ▼
                 catálogo / export ZIP entrada|saida
```

### Peças de código (quando implementar)

| Peça | Sugestão |
|------|----------|
| Enum | `CaptureChannel::NfceSaeSp = 'NFCE_SAE_SP'` |
| Interface | `NfceSaeClient` |
| Impl | `HttpNfceSaeSpClient` + parser + fixtures homolog |
| Job | `SyncNfceSaeSpJob` / fila `sync-sefaz-nfce` |
| Config | bloco `sefaz.nfce` em `config/sefaz.php` |
| Reuso | `CurlMtlsTransport`, vault, processors de projeção, inbox ops |

---

## Fluxo SAE-SP (resumo operacional)

1. Elegível: flag on + UF SP + A1 ativo + lease/lock.  
2. Cursor: janela `dhIni`–`dhFim` (máx. ~100 dias; fatiar se volume).  
3. `NFCeListagemChaves` → lista de chaves (até 2000).  
4. Se `cStat=101` (lista incompleta): usar `dhEmisUltNfce` e reconsultar fatia.  
5. Para cada chave: `NFCeDownloadXML` com rate limit (IP).  
6. Persistir XML + eventos; só então avançar cursor.  
7. Falha de decode/persistência: **não** avançar janela (mesma regra ADN).  
8. 656 / consumo indevido: backoff + inbox.

---

## Import de saídas (universal)

- UI/API: OPERATOR/ADMIN envia XML ou ZIP.  
- Aceita `procNFe` / NFC-e 65; `direction=OUT` se emitente = estabelecimento do cliente.  
- Idempotente por SHA-256.  
- VIEWER → 403.  
- **Não** chama SEFAZ de autorização.

Cobre **MA e qualquer UF** sem adapter estadual.

---

## Critérios de aceite do MVP

- [ ] Cliente piloto com A1 no vault; PFX nunca em disco/log  
- [ ] Entradas NF-e aparecem no catálogo (DistDFe; full após ciência quando aplicável)  
- [ ] Saídas NF-e/NFC-e entram via **import** e ficam com `direction=OUT`  
- [ ] (SP) Com flag on, job SAE baixa NFC-e faltantes da janela e grava XML original validável  
- [ ] Export/listagem filtra entrada × saída  
- [ ] Duplicata import + SAE não gera dois vaults do mesmo SHA  
- [ ] Ops: 656/137/falha TLS geram item de inbox sanitizado  
- [ ] Homolog/smoke restrito; sem cert de produção em CI  

---

## Ordem de implementação sugerida

```
1. Garantir import saídas (P0) se ainda incompleto na UI/API
2. Spike SAE-SP: WSDL/NT + 1 listagem + 1 download em homolog
3. Cliente + parser + testes com fixtures
4. Cursor + job + flag + elegibilidade UF=SP
5. Projeção NFCE OUT + catálogo + export
6. Piloto 1–3 raízes SP (ou import-only se piloto for MA)
```

OpenSpec: criar change (ex. `capture-nfce-sae-sp`) com `proposal` → `design` → `specs` → `tasks` antes de codar em massa (`/opsx-propose` ou `/opsx-apply` quando existir change).

---

## Referências rápidas

| Tema | Link / local |
|------|----------------|
| DistDFe MOC | http://moc.sped.fazenda.pr.gov.br/NFeDistribuicaoDFe.html |
| SAE-SP portal | https://portal.fazenda.sp.gov.br/servicos/nfce/Paginas/saenfce.aspx |
| SAE-SP NT PDF | https://portal.fazenda.sp.gov.br/servicos/nfce/Documents/SAE-NFC-e%20v1.0.0.pdf |
| Download MA (portal) | https://sistemas1.sefaz.ma.gov.br/download-nfe/ |
| Spec import | `openspec/specs/outbound-xml-ingestion/` |
| Spec DistDFe | `openspec/specs/sefaz-distdfe-sync/` |
| Spec direção | `openspec/specs/fiscal-document-direction/` |
| Config flags | `backend/config/sefaz.php` (`nfce_enabled`, etc.) |
| Domínio / ADR | `AGENTS.md` |

---

## Decisão de produto (uma frase)

**MVP = entradas oficiais (ADN + DistDFe) + saídas por import em qualquer UF + canal MA outbound assistido/consulta 562 (flags off) + NFC-e saída automática só onde há WS oficial (SAE-SP).**  
M2M MA e mutação 539 só após gates G4/G5 documentados — não promessa de release.
