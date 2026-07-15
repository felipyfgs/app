## MODIFIED Requirements

### Requirement: Uso do A1 da raiz em múltiplos canais oficiais
O sistema SHALL reutilizar o certificado e-CNPJ A1 ativo da raiz do cliente para canais de captura oficiais habilitados (ADN NFS-e e SEFAZ DistDFe/CT-e/MDF-e), sem armazenar cópia adicional do PFX fora do vault e sem expor material criptográfico.

#### Scenario: Mesmo A1 para ADN e DistDFe
- **WHEN** o estabelecimento tem captura ADN e captura DistDFe habilitadas
- **THEN** ambos os jobs obtêm o PFX do mesmo objeto de vault da raiz e o usam somente em memória

## ADDED Requirements

### Requirement: Elegibilidade de captura por canal
O sistema SHALL expor elegibilidade de captura por canal (ADN, DistDFe, CT-e, MDF-e) com motivos claros (sem A1, A1 vencido, captura desligada, cursor bloqueado).

#### Scenario: DistDFe inelegível sem A1
- **WHEN** o cliente não possui credencial A1 ativa
- **THEN** a elegibilidade DistDFe é falsa e o job não é enfileirado
