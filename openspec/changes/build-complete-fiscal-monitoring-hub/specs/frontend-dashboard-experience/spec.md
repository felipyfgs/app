## MODIFIED Requirements

### Requirement: Shell autenticado equivalente ao template de referência
O sistema SHALL manter, em todas as rotas autenticadas, um shell composto por sidebar recolhível e redimensionável, navegação vertical, command palette, identidade do escritório ativo, menu do usuário e painel global de alertas, adaptado do template fixado no commit `0f30c09`. Usuários com múltiplas memberships SHALL poder trocar somente entre escritórios autorizados por um controle explícito e acessível.

#### Scenario: Sidebar expandida ou recolhida
- **WHEN** o usuário expande ou recolhe a sidebar em desktop
- **THEN** os mesmos destinos continuam disponíveis e os itens recolhidos possuem identificação acessível por tooltip e nome acessível

#### Scenario: Navegação móvel
- **WHEN** o usuário escolhe um destino pela sidebar em viewport móvel
- **THEN** a navegação fecha e o conteúdo de destino recebe o espaço principal da tela

#### Scenario: Escritório ativo no cabeçalho
- **WHEN** a identidade autenticada contém um escritório ativo
- **THEN** o shell exibe sua identidade e oferece somente memberships autorizadas quando a troca estiver disponível

#### Scenario: Troca de escritório
- **WHEN** o usuário confirma outro escritório autorizado
- **THEN** a UI invalida dados tenant-scoped, atualiza o shell e recarrega a rota sem misturar resultados anteriores

## ADDED Requirements

### Requirement: Navegação fiscal segue o catálogo de módulos
A interface SHALL oferecer Dashboard Fiscal, Simples Nacional/MEI, DCTFWeb/MIT, Parcelamentos, Situação Fiscal, Caixas Postais, Declarações, Guias e FGTS conforme permissão, feature flag e cobertura, reutilizando os arquétipos do template oficial.

#### Scenario: Módulo indisponível para a coorte
- **WHEN** um módulo não está habilitado para o tenant
- **THEN** a navegação o oculta ou apresenta estado indisponível coerente, sem ação falsa ou dados demonstrativos

### Requirement: Tabelas fiscais são server-side e tenant-scoped
O sistema SHALL usar tabelas server-side com busca, filtros, competência, situação, cobertura, paginação e ações por registro, preservando filtros reproduzíveis na URL.

#### Scenario: Filtrar clientes pendentes
- **WHEN** usuário aplica categoria, competência e situação `PENDING`
- **THEN** a UI reinicia paginação, consulta a API do tenant ativo e atualiza a URL sem carregar toda a carteira localmente

### Requirement: Estados de cobertura são visíveis por texto e fonte
A interface MUST distinguir dados atuais, processando, pendentes, em atenção, desconhecidos, não aplicáveis, não suportados e bloqueados por texto, ícone e origem, sem depender somente de cor.

#### Scenario: Fonte sem API oficial
- **WHEN** a informação desejada não possui integração M2M oficial
- **THEN** a tela mostra `Não suportado`, a limitação e a fonte disponível, sem botão de atualização por portal

### Requirement: Consumo do plano não expõe custo global
A interface SHALL mostrar ao tenant uso atribuído, franquia, saldo, alertas e período conforme seu plano e MUST NOT revelar fatura consolidada, preço comercial interno ou consumo de outros escritórios.

#### Scenario: Admin do tenant abre consumo
- **WHEN** usuário autorizado acessa o detalhamento mensal
- **THEN** a tabela mostra apenas operações/agregados do escritório ativo e valores permitidos pelo plano

### Requirement: Ação mutante apresenta confirmação fiscal reforçada
A interface MUST apresentar contribuinte, competência, efeito, fonte, custo estimado, estado de procuração e consequência antes de emissão ou transmissão e SHALL solicitar 2FA recente quando exigido.

#### Scenario: Transmissão em coorte somente leitura
- **WHEN** usuário acessa detalhe de obrigação no piloto
- **THEN** a UI não oferece transmissão e explica que a coorte está restrita a monitoramento

