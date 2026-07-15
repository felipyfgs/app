# Síntese técnica — captura multi-DF-e (levantamento 2026-07-14)

Levantamento orquestrado em 5 frentes: DistDFe SEFAZ, libs PHP, CT-e/MDF-e/NFC-e, manifestação do destinatário, arquitetura multi-canal.  
**Estado:** pesquisa concluída → decisões refinadas no `design.md` e specs.

---

## 1. DistDFe NF-e (Ambiente Nacional)

| Item | Achado |
|------|--------|
| Serviço | `NFeDistribuicaoDFe` / `nfeDistDFeInteresse` |
| Produção | `https://www1.nfe.fazenda.gov.br/NFeDistribuicaoDFe/NFeDistribuicaoDFe.asmx` |
| Homologação | `https://hom.nfe.fazenda.gov.br/NFeDistribuicaoDFe/NFeDistribuicaoDFe.asmx` |
| SOAP | 1.2; payload `distDFeInt` |
| Auth | mTLS A1/A3; PJ pelo **CNPJ base** do cert |
| Contingência | **Não há** SVC para distribuição |
| Lote | até **50** `docZip` (GZip+Base64) |
| Cursor | `ultNSU` → próxima chamada; fim: `ultNSU == maxNSU` ou cStat **137** |
| cStat chave | **138** docs · **137** nenhum (≥1h quiet) · **656** consumo indevido (bloqueio ~1h) |
| Modalidades | `distNSU` (principal) · `consNSU` · `consChNFe` (máx ~20/h — risco 656) |
| Janela | ~**3 meses** no AN; inatividade longa pode parar geração de NSU (~60 dias em NTs) |
| Schema docZip | `resNFe`, `procNFe`, `resEvento`, `procEventoNFe` (versões variam) |
| CNPJ alfa | NT **2014.002 v1.40** — leiaute DistDFe alinha CNPJ alfanumérico (cronograma 2026) |

**Destinatário:** antes da MD-e → sobretudo `resNFe` (+ cancelamento); após Ciência/Confirmação/Op. não realizada → `procNFe`.

**Fontes:** portal NF-e webServices, MOC DistDFe, NT 2014.002, Focus/FlexDocs.

---

## 2. Libs PHP (`sped-nfe` e família)

| Achado | Impacto no monorepo |
|--------|---------------------|
| `Tools::sefazDistDFe($ultNSU, $numNSU, $chave, $fonte)` | Contrato de referência |
| `Certificate::readPfx($bytes, $password)` | PFX em memória **ok** |
| `SoapCurl` grava **PEM em disco** (`sys_get_temp_dir()/sped-…`) | **Proibido** (ADR 001) |
| `SSL_VERIFYPEER` efetivamente frágil / off no path atual | **Proibido** |
| Popularidade alta (~79k downloads/mês, ~1.5k★) | Oráculo de schemas/exemplos |
| RTC + CNPJ alfa no README 2025–2026 | Lib atualizada como referência |
| `sped-cte` / `sped-mdfe` | `sefazDistDFe` análogo; **mesmo** SoapCurl inseguro |

**Decisão reafirmada:** runtime = **cliente próprio** DistDFe (padrão ADN).  
`sped-nfe`/`sped-cte`/`sped-mdfe` = fixtures/docs **dev only**, não transporte de produção.  
`sped-common` permanece **só** metadados PFX.

---

## 3. CT-e / MDF-e / NFC-e

| Kind | Serviço | Método | Prioridade contábil |
|------|---------|--------|---------------------|
| **CT-e (57)** | `CTeDistribuicaoDFe` | `cteDistDFeInteresse` | **P3** — frete de entrada (tomador etc.) |
| **MDF-e (58)** | `MDFeDistribuicaoDFe` | `mdfeDistDFeInteresse` | **P4** — opt-in (contratante/autXML) |
| **NFC-e (65)** | não é pipeline DistDFe de entrada B2B | — | **P5 gap / MAY** — varejo; capturar de terceiros é raro |

- NSU **independente** entre NF-e, CT-e, MDF-e e ADN.  
- CT-e: **sem** “ciência” para liberar XML (diferente da NF-e destinatário).  
- CT-e OS/Simplificado/GTV-e: mesmo canal CT-e (NT 2015.002 evoluções).  
- MDF-e AN exposto tipicamente via **SVRS** URLs oficiais.  
- **Não** unificar SOAP NF-e/CT-e/MDF-e num “client mágico” — interfaces por canal.

**URLs (confirmar na relação de WS vigente):**  
- CT-e prod: `https://www1.cte.fazenda.gov.br/CTeDistribuicaoDFe/CTeDistribuicaoDFe.asmx`  
- MDF-e prod: `https://mdfe.svrs.rs.gov.br/ws/MDFeDistribuicaoDFe/MDFeDistribuicaoDFe.asmx`

---

## 4. Manifestação do destinatário (MD-e)

