## Why

No stack local, consultas “reais” em Produção continuam bloqueadas: `FISCAL_PROFILE=dev` força drivers fixture e offices sem `serpro_segregation_class=PRODUCTION` falham no egress (`OFFICE_SEGREGATION`). O admin precisa avaliar Integra Contador real a partir do ambiente de desenvolvimento.

## What Changes

- Documentar e aplicar no local: `FISCAL_PROFILE=production` (drivers `real`, ambiente padrão PRODUCTION).
- Marcar offices operacionais (`plataforma`, `contador`) com `serpro_segregation_class=PRODUCTION`.
- Manter kill switch e onboarding flag como controles explícitos; não abrir produção deployada sem env deliberado.

## Capabilities

### New Capabilities

- (nenhuma)

### Modified Capabilities

- `serpro-admin-console`: avaliação local de Produção exige perfil fiscal `production` e segregação PRODUCTION nos offices sob teste.

## Impact

- `apps/api/.env` / `.env.example`
- Dados: coluna `offices.serpro_segregation_class`
- Comportamento: egress faturável e drivers deixam de ser fixture no local quando perfil=production

### Non-goals

- Não remover fail-closed de kill switch / credenciais ausentes.
- Não alterar Compose com serviços MEI.

### Dependências entre changes

- Nível: `C1`
- Depende de: `enable-serpro-prod-credentials-form` — marco `apply` — relação `coordenada`
- Desbloqueia: consultas reais SERPRO Produção no stack local após credenciais
