## 1. Persistência e runtime

- [x] 1.1 Criar migrations e models tenant-scoped para operações, artefatos, RBT12 e preferências/dispatches/eventos de comunicação, ampliando a projeção fiscal sem editar migrations históricas.
- [x] 1.2 Adicionar `poppler-utils` à imagem PHP e um extrator `pdftotext` limitado, observável e testável.

## 2. Contratos e projeção SERPRO

- [x] 2.1 Corrigir catálogo, coordenadas, fixtures e surface registry das operações oficiais PGDAS-D 13–16, excluindo operações de emissão desta superfície.
- [x] 2.2 Implementar codecs separados dos payloads/respostas 13–16 e corrigir o adapter Simples/MEI para consumir `response.dados`.
- [x] 2.3 Sanitizar respostas documentais antes do attempt store, armazenar PDFs no cofre e oferecer reidratação segura para replay/download.
- [x] 2.4 Projetar original, retificadoras e DAS de forma idempotente, sem vínculo artificial e com escolha determinística da última declaração.
- [x] 2.5 Implementar source key, reserva única, consulta 16 e parser versionado de RBT12 sem retry automático.
- [x] 2.6 Corrigir o scheduler para congelar o PA/ano do serviço 13 e avançar a última consulta somente em resposta real produtiva.
- [x] 2.7 Implementar estado fail-closed da declaração e ajuste de próximo dia bancário condicionado a calendário verificado.

## 3. APIs tenant-scoped

- [x] 3.1 Enriquecer a carteira PGDAS-D e implementar histórico local com estado, RBT12 e proveniência.
- [x] 3.2 Implementar coleta explícita de documentos 14/15 e download autorizado de artefatos, sem consulta em GET/modal.
- [x] 3.3 Implementar preferências individual/lote com optimistic lock, preview mascarada e rastreamento somente leitura.

## 4. Interface PGDAS-D

- [x] 4.1 Ampliar tipos/composables do frontend para os contratos PGDAS-D e comunicação.
- [ ] 4.2 Reconciliar o renderer PGDAS-D com as sete colunas do template operacional, movendo última declaração e RBT12 para tooltip/histórico e mantendo seleção acessível.
- [ ] 4.3 Reconciliar Ações, Enviar, Rastreio de envio, Última Busca e Histórico de Busca com os modais locais, sem consulta nem envio ao visualizar.

## 5. Verificação

- [x] 5.1 Cobrir codecs, histórico, estado, documentos protegidos, RBT12 e idempotência com testes Laravel/fixtures.
- [x] 5.2 Cobrir tenancy, papéis, lote atômico, concorrência, mascaramento e ausência de jobs/mails/eventos.
- [ ] 5.3 Cobrir a ordem exata das sete colunas, ausência das colunas antigas, tooltips, menus, modais, seleção em massa e permissões com testes unitários Nuxt.
- [ ] 5.4 Executar Pint, testes Laravel focados, `pnpm run test:gate`, generate, fidelity e validação OpenSpec; registrar qualquer limitação ambiental.

## 6. Fechamento

- [ ] 6.1 Sincronizar specs principais, arquivar a change e criar commit atômico após todos os gates e autorização de fechamento.
