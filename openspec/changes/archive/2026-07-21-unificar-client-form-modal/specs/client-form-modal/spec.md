## ADDED Requirements

### Requirement: Modal único para criar e editar cliente
O painel SHALL usar um único `ClientFormModal` (`ShellFormModal`) para criar e editar o cadastro geral do cliente, tanto a partir da lista quanto a partir da ficha `/clients/[id]`.

#### Scenario: Criar cliente pela lista
- **WHEN** o usuário aciona “Novo cliente” (navbar, empty state, deep link ou comando)
- **THEN** o sistema abre `ClientFormModal` em modo create com título “Novo cliente” (ou “Nova filial…” quando aplicável)

#### Scenario: Editar cliente pela lista
- **WHEN** o usuário aciona editar na lista de clientes
- **THEN** o sistema abre o mesmo `ClientFormModal` em modo edit com título “Editar cliente” e subtítulo contendo razão social e/ou CNPJ

#### Scenario: Editar cliente pela ficha
- **WHEN** o usuário aciona “Editar cliente” no header da ficha ou “Editar” no dossiê de cadastro
- **THEN** o sistema abre `ClientFormModal` no shell `/clients/[id]` em modo edit com o mesmo título e subtítulo da lista

### Requirement: Dossiê de cadastro somente-leitura
A aba Cadastro (`/clients/[id]/cadastro`) SHALL exibir o dossiê em modo somente-leitura e MUST NOT renderizar o formulário de edição inline.

#### Scenario: Visualizar cadastro
- **WHEN** o usuário abre a aba Cadastro
- **THEN** o sistema mostra os campos do dossiê em leitura (grids/accordion) sem `ClientForm` embutido

#### Scenario: CTA Editar no dossiê
- **WHEN** o usuário com permissão de gestão clica “Editar” no dossiê
- **THEN** o sistema abre o modal de edição (não alterna a página para formulário inline)

### Requirement: Atualização RFB fora do modal de edição
A consulta/atualização cadastral RFB SHALL permanecer disponível no header da aba Cadastro e MUST NOT aparecer como ação dentro do `ClientForm` em modo edição. O botão Atualizar MUST consultar a fonte primeiro, apresentar revisão (diff + formulário editável) e só gravar após confirmação explícita do usuário.

#### Scenario: Atualizar no header inicia consulta
- **WHEN** o usuário clica “Atualizar” no header da aba Cadastro
- **THEN** o sistema consulta o CNPJ (lookup) e abre o modal de revisão sem gravar ainda

#### Scenario: Confirmar aplicação
- **WHEN** o usuário revisa o snapshot (opcionalmente altera campos) e confirma “Aplicar atualização”
- **THEN** o sistema persiste o snapshot confirmado via `refresh-registration` e recarrega o dossiê

#### Scenario: Sem RFB no formulário de edição
- **WHEN** o modal de edição cadastral está aberto
- **THEN** o formulário MUST NOT exibir o botão “Atualizar cadastro RFB”

### Requirement: Pós-save na ficha
Após salvar com sucesso o modal de edição aberto pela ficha, o sistema SHALL fechar o modal e recarregar os dados do cliente na ficha.

#### Scenario: Salvar edição na ficha
- **WHEN** o usuário salva alterações no modal aberto a partir de `/clients/[id]`
- **THEN** o modal fecha e o shell recarrega o cliente (`load()`), atualizando o dossiê no lugar

### Requirement: Payload de edição inalterado
O modo edição SHALL continuar persistindo apenas o subset atual de campos via `clients.update` (sem expandir o contrato nesta change).

#### Scenario: Campos de update
- **WHEN** o usuário salva o modal em modo edit
- **THEN** o sistema envia o mesmo conjunto de campos já suportado pelo update (identidade editável / regime / porte / natureza / ativo), sem exigir novos campos de API
