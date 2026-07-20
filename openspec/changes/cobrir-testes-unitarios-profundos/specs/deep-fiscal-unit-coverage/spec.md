## ADDED Requirements

### Requirement: SimplesMei adapter e pós-consulta PGMEI têm cobertura unitária
O sistema SHALL manter testes unitários que exercitam o adapter Simples/MEI (pelo menos uma operação PGMEI de leitura e uma PGDASD ou equivalente com stubs) e a projeção/pós-consulta de dívida ativa PGMEI a partir de payloads decodificados — sem egress SERPRO/portal real.

#### Scenario: Adapter com stub de fonte
- **WHEN** o adapter executa uma operação PGMEI MONITOR/CONSULTAR com fonte stubada
- **THEN** o resultado de domínio (sucesso ou falha classificada) é assertado sem rede externa

#### Scenario: Pós-consulta / projector
- **WHEN** um decode DIVIDAATIVA24 válido (lista vazia ou com débitos) alimenta o pós-consulta/projector
- **THEN** o estado de dívida e contagens persistem ou retornam de forma coerente com o codec

### Requirement: Política de mutação fiscal é fail-closed sob teste
O sistema SHALL cobrir unitariamente `FiscalMutationPolicy` (ou equivalente) para recusas por kill switch, ausência de 2FA/confirmação, ou pré-condições de orçamento/autorização — códigos estáveis, sem mutação real.

#### Scenario: Kill switch ou pré-condição bloqueia mutação
- **WHEN** a política avalia uma mutação com kill switch ativo ou pré-condição faltante
- **THEN** a mutação é recusada com código/resultado assertado

### Requirement: Jornada PGMEI consult após autorização utilizável
O sistema SHALL ter Feature (fakes) em que um escritório com autorização em estado utilizável e contrato/limites mínimos enfileira ou executa consult PGMEI MONITOR sem terminar em `AUTHORIZATION_MISSING` por DRAFT, e sem chamar SERPRO real.

#### Scenario: Consult não bloqueia por DRAFT
- **WHEN** a autorização do escritório está em status utilizável no ambiente TRIAL de teste
- **THEN** a consult PGMEI não retorna bloqueio `AUTHORIZATION_MISSING` atribuível a DRAFT/PendingTerm

#### Scenario: Sem egress real
- **WHEN** a Feature roda
- **THEN** não há HTTP para gateway SERPRO/mei real (apenas fakes/stubs)

### Requirement: Envelope crypto Vault tem round-trip unitário
O sistema SHALL testar `EnvelopeCrypto` (ou API pública equivalente) com round-trip de plaintext e falha controlada com material inválido — sem gravar segredos em fixtures versionadas.

#### Scenario: Round-trip
- **WHEN** um payload de teste é envelopado e aberto com a chave de teste
- **THEN** o plaintext recuperado coincide

#### Scenario: Material inválido
- **WHEN** o open usa material corrompido ou chave errada
- **THEN** a operação falha de forma explícita (exceção/código), sem vazamento do plaintext

### Requirement: Verify da onda profundos
Antes do archive, SHALL evidenciar PHPUnit filtrado da onda e `openspec validate --strict` desta change.

#### Scenario: Suites verdes
- **WHEN** os filtros da change são executados
- **THEN** passam sem rede SERPRO/mei
