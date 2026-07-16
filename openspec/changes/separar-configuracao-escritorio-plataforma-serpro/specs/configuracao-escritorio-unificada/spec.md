## ADDED Requirements

### Requirement: Perfil institucional único do escritório
O sistema SHALL manter por `Office` um perfil institucional com exatamente CNPJ, razão social, e-mail institucional e telefone institucional, editável por `OfficeRole::ADMIN` e por `PLATFORM_ADMIN` no contexto privilegiado daquele escritório. O escopo MUST ser derivado de `CurrentOffice`, ignorando qualquer `office_id` enviado por body ou query.

#### Scenario: Administrador do escritório atualiza o perfil
- **WHEN** um `OfficeRole::ADMIN` autenticado envia dados institucionais válidos em `/settings`
- **THEN** o sistema SHALL atualizar somente o perfil do `CurrentOffice` e registrar ator e alterações sem segredos

#### Scenario: Papel sem permissão tenta alterar o perfil
- **WHEN** um `OPERATOR` ou `VIEWER` tentar alterar os dados institucionais
- **THEN** o sistema SHALL negar a mutação sem modificar o perfil

### Requirement: Mudança controlada do CNPJ
A mudança de CNPJ no mesmo `Office` MUST exigir confirmação forte, apresentar o impacto e invalidar atomicamente toda credencial A1 incompatível, Termo, token e autorização derivados. O onboarding SHALL permanecer bloqueado até existir A1 e-CNPJ compatível exatamente com o novo CNPJ.

#### Scenario: CNPJ é alterado com confirmação
- **WHEN** um administrador autorizado confirma a troca para um CNPJ válido diferente
- **THEN** o sistema SHALL salvar o novo CNPJ, revogar os artefatos derivados incompatíveis e marcar as integrações afetadas como ação necessária

#### Scenario: Troca de CNPJ sem confirmação
- **WHEN** uma requisição tentar mudar o CNPJ sem a confirmação forte exigida
- **THEN** o sistema MUST rejeitar toda a alteração

### Requirement: Credencial canônica e-CNPJ A1
Cada `Office` MUST possuir no máximo uma credencial canônica ativa e-CNPJ A1. O sistema SHALL aceitar somente `.pfx` ou `.p12` que abra com a senha informada, seja A1, esteja válido e tenha CNPJ titular exatamente compatível com o perfil; PFX e senha MUST ser armazenados cifrados no `SecureObjectStore` e MUST NOT ser retornados, baixados, registrados em log ou exportados.

#### Scenario: Upload válido do A1
- **WHEN** um administrador envia um A1 compatível e a senha correta
- **THEN** o sistema SHALL guardar o segredo no vault e expor somente status, subject, CNPJ titular, validade e fingerprint

#### Scenario: Upload incompatível
- **WHEN** o arquivo não abrir, não for A1, estiver inválido ou pertencer a outro CNPJ
- **THEN** o sistema SHALL rejeitar o upload sem criar credencial ativa nem persistir material temporário

### Requirement: Vínculos de finalidade sem duplicação do segredo
As integrações SHALL usar vínculos de finalidade para referenciar a credencial canônica, incluindo ao menos `SERPRO_TERM_SIGNING` e `NFE_AUTXML_DISTDFE`. Cada uso MUST registrar office, finalidade e ator ou job, e nenhuma finalidade SHALL criar uma segunda cópia lógica do PFX ou da senha.

#### Scenario: Duas integrações usam o mesmo A1
- **WHEN** SERPRO e autXML estiverem habilitados para o mesmo escritório
- **THEN** ambos SHALL resolver a mesma credencial canônica por vínculos distintos e auditáveis

### Requirement: Substituição segura da credencial
A substituição MUST validar completamente o novo A1 antes do cutover. Se for válido, o sistema SHALL aposentar atomicamente a credencial anterior, ativar a nova e enfileirar o reonboarding das finalidades derivadas; se falhar, a credencial anterior MUST permanecer ativa e inalterada.

#### Scenario: Novo certificado é inválido
- **WHEN** a tentativa de substituição falhar em qualquer validação
- **THEN** o sistema SHALL preservar a credencial anterior e informar erro acionável sem expor detalhes secretos

#### Scenario: Substituição concluída
- **WHEN** o novo A1 passar por todas as validações
- **THEN** o sistema SHALL efetuar um único cutover e reprocessar os vínculos sem período com duas credenciais ativas

### Requirement: Remoção com revogação imediata
A remoção do A1 MUST exigir confirmação e SHALL bloquear imediatamente todas as finalidades, invalidar Termo e tokens derivados e conservar somente metadados mínimos e auditoria. O sistema MUST NOT oferecer recuperação ou download da credencial removida.

#### Scenario: Administrador remove o certificado
- **WHEN** uma remoção confirmada e autorizada for concluída
- **THEN** todos os usos SHALL ficar bloqueados antes de qualquer novo transporte externo

### Requirement: Consentimento técnico versionado
Antes de usar o A1, o sistema MUST obter consentimento explícito em checkbox, registrar versão, finalidades apresentadas, ator e instante e permitir revogação. Uma nova finalidade material MUST exigir nova concordância, mas MUST NOT exigir novo upload quando o A1 continuar compatível.

#### Scenario: Primeiro consentimento
- **WHEN** um administrador aceita a versão vigente após visualizar suas finalidades
- **THEN** o sistema SHALL registrar evidência imutável e poderá iniciar o onboarding técnico

#### Scenario: Finalidade material é acrescentada
- **WHEN** a plataforma publicar uma versão com nova finalidade material
- **THEN** o uso novo SHALL permanecer bloqueado até nova concordância do escritório

### Requirement: Alertas de certificado somente no painel
O sistema SHALL gerar e deduplicar alertas no painel quando a validade do A1 atingir as janelas de 30, 7 e 1 dia. Esta capacidade MUST NOT enviar e-mail, WhatsApp, SMS ou outra notificação externa.

#### Scenario: Certificado entra na janela de sete dias
- **WHEN** o A1 ativo passar a vencer em até sete dias
- **THEN** o painel SHALL exibir o alerta correspondente sem reenviar continuamente o mesmo evento

