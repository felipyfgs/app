## Por quê

O monitoramento DCTFWeb atual combina dados da declaração e do MIT em uma grade genérica, infere evidência e pagamento sem base oficial e não reproduz a organização operacional usada pelo escritório. A página precisa ter contrato e renderer próprios, fiel à referência visual, com consultas fail-closed e documentos protegidos.

## O que muda

- Especializar a cápsula DCTFWeb com as oito colunas fiscais, nesta ordem: Situação, Últ. Declaração, Ações, Enviar, Cliente, Rastreio de envio, Última Busca e Histórico de Busca. Quando o perfil puder consultar, uma coluna técnica de seleção poderá precedê-las exclusivamente para consulta manual em massa.
- Separar renderer, contrato e estado da DCTFWeb daqueles da cápsula MIT, mantendo ambas na mesma rota.
- Consultar `DCTFWEB/CONSRECIBO32/1.0` para a categoria mensal geral `GERAL_MENSAL` (`40`) com `categoria`, `anoPA` e `mesPA`, uma vez por cliente e competência congelada em cada execução.
- Persistir observações imutáveis e documentos validados no cofre, sem Base64, conteúdo integral ou caminhos internos em banco operacional, logs e respostas públicas.
- Consolidar os estados `CURRENT`, `NO_MOVEMENT_VALID`, `DUE_WITHIN_DEADLINE`, `OVERDUE_NOT_FOUND` e `UNVERIFIED`, impedindo “Sem Declaração” sem consulta produtiva, obrigação confirmada e prazo vencido.
- Disponibilizar histórico, prévia e rastreio locais, além da preferência “Enviar” em modo `TEMPLATE_ONLY`, sem chamadas remotas ao abrir modais e sem envio real.
- Permitir consulta manual somente-leitura para uma seleção ou para a página atual, com confirmação, limite de 100 clientes, atualização automática do resultado e identificação explícita do ambiente Trial.
- Remover da grade DCTFWeb as colunas genéricas Competência, Encerramento, Transmissão, Recibo, Evidência, DARF e Pagamento; os detalhes documentais ficam no histórico local.

Não são objetivos desta change: habilitar SERPRO live, gerar DARF, transmitir DCTFWeb, encerrar MIT, comprovar pagamento, criar provider/webhook/job de comunicação, apagar dados históricos ou emitir parecer jurídico.

## Capacidades

### Novas capacidades

- `dctfweb-monitoring`: consulta oficial, normalização fail-closed, persistência segura, comunicação template, histórico local e interface especializada do monitoramento DCTFWeb, mantendo MIT independente.

### Capacidades modificadas

Nenhuma.

## Impacto

- Backend Laravel: adapter Integra Contador, scheduler fiscal, modelos/projeções DCTFWeb, cofre de documentos, APIs tenant-scoped e comunicação template.
- Banco PostgreSQL/SQLite de testes: migrations aditivas para categoria, observações de consulta e metadados de estado.
- Frontend Nuxt/Nuxt UI: tabela DCTFWeb derivada do arquétipo de lista, oito colunas fixas, switches e modais locais; renderer MIT separado.
- Compatibilidade: dados DCTFWeb existentes serão preservados e normalizados de forma aditiva; operações fiscais mutantes continuam fora do monitoramento e protegidas pelas flags atuais.
