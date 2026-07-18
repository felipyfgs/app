# Matriz de filtros das tabelas

Levantamento das superfícies tabulares do painel. A regra é expor apenas filtros que alteram
de fato a consulta completa (servidor ou feed), nunca apenas a página carregada.

## Padrão visual

- Busca textual à esquerda, quando suportada.
- Filtros estruturados, presets, atualização e colunas à direita.
- Sem busca: o grupo de ações permanece à direita.
- Mobile: busca em largura total e ações em faixa horizontal rolável.
- Checkbox, ações, switches, downloads, histórico e rastreio não são campos filtráveis.

## Monitoramento fiscal

| Tabela | Colunas de dados | Filtros disponíveis | Lacunas de API |
|---|---|---|---|
| Simples Nacional (PGDAS-D) | Cliente, Situação, Últ. declaração, Sublimite | Busca, Cliente, Competência; Situação pela faixa KPI | Sublimite |
| MEI (PGMEI) | Cliente, Situação e dados operacionais | Busca, Cliente; Situação pela faixa KPI | Ano e estados específicos do PGMEI |
| DCTFWeb | Cliente, Situação, Últ. declaração | Busca, Cliente, Situação, Competência | Estados de transmissão/pagamento internos |
| MIT | Cliente, Competência, Situação, Encerramento, Apurações | Busca, Cliente, Situação, Competência | Encerramento e presença de apurações |
| Declarações | Cliente, Obrigação, Aplicabilidade, Vencimento, Entrega, Evidência, Abertas | Busca, Cliente, Situação, Competência, Status de entrega | Obrigação, aplicabilidade, vencimento e evidência |
| FGTS/eSocial | Cliente, Fechamento, Totalização, Guia, Pagamento | Busca, Cliente, Situação, Competência | Estados individuais das quatro etapas |
| SITFIS | Cliente, Procuração, Agenda, Idade/TTL, Achados, Cobertura | Busca, Cliente, Situação, Cobertura | Procuração, TTL e quantidade de achados |
| Parcelamentos | Cliente, Modalidade, Pedido, Saldo, Parcelas, Próxima parcela, Atraso, Guia | Busca, Cliente, Situação, Modalidade | Pedido, faixas de saldo/parcela/data/atraso e guia |
| Guias | Cliente, Sistema/tipo, Valor, Vencimento, Emissão, Pagamento, Validade, Versão | Cliente, Status de pagamento | Busca, tipo, faixas de valor/data, emissão, validade e versão |
| Cadastro/Vínculos | Cliente, Vínculo, Status, Fonte, Atualizado | Busca server-side (cliente/CNPJ/vínculo/proveniência), Cliente, Status | Intervalo de atualização; Fonte já é pesquisável, mas ainda não possui chip dedicado |
| Processos fiscais | Cliente, Processo, Status, Fonte, Atualizado | Busca server-side (cliente/CNPJ/número/proveniência), Cliente, Status | Intervalo de atualização; Fonte já é pesquisável, mas ainda não possui chip dedicado |
| Caixa Postal | Cliente, Triagem e dados da mensagem | Cliente, Triagem | Busca, assunto/remetente e intervalo de recebimento |

## Demais listas

| Tabela | Contrato atual |
|---|---|
| Clientes | Busca, estado, recorte operacional e filtros salvos |
| Documentos por cliente | Busca, situação de captura e colunas |
| Processos de trabalho | Busca e filtros estruturados com presets |
| Modelos de processo | Busca e atualização |
| Fechamento | Competência e filtros estruturados com presets |
| Saúde operacional | Severidade e tipo |
| Escritórios | Busca e estado do ciclo de vida |
| Itens de lote de importação | Status do resultado |
| Catálogo SERPRO | Ambiente e suporte |
| Contratos SERPRO | Ambiente |

## Critério para novas colunas

Uma nova coluna deve ser classificada como:

1. `filterable-server`: parâmetro validado e aplicado antes da paginação;
2. `filterable-local`: permitido apenas quando a coleção inteira está carregada;
3. `not-filterable`: coluna de ação, apresentação derivada sem contrato ou dado sem suporte.

Filtros `filterable-server` devem entrar no DTO/validação do backend, no client API, no estado
normalizado da tela e na definição do `DataTableFilterRoot` na mesma entrega.
