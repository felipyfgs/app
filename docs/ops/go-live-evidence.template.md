# Evidência de go-live — plataforma contida

Preencha com **referências opacas** (IDs de ticket, hash de artefato, run ID).
**Nunca** versionar contatos pessoais, senhas, tokens, PFX, SMTP password ou
identidade real de Office/usuário.

| Campo | Valor (opaco) | Status |
|-------|---------------|--------|
| RELEASE_SHA | | |
| CI_RUN_REFERENCE | | |
| DNS/TLS ok | | |
| HSTS/redirect | | |
| `prod-readiness` source | | |
| `prod-readiness` predeploy | | |
| `prod-readiness` postdeploy | | |
| Backup v3 pré-deploy / first | | |
| OFFSITE_BACKUP_REFERENCE | | |
| Restore drill (data) | | |
| SMTP smoke (domínio destino) | | |
| OBSERVABILITY_REFERENCE | | |
| ON_CALL_REFERENCE | | |
| Escalonamento referência | | |
| RPO (h) | 24 | |
| RTO (h) | 4 | |
| Restore drill trimestral (próximo) | | |
| Flags fiscais OFF | | |
| SERPRO_KILL_SWITCH | true | |
| Drivers SERPRO real | nenhum | |
| Canais SEFAZ/autXML | OFF | |
| Onboarding encerrado | | |

## Placeholders proibidos

O gate rejeita valores como `substitua`, `example.com`, `change-me`, `TBD`,
`TODO`, `xxx`, `pending` em campos obrigatórios de aceite.

## Observabilidade mínima (fornecedor-neutro)

- Coleta consultável de logs (stderr dos containers + rotação json-file)
- Uptime HTTPS do domínio
- Alertas: disco, CPU, memória, container down, Horizon, scheduler, backup atrasado (>24h off-site)
- On-call e escalonamento com referência opaca

## Separação SERPRO

Este aceite **não** promove `PRODUCTION_READY` do Integra Contador nem habilita
egress faturável. Rollout SERPRO = change/runbook próprio.
