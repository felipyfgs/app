# Piloto e rollback

## Critérios de piloto

| Fase | Escopo | Critérios de avanço |
|------|--------|---------------------|
| 1 | 5 raízes | mTLS ok; backfill sem bloqueio indevido; backup testado |
| 2 | 50 raízes | ciclo horário < 60 min; filas estáveis; alertas de cert |
| 3 | Todos | métricas documentadas; on-call definido |

## Rollback

1. Parar Scheduler e workers Horizon
2. Preservar banco e volumes do cofre
3. Reverter imagens da aplicação
4. Não executar migrações destrutivas no MVP

## Smoke mTLS (produção restrita)

- Certificado dedicado de teste (não usar de cliente real no CI)
- Validar papéis emitente, tomador e intermediário
- Divergência do manual oficial bloqueia release (sem fallback de portal)

## Base de desenvolvimento (não CI)

Empresa piloto local para implementar e validar a **busca/distribuição ADN** (envelope JSON):

| Campo | Valor |
|-------|--------|
| Painel | http://localhost:3000/clients/8 |
| `client_id` | 8 |
| `establishment_id` | 9 |
| CNPJ | `34194865000158` |
| PFX local (gitignored) | `secrets/dev/sel-de-souza-suares-veiculos-34194865000158.pfx` |
| API | `ADN_BASE_URL=https://adn.nfse.gov.br/contribuintes` |

Regras:

- **CI não usa** este A1 nem chama o ADN real.
- Material do certificado permanece em `secrets/` (ignorado pelo git); upload no painel grava no cofre cifrado.
- Smoke manual: sync do estabelecimento 9 → notas no catálogo → download XML.
- O ADN pode responder HTTP **404** com JSON `NENHUM_DOCUMENTO_LOCALIZADO` no fim da distribuição; isso é normal e não deve bloquear o cursor.
