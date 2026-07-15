## Why

A software house precisa transformar a base fiscal atual em uma plataforma SaaS multi-escritório na qual empresas contábeis contratam o MonitorHub para monitorar seus contribuintes. O contrato, as credenciais e o faturamento do Integra Contador devem pertencer à plataforma, enquanto cada escritório autoriza a atuação da plataforma como Autor do Pedido de Dados/procurador e permanece isolado como tenant.

## What Changes

- **BREAKING de escopo:** substituir o modelo de implantação interna para um único escritório pelo modelo SaaS multi-escritório, mantendo `office_id` como fronteira obrigatória dos dados fiscais e sem criar portal para os clientes finais dos escritórios.
- Contratar o Integra Contador uma única vez no e-CNPJ da software house; manter `Consumer Key`, `Consumer Secret`, certificado contratante, Bearer/JWT e configuração do contrato em escopo global da plataforma e no `SecureObjectStore`.
- Representar explicitamente a cadeia `Contratante da API → Autor do Pedido de Dados/procurador → Contribuinte`, exigindo Termo de Autorização XML assinado pelo escritório e procuração eletrônica válida por contribuinte e serviço.
- Criar onboarding do escritório tenant, identidade fiscal do autor, assinatura/renovação do Termo de Autorização, elegibilidade, suspensão, plano, limites e estado operacional sem expor certificados, termos, tokens ou credenciais SERPRO.
- Medir toda chamada externa em ledger imutável por escritório, contribuinte, sistema, serviço e classe faturável; conciliar o consumo agregado da plataforma e permitir franquias, alertas e excedentes sem implementar cobrança bancária no MVP.
- Criar um núcleo de monitoramento fiscal com categorias, vínculos, agendas, competências, execuções, snapshots imutáveis, evidências, achados, pendências, artefatos e idempotência.
- Adicionar Dashboard Fiscal e páginas para Simples Nacional/MEI, DCTFWeb/MIT, Parcelamentos, Situação Fiscal, Caixas Postais, Declarações, Guias e FGTS Digital, mantendo o arquétipo obrigatório do Nuxt UI Dashboard.
- Integrar os serviços oficialmente cobertos por Integra-SN, Integra-MEI, Integra-DCTFWeb, Integra-Parcelamento, Integra-SITFIS, Integra-CaixaPostal, Integra-Procurações, Integra-Pagamento e Sicalc.
- Usar Eventos de Última Atualização e caches oficiais para direcionar consultas, limitar custos e evitar varreduras indiscriminadas de todos os contribuintes.
- Tratar FGTS Digital como cobertura parcial via eventos, recibos e totalizadores oficiais do eSocial; não afirmar consulta de guias, pagamentos ou pendências do portal sem API pública oficial.
- Restringir leitura a `VIEWER`, operações assistidas a `OPERATOR` e ações fiscais mutantes ou administrativas a `ADMIN` com 2FA recente, confirmação explícita, idempotência e auditoria.
- Implantar por etapas: contrato e cofre globais, trial com mocks, autorização de escritório, piloto produtivo somente leitura, medição/conciliação, guias assistidas e mutações explicitamente autorizadas.

## Capabilities

### New Capabilities

- `platform-tenant-governance`: onboarding, ciclo de vida, plano, suspensão e isolamento dos escritórios que contratam a plataforma, com administração global sem acesso fiscal implícito.
- `serpro-integra-contador-access`: contrato global da software house, credenciais, mTLS/OAuth2, Termo de Autorização por escritório, procurações, elegibilidade e saúde da integração SERPRO.
- `serpro-api-usage-ledger`: classificação, atribuição, orçamento, franquia, alerta, agregação e conciliação do consumo faturável do SERPRO por tenant e serviço.
- `fiscal-monitoring-core`: catálogo de categorias, vínculos, agendas, competências, execuções, snapshots, evidências, achados, estados e idempotência compartilhados pelos módulos fiscais.
- `simples-mei-monitoring`: monitoramento de PGDAS-D, DEFIS, Regime de Apuração, PGMEI, CCMEI e DASN-SIMEI.
- `dctfweb-mit-monitoring`: monitoramento de DCTFWeb e MIT, incluindo eventos de atualização, apurações, recibos, XMLs e guias.
- `tax-installment-monitoring`: consulta e acompanhamento das modalidades oficiais de parcelamento do Simples Nacional e MEI.
- `fiscal-situation-monitoring`: execução assíncrona de SITFIS, preservação do relatório e normalização de pendências.
- `fiscal-mailbox-monitoring`: monitoramento de Caixa Postal e DTE, conteúdo autorizado, alertas e trilha de leitura.
- `tax-declaration-monitoring`: consolidação de obrigações e declarações cobertas por fonte oficial, competência, prazo, situação e evidência.
- `tax-guide-management`: emissão, armazenamento, entrega interna, validade, pagamento conhecido e controles de risco para guias fiscais.
- `fgts-esocial-monitoring`: cobertura parcial e explicitamente rotulada do FGTS a partir de eventos, recibos e totalizadores oficiais do eSocial.

