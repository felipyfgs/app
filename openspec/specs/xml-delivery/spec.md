# XML Delivery

## Purpose

Download individual auditado e exportação ZIP assíncrona com estrutura determinística e expiração.

## Requirements

### Requirement: Download individual auditado
O sistema SHALL permitir o download do XML original por usuário autorizado e SHALL registrar usuário, documento, escritório, horário e resultado.

#### Scenario: Download autorizado
- **WHEN** um usuário autorizado solicita o XML de uma nota de seu escritório
- **THEN** o sistema descriptografa e transmite o conteúdo original como anexo sem expor o caminho de armazenamento

### Requirement: Exportação ZIP assíncrona
O sistema SHALL criar ZIPs em fila a partir dos mesmos filtros do catálogo, com opção explícita para incluir eventos.

#### Scenario: Solicitação de exportação
- **WHEN** um usuário autorizado envia filtros válidos
- **THEN** o sistema cria uma exportação `PENDING`, retorna seu identificador e a processa fora da requisição web

### Requirement: Estrutura determinística do ZIP
O sistema SHALL organizar cada NFS-e como `CNPJ/AAAA-MM/papel/chave.xml`, usando `sem-competencia` quando a competência não existir e nomes seguros para filesystem.

#### Scenario: ZIP concluído
- **WHEN** o job termina uma exportação com notas emitidas e recebidas
- **THEN** cada XML aparece uma única vez no diretório correspondente ao interesse selecionado

### Requirement: Entrega privada e expiração
O sistema MUST manter exportações fora da área pública, autorizá-las a cada download e apagá-las 24 horas após a conclusão.

#### Scenario: Exportação expirada
- **WHEN** um usuário solicita uma exportação após seu prazo de 24 horas
- **THEN** o sistema nega o download, marca o recurso como expirado e remove o arquivo remanescente

### Requirement: Falhas de exportação observáveis
O sistema SHALL marcar uma exportação como `FAILED` com mensagem sanitizada quando não puder concluí-la, sem deixar ZIP parcial disponível.

#### Scenario: Falha ao ler um XML
- **WHEN** qualquer objeto selecionado não pode ser descriptografado
- **THEN** o job remove o ZIP parcial, registra a falha e não oferece link de download
