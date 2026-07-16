## MODIFIED Requirements

### Requirement: Auditoria resistente a adulteração
Ações sensíveis, mudanças de gate, rotação, consentimento, assinatura, aceite, aprovação, kill switch e conciliação MUST produzir auditoria append-only com ator, método e instante da confirmação aplicável, tempo, motivo, versão e hash encadeado ou armazenamento imutável equivalente. A auditoria MUST NOT exigir nem afirmar TOTP/2FA quando o produto usa reconfirmação de senha. Segredos, senhas e XML bruto MUST ser excluídos do evento.

#### Scenario: Edição de evento histórico
- **WHEN** um evento armazenado é alterado ou removido fora do fluxo autorizado
- **THEN** a verificação de integridade detecta a quebra e abre alerta

#### Scenario: Ação com senha recente
- **WHEN** uma ação sensível é autorizada por reconfirmação de senha
- **THEN** o evento SHALL registrar o método e instante da confirmação sem registrar a senha, hash de senha ou dado reutilizável

