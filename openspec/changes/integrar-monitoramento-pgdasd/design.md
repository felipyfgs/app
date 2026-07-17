## Contexto

O submódulo PGDAS-D hoje compartilha a superfície genérica de Simples/MEI, enquanto o adapter usa parâmetros e formatos diferentes do contrato oficial. O monitoramento já possui `FiscalMonitoringRun`, snapshots, projeções de obrigação, ledger SERPRO, `CurrentOffice` e cofre seguro, mas ainda não há uma projeção própria para original/retificadoras, DAS, RBT12 ou comunicação ao cliente.

Os serviços 14–16 devolvem PDFs em Base64. O armazenamento terminal atual preserva `dados`/`body`, portanto habilitá-los sem uma barreira de sanitização gravaria documentos no banco. A data limite existente também não possui calendário bancário verificado suficiente para afirmar atraso com segurança.

## Objetivos / Não objetivos

**Objetivos:**

- Tornar o serviço 13 a fonte exclusiva da observação mensal de declaração/DAS e da última consulta válida.
- Preservar histórico sem depender da ordem dos arrays da SERPRO.
- Obter RBT12 do extrato 16 apenas quando a referência fiscal mudar.
- Guardar PDFs no cofre e expor somente descritores autorizados.
- Entregar a tabela PGDAS-D e os controles de comunicação em modo template.

**Não objetivos:**

- Emitir DAS, transmitir declarações ou realizar envio ao cliente.
- Fazer backfill a partir de snapshots antigos/simulados.
- Executar smoke real da SERPRO ou habilitar flags de produção.
- Resolver consentimento jurídico ou contratar provider de comunicação.

## Decisões

### Projeção fiscal própria sobre o núcleo existente

`tax_obligation_projections` continua sendo a linha por cliente/PA e recebe apenas referências da última consulta válida. `pgdasd_operations` preserva declarações e DAS como operações independentes; `pgdasd_artifacts` liga metadados de documentos ao `FiscalEvidenceArtifact`; `pgdasd_rbt12_projections` controla extração e idempotência.

Alternativa rejeitada: guardar tudo em JSON no snapshot. Isso impediria constraints, ordenação confiável, consulta tenant-scoped eficiente e remoção segura de Base64.

### Codec por operação oficial

Cada operação 13–16 terá payload e mapper próprios. O serviço 13 usa XOR entre `anoCalendario` e `periodoApuracao`; 14 usa PA; 15 número da declaração; 16 número do DAS. O scheduler anual congela o PA esperado no run e consulta o ano correspondente, inclusive dezembro do ano anterior quando executado em janeiro.

Operação incompleta não promove estado verde nem ausência confirmada. A declaração mais recente é a maior transmissão válida, com número como desempate. DAS e declaração não recebem vínculo artificial.

### Documentos são retirados do envelope antes da persistência

Um normalizador de resposta documental decodifica Base64 estritamente, valida `%PDF`, limite de 10 MiB e SHA-256, grava bytes no `SecureObjectStore` e substitui o conteúdo por descritor sanitizado. Replay documental reidrata o mapper a partir do artefato, não do Base64. APIs nunca expõem chave/path do cofre.

### RBT12 idempotente por referência fiscal

Após uma consulta 13 produtiva, a presença de DAS cria, sob constraint única, uma `source_reference_key = sha256(office|client|PA|numeroDas|ultimaDeclaracao|transmissao)`. Somente a criação dessa chave agenda uma consulta 16. `NO_DAS` não chama a SERPRO; falha ou ambiguidade não entra em retry automático; uma retificadora ou novo DAS gera outra chave.

O PDF será convertido por `/usr/bin/pdftotext -layout -enc UTF-8 -nopgbrk - -` via Symfony Process, com timeout de 15 segundos, entrada máxima de 10 MiB e saída máxima de 2 MiB. Apenas valores ancorados no rótulo RBT12 e no PA esperado serão aceitos; RBT12 proporcionalizado não substitui RBT12. O texto intermediário não é persistido.

### Estado de declaração é semântico e fail-closed

