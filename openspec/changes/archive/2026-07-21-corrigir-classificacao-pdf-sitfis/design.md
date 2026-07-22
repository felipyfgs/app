## Context

O `RELATORIOSITFIS92` produtivo observado retorna `dados.pdf` e uma mensagem genérica de sucesso; não há campo estruturado com situação ou lista de pendências. O `SitfisReportParser` já prioriza JSON quando recebe estrutura, porém atualmente transforma qualquer `%PDF` em um finding genérico `SITFIS_PDF_UNSTRUCTURED` e situação `ATTENTION`.

A auditoria local dos 11 artefatos reais, com hash validado pelo `FiscalEvidenceStore`, mostrou 2 relatórios com a declaração geral explícita de ausência de pendências e 9 com uma ou mais seções `Pendência - ...`. A extração experimental com `smalot/pdfparser` 2.12 leu corretamente todos os documentos sem binário externo. Snapshots são imutáveis no modelo; correções devem gerar versão sucessora rastreável, não alterar silenciosamente o registro original.

## Goals / Non-Goals

**Goals:**

- Preferir dados estruturados quando disponíveis e usar texto do PDF somente porque o contrato produtivo atual não oferece situação fora do arquivo.
- Projetar `PENDING`, `UP_TO_DATE` ou `ATTENTION` a partir de marcadores explícitos e conservadores.
- Preservar a distinção entre “sem pendências detectadas no relatório” e certidão negativa (`is_negative_certificate=false`).
- Gerar achados por seção oficial, reconciliar projeções SITFIS correntes e mostrar contadores do snapshot atual.
- Reprocessar evidências locais em nova versão de snapshot com o mesmo `observed_at`, sem egress SERPRO.

**Non-Goals:**

- OCR, interpretação jurídica, cálculo de valores/datas ou parsing de cada linha de débito.
- Chamar SERPRO no reprocessamento, alterar protocolo/polling/bilhetagem ou habilitar flags.
- Modificar histórico/modal da change `sitfis-historico-busca`, canais SEFAZ, mutações ou Compose.

## Decisions

1. **Prioridade estruturado → PDF fallback**  
   `SitfisReportParser` mantém o caminho JSON existente. Somente um payload identificado como PDF passa por `SitfisPdfTextExtractor`; isso atende ao contrato atual sem tornar PDF a fonte preferencial caso o SERPRO evolua para dados estruturados.

2. **Extração PHP in-process, isolada por contrato**  
   Adicionar `smalot/pdfparser:^2.12` e um contrato `SitfisPdfTextExtracting` com implementação encapsulada. É uma biblioteca PHP pura (zlib/iconv), evitando `pdftotext`, processo shell e mudanças em `infra/docker`. Limites existentes de evidência (5 MiB), limite adicional de texto normalizado e captura de `Throwable` impedem que falha/complexidade do PDF derrube a run.  
   Alternativas rejeitadas: regex em bytes comprimidos (não funciona de forma confiável); binário Poppler (aumenta imagem/attack surface); OCR (desnecessário para PDFs textuais oficiais observados).

3. **Classificação por evidência explícita**  
   - Uma ou mais linhas de cabeçalho `Pendência - <seção>` → `PENDING`, um finding estável por seção reconhecida.
   - Declaração geral “Não foram detectadas pendências/exigibilidades suspensas nos controles da Receita Federal e da Procuradoria-Geral da Fazenda Nacional”, sem cabeçalho de pendência → `UP_TO_DATE`, zero finding de pendência.
   - Extração falha, texto truncado/ambíguo, identidade/layout incompatível ou nenhum marcador conclusivo → `ATTENTION` com finding técnico.
   - A frase restrita apenas aos controles da PGFN não neutraliza pendências da Receita Federal.
   Em todos os casos `is_negative_certificate=false` e `claims_negative_certificate=false`.

4. **Normalização mínima e sem texto integral**  
   Persistir somente `report_format`, `parser_version`, `recognized_sections`, `recognized_items_count`, marcador de conclusão e metadados técnicos de extração. O texto completo, CNPJs, débitos e valores não entram em logs nem em JSON público.

5. **Projeções correntes e pendências operacionais**  
   Findings de seção usam códigos determinísticos (`SITFIS_PENDING_<hash/slug>`) e `creates_pending=true`. Ao promover um snapshot SITFIS corrente, projeções SITFIS anteriores do mesmo cliente são desativadas e pendências abertas que não aparecem mais são resolvidas (`open_dedupe_key=null`). A reconciliação é escopada a `INTEGRA_SITFIS`/`SITFIS`, sem alterar outros módulos.