### Modified Capabilities

- `operations-dashboard`: incluir resumo fiscal, autorizações, consumo SERPRO, limites, falhas e deep-links dos novos módulos, sempre no tenant ativo.
- `office-access-control`: adaptar autenticação e autorização ao SaaS multi-escritório, separar papéis do tenant da administração da plataforma e reforçar 2FA para ações fiscais de alto risco.
- `frontend-dashboard-experience`: adicionar contexto do tenant, navegação e páginas dos módulos fiscais, tabelas, filtros, estados de cobertura e detalhe mestre–cliente conforme o template oficial.

## Impact

- **Domínio e dados:** `office_id` continua obrigatório em dados de tenant; contrato SERPRO, credenciais contratantes, tabela de preços e consolidação de fatura são exceções explicitamente globais. Autorizações, procurações, consumo e dados fiscais permanecem vinculados ao escritório.
- **Backend:** novos contratos de cliente externo, modelos, migrations, jobs, scheduler, parsers, policies, endpoints REST v1, auditoria, métricas e integrações com Integra Contador/eSocial.
- **Frontend:** onboarding e administração do escritório, novo grupo de Monitoramento, Dashboard Fiscal, páginas por categoria, centrais de declarações/guias, detalhe do cliente e consumo do plano.
- **Infraestrutura:** certificado e chaves SERPRO globais, objetos seguros por escritório, filas dedicadas, limites de consumo, circuit breakers, observabilidade, backup e teste de restauração.
- **Segurança e conformidade:** respostas fiscais, relatórios, mensagens, recibos, guias, termos e tokens exigem classificação, retenção, criptografia, sanitização, autorização e auditoria próprias.
- **Operação comercial:** a software house recebe e concilia a fatura do SERPRO; cada escritório contrata um plano da plataforma e tem seu consumo atribuído, sem receber ou controlar as credenciais globais da API.
- **Compatibilidade:** nenhuma API atual é removida nesta change; módulos documentais existentes permanecem independentes e passam a contribuir para o resumo operacional do tenant.

## Não-objetivos

- Criar login ou portal para os contribuintes finais atendidos pelos escritórios.
- Automatizar portais por scraping, navegador robotizado, CAPTCHA, Gov.br, cookies ou sessões humanas.
- Declarar cobertura integral do e-CAC, FGTS Digital, ECD, ECF, EFD-Reinf ou obrigação sem fonte oficial comprovada.
- Implementar gateway de pagamento, emissão de nota fiscal da assinatura, cobrança bancária, conciliação bancária completa ou motor tributário de precificação no MVP.
- Compartilhar, sublicenciar ou expor `Consumer Key`, `Consumer Secret`, PFX, senha, chave privada, PEM, termo assinado ou token SERPRO a escritórios ou contribuintes.
- Transmitir declarações, aderir a parcelamentos, confessar débitos ou executar outra mutação de alto risco no piloto somente leitura.
- Alterar os canais ADN/SEFAZ existentes, seus cursores NSU/nNF ou as garantias de imutabilidade dos documentos fiscais.
- Assumir que o contrato eletrônico do SERPRO autoriza repasse ou marcação comercial do consumo sem validação jurídica/comercial expressa antes do piloto produtivo.
