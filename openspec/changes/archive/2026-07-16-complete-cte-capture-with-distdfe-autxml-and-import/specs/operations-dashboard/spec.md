## ADDED Requirements

### Requirement: Saúde operacional dos canais CT-e
O dashboard operacional SHALL agregar separadamente saúde de `CTE_DISTDFE` por cliente e `CTE_AUTXML_DISTDFE` por escritório, incluindo cursores, fila, atraso, última/próxima execução, validade da credencial, cStat, falhas consecutivas, quarentena e estado de cobertura, sempre filtrado por `office_id` derivado do servidor.

#### Scenario: Cliente saudável e escritório bloqueado
- **WHEN** os cursores dos clientes estão alcançados, mas o canal `autXML` está bloqueado
- **THEN** o resumo apresenta os dois estados separadamente e não degrada todos os canais como uma falha única

#### Scenario: Consulta de outro escritório
- **WHEN** usuário tenta acessar saúde por identificador pertencente a outro `office_id`
- **THEN** o sistema responde como recurso não acessível e não revela CNPJ, cursor ou contagens

### Requirement: Inbox tipada para CT-e
A inbox SHALL criar itens tipados para A1 ausente/expirado, `593`, `656`, cinco falhas de decode, cursor sem heartbeat, conflito de consumidor externo, `UNEXPECTED_OWN_ISSUER_DOCUMENT`, documento/evento sem vínculo, `AUTXML_REDACTED`, divergência de bytes e `PENDING_IMPORT`. Cada item SHALL conter severidade, ação permitida, correlação e mensagem sanitizada.

#### Scenario: Documento emitido sem fonte automática
- **WHEN** a cobertura identifica CT-e emitido pendente sem `autXML` ou entrega do emissor
- **THEN** a inbox cria pendência de importação com ação XML/ZIP e não oferece automação de portal

#### Scenario: Quinta falha de decode
- **WHEN** o mesmo stream e ponto acumulam cinco falhas consecutivas de Base64/GZip
- **THEN** o cursor é bloqueado e a inbox cria alerta crítico sem incluir `docZip` ou XML

#### Scenario: Artefato redigido aceito
- **WHEN** um CT-e `AUTXML_REDACTED` é catalogado
- **THEN** a inbox ou indicador informativo registra a limitação sem tratá-la automaticamente como corrupção

### Requirement: Ações CT-e protegidas por papel e circuito
ADMIN com 2FA recente SHALL poder gerir flags, allowlist, credencial do escritório e resolver quarentena sensível; OPERATOR SHALL poder importar e solicitar reparo de NSU conhecido quando elegível; VIEWER MUST ser somente leitura. Nenhum papel SHALL poder forçar chamada durante quiet ou circuito `656`.

#### Scenario: Operator tenta retry durante circuito
- **WHEN** OPERATOR solicita nova chamada antes do desbloqueio por `656`
- **THEN** a ação é recusada sem executar rede e informa o horário mínimo permitido

#### Scenario: ADMIN promove quarentena
- **WHEN** ADMIN com 2FA recente associa item a estabelecimento inequívoco do mesmo escritório após validação
- **THEN** a promoção é auditada com antes/depois, ator e correlação, sem registrar XML bruto

### Requirement: Métricas e logs CT-e sem segredos
Métricas e logs SHALL registrar somente canal, IDs internos, cStat, latência, páginas, contagens, `ultNSU`, `maxNSU`, estado e códigos sanitizados. O sistema MUST NOT registrar XML, Base64, PFX, senha, PEM, chave privada, cookie de portal ou cabeçalhos sensíveis.

#### Scenario: Job concluído
- **WHEN** um job CT-e termina
- **THEN** o log estruturado contém cursor, páginas, documentos, duração e último NSU sem conteúdo fiscal ou segredo

#### Scenario: Exceção remota
- **WHEN** o endpoint retorna erro inesperado
- **THEN** o sistema registra classificação e correlação sanitizadas, preserva o corpo somente quando necessário em custódia privada e nunca o despeja no log comum

