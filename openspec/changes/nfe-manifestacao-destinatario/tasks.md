## 1. Backend — desbloqueio de XML (primário)

- [ ] 1.1 Client `SefazNfeManifestationClient` (RecepcaoEvento4, mTLS BLOB) — ao menos 210210
- [ ] 1.2 Serviço “unlock full XML”: se já tem procNFe no-op; senão ciência + enfileira reconsulta
- [ ] 1.3 Job reconsulta DistDFe / consChNFe throttled → persistir procNFe
- [ ] 1.4 API: POST unlock (ou manifestations type=CIENCIA purpose=UNLOCK_XML) + detalhe com has_full_xml
- [ ] 1.5 Download/export preferem procNFe; flag SEFAZ_MANIFEST_ENABLED
- [ ] 1.6 Testes: unlock path, full já existe, 403 VIEWER, anti-PEM, flag off

## 2. Backend — MD-e opcional (secundário)

- [ ] 2.1 Suportar CONFIRMACAO / DESCONHECIMENTO / NAO_REALIZADA na mesma API
- [ ] 2.2 Regras: sem ciência após conclusiva; xJust; sem auto-conclusiva
- [ ] 2.3 Auditoria de tentativas opcionais
- [ ] 2.4 Testes de ordem ciência→desconhecimento e rejeição ciência pós-final

## 3. Frontend — entrega first

- [ ] 3.1 Detalhe NF-e: botão primário Download
- [ ] 3.2 Ação secundária “Obter XML completo” (ciência unlock) com copy correta
- [ ] 3.3 Seção colapsável “Manifestação opcional” (conclusivas + confirmação modal)
- [ ] 3.4 VIEWER: só download do que existir; sem ações SEFAZ

## 4. Piloto

- [ ] 4.1 Smoke client 8: uma chave só-resumo → obter full → download
- [ ] 4.2 Atualizar `docs/ops/pilot-and-rollback.md` (produto = entrega XML; MD-e opcional)
- [ ] 4.3 Não smoke de desconhecimento em nota comercial real

## 5. Segurança

- [ ] 5.1 Testes TLS/PEM no client de evento
- [ ] 5.2 Logs sem material de certificado
