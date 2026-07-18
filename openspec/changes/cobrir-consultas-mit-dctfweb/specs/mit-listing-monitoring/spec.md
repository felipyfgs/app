## ADDED Requirements

### Requirement: Consulta MIT de lista de apurações
O sistema SHALL executar `mit.listaapuracoes` exclusivamente pela capability
`dctfweb`, com DTO validado e coordenada oficial `LISTAAPURACOES317`, e SHALL
recusar payload malformado, capability desabilitada ou resposta não reconhecida
antes de projetar dados ao escritório.

#### Scenario: Consulta offline produtiva
- **WHEN** o ambiente de teste entrega fixture sintética válida de 317 para um cliente do escritório atual
- **THEN** o adapter projeta a lista normalizada sem instanciar `HttpIntegraContadorClient` nem abrir conexão de rede

#### Scenario: Filtro inválido
- **WHEN** a consulta recebe ano ou filtro fora do contrato tipado
- **THEN** a API retorna erro validado e não cria job, projeção ou artefato

### Requirement: Lista MIT tenant-scoped na interface
O sistema SHALL expor à cápsula MIT somente descritores/projeções pertencentes
ao `CurrentOffice`, SHALL mostrar a disponibilidade de apurações locais na UI e
MUST NOT disparar coleta quando o usuário abrir a página, o detalhe ou o
histórico.

#### Scenario: Listagem local disponível
- **WHEN** existem apurações 317 persistidas para o cliente do escritório atual
- **THEN** a UI mostra o total e o detalhe local usando a resposta da API sem bytes, XML, segredo ou referência ao cofre

#### Scenario: Recurso de outro escritório
- **WHEN** a sessão tenta consultar ou baixar uma evidência MIT de outro office
- **THEN** a API não a lista e responde ausência/negação sem ler o cofre

### Requirement: Download DCTFWeb preserva descritor seguro
O sistema SHALL usar `download_path` fornecido pela API para documentos locais
DCTFWeb, SHALL preservar `content_type` e filename sanitizados no streaming e
MUST NOT assumir PDF para evidência XML.

#### Scenario: Documento XML existente
- **WHEN** o histórico possui evidência XML autorizada
- **THEN** o link same-origin usa o `download_path` e o download retorna MIME e nome XML seguros
