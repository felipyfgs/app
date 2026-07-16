# Governança LGPD / sigilo fiscal — Integra Contador

Documento operacional do hub fiscal multi-escritório para o canal SERPRO Integra Contador.
Não contém segredos, CNPJs reais de produção nem tokens.

## Papéis

| Papel | Quem | Escopo |
|-------|------|--------|
| **Controlador** | Software-house / plataforma (operador do hub) | Tratamento global de credenciais de contrato, bilhetagem agregada, telemetria sanitizada |
| **Operador (processador)** | Time de plataforma (`PLATFORM_ADMIN`) | Manutenção de contrato/credencial, kill switch, conciliação, rollout |
| **Controlador local** | Escritório (`Office`) / responsável legal | Dados fiscais dos clientes do escritório; instruções e consentimentos de A1/Termo |
| **Operador tenant** | `ADMIN` / `OPERATOR` do office | Onboarding Autor/Termo/poderes, consultas autorizadas, uso de franquia |
| **Titular** | Pessoa física cujos dados eventualmente constem em XML/documentos | Atendimento via Office; plataforma não atende titular final sem instrução do Office |

`PLATFORM_ADMIN` **sem** membership no office **não** acessa dados fiscais tenant-scoped.

## Finalidade

- Prestação de serviços de monitoramento e consulta fiscal via Integra Contador em nome do escritório.
- Cumprimento de obrigações contratuais com o SERPRO e com o escritório.
- Segurança, auditoria, bilhetagem e contenção de incidentes.

## Hipótese legal (resumo)

- Execução de contrato / procedimentos preliminares (prestação ao Office).
- Cumprimento de obrigação legal/regulatória quando aplicável a guarda de documentos fiscais.
- Legítimo interesse **limitado** a segurança da plataforma (kill switch, auditoria, detecção de abuso), com minimização e sem marketing.
- Consentimento versionado quando o Office autoriza assinatura A1 gerenciada do Termo.

A vigência/tarifário do contrato SERPRO e responsabilidades de software-house permanecem gates jurídico-comerciais externos; o software não os resolve por inferência.

## Sigilo fiscal

- XML canônico, tokens de procurador, PFX e Consumer Secret ficam no vault (`SecureObjectStore`).
- APIs e logs devolvem apenas metadados sanitizados (fingerprints, hints, status).
- Labels de métricas/alertas têm cardinalidade limitada e **não** carregam CNPJ, CPF, nome, e-mail, XML ou token.
- Isolamento multi-tenant: escopo por `CurrentOffice` / membership; `office_id` do cliente HTTP é ignorado.

## Categorias de dados

| Categoria | Exemplos | Armazenamento |
|-----------|----------|---------------|
| Credencial de contrato | Consumer Key/Secret, PFX e-CNPJ | Vault; metadados no banco |
| Autorização tenant | Termo XML, token procurador, A1 autor | Vault; estado em `office_serpro_authorizations` |
| Representação | Poderes e-CAC | Banco (códigos/status); evidência referenciada |
| Operacional | Ledger, readiness, auditoria | Banco; auditoria append-only com hash encadeado |
| Telemetria | Contadores 401/403/429/5xx, breaker | Métricas sem PII |

## Retenção

Configurável em `serpro.retention.*` / env `SERPRO_RETENTION_*_DAYS` (defaults ~7 anos / 2555 dias para material fiscal; tokens revogados: purge imediato).

| Material | Revogação | GC |
|----------|-----------|-----|
| Token procurador | Imediata no offboarding | Imediato |
| Termo / PFX autor | Uso bloqueado | Após prazo legal (`serpro:retention-gc`) |
| Poderes | Status `REVOKED` | Após prazo |
| Ledger / auditoria | Nunca no offboarding precoce | Somente após retenção documentada |

## Instruções do Office

- O Office seleciona ambiente, autor e concede consentimento A1 quando aplicável.
- Mutações de credencial/Termo exigem `ADMIN` + 2FA.
- O Office é responsável por base legal perante seus clientes e por solicitar exclusão/atendimento ao titular.

## Atendimento ao titular

1. Titular contata o Office.
2. Office (ADMIN) solicita à plataforma ação de suporte com identificação do office e categoria.
3. Plataforma executa revogação/minimização sem expor material de outros tenants e registra auditoria.

## Resposta a incidente

Ver runbooks em `docs/ops/runbooks/`:

- Credencial/PFX comprometido
- Termo rejeitado / 401 / 403 bilhetável / 429 / 5xx
- Custo anômalo, procuração revogada, cross-tenant, vault/key loss, indisponibilidade

Princípios: fail-closed, kill switch global prevalece, preservar ledger/auditoria, não logar segredo.