O PA esperado é o mês anterior no fuso do escritório. `CURRENT` exige declaração do PA; `DUE_WITHIN_DEADLINE` exige consulta produtiva sem a declaração e vencimento ainda futuro; `OVERDUE_NOT_FOUND` exige consulta produtiva posterior ao vencimento e versão de calendário `VERIFIED`; qualquer lacuna produz `UNVERIFIED`.

O calendário implementará `NEXT_BANKING_BUSINESS_DAY`, mas uma versão sem fonte oficial permanece não verificada. A interface não deriva cor de datas locais isoladamente.

### Comunicação registra intenção, não capacidade operacional

Preferências são separadas por cliente/módulo/submódulo e usam optimistic locking. `automatic_requested` pode ser persistido, mas `automatic_effective` é sempre falso e `execution_mode` é `TEMPLATE_ONLY`. Ativação exige ao menos um canal configurado e contato ativo elegível em `client_contacts`; lote é atômico e limitado a 100.

Dispatches e eventos modelam o rastreio futuro, mas nenhuma leitura, prévia ou alteração de preferência os cria. Destinatários futuros são armazenados apenas mascarados e com hash.

### Template operacional das cápsulas (referência visual)

A página mantém o arquétipo de lista de `.reference/nuxt-dashboard-template/app/pages/customers.vue`: toolbar, seleção real, `UTable`, estados e largura fluida. As duas cápsulas compartilham o mesmo esqueleto operacional (Ações, Enviar, Cliente, Rastreio, Última Busca, Histórico), mas o PGDAS-D inclui duas colunas fiscais mensais a mais. A seleção autorizada é acrescentada pelo shell antes de Situação e não conta como coluna de negócio.

**PGDAS-D — nove colunas de negócio, nesta ordem:**
1. Situação — badge semântico (`Em dia` / `Pendências` / `Atrasado` / `Não verificado`)
2. Últ. Declaração — PA MM/AAAA com cor/ícone do estado
3. Sublimite (RBT12) — valor formatado ou indisponível; tooltip explica que é receita bruta acumulada em 12 meses (não confunde com sublimite legal)
4. Ações — ícone de prévia + menu contextual
5. Enviar — switch `automatic_requested`; cabeçalho com switch em massa da seleção
6. Cliente — razão social + CNPJ
7. Rastreio de envio — status + anexo local + abrir modal
8. Última Busca — data compacta; data/hora no tooltip
9. Histórico de Busca — botão lupa em largura da coluna → modal local

Histórico, prévia e rastreio usam `UModal` responsivo com rolagem interna.

## Riscos / Trade-offs

- [Mudança de layout do PDF] → parser versionado, estados `NOT_FOUND`/`AMBIGUOUS`, sem inferência silenciosa e fixtures de regressão.
- [Custo SERPRO duplicado] → constraint única da source key, reserva transacional e ausência de retry automático.
- [Base64 escapar por caminho genérico] → sanitização antes do attempt store e teste que busca assinatura/Base64 em banco, log e JSON.
- [Calendário incompleto produzir falso atraso] → vermelho condicionado a versão verificada; caso contrário, cinza.
- [Tabela larga em mobile] → scroll horizontal controlado, headers acessíveis e modais em modo drawer/mobile.
- [Worktree com change de schema paralela] → migrations somente aditivas e sem editar migrations históricas modificadas pelo usuário.

## Plano de migração

1. Aplicar migrations aditivas e instalar `poppler-utils` na imagem PHP.
2. Publicar codecs e sanitização documental antes de habilitar os serviços 14–16.
3. Publicar APIs e UI; campos permanecem vazios até a primeira consulta real válida.
4. Manter flags existentes desligadas; ativação por office continua sendo operação separada.
5. Rollback desabilita a superfície/worker e reverte apenas as migrations novas; artefatos do cofre permanecem recuperáveis até limpeza operacional explícita.

## Questões em aberto

Nenhuma. Falta de calendário oficial verificado ou de `pdftotext` é tratada como indisponibilidade observável, não como decisão em aberto.
