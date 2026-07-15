## MODIFIED Requirements

### Requirement: Uso do A1 da raiz em múltiplos canais oficiais
O sistema SHALL reutilizar o certificado e-CNPJ A1 ativo da raiz do cliente somente para canais de captura oficiais habilitados e pertencentes ao escopo escritural (ADN NFS-e e SEFAZ DistDFe/CT-e), sem armazenar cópia adicional do PFX fora do vault e sem expor material criptográfico. O sistema MUST NOT usar o A1 para captura MDF-e.

#### Scenario: Mesmo A1 para ADN e DistDFe
- **WHEN** o estabelecimento tem captura ADN e captura DistDFe habilitadas
- **THEN** ambos os jobs obtêm o PFX do mesmo objeto de vault da raiz e o usam somente em memória

#### Scenario: Canal MDF-e legado
- **WHEN** existe configuração legada para captura MDF-e
- **THEN** o sistema não materializa o A1 nem enfileira job para esse canal

### Requirement: Elegibilidade de captura por canal
O sistema SHALL expor elegibilidade somente para os canais escriturais suportados (ADN, DistDFe e CT-e), com motivos claros (sem A1, A1 vencido, captura desligada, cursor bloqueado), e MUST NOT publicar MDF-e como canal elegível.

#### Scenario: DistDFe inelegível sem A1
- **WHEN** o cliente não possui credencial A1 ativa
- **THEN** a elegibilidade DistDFe é falsa e o job não é enfileirado

#### Scenario: Resumo de elegibilidade
- **WHEN** a API lista a elegibilidade de captura de um cliente
- **THEN** a resposta não contém o canal `MDFE_DISTDFE`
