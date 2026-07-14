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
