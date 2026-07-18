## ADDED Requirements

### Requirement: Consulta local segura da última DEFIS
O monitor SHALL permitir solicitar explicitamente `DEFIS/CONSULTIMADECREC143`
por cliente e ano-calendário válido, e SHALL disponibilizar apenas o histórico
local de descritores de declaração e recibo.

#### Scenario: Solicitação confirmada
- **WHEN** um operador autorizado confirma uma consulta para cliente do escritório atual e ano válido
- **THEN** o sistema SHALL enfileirar uma execução manual sem aceitar `office_id` do cliente HTTP

#### Scenario: Leitura sem consulta externa
- **WHEN** um usuário autorizado abre o histórico da última DEFIS
- **THEN** o sistema SHALL retornar somente dados locais e SHALL indicar que a SERPRO não foi chamada

### Requirement: PDFs DEFIS protegidos no cofre
O sistema SHALL decodificar os PDFs retornados pela 143 de forma fail-closed e
SHALL guardar seus bytes exclusivamente no `SecureObjectStore`.

#### Scenario: Resposta válida
- **WHEN** a resposta 143 contém recibo e declaração PDF em base64 válidos
- **THEN** o sistema SHALL persistir descritores tenant-scoped sem `idDefis`, base64 ou conteúdo do PDF

#### Scenario: Resposta inválida
- **WHEN** a resposta contém base64 inválido, bytes fora do limite ou PDF ausente
- **THEN** o sistema SHALL falhar sem criar descritores públicos nem registrar conteúdo sensível

### Requirement: Download tenant-scoped de artefato local
O sistema SHALL permitir download autenticado somente ao cliente do escritório
atual ao qual o descritor pertence.

#### Scenario: Acesso autorizado
- **WHEN** um usuário do escritório atual solicita o artefato local de seu cliente
- **THEN** o sistema SHALL entregar o arquivo do cofre com cabeçalhos seguros e sem expor a referência interna

#### Scenario: Acesso cruzado
- **WHEN** um usuário tenta acessar um descritor de outro escritório ou cliente
- **THEN** o sistema SHALL responder como recurso não encontrado e não SHALL consultar a SERPRO
