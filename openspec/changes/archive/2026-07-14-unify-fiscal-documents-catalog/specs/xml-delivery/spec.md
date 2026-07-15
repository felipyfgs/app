## ADDED Requirements

### Requirement: Download e export no catálogo de documentos
O sistema SHALL permitir download de XML e exportação ZIP a partir da identidade de documento do catálogo (chave de acesso), incluindo a superfície Documentos (`/docs`). Enquanto apenas NFS-e tiver captura, export MUST operar sobre projeções NFS-e e SHOULD recusar ou avisar quando o filtro de tipo excluir NFS-e.

#### Scenario: Download XML via documents
- **WHEN** um usuário autorizado solicita o XML de um documento existente via API de documents
- **THEN** o sistema entrega o XML original do vault sem expor material de certificado

#### Scenario: Export com filtro de tipo sem NFS-e
- **WHEN** o operador tenta exportar com filtro de kind que não inclui NFS-e
- **THEN** o sistema não gera ZIP vazio silencioso: desabilita a ação ou informa que export multi-tipo ainda não está disponível
