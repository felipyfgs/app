## Purpose

Disponibilizar ao escritório o comprovante de arrecadação da operação PAGTOWEB
7.2, com dados documentais protegidos, autorização tenant-scoped e solicitação
manual explícita.

## Requirements

### Requirement: Comprovante PAGTOWEB é obtido por contrato tipado e protegido

O sistema SHALL executar `pagtoweb.comparrecadacao` somente por adapter tipado que resolve a coordenada oficial, valida o request, usa a cadeia central de capability, orçamento, rate limit, procuração e bilhetagem, e rejeita resposta documental inválida sem persistir conteúdo parcial.

#### Scenario: Resposta PDF válida é projetada

- **WHEN** uma execução manual confirmada recebe um comprovante PDF válido pela operação 7.2
- **THEN** o sistema SHALL armazenar os bytes apenas no `SecureObjectStore` e criar uma projeção sanitizada idempotente para o cliente do escritório atual

#### Scenario: Documento inválido não é persistido

- **WHEN** a resposta não contiver PDF Base64 válido dentro dos limites aceitos
- **THEN** o sistema SHALL marcar a execução como falha sanitizada e não SHALL criar metadados nem arquivo no cofre

### Requirement: Histórico e download do comprovante são tenant-scoped

O sistema SHALL oferecer GET de histórico local e download same-origin do comprovante somente para o cliente pertencente ao `CurrentOffice`, sem aceitar `office_id`, referências de cofre ou dados documentais brutos do navegador.

#### Scenario: Download autorizado

- **WHEN** um usuário autorizado solicita um comprovante projetado do próprio escritório
- **THEN** o sistema SHALL responder o PDF com MIME, nome seguro e cabeçalhos privados sem expor a chave interna do cofre

#### Scenario: Tentativa cross-tenant

- **WHEN** um usuário solicita histórico ou download de cliente ou comprovante de outro escritório
- **THEN** o sistema SHALL negar o acesso sem revelar metadados do documento

### Requirement: Solicitação manual exige confirmação explícita

O sistema SHALL manter leitura local em GET e SHALL iniciar a operação bilhetável somente após POST confirmado por usuário com permissão de disparo. O POST receberá `numeroDocumento` efêmero conforme o contrato oficial, validará seu formato e não SHALL persistir, registrar em log, devolver pela API nem renderizar o valor completo; não haverá transmissão de declaração, DARF ou qualquer mutação fiscal.

#### Scenario: Abertura da superfície não consulta SERPRO

- **WHEN** o usuário abre o painel ou o histórico de comprovantes
- **THEN** o sistema SHALL carregar apenas a projeção local e não SHALL criar job ou chamada externa

#### Scenario: Confirmação executa consulta síncrona

- **WHEN** o usuário revisa o aviso de bilhetagem e confirma a solicitação
- **THEN** o sistema SHALL executar exclusivamente `pagtoweb.comparrecadacao` no contexto efêmero da requisição, sem job, retry ou continuação que serialize `numeroDocumento`, e apresentará apenas o resultado sanitizado
