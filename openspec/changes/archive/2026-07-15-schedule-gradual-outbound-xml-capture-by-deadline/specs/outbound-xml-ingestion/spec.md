## ADDED Requirements

### Requirement: Satisfação do prazo por qualquer fonte válida
Uma pendência SHALL ser considerada capturada no prazo somente após ingestão canônica completa por vault, emissão/importação, `autXML`, XML/ZIP, pacote oficial ou SVRS. A aquisição SHALL registrar fonte, `captured_at`, `due_at` e resultado de prazo, preservando bytes imutáveis.

#### Scenario: Upload conclui antes do portal
- **WHEN** XML/ZIP válido satisfaz uma chave com slot SVRS ainda não iniciado
- **THEN** o slot é cancelado, a fonte upload é registrada e o documento conta como capturado no prazo

### Requirement: Divergência não conta como completude
Documento com chave, identidade, protocolo, digest, assinatura ou hash em divergência MUST permanecer fora da contagem concluída até revisão segura e MUST NOT substituir o canônico.

#### Scenario: Hash divergente perto do prazo
- **WHEN** uma segunda fonte apresenta bytes divergentes para a mesma chave
- **THEN** o sistema abre revisão crítica e não mascara o risco de prazo como captura concluída

