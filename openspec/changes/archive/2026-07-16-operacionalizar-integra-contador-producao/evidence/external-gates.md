# Gates documentais externos

Registro operacional (sem segredos) das pendências que bloqueiam `PRODUCTION_READY`.
Identidades de Office/cliente canário **não** devem ser gravadas neste arquivo.

Comando: `php artisan serpro:external-gates list`

| Kind | Ação requerida | Status alvo | Ticket / evidência |
|------|----------------|-------------|-------------------|
| `OAUTH_ENDPOINT_DIVERGENCE` | Abrir chamado SERPRO sobre curl da Área do Cliente vs `/authenticate`; manter fluxo alternativo bloqueado | `ANSWERED` + decisão canônica | _pendente_ |
| `TERMO_XSD_OFFICIAL` | Solicitar XSD oficial versionado do Termo | `ACCEPTED` ou schema DERIVED formal | _pendente_ |
| `CNPJ_ALPHANUMERIC_SERIALIZATION` | Confirmar serialização alfanumérica no Termo/Eventos | `ACCEPTED` | _pendente_ |
| `CONTRACT_VIGENCY_TARIFF` | Revisão jurídico-comercial vigência + tabela/ciclo 21–20 | `ACCEPTED` | _pendente_ |
| `SOFTWARE_HOUSE_LEGAL_MODEL` | Parecer sobre arranjo software-house | `ACCEPTED` | _pendente_ |
| `OPS_ROLES_RPO_RTO` | Definir on-call, RPO/RTO, escrow/KMS, custódia A1 e canário (fora deste repo) | `ACCEPTED` | _pendente_ |

## Política

- Respostas oficiais entram como `answer_summary` sanitizado via comando/`serpro_external_gates`.
- Hash de documentos oficiais: `backend/resources/serpro/official-sources.v2026-07-16.json`.
- Alternativa OAuth na raiz do gateway **não** é implementada até `OAUTH_ENDPOINT_DIVERGENCE` aceito com protocolo formal.

## Ops roles (placeholders — preencher fora do OpenSpec)

| Papel | Responsável | Contato | Notas |
|-------|-------------|---------|-------|
| Platform on-call | _TBD_ | _TBD_ | Escala externa |
| Security / vault escrow | _TBD_ | _TBD_ | Chave mestra fora do backup |
| Jurídico-comercial SERPRO | _TBD_ | _TBD_ | Vigência e tarifas |
| Office canário smoke gratuito | _selecionar na aplicação_ | — | Não versionar identidade aqui |

### RPO / RTO (rascunho)

| Item | Alvo | Evidência |
|------|------|-----------|
| RPO vault+DB | _TBD_ | restore drill |
| RTO kill switch | minutos | `serpro:contract kill-on` + flag env |
| RTO rotação credencial | _TBD_ | cutover quatro olhos |
