## 1. Backend — desbloqueio de XML (primário)

- [x] 1.1 Client `SefazNfeManifestationClient` (RecepcaoEvento4, mTLS BLOB) — ao menos 210210
- [x] 1.2 Serviço “unlock full XML”: se já tem procNFe no-op; senão ciência + enfileira reconsulta
- [x] 1.3 Job reconsulta DistDFe / consChNFe throttled → persistir procNFe
- [x] 1.4 API: POST unlock (ou manifestations type=CIENCIA purpose=UNLOCK_XML) + detalhe com has_full_xml
- [x] 1.5 Download/export preferem procNFe; flag SEFAZ_MANIFEST_ENABLED
- [x] 1.6 Testes: unlock path, full já existe, 403 VIEWER, anti-PEM, flag off

## 2. Backend — MD-e opcional (secundário)

- [x] 2.1 Suportar CONFIRMACAO / DESCONHECIMENTO / NAO_REALIZADA na mesma API
- [x] 2.2 Regras: sem ciência após conclusiva; xJust; sem auto-conclusiva
- [x] 2.3 Auditoria de tentativas opcionais
- [x] 2.4 Testes de ordem ciência→desconhecimento e rejeição ciência pós-final

## 3. Frontend — entrega first

- [x] 3.1 Detalhe NF-e: botão primário Download
- [x] 3.2 Ação secundária “Obter XML completo” (ciência unlock) com copy correta
- [x] 3.3 Seção colapsável “Manifestação opcional” (conclusivas + confirmação modal)
- [x] 3.4 VIEWER: só download do que existir; sem ações SEFAZ

## 4. Piloto

- [x] 4.1 Smoke client 8: uma chave só-resumo → obter full → download
- [x] 4.2 Atualizar `docs/ops/pilot-and-rollback.md` (produto = entrega XML; MD-e opcional)
- [x] 4.3 Não smoke de desconhecimento em nota comercial real

## 5. Segurança

- [x] 5.1 Testes TLS/PEM no client de evento
- [x] 5.2 Logs sem material de certificado
