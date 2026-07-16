## Why

As rotas de Monitoramento Fiscal já existem, mas o ambiente de demonstração não popula as tabelas fiscais e várias telas ainda consomem contratos genéricos ou campos diferentes dos retornados pelas APIs. Isso deixa o produto visualmente vazio e impede validar, com dados representativos, os fluxos de Dashboard, Simples/MEI, DCTFWeb/MIT, Parcelamentos, Situação Fiscal, Caixas Postais, Declarações, Guias, FGTS e detalhe fiscal do cliente.

## What Changes

- Completar a experiência visual de todas as rotas em `frontend/app/pages/monitoring`, preservando o shell e os arquétipos do Nuxt UI Dashboard fixado e adotando das referências visuais fornecidas apenas a direção de densidade: navegação horizontal do módulo, indicadores, filtros, tabela de carteira, ações e estados compactos.
- Corrigir os contratos frontend/backend hoje divergentes e substituir `Record<string, unknown>` nos fluxos principais por tipos fiscais explícitos, sem fallback que apresente dados de outro módulo.
- Criar um read model tenant-scoped de visão geral por módulo, com totais de carteira, situações, cobertura, atualização, agenda e indicadores auxiliares necessários aos cabeçalhos das páginas.
- Criar fixtures fiscais determinísticas e idempotentes no backend para `local` e `testing`, persistidas nas mesmas tabelas e lidas pelas mesmas APIs do produto, cobrindo clientes, vínculos, competências, execuções, snapshots, findings, pendências, DCTFWeb/MIT, parcelamentos, SITFIS, mensagens, declarações, guias, FGTS/eSocial e consumo atribuído.
- Identificar visualmente o ambiente/dado de demonstração e proibir que seeder, fixture, transporte fake ou fallback sintético seja carregado em produção.
- Tornar funcionais filtros, busca por cliente/CNPJ, paginação, submódulos, detalhes, triagem interna, deep-links e ações permitidas; ações fiscais externas permanecem bloqueadas ou explicitamente simuladas no ambiente demo.
- Completar o detalhe fiscal por cliente e o mestre–detalhe de Caixa Postal, eliminando abas sem conteúdo e alinhando rótulos/estados aos contratos reais.
- Adicionar testes de contrato, componentes, E2E e regressão visual com fixtures sanitizadas para desktop, mobile, loading, vazio, erro e dados preenchidos.

## Capabilities

### New Capabilities

- `fiscal-monitoring-demo-fixtures`: dataset fiscal sintético, determinístico, tenant-scoped, sanitizado e estritamente limitado a `local`/`testing`, incluindo sua carga, identificação, reinicialização e proibição em produção.

### Modified Capabilities

- `frontend-dashboard-experience`: completar as páginas e fluxos do Monitoramento Fiscal com contratos tipados, navegação do módulo, indicadores, filtros, tabelas, detalhes e estados responsivos realmente funcionais.
- `dashboard-template-fidelity`: incluir todas as rotas de Monitoramento na matriz de derivação e na validação visual determinística em desktop e mobile.

## Impact

- **Frontend:** páginas em `frontend/app/pages/monitoring`, componentes fiscais compartilhados, `useApi`, tipos fiscais, filtros URL, navegação, estados e testes Playwright/Vitest.
- **Backend:** seeder/factories de demonstração, agregações tenant-scoped, resources/DTOs tipados e possíveis extensões compatíveis nos endpoints REST v1 fiscais; nenhuma API atual será removida.
- **Dados:** tabelas fiscais existentes receberão registros marcados de demonstração somente no tenant demo e apenas em `local`/`testing`; o processo deverá ser reexecutável sem duplicação.
- **Segurança:** nenhuma fixture conterá PFX, senha, PEM, token, Termo XML, XML fiscal real ou identificador de cofre utilizável; consultas continuam isoladas por `office_id`.
- **Operação:** o ambiente local passará a permitir avaliação visual completa sem SERPRO, eSocial, certificado ou serviço externo; produção continuará sem fallback sintético.

## Não-objetivos

- Declarar que dados sintéticos são consultas, transmissões, pagamentos ou evidências fiscais reais.
- Habilitar scraping, automação de portal, CAPTCHA, sessão Gov.br ou cobertura integral do FGTS Digital.
- Executar transmissão de declaração, emissão fiscal, adesão a parcelamento ou outra mutação externa para tornar a demonstração “clicável”.
- Copiar marca, identidade, código ou layout proprietário da HubStorm; as capturas servem somente como referência de densidade e hierarquia, enquanto a forma obrigatória continua sendo o template Nuxt UI fixado no repositório.
- Criar um segundo frontend, servidor mock de runtime, API paralela ou arrays hardcoded como fonte de verdade das páginas.
