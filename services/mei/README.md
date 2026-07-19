# MEI

Microserviço interno (Playwright) solicitado pela API Laravel em `apps/api`.
Não publicar a API diretamente na internet.

Por padrão, `MEI_AUTOMATION_LIVE_EGRESS_ENABLED=false`; somente a operação
`fixture.health` pode ser executada para smoke local.
