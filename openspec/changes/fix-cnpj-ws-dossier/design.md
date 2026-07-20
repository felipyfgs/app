## Context

O hub já consulta `https://publica.cnpj.ws/cnpj/{cnpj}` via `CnpjWsRegistrationLookup`, sanitiza o Cartão CNPJ em DTOs e persiste snapshot em `clients` / `establishments` (JSON: `secondary_cnaes`, `state_registrations`, `shareholders`). O formulário de criação/edição (`ClientForm`) já lista CNAEs secundários; o dossiê somente-leitura (`ClientRegistration`) mostra só o CNAE principal. O payload da fonte frequentemente duplica o mesmo sócio (entrada mascarada recente + CPF em claro legado).

Stakeholders: operadores do escritório que confiam no dossiê RFB para atividades e IE; API office-scoped (Sanctum + `CurrentOffice`).

## Goals / Non-Goals

**Goals:**

- Dossiê exibe atividades essenciais (principal + secundários) e IEs a partir do snapshot já persistido.
- Lookup CNPJ.ws devolve QSA sem duplicatas óbvias da fonte, com documento sempre mascarado.
- Testes cobrem contagem de CNAEs secundários e QSA deduplicado na fixture Globo.

**Non-Goals:**

- Overlay SERPRO / CCMEI (flags continuam fail-closed).
- Persistência de hierarquia CNAE (`secao`, `divisao`, `grupo`, `classe`, `subclasse`).
- Migration de schema; mudança de rotas; redesign do shell do painel.
- Bilhetagem SERPRO acidental ou mei no Compose.

## Decisions

1. **UI no dossiê, não no mapper de CNAE**  
   Atividades já chegam no GET do cliente. A correção principal é `ClientRegistration.vue`: card/seção “Atividades” (padrão do `ClientForm`) + bloco de IEs (ativas primeiro).  
   *Alternativa descartada:* re-fetch no dossiê — desnecessário e aumenta carga na API pública.

2. **Dedupe QSA por chave de negócio**  
   Em `mapShareholders`, chave `mb_strtolower(nome)|entered_at|qualification_code`. Em colisão, preferir entrada cujo `cpf_cnpj_socio` já vinha mascarado (`*`) ou, na igualdade, a primeira após máscara via `DocumentMask`. Aplicar no adapter CNPJ.ws; o merger já escolhe overlay não-vazio — se SERPRO trouxer lista, ela prevalece sem re-dedupe nesta change.  
   *Alternativa descartada:* chave só por dígitos do meio do CPF — frágil quando a fonte só envia mascarado inconsistente.

3. **Sem nova capability de API HTTP**  
   `GET /api/v1/cnpj/{cnpj}/lookup` e serialização do establishment permanecem; muda o conteúdo sanitizado (menos sócios) e a UI.

## Risks / Trade-offs

- [Dedupe agressivo remove sócios legítimos homônimos] → chave inclui `entered_at` + `qualification_code`; teste com fixture conhecida.
- [Clientes já persistidos com QSA duplicado] → dedupe só no próximo lookup/refresh; sem backfill nesta change.
- [Lista longa de CNAEs secundários polui o dossiê] → lista com scroll (`max-h`) como no form.
- [Vazamento de CPF em claro] → `DocumentMask` permanece obrigatório; testes assertam ausência de CPF puro.

## Migration Plan

1. Deploy API (dedupe) + web (dossiê) juntos ou web primeiro (só exibe o que já existe).
2. Clientes antigos: `refresh-registration` para limpar QSA duplicado.
3. Rollback: reverter commits; dados JSON anteriores permanecem válidos.

## Open Questions

- Nenhuma bloqueante.
