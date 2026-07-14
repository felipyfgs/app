## Why

O monorepo já captura NFS-e (NSU/ADN), cadastra clientes/A1 e expõe resumos agregados, mas o operador ainda não tem uma **fila acionável** de problemas (cursor bloqueado, A1 a vencer, falhas recentes) e o painel **não prova** backup/restauração — exigência da spec `operations-dashboard` e pré-requisito do piloto com dados fiscais reais. Sem isso, o escritório opera cego e sem rede de segurança.

## What Changes

- Introduzir **inbox operacional** do escritório: itens tipados e priorizados (cursor `BLOCKED`/`ERROR`, A1 em alerta/vencido, falhas de sync recentes, capturas inelegíveis “problemáticas”), com motivos sanitizados, severidade e deep-links para cliente/estabelecimento/sync/certificado.
- Expor API de inbox com filtros por severidade e tipo, paginação/cursor, isolamento por `office_id` e ações permitidas por papel (`VIEWER` só leitura; `OPERATOR`/`ADMIN` com sync manual quando elegível).
- Ampliar o resumo operacional e o home com **contagens da inbox** e atalho para a fila.
- Introduzir **backup verificável da instância**: registro de execuções de backup (PostgreSQL + inventário/cópia do cofre de objetos cifrados e, se aplicável, storage de XML), sem incluir `VAULT_MASTER_KEY` nem material em claro.
- Comandos Artisan de **backup** e **ensaio de restore** (drill) que gravam status, horário, tamanho/checksum e mensagem sanitizada.
- Exibir no home e em Administração o **último backup SUCCESS**, o **último restore drill** e **alerta se não houver backup OK nas últimas 24 horas**.
- Ligar o slideover de notificações a um subconjunto real da inbox (sem inventar alertas cosméticos).
- Cobrir com testes de feature/unidade (isolamento, ausência de segredos, idade do backup, severidade) e UI fiel ao template de dashboard.

## Capabilities

### New Capabilities

- `instance-backup-status`: registro e exposição do estado de backup/restore da instância (metadados, comandos, alerta de atraso, sem chave mestra).

### Modified Capabilities

- `operations-dashboard`: detalha a saúde operacional como **inbox acionável** (tipos, severidade, ações por papel) e amarra o requisito de backup já existente à superfície de UI e API.
- `frontend-dashboard-experience`: home, notificações e (se necessário) rota/admin cards para inbox e status de backup, com permissões e arquétipos do template.

## Impact

- **Backend:** novos modelos/migração de metadados de backup; comandos `ops:backup-*`; serviço de agregação da inbox reutilizando `SyncCursor`, `SyncRun`, `ClientCredential`, elegibilidade; endpoints `GET /operations/inbox` e campos de backup no summary (ou endpoint dedicado); policies/papéis; auditoria de comandos se aplicável.
- **Frontend:** home (`/`), `NotificationsSlideover`, possivelmente página ou painel “Saúde”/Admin; tipos em `api.ts` e `useApi`.
- **Ops/Docker:** volumes de destino de backup documentados; runbook curto; **não** versionar dumps.
- **Segurança:** respostas e logs sem PFX, senha, PEM, XML fiscal, `vault_object_id` ou `VAULT_MASTER_KEY`; backup de blobs permanece cifrado; master key só em procedimento offline separado.
- **Não-objetivos:** desbloqueio de cursor com override de NSU; gestão de usuários; smoke ADN real; e-mail/SMS de alerta; KMS cloud; multi-escritório SaaS; portal do cliente; emitir/cancelar NFS-e; DANFSe/PDF; scraping/portal municipal; “restore em um clique” em produção sem runbook.

## Não-objetivos

- Editar ou avançar NSU manualmente; reprocessar distribuição pulando documentos.
- Restauração self-service destrutiva pela UI em produção.
- Incluir `VAULT_MASTER_KEY` em qualquer artefato de backup comum.
- Alertas externos (e-mail, Slack, webhook) nesta change.
- CI enterprise, multi-região ou backup contínuo (PITR) — apenas backup periódico verificável + drill documentado.
