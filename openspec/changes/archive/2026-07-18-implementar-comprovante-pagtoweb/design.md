## Context

`pagtoweb.comparrecadacao` é uma operação produtiva e bilhetável na rota `Emitir`, mas está apenas inventariada no hub. A resposta contém documento; portanto, o padrão de listagem 7.1 não é suficiente: bytes não podem passar pelo banco, logs, API JSON ou DOM. A implementação deve seguir os padrões de documentos PGDAS-D/CCMEI, sem modificar a emissão de guia SICALC nem reutilizar JSON cru.

## Goals / Non-Goals

**Goals:**

- Resolver a coordenada oficial 7.2, validar o request e decodificar a resposta fail-closed.
- Guardar o PDF no `SecureObjectStore` e expor apenas descritores sanitizados e download autorizado para o escritório atual.
- Oferecer histórico local e solicitação manual confirmada no monitoramento de guias, com estados de loading, vazio, erro, bloqueio e processamento.
- Manter rastreabilidade, orçamento, rate limit, capability, kill switch, procuração e classificação de bilhetagem pela cadeia central.

**Non-Goals:**

- Não transmitir declarações, gerar/alterar DARF, alterar pagamentos, executar canário real ou habilitar flags.
- Não expor CNPJ completo, identificador de documento de arrecadação, Base64, hash, chave de cofre, tokens ou parâmetros técnicos SERPRO.
- Não substituir os fluxos existentes PAGTOWEB 7.1/7.3 nem criar polling automático.

## Decisions

### Adapter tipado em vez de executor genérico

O DTO de negócio receberá `numeroDocumento` fornecido manualmente pelo operador, como exige o contrato oficial. O valor será validado, usado somente na execução e nunca persistido, retornado, logado ou reapresentado; a projeção 7.1 só possui digest/máscara e não pode reconstruí-lo. O adapter chama `SerproOperationExecutor` com `pagtoweb.comparrecadacao`; o controller não monta envelope. A alternativa de expor uma caixa de JSON foi rejeitada por não validar o identificador, dificultar o mascaramento e burlar a semântica da operação.

### Captura pré-ACK, documento somente no cofre e projeção idempotente

O executor central persiste a resposta no `SerproOperationAttemptStore` antes de devolver o controle ao adapter. Portanto, um dispatcher de captura pré-ACK selecionará o capturador por `operation_key`; o comprovante 7.2 será capturado e validado **antes do ACK terminal**, gravará os bytes no cofre, projetará apenas metadados e devolverá ao executor um descritor sanitizado. O `attempt store` também tratará 7.2 como documental em defesa adicional, omitindo `dados` Base64, `body.dados` e qualquer blob correlato. O redator dinâmico removerá o `numeroDocumento` de `body`, `dados`, mensagens, erro e retorno, inclusive em respostas 4xx. Falha na captura deixa a tentativa incerta e não cria ACK com conteúdo bruto.

O codec aceitará PDF Base64 somente dentro de limites definidos, validará Base64 canônico, assinatura/MIME e retornará bytes efêmeros. O projetor gravará bytes no cofre com AAD por office, cliente, `operation_key` e digest, e persistirá metadados sanitizados por chave idempotente. Armazenar o PDF/Base64 em coluna JSON foi rejeitado por risco de vazamento e backup indevido.

### API separada e confirmação em duas etapas

GET retorna somente histórico local e download usa lookup `office_id + client_id + receipt_id`, `no-store` e nome/MIME seguros. POST exige `confirmed=true`, permissão de disparo e `numeroDocumento` efêmero, validado e excluído de logs/evidências; a UI mascara o valor ao revisar a confirmação.

Como `FiscalMonitoringRun.progress` e o adapter normal são consumidos por job assíncrono, o 7.2 não pode enfileirar nem serializar o número: a rota criará a run de auditoria sem `progress` sensível e chamará um caso de uso manual síncrono com o contexto apenas em memória. Timeout/falha fica terminal e não sofre retry/continuação automática; uma nova tentativa exige nova confirmação e nova digitação do número. Reutilizar a rota genérica de guias foi rejeitado porque persistiria o identificador e não preserva o contrato do comprovante.

### Superfície de monitoramento de guias

O painel seguirá o arquétipo settings/detalhe já usado nos painéis de cliente: histórico, ação manual e download autorizado. A referência pública de comprovante só orienta a hierarquia “solicitar → gerar → baixar”; não será copiado conteúdo protegido nem acessada área autenticada.

## Mapa de dependências

```text
C0 implementar-comprovante-pagtoweb
 ├─ N0 coordenada/fixtures/codec
 ├─ N1 projeção no cofre + adapter + API
 ├─ N2 painel e confirmação
 └─ N3 testes, ledger e gates
```

`exibir-pagamentos-pagtoweb-71` e `cobrir-contagem-pagamentos-pagtoweb-73` são bases arquivadas; seus padrões de filtros e tenancy são compatíveis. A nova change é dona dos novos artefatos de comprovante. Alterações no ledger e em contratos compartilhados serão serializadas com outras changes ativas.

## Risks / Trade-offs

- [Contrato documental oficial divergir do snapshot] → confrontar a página específica e interromper o adapter até registrar o diff.
- [Consulta `Emitir` ser bilhetável] → confirmação dupla, rate limit, orçamento e nenhuma atualização automática.
- [Timeout após solicitação] → persistir execução pendente para conciliação; não repetir cegamente.
- [Número efêmero atravessar a fila] → execução manual síncrona, contexto somente em memória e proibição de `progress`, job, retry ou continuação para 7.2.
- [ACK antes da projeção] → captura pré-ACK obrigatória; tentativa terminal só recebe descritor sanitizado, com teste de ausência de Base64 em `dados` e `body`.
- [Documento malformado ou excessivo] → codec fail-closed, limite de bytes e nenhuma projeção parcial.
- [Vazamento entre escritórios] → `CurrentOffice`, lookup composto, AAD tenant-scoped e testes cross-tenant.

## Migration Plan

1. Adicionar migration forward-only para metadados do comprovante e aplicar normalmente.
2. Publicar com capability default OFF e endpoints protegidos; a UI exibirá bloqueio acionável quando necessário.
3. Em erro, desabilitar a capability/kill switch; documentos já válidos permanecem acessíveis apenas ao tenant autorizado. Não há rollback destrutivo de migration.

## Open Questions

- Confirmar na fonte oficial específica todos os campos de request e o formato de resposta/documento antes do código.
- Definir o cenário Trial oficial aplicável sem contar sua resposta como `PASS_REAL_*`.
