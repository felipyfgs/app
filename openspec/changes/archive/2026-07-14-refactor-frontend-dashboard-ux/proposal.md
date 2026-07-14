## Por quê

O frontend já utiliza a estrutura básica do template oficial Nuxt UI Dashboard, mas as telas de domínio cresceram de forma desigual, com páginas monolíticas, padrões distintos para filtros, ações, detalhes e estados assíncronos. A refatoração é necessária agora para consolidar uma experiência previsível, responsiva e acessível antes de ampliar os fluxos operacionais e sua cobertura de testes.

## O que muda

- Preservar o shell já adaptado do template fixado em `.reference/nuxt-dashboard-template` e completar seus contratos de sidebar, command palette, atalhos, identidade do escritório, usuário, tema e alertas.
- Aplicar explicitamente os padrões internos do template: dashboard analítico, lista administrativa, mestre–detalhe responsivo e página de seções no estilo Settings.
- Definir uma hierarquia concreta: ação primária na navbar; filtro global ou subnavegação na toolbar; busca e utilidades de tabela no início do corpo; ações secundárias no fim da linha.
- Transformar Notas em mestre–detalhe, com painéis adjacentes redimensionáveis no desktop, slideover no mobile e `/notes/:accessKey` como rota canônica da seleção.
- Organizar o detalhe de Cliente em `Resumo`, `Estabelecimentos`, `Certificado A1` e `Sincronização`, seguindo o padrão Settings do template.
- Uniformizar tabelas no estilo visual do template sem trocar paginação server-side por paginação local demonstrativa.
- Extrair componentes reutilizáveis das páginas de Clientes, Notas, Exportações, Sincronizações e Administração, reduzindo páginas monolíticas sem alterar as regras de domínio.
- Tornar listas e detalhes responsivos, com apresentação adequada para desktop e viewport móvel.
- Uniformizar estados de carregamento, vazio, erro, sucesso, bloqueio e falta de permissão, sempre com mensagens sanitizadas.
- Preservar navegação e ações condicionadas aos papéis `ADMIN`, `OPERATOR` e `VIEWER` e ao segundo fator administrativo.
- Adicionar critérios de acessibilidade, navegação por teclado e testes visuais dos fluxos principais.
- Manter o frontend conectado exclusivamente à API Laravel/Sanctum por composables tipados, sem incorporar mocks ou dependências de runtime do template de referência.
- Não incorporar gráfico ou variação percentual enquanto a API não fornecer série temporal real.

## Capacidades

### Novas capacidades

- `frontend-dashboard-experience`: estabelece o contrato de composição visual, responsividade, interação, acessibilidade e feedback das telas autenticadas do painel interno.

### Capacidades modificadas

Nenhuma. As specs principais ainda não possuem capacidades sincronizadas; esta mudança complementa, sem substituir, os requisitos funcionais do change `build-nfse-adn-capture-system`.

## Impacto

- Afeta principalmente `frontend/app/layouts`, `frontend/app/pages`, `frontend/app/components`, composables de interface e testes do frontend.
- Não altera contratos da API, banco de dados, sincronização ADN, armazenamento de XML, exportações ou gestão criptográfica de certificados.
- Pode reorganizar componentes e rotas internas de apresentação, preservando URLs públicas do painel e autorizações existentes.
- Mantém `/notes/:accessKey` e `/clients/:id`; filtros e seções relevantes passam a ser representados na URL.
- Usa a cópia MIT em `.reference/nuxt-dashboard-template` apenas como referência de composição; ela não se torna dependência de produção.
- A implementação deverá ser coordenada com as tarefas 9.2 a 9.10 do change ativo para evitar duplicação de trabalho e marcação prematura de tarefas.

## Não-objetivos

- Redesenhar a identidade visual fora dos tokens e componentes do Nuxt UI 4.
- Criar portal para clientes finais, experiência mobile nativa ou aplicação separada.
- Alterar regras fiscais, fluxo por NSU, emissão ou cancelamento de NFS-e.
- Adicionar SSR, processo Node em produção ou comunicação cross-origin.
- Expor PFX, senha, chave privada, PEM, XML fiscal ou mensagens remotas não sanitizadas.
- Incorporar dados demonstrativos, mocks de API ou funcionalidades de negócio do template de referência.
- Copiar seleção em massa, seletor de colunas, gráfico ou paginação client-side quando não houver função real correspondente.