6. **Reprocessamento versionado local**  
   Um comando Artisan explícito, com `--office`, `--client` e `--dry-run`, lê somente `FiscalEvidenceStore`, parseia e cria snapshot sucessor apenas quando a projeção difere. O sucessor reutiliza `run_id`/`evidence_artifact_id`, mantém `observed_at` da consulta original, incrementa `version`, registra `reprocessed_from_snapshot_id`/timestamp no normalizado e demove o anterior. A run pode ter `situation` alinhada ao sucessor, sem alterar dados de transporte. Idempotência: a mesma versão de parser e mesma classificação/seções não cria nova versão.

7. **Contadores da carteira**  
   `findings_count` consulta somente findings ativos cujo `snapshot_id` é o snapshot SITFIS corrente selecionado. `pending_count` consulta somente itens `OPEN` ligados a runs SITFIS do cliente, evitando soma de outros módulos.

8. **API office-scoped; nenhuma mudança visual de shell**  
   O comando exige escopo explícito e opera sem HTTP. As APIs Sanctum existentes continuam office-scoped; a UI apenas recebe estados/contadores corrigidos e mantém o layout Nuxt UI atual.

9. **Download sempre autenticado na SPA**  
   Tanto o botão do detalhe quanto a opção de documento no menu `Ações` SHALL chamar `useAuthenticatedDownload`; o path público do descriptor não pode ser entregue a `NuxtLink`/`navigateTo`, pois `/api/v1/...` no host `:3000` é interpretado como rota da SPA. O composable conserva cookies e usa o proxy `/api/sanctum` em desenvolvimento.

## Risks / Trade-offs

- [Layout PDF muda] → fallback `ATTENTION`, finding técnico e artefato preservado; nunca inferir regularidade.
- [PDF malicioso/complexo amplifica memória] → limite de bytes/texto, parser isolado e exceções fail-closed.
- [Falso “Em dia” por frase parcial] → exigir frase geral conjunta RFB+PGFN e ausência de qualquer cabeçalho de pendência.
- [Pendência obsoleta permanece aberta] → reconciliação escopada ao snapshot SITFIS sucessor e códigos determinísticos.
- [Histórico mostra versão de reprocessamento] → manter mesmo `observed_at`, `run_id`, evidência e metadado de origem; nenhuma nova busca é afirmada.
- [Vazamento entre offices] → leitura do cofre usa `artifact.office_id` e comando filtra office/client; sem office derivado de input HTTP.
- [Bilhetagem SERPRO acidental] → comando depende apenas de snapshot/evidência local e testes garantem ausência de executor/dispatch.
- [Segredos/PDF em log] → não logar texto, tokens, bytes ou `vault_object_id`; saída do comando limita-se a IDs/contagens/situação.
- [Kill switch/Compose] → nenhuma flag é aberta e nenhum serviço `mei`/`mei-worker` é adicionado.
- [Path da API aberto como página Nuxt] → ações de documento usam callback autenticado e teste impede reintroduzir `to: href`.

## Migration Plan

1. Instalar dependência e publicar parser/reconciliador/comando com testes.
2. Rodar `--dry-run` para o office piloto e conferir matriz esperada (2 `UP_TO_DATE`, 9 `PENDING`).
3. Executar reprocessamento local uma vez para os 11 snapshots correntes; verificar snapshots sucessores, findings, pendências e UI.
4. Rollback de código remove o parser novo; snapshots anteriores continuam versionados e podem ser repromovidos por procedimento explícito, sem apagar evidências.

## Mapa de dependências

- DAG: `sitfis-historico-busca` (C0, marco specs) → `corrigir-classificacao-pdf-sitfis` (C1).
- Ownership desta change: parser/extrator, persistência/reconciliação SITFIS, contadores e comando local.
- Ownership upstream: endpoint/modal/menu de histórico; não editar seus artifacts nem componentes.
- Contrato compatível: snapshots sucessores continuam no mesmo schema e downloads reutilizam a mesma evidência.
- Gate coordenado: validar ambas as deltas de `sitfis-monitoring-surface`; implementação não depende de concluir o modal de histórico.
- Rollout/rollback seguem o plano de migração; sem chamada SERPRO em nenhuma etapa de backfill.

## Open Questions

- Nenhuma para a implementação atual; novos layouts oficiais deverão entrar como fixtures sanitizadas antes de ampliar marcadores.
