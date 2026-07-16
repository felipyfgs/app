## Why

A implementação atual apresenta o CT-e como uma área autônoma em Configurações, embora ele seja um tipo de documento fiscal capturado pelo mesmo catálogo de NF-e e NFC-e. Essa separação cria uma taxonomia incorreta, duplica navegação e leva o operador para fora do fluxo natural de consulta, cobertura, pendências, importação e download de documentos.

## What Changes

- Tratar CT-e como tipo documental de primeira classe da superfície **Documentos**, ao lado de NF-e e NFC-e, mantendo diferenças de origem, papel, qualidade e cobertura apenas como atributos e filtros do documento.
- Tornar `/docs/catalog` o destino canônico da visão **Por documento** para NF-e, NFC-e e CT-e e concentrar ali os estados e ações documentais hoje isolados no onboarding CT-e.
- Remover CT-e da navegação e das seções de Configurações, eliminar a página dedicada `/settings/cte` e manter redirecionamento compatível dessa URL para `/docs/catalog` com o contexto CT-e aplicável.
- Migrar para o catálogo todos os deep-links, atalhos, permissões de rota, estados de carregamento/erro/vazio e testes que hoje dependem de `/settings/cte`.
- Corrigir os requisitos da change `complete-cte-capture-with-distdfe-autxml-and-import` que induziram a superfície separada, preservando saúde de canais em Sincronizações e gestão sensível de identidade/A1 na Administração.

## Capabilities

### New Capabilities

Nenhuma.

### Modified Capabilities

- `frontend-dashboard-experience`: consolidar a experiência de CT-e na superfície Documentos, definir `/docs/catalog` como rota da visão por documento e remover CT-e de Configurações sem perder deep-links, estados, permissões ou compatibilidade da URL antiga.
- `fiscal-document-catalog`: explicitar paridade de CT-e com NF-e e NFC-e na listagem, detalhe, filtros, pendências, importação, exportação e download do catálogo unificado, mantendo metadados específicos como atributos documentais.

## Impact

- Frontend Nuxt: navegação/command palette, layout de Configurações, middleware de rota, `/settings/cte`, `/docs/catalog`, `NotesWorkspace`, links de Sincronizações e Exportações e componentes CT-e reutilizados pelo catálogo.
- Testes: unitários de navegação e middleware, E2E de operações CT-e, catálogo, troca de escritório, permissões e redirecionamento legado.
- Documentação/OpenSpec: requisitos conflitantes da change CT-e ativa, documentação de rotas do frontend e matriz da superfície Documentos.
- APIs e persistência: sem nova API, migration ou alteração do modelo fiscal; os endpoints CT-e existentes continuam tenant-scoped e são consumidos dentro da experiência unificada.

## Não-objetivos

- Alterar captura CT-e, cursores NSU, regras de `autXML`, importação, qualidade do artefato ou deduplicação do catálogo.
- Misturar saúde operacional dos canais com o catálogo: cursores e falhas continuam em Sincronizações/saúde, com deep-link para Documentos quando a ação for documental.
- Mover cadastro de identidade fiscal, A1 ou qualquer segredo para o catálogo; operações sensíveis permanecem na Administração com 2FA e sem exposição de material criptográfico.
- Criar um módulo CT-e paralelo, uma nova API específica de catálogo ou tratamento incompatível com o isolamento por `office_id`.
