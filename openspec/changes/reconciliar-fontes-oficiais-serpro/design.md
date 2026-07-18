## Context

O catálogo local continua alinhado 1:1 à página oficial: 119 coordenadas, 98 `PRODUCTION`, 19 `PROSPECTION`, uma `UNDER_CONSTRUCTION`, uma `CANCELED` e as mesmas contagens por rota. Os hashes do catálogo e da página de procurações gravados em `official-service-catalog.v2026-07-16.json` também coincidem com o corpo oficial vigente.

O problema está no registro transversal `official-sources.v2026-07-16.json` e no `source_content_sha256` da matriz de poderes: os valores seguem sequências artificiais e passam por validações que checam apenas formato hexadecimal. Como esses recursos alimentam readiness, alertas e elegibilidade, formato válido não pode equivaler a proveniência válida.

## Goals / Non-Goals

**Goals:**

- tornar a proveniência reproduzível e fail-closed;
- separar documentos HTTP de evidência TLS e notas históricas;
- impedir regressão para hashes sintéticos com testes offline;
- permitir verificação atual explícita sem egress fiscal;
- corrigir o ledger para o critério `PASS_REAL_*` do goal.

**Non-Goals:**

- alterar coordenadas, schemas de operação ou poderes sem diff oficial;
- executar `/Apoiar`, `/Consultar`, `/Declarar`, `/Emitir` ou `/Monitorar`;
- habilitar capabilities, kill switch, allowlists ou mutações;
- persistir corpos oficiais no banco ou no Git;
- resolver aprovação de canário, contrato comercial ou dados piloto.

## Decisions

### Hash do corpo HTTP após transferência

Para fontes `HTTP_CONTENT`, o hash será calculado sobre os bytes do corpo entregue ao cliente após tratamento normal de transferência HTTP, exigindo resposta 200, HTTPS e host allowlisted. Esse é o mesmo contrato já comprovado pelos hashes vigentes do catálogo e da matriz de procurações.

Alternativa rejeitada: normalizar HTML antes do hash. A normalização esconderia alterações e exigiria um parser/versionamento próprio; o snapshot deve detectar qualquer mudança para revisão humana.

### Manifesto novo em vez de reescrever a proveniência histórica

Será criado um snapshot datado de 2026-07-18 e a configuração passará a apontar para ele. O arquivo antigo permanecerá como evidência histórica inválida, sem ser usado pelo runtime. A matriz de poderes também receberá nova versão com o hash real da fonte, mantendo as mesmas entradas quando o diff oficial for vazio.

Alternativa rejeitada: corrigir silenciosamente arquivos `v2026-07-16`. Isso apagaria a trilha do erro e faria parecer que os hashes corretos já existiam na captura anterior.

### Validação offline e verificação de rede separadas

O loader validará campos, tipos, hosts, formatos, padrões placeholder e coerência entre recursos sem rede. Um comando explícito fará a comparação vigente com timeouts, limite de bytes e saída sanitizada; ele não fará parte do PHPUnit comum e poderá usar HTTP fake nos próprios testes.

Alternativa rejeitada: buscar a internet em todo gate. Isso tornaria testes determinísticos dependentes de disponibilidade externa e confundiria indisponibilidade com regressão local.

### Fontes sem documento estável não recebem `content_sha256`

TLS/readiness será verificado pelo fluxo de smoke existente. Divergências históricas sem URL e páginas oficiais cujo HTML bruto seja dinâmico ficarão `canonical=false`, com tipo próprio e hash ausente. O registry não deverá criar snapshot de documento para esses registros. A página específica do CNPJ alfanumérico permanece citável como `DYNAMIC_REFERENCE`, mas não participa da cadeia automática de hashes.

Alternativa rejeitada: usar hash vazio, hash de uma resposta 404 ou fingerprint TLS no campo de conteúdo. Todos simulam uma semântica que o campo não possui.

### Ledger permanece conservador

O lote atualizará somente fatos transversais: 25 mutações produtivas, 33 totais, 21 não produtivas e invalidação das classificações históricas como aceite final. Não promoverá nenhuma operação a `READY_PRODUCTION`.

## Risks / Trade-offs

- [HTML oficial pode mudar por alteração cosmética] → a verificação marca `REVIEW_REQUIRED`; a revisão humana diferencia mudança editorial de contrato antes de gerar novo snapshot.
- [Arquivo legado ainda existe no Git] → configuração e testes apontam exclusivamente para a nova versão e documentam o legado como inválido.
- [Hash correto não prova semântica da página] → contagens/coordenadas continuam confrontadas por parser/testes e cada operação ainda exige sua página específica.
- [Working tree contém mudanças concorrentes] → task-loops usam ownership restrito aos manifestos, serviços/testes da capability e trechos factuais do ledger.

## Migration Plan

1. Criar os manifestos datados com hashes reais e metadados de tipo.
2. Implementar validação offline e testes de coerência/placeholder.
3. Apontar config e consumidores para as novas versões; manter compatibilidade de leitura somente quando fail-closed.
4. Executar verificação HTTP explícita e gates focados.
5. Atualizar ledger/evidências com o resultado sanitizado e o bloqueio das operações.

Rollback: restaurar os paths de configuração para a versão anterior somente para diagnóstico offline; isso reativa `REVIEW_REQUIRED` e não autoriza egress real. Nenhum dado ou migration é revertido.

## Open Questions

Nenhuma para implementação deste lote. Mudança semântica futura em qualquer página abrirá uma nova change específica antes de alterar catálogo ou poderes.
