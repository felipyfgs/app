## ADDED Requirements

### Requirement: Emissão CCMEI deve usar contrato de domínio isolado por escritório

O sistema MUST executar `ccmei.emitirccmei` somente por um adaptador de domínio
que receba o `Office` resolvido no servidor e um cliente pertencente a esse
escritório. Nenhuma rota de negócio MUST aceitar `office_id`, coordenadas ou
credenciais SERPRO do navegador.

#### Scenario: Cliente de outro escritório é informado

- **WHEN** um usuário solicita a emissão para cliente que não pertence ao `Office` corrente
- **THEN** o sistema MUST rejeitar a solicitação antes de chamar o executor SERPRO e não persistir evidência

### Requirement: Resposta documental CCMEI deve ser sanitizada e protegida

O sistema MUST validar a estrutura oficial de `EMITIRCCMEI121` de forma
fail-closed e guardar bytes documentais somente no `SecureObjectStore`. Banco,
logs e API pública MUST conter apenas metadados sanitizados e referência segura.

#### Scenario: Documento oficial válido é recebido

- **WHEN** uma resposta oficial válida contém o certificado CCMEI
- **THEN** o sistema MUST armazenar o documento no cofre e persistir somente descritor, hash e proveniência permitidos

#### Scenario: Documento inválido ou sintético é recebido

- **WHEN** o executor retorna layout inválido, fonte sintética ou conteúdo documental não permitido
- **THEN** o sistema MUST rejeitar a resposta sem criar projeção consultável nem gravar bytes em banco ou log

### Requirement: Interface deve exigir ação explícita e permitir entrega autorizada

O painel de cliente MUST mostrar histórico local sem egress implícito e exigir
confirmação antes de emitir certificado. O download MUST ser same-origin,
autorizado para o escritório corrente e não revelar referência interna de
cofre.

#### Scenario: Usuário autorizado confirma emissão

- **WHEN** o usuário autorizado confirma uma emissão potencialmente bilhetável
- **THEN** a UI MUST mostrar processamento e resultado sanitizado, sem disparar outra chamada ao montar, navegar ou atualizar visualmente

#### Scenario: Usuário baixa certificado já projetado

- **WHEN** o usuário autorizado solicita download de certificado do cliente corrente
- **THEN** o sistema MUST validar o escopo tenant e entregar somente o documento autorizado com MIME e nome seguro

### Requirement: Produção permanece bloqueada sem evidência de canário válida

O sistema MUST manter a capability de emissão fail-closed até que exista
autorização operacional e evidência `PASS_REAL_*` com proveniência
`PRODUCTION_CANARY`. Trial MUST ser registrado apenas como `PASS_TRIAL`.

#### Scenario: Não há autorização de canário

- **WHEN** não existe manifest de aprovação ou pré-condição real válida
- **THEN** o ledger MUST manter `ccmei.emitirccmei` como `BLOCKED` e a UI MUST informar indisponibilidade acionável sem prometer execução
