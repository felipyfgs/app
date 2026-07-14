## 1. Contrato e fixtures

- [x] 1.1 Documentar no código (comentário curto ou ADR addendum opcional) o envelope JSON observado e a decisão de não usar SDK comunitário ADN
- [x] 1.2 Criar fixtures JSON sanitizadas: `DOCUMENTOS_LOCALIZADOS` (lote misto NFSE/EVENTO), `NENHUM_DOCUMENTO_LOCALIZADO` (E2220), `REJEICAO`, página com NSUs inválidos/duplicados
- [x] 1.3 Reaproveitar payloads `.b64` de teste existentes em `ArquivoXml` das fixtures; não incluir XML real do cliente piloto no git
- [x] 1.4 Remover ou arquivar fixtures de envelope XML `retDistDFeInt` que não correspondem à API real, atualizando referências nos testes

## 2. Cliente ADN (parse e DTO)

- [x] 2.1 Implementar parse de distribuição JSON em `HttpAdnContributorClient` (`StatusProcessamento`, `LoteDFe`, erros/alertas sanitizados)
- [x] 2.2 Mapear `TipoDocumento` NFSE/EVENTO/outros → `AdnDocumentType` e preencher `contentBase64` a partir de `ArquivoXml`
- [x] 2.3 Derivar `ultimoNsu`, `hasMore` e campos do `DistributionPageDto` conforme design (sem depender de `maxNSU` no JSON)
- [x] 2.4 Tratar `REJEICAO` e envelopes inválidos com exceções de domínio (sem avançar NSU)
- [x] 2.5 Alinhar `events()` ao path oficial e parse JSON se a resposta for JSON; manter contrato `AdnContributorClient`
- [x] 2.6 Garantir `ADN_BASE_URL` não vazio (config/env) e Accept adequado a JSON/XML

## 3. Pipeline de persistência e job

- [x] 3.1 Verificar `DocumentDecoder` + `DistributionPageProcessor` com itens vindos do lote JSON (sem mudança de regra de atomicidade)
- [x] 3.2 Confirmar que status interno de “nenhum documento” preserva cursor e agenda próxima hora
- [x] 3.3 Confirmar reenfileiramento quando `hasMore` e elegibilidade ok (máx. 20 páginas/job)
- [x] 3.4 Garantir logs estruturados sem corpo bruto completo do ADN nem material de certificado

## 4. Testes automatizados

- [x] 4.1 Atualizar testes unitários de `HttpAdnContributorClient` para fixtures JSON e cenários de status
- [x] 4.2 Manter testes de contrato: TLS verify obrigatório; sem PEM temporário no transporte
- [x] 4.3 Atualizar testes de `DistributionPageProcessor` / feature de sync com stubs de status oficiais
- [x] 4.4 Rodar suite PHPUnit relevante (Unit Adn + Feature Sync) e corrigir regressões

## 5. Smoke e aceite no cliente piloto (dev, fora do CI)

- [x] 5.1 Smoke mTLS distribution no estabelecimento 9 (cliente 8) com parse JSON OK e amostra de NSUs
- [x] 5.2 Disparar sync manual do estabelecimento 9 e validar avanço de `last_nsu`, `sync_runs` e persistência em `dfe_documents` / `nfse_notes` / `nfse_events`
- [x] 5.3 Validar download de XML e presença de pelo menos uma nota no catálogo do painel
- [x] 5.4 Registrar no runbook ops (`docs/ops/pilot-and-rollback.md` ou nota breve) o uso do cliente 8 como base de dev e que CI não usa o A1

## 6. Fechamento

- [x] 6.1 Revisar delta da spec `adn-document-sync` vs implementação
- [x] 6.2 Marcar tarefas concluídas e deixar a change pronta para `/opsx-archive` após validação
