## ADDED Requirements

### Requirement: CNPJ completo na linha do portfolio

A linha de cliente do portfolio de módulos fiscais (`ModuleClientRowDto` / resposta de clients do módulo) SHALL incluir o campo `cnpj` com o identificador normalizado (14 caracteres, sem máscara de exibição), resolvido a partir do CNPJ do estabelecimento ou, se ausente, do `root_cnpj` do cliente, no escopo do office autenticado.

O campo `cnpj_masked` MUST permanecer presente por compatibilidade; esta change MUST NOT alterar a semântica de `cnpj_masked` em modais, histórico ou exports.

#### Scenario: Portfolio expõe cnpj normalizado
- **WHEN** a API de portfolio devolve uma linha de cliente com CNPJ conhecido
- **THEN** o JSON da linha SHALL incluir `cnpj` com 14 caracteres normalizados (sem pontuação de máscara BR)

#### Scenario: Compatibilidade com cnpj_masked
- **WHEN** a API de portfolio devolve uma linha de cliente
- **THEN** o JSON SHALL continuar incluindo `cnpj_masked` sem mudança de contrato para consumidores legados

### Requirement: Célula Cliente com máscara BR e cópia dos dígitos

Nas grades de monitoramento que usam `FiscalClientCell`, a célula Cliente SHALL exibir o CNPJ com a máscara visual padrão Brasil (`AA.AAA.AAA/AAAA-AA`) a partir do campo `cnpj` da linha, usando formatação só de exibição.

Ao clicar no CNPJ exibido, o sistema SHALL copiar para a área de transferência apenas os dígitos/caracteres normalizados (sem máscara) e MUST informar o operador com feedback de sucesso ou falha (toast).

O clique no CNPJ MUST NOT navegar para a ficha do cliente; a navegação permanece no nome/razão social.

Quando `cnpj` estiver ausente, a célula MAY exibir `cnpj_masked` legado e MUST NOT oferecer cópia de dígitos a partir da máscara ocultadora.

#### Scenario: Exibição com máscara Brasil
- **WHEN** a linha tem `cnpj` com 14 caracteres válidos e a grade renderiza `FiscalClientCell`
- **THEN** o CNPJ visível MUST usar a máscara `AA.AAA.AAA/AAAA-AA` e MUST NOT usar o formato ocultador `****`

#### Scenario: Clique copia só dígitos
- **WHEN** o operador clica no CNPJ exibido na célula
- **THEN** a área de transferência MUST receber o valor normalizado sem máscara e o sistema MUST exibir feedback de cópia

#### Scenario: Clique não navega
- **WHEN** o operador clica no CNPJ (não no nome)
- **THEN** a aplicação MUST NÃO navegar para a rota do cliente

#### Scenario: Fallback sem cnpj
- **WHEN** a linha não traz `cnpj` mas traz `cnpj_masked`
- **THEN** a célula MAY exibir o valor mascarado legado e MUST NOT tratar a máscara ocultadora como fonte de cópia de dígitos
