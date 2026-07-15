## ADDED Requirements

### Requirement: Paginação server-side em toda lista potencialmente não limitada
Clientes, Exportações, Documentos por empresa e qualquer lista tabular cujo universo possa crescer SHALL paginar ou usar cursor no backend, MUST retornar metadados suficientes ao controle visual do template e MUST NOT baixar todas as páginas para aplicar paginação, busca, filtro ou ordenação somente no navegador.

#### Scenario: Carteira com várias páginas de clientes
- **WHEN** o operador abre Clientes e muda busca, estado, ordenação ou página
- **THEN** o frontend solicita somente o recorte correspondente, atualiza a URL e mantém os KPIs do escritório independentes da página atual

#### Scenario: Histórico com mais de cinquenta exportações
- **WHEN** o usuário navega no histórico de Exportações
- **THEN** consegue acessar páginas anteriores sem limite silencioso aos cinquenta registros mais recentes

#### Scenario: Agregação por empresa
- **WHEN** o filtro de Documentos alcança muitas empresas e notas
- **THEN** o PostgreSQL agrega e pagina por empresa antes da resposta, sem carregar todas as notas e vínculos em memória da aplicação

### Requirement: Cursor sem simulação de offset
Uma API cursor-based SHALL oferecer navegação incremental e MUST NOT ser convertida em paginação aleatória que execute consultas intermediárias ocultas. Alterar filtro ou tamanho de lote SHALL limpar linhas e cursor da consulta anterior.

#### Scenario: Carregar mais documentos
- **WHEN** o operador solicita a próxima página do catálogo
- **THEN** o frontend usa exatamente o `next_cursor`, acumula as novas linhas e não consulta páginas intermediárias para alcançar um número de página

#### Scenario: Alteração de filtro
- **WHEN** busca, situação, cliente, competência ou tipo muda
- **THEN** a lista, seleção e cursor anteriores são reiniciados antes da primeira resposta do novo filtro

### Requirement: Vocabulário tabular em pt-BR
Cabeçalhos, estados vazios, ações e badges SHALL usar labels pt-BR do domínio, enquanto códigos técnicos MAY permanecer em dica, descrição ou detalhe acessível.

#### Scenario: Resultado técnico de importação
- **WHEN** um item possui estado `DUPLICATE`, `UNMATCHED` ou `QUARANTINED`
- **THEN** a tabela mostra “Duplicado”, “Sem vínculo” ou “Em quarentena” e preserva o código técnico no detalhe quando necessário

