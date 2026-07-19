# MEI

Microserviço interno (Playwright) solicitado pela API Laravel em `apps/api`.
Não publicar a API diretamente na internet.

Por padrão, `MEI_AUTOMATION_LIVE_EGRESS_ENABLED=false`; somente a operação
`fixture.health` pode ser executada para smoke local.

## Fronteiras operacionais

- Horizon executa domínio fiscal, SERPRO, SEFAZ, polling e ingestão no vault.
- Celery executa somente os jobs de browser deste serviço.
- Laravel usa Redis DB `/0`/`/1`; o MEI usa DB `/4` com estado sujeito a TTL.
- Os containers FastAPI/Celery ficam somente na rede interna e não publicam portas.
- Postgres no Laravel é a fonte de verdade; este serviço não recebe DB nem vault.

O contrato completo está em
[`docs/architecture/mei-stack-boundaries.md`](../../docs/architecture/mei-stack-boundaries.md).
