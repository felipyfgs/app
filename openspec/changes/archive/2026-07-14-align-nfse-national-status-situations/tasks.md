## 1. Backend — mapeamento e projeção

- [x] 1.1 Corrigir `NfseXmlParser::mapOfficialStatus` (100 ACTIVE, 101 SUBSTITUTE, 102 JUDICIAL, 103 ACTIVE, default UNKNOWN)
- [x] 1.2 Tabela/helper de descrição oficial curta por cStat (para API/detalhe)
- [x] 1.3 Ao processar eventos ADN, atualizar `nfse_notes.status` (CANCELLED / SUPERSEDED) sem apagar XML
- [x] 1.4 Testes unitários parser: cStat 100, 101, 102, ausente; evento cancela/substitui

## 2. Backend — dados e API

- [x] 2.1 Comando/script de remap do piloto (101+CANCELLED → SUBSTITUTE se sem evento de cancelamento)
- [x] 2.2 Garantir list/show/insights expõem status + official_status_code coerentes
- [x] 2.3 Ajustar insights: review = UNKNOWN; cancelled = CANCELLED; opcional contagem de SUBSTITUTE

## 3. Frontend — labels e triagem

- [x] 3.1 `statusLabel` + `AppStatusBadge`: SUBSTITUTE, SUPERSEDED, JUDICIAL, CANCELLED, ACTIVE=Gerada
- [x] 3.2 Filtros Notes/Export: opções NFS-e (remover Autorizada como situação de nota)
- [x] 3.3 Insights/filas de triagem alinhadas aos novos status
- [x] 3.4 Modal de detalhe: situação + linha cStat + descrição

## 4. Fechamento

- [x] 4.1 Validar com amostra de notas reais do piloto (100 / 101 / canceladas)
- [x] 4.2 Marcar tarefas e deixar change pronta para archive após aceite
