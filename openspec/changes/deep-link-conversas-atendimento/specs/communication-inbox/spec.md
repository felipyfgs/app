## ADDED Requirements

### Requirement: Conversa selecionada tem URL canônica compartilhável
O sistema SHALL expor a conversa aberta em `/communication/conversations/{id}` (id numérico positivo). Abrir essa URL MUST selecionar e carregar a conversa após o workspace inicializar. Selecionar na lista MUST atualizar a URL; limpar a seleção MUST navegar para `/communication`.

#### Scenario: Deep-link abre conversa
- **WHEN** operador autenticado com acesso navega para `/communication/conversations/42`
- **THEN** a conversa 42 fica selecionada e a timeline é carregada sem exigir clique na lista

#### Scenario: Seleção atualiza a URL
- **WHEN** operador seleciona a conversa 7 em `/communication`
- **THEN** a rota passa a `/communication/conversations/7`

#### Scenario: Fechar seleção
- **WHEN** a seleção é limpa (escape/mobile close) em `/communication/conversations/7`
- **THEN** a rota volta para `/communication`

#### Scenario: ID inacessível
- **WHEN** a URL aponta para conversa inexistente ou sem acesso
- **THEN** o sistema informa o erro e retorna à lista em `/communication`
