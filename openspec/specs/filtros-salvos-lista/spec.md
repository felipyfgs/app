# filtros-salvos-lista Specification

## Purpose
Armazenamento e API de presets de filtros nomeados (pessoais e compartilhados no Office) por superfície de lista, com isolamento multi-tenant.

## Requirements

### Requirement: Presets nomeados persistem estado de filtro por superfície e Office
O sistema SHALL permitir que um usuário com membership no Office corrente crie um preset com nome e payload versionado associado a uma `surface` estável. O Office SHALL ser resolvido apenas pelo contexto servidor (`CurrentOffice`). Requests MUST NOT aceitar `office_id` do client como autoridade de escopo. O payload MUST representar o estado de filtro aplicado da superfície (busca e eixos estruturados), omitindo defaults vazios.

#### Scenario: Criar preset pessoal
- **WHEN** o usuário autenticado com membership envia nome válido e payload da superfície atual
- **THEN** o sistema SHALL persistir o preset com `visibility=personal`, autor = usuário, office = CurrentOffice e schema_version definido

#### Scenario: Rejeitar office_id do client
- **WHEN** o request incluir `office_id` no body ou query
- **THEN** o middleware/contexto SHALL ignorar ou remover o valor e o preset MUST ficar no Office da sessão

### Requirement: Visibilidade pessoal ou compartilhamento com o Office
Um preset SHALL ter `visibility` `personal` ou `office`. Presets `personal` MUST ser listados apenas ao autor no Office corrente. Presets `office` SHALL ser listados a qualquer membership do mesmo Office. Publicar ou alterar para `office` MUST exigir papel ADMIN ou OPERATOR. VIEWER MUST poder aplicar presets `office` e os próprios `personal`, mas MUST NOT publicar para o Office.

#### Scenario: Listar presets mistos
- **WHEN** o usuário lista presets da surface S no Office O
- **THEN** a resposta SHALL conter seus presets pessoais de S em O e todos os presets com visibility office de S em O

#### Scenario: VIEWER não publica
- **WHEN** um VIEWER tenta criar ou atualizar um preset com visibility office
- **THEN** o sistema MUST recusar com erro de autorização

#### Scenario: OPERATOR compartilha
- **WHEN** um OPERATOR marca “Compartilhar com o escritório” ao salvar um preset válido
- **THEN** o preset SHALL ficar com visibility office e outros membros do Office MUST vê-lo na listagem

### Requirement: Ownership e exclusão
O autor SHALL poder renomear, atualizar payload, alternar visibilidade (se papel permitir office) e excluir seus presets. Um ADMIN do Office SHALL poder excluir ou descompartilhar presets `office` de qualquer autor no Office. Exclusão de preset MUST NOT alterar o estado de filtro já aplicado na sessão do cliente.

#### Scenario: Autor exclui preset pessoal
- **WHEN** o autor solicita exclusão do próprio preset personal
- **THEN** o registro MUST ser removido e deixar de aparecer na listagem

#### Scenario: ADMIN remove preset compartilhado de outro
- **WHEN** um ADMIN exclui um preset office criado por outro usuário do mesmo Office
- **THEN** o preset MUST deixar de ser listado para todos os membros

### Requirement: Aplicar preset não contamina outro tenant
Ao trocar o CurrentOffice, a UI e as listagens de presets MUST referir apenas o novo Office. Aplicar um preset MUST validar que ele pertence ao Office corrente e à surface da página; caso contrário MUST falhar sem aplicar estado parcial de outro tenant.

#### Scenario: Troca de Office
- **WHEN** o CurrentOffice muda
- **THEN** presets do Office anterior MUST NOT permanecer visíveis nem aplicáveis

#### Scenario: Surface incompatível
- **WHEN** o cliente tenta aplicar um preset cuja surface difere da lista aberta
- **THEN** o sistema MUST recusar a aplicação
