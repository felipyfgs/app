# Evidence template — 12.1 / 12.2 credential rotation

**Não preencher com segredos, CNPJ completo de cliente, Office slug real se sensível, nem tokens.**

| Campo | Valor sanitizado |
|-------|------------------|
| Data/hora (UTC) | _TBD_ |
| Ambiente | PRODUCTION / TRIAL |
| Versão antiga id | _id numérico_ |
| Status pós-compromise | COMPROMISED |
| Versão nova id | _id numérico_ |
| Fingerprint prefix (12 hex) | _xxxxxxxxxxxx_ |
| Aprovador 1 user_id | _id_ |
| Aprovador 2 user_id | _id_ |
| Cutover status | ACTIVE |
| `serpro:prod-check` exit | 0 / ≠0 |
| Cópias transitórias removidas | sim / não |
| Ticket SERPRO (ref opaca) | _TBD_ |

## Checklist

- [ ] Key/Secret antigas invalidadas no canal SERPRO
- [ ] Versão exposta `COMPROMISED` ou `RETIRED`
- [ ] PENDING → VERIFIED → dual approve → cutover
- [ ] Zero segredo em issue/log/change
- [ ] `shred`/remoção de PFX e arquivos de key/secret locais

## Runtime

Implementação completa; **execução live ops-gated**.