| tpEvento | Nome | Natureza |
|----------|------|----------|
| **210210** | Ciência da Emissão/Operação | Intermediária — **abre XML** no DistDFe |
| **210200** | Confirmação da Operação | Conclusiva — bloqueia cancelamento emitente |
| **210220** | Desconhecimento | Conclusiva |
| **210240** | Operação não realizada | Conclusiva + `xJust` 15–255 |

| Item | Valor |
|------|-------|
| WS | `NFeRecepcaoEvento4` / Ambiente Nacional (`cOrgao` 91) |
| Ciência | **Opcional** (pode ir direto a conclusiva); prazo **10 dias** da autorização |
| Conclusiva | Prazo **90 dias** (desde **01/06/2026**, Ajuste SINIEF 14/2026); silêncio → **confirmação automática** |
| Retificação | até 2× por tipo conclusivo (`nSeqEvento`); última vale |
| Desconhecimento | **não** listado no MOC como liberador de `procNFe` — preferir Ciência antes se precisar do XML |
| Certificado | **do destinatário (cliente)**, nunca do escritório |

**UX default seguro:** automação só de **Ciência**; conclusivas **human-in-the-loop**.  
`procNFe` **não** chega síncrono no mesmo request — reconsultar DistDFe (NSU ou `consChNFe`).

---

## 5. Arquitetura multi-canal (moderna)

1. **Cursor:** tabela única `channel_sync_cursors` com `(office, establishment, environment, source, channel)` — NSU independente.  
2. **Rate limit em 3 camadas:** global endpoint · por CNPJ base/cert · por cursor (lock + next_sync_at).  
3. **Dados:** `dfe_documents` imutável + `document_interests` + projeções por kind.  
4. **Filas Horizon:** `sync-adn`, `sync-sefaz-nfe`, `sync-sefaz-cte`, `sync-sefaz-mdfe`, `manifest-nfe`, `export`.  
5. **Serializar DistDFe por root_cnpj** (filiais da mesma base não martelam AN em paralelo).  
6. **Feature flags** por canal default **off**; piloto 1–3 raízes; gates de 656/lag.  
7. **Ops first-class:** 656/137 na inbox; métricas por canal; sem SOAP bruto nos logs.  
8. **Captura ≠ emissão** — bounded contexts separados (emissão non-goal).  
9. **Um dono de DistDFe por A1** (evitar ERP + painel no mesmo cert).  
10. **max_nsu_seen** + last_cstat sanitizado no cursor.

### Ordem de entrega refinada

```
P0  schema cursors multi-canal + flags + interfaces + filas
P1  DistDFe NF-e + processor + /docs NFE
P1.1 ops 137/656/inbox/métricas/coexistência ADN
P2  MD-e (ciência manual → reconsulta procNFe)
P2.1 export multi-kind NFSE+NFE
P3  CT-e DistDFe
P4  MDF-e DistDFe (opt-in)
P5  NFC-e só se demanda; polish
```

---

## 6. Checklist segurança (DoD transporte)

- [ ] PFX só memória (BLOB); testes falham se PEM em disco  
- [ ] TLS ≥1.2 + VERIFYHOST + VERIFYPEER on  
- [ ] Sem cert em CI; smoke restrito  
- [ ] Vault + SHA-256 + imutabilidade  
- [ ] office_id do contexto auth  
- [ ] Logs sem segredo; audit de manifestação/export/sync  
- [ ] Rate limit e lock anti-656  

---

## 7. Links oficiais (atalho)

| Tema | URL |
|------|-----|
| WS NF-e | https://www.nfe.fazenda.gov.br/portal/webServices.aspx?tipoConteudo=OUC/YVNWZfo= |
| MOC DistDFe | http://moc.sped.fazenda.pr.gov.br/NFeDistribuicaoDFe.html |
| MOC MD-e | http://moc.sped.fazenda.pr.gov.br/RecepcaoEventoManifestacao.html |
| NT 2014.002 (Focus histórico) | https://focusnfe.com.br/notas-tecnicas/nfe/2014-002/ |
| CONFAZ AJ 14/2026 | https://www.confaz.fazenda.gov.br/legislacao/ajustes/2026/AJ014_26 |
| sped-nfe DistDFe.md | https://github.com/nfephp-org/sped-nfe/blob/master/docs/metodos/DistDFe.md |
| Portal CT-e WS | https://www.cte.fazenda.gov.br/portal/webServices.aspx?tipoConteudo=wpdBtfbTMrw= |
| SVRS MDF-e Serviços | https://dfe-portal.svrs.rs.gov.br/Mdfe/Servicos |

---

## 8. Agentes executados

| Agente | Objetivo |
|--------|----------|
| DistDFe SEFAZ | Contrato WS, cStat, NSU, rate limit |
| sped-nfe / libs | Runtime vs fixtures; PEM/TLS |
| CT-e MDF-e NFC-e | Canais e priorização MVP |
| Manifestação | tpEvento, prazos, UX |
| Arquitetura multi-DF-e | Cursors, filas, flags, ops |
