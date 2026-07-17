## Why

O painel de monitoramento hoje pode apresentar dados demonstrativos, estados desconhecidos e campos inferidos com a mesma aparência de um resultado fiscal oficial. Antes de otimizar cada módulo, o produto precisa tornar origem, suporte, frescor e limitações do dado explícitos, para que o escritório tome decisões somente sobre informações confiáveis.

## What Changes

- Estabelecer um contrato público comum para as carteiras fiscais, preservando origem, frescor e todos os estados canônicos já produzidos pelo read model.
- Exibir de forma obrigatória quando o conteúdo for demonstrativo/sintético, indisponível, bloqueado, não suportado, não aplicável ou desatualizado; resultado sintético não contará como evidência nem KPI produtivo.
- Tornar os contadores mutuamente exclusivos, completos e reconciliados com o total da carteira, incluindo estados sem resultado fiscal.
- Proibir que estado desconhecido, bloqueado ou sem suporte receba apresentação, KPI ou ação de sucesso; ausência de evidência permanecerá desconhecida.
- Aplicar o contrato compartilhado ao resumo, listas e detalhes existentes sem alterar rotas, ordem, visibilidade dos módulos ou arquétipos visuais já definidos.
- Tratar cada superfície de monitoramento como uma responsabilidade própria, com `operation_key`, estado do catálogo, tipo de retorno e local de visualização definidos na matriz `page-payload-matrix.md`.
- Diferenciar retorno estruturado, documento PDF/recibo, processamento assíncrono, agregado e capacidade indisponível; cada página mostrará somente os campos úteis ao gerenciamento daquele módulo.
- Oferecer `Ver/Baixar documento oficial` apenas quando existir `FiscalEvidenceArtifact` autorizado para o `CurrentOffice`; JSON bruto, envelope, Base64, XML bruto, protocolo e identificadores internos não serão superfície pública.
- Preservar isolamento por `CurrentOffice`; `office_id` enviado pelo cliente continuará sem autoridade de escopo.
- Corrigir o estado vazio compartilhado para que defaults como `all` não sejam tratados como filtros aplicados e loading real não apareça como carteira vazia.
- Non-goals: corrigir em profundidade `response->body` versus `response->dados`, reimplementar codecs/mappers e todas as colunas específicas de cada família, ocultar/reordenar módulos, alterar filtros/presets ou rotas, otimizar N+1/dashboard, emitir novas guias/declarações ou outras mutações, executar live smoke SERPRO, abrir tickets externos, tratar jurídico/LGPD ou habilitar flags/canais SEFAZ.

## Capabilities

### New Capabilities

Nenhuma.

### Modified Capabilities

- `serpro-monitoramento-familias`: ampliar o contrato das carteiras para tornar proveniência e frescor visíveis, preservar estados sem resultado, reconciliar contadores e definir responsabilidade/retorno/evidência de cada página sem inferência de sucesso.

## Impact

- `backend/app/Services/FiscalMonitoring/**` e DTOs tenant-scoped: contadores completos e origem/frescor consistentes sob o mesmo escopo de carteira.
- `backend/resources/serpro/official-service-catalog.v2026-07-16.json`, registro de contratos de superfície, `FiscalEvidenceStore` e DTOs públicos: coordenadas validadas, projeções allowlisted e links de evidência gerados no servidor.
- `frontend/app/composables/**`, `frontend/app/components/{fiscal,monitoring}/**` e `frontend/app/pages/monitoring/**`: origem/frescor visíveis, estados honestos, KPIs reconciliados e vazio canônico.
- `openspec/changes/tornar-monitoramento-fiscal-confiavel/page-payload-matrix.md`: fonte de aceite page-by-page para responsabilidade, operação, retorno e lugar de visualização.
- Testes backend e unitários frontend com fixtures oficiais/sintéticas; nenhuma chamada SERPRO real será necessária para aceite em CI.
- A implementação deve ocorrer depois da verificação da change ativa `reorganizar-rotas-monitoramento`, preservando suas rotas canônicas e todos os módulos visíveis.
