# Evidência comercial/jurídica — Integra Contador como insumo de SaaS

**Change:** `build-complete-fiscal-monitoring-hub` · task 1.3  
**Atualizado:** 2026-07-15  
**Status:** **PENDENTE DE EVIDÊNCIA FORMAL** — **GATE BLOQUEANTE** antes do piloto produtivo com chamadas reais faturáveis.

> Este documento **não** substitui contrato, aditivo ou parecer. **Não inventa** carta, cláusula ou autorização SERPRO. Serve apenas para registrar o que se sabe publicamente, o que falta confirmar por escrito e o estado do gate operacional.

## Objetivo do gate

Antes de habilitar piloto produtivo (chamadas reais ao Integra Contador atribuídas a tenants pagantes ou em trial com risco de fatura SERPRO), a software house precisa de confirmação **comercial e jurídica escrita** de que o modelo SaaS multi-escritório é compatível com o contrato eletrônico vigente do Integra Contador.

Sem essa evidência: manter flags de Integra Contador **desabilitadas** em produção; trial apenas com mock/fake/fixtures; ledger em shadow mode se já implementado.

## O que se sabe publicamente (não-secretos, não-contratuais)

Informações de domínio público / documentação de produto SERPRO (sujeitas a mudança; validar na fonte oficial no momento da contratação):

| Tema | Entendimento operacional (público / genérico) | Limitação |
|------|-----------------------------------------------|-----------|
| Produto | Integra Contador expõe APIs de consulta e serviços fiscais para contratantes autorizados | Escopo exato de soluções depende do contrato e do catálogo vigente |
| Autenticação | Padrão conhecido: OAuth2 com Consumer Key/Secret, papel de terceiros e mTLS com certificado do contratante | Detalhes de ambiente, URLs e headers são da documentação técnica vigente |
| Cadeia de identidades | Há distinção entre contratante da API, autor/procurador e contribuinte (Termo / procurações) | Regras de renovação de token e reapresentação de Termo precisam ser validadas no piloto técnico **e** no contrato |
| Faturamento | SERPRO fatura o **contratante** (software house no nosso modelo), tipicamente de forma agregada por consumo | **Não** há, neste repositório, fatura, tabela de preços contratada nem regra de franquia assinada |
| Documentação técnica | Manuais e catálogos de serviços (Integra-SN, MEI, DCTFWeb, etc.) descrevem operações e, em alguns casos, classificação de uso | Documentação técnica **≠** autorização comercial para revenda/SaaS |

Nenhuma linha acima deve ser citada a clientes como “autorização SERPRO para SaaS”.

## O que FALTA (evidência escrita — checklist)

Itens **obrigatórios** para sair do estado bloqueante. Anexar referência externa (protocolo, e-mail corporativo, aditivo, parecer) **sem** colar texto confidencial desnecessário no repositório.

| # | Pergunta a confirmar por escrito com SERPRO / jurídico | Status | Referência (preencher) |
|---|--------------------------------------------------------|--------|-------------------------|
| 1 | O contrato eletrônico vigente **permite expressamente** o uso do Integra Contador como **insumo de plataforma SaaS multi-tenant**, em que a software house é a contratante e escritórios contábeis são clientes da plataforma? | **PENDENTE** | — |
| 2 | É permitido **atribuir e cobrar** dos escritórios (franquia inclusa no plano, excedente, pacotes) o consumo gerado em nome da cadeia Autor→Contribuinte, mantendo a fatura SERPRO na software house? | **PENDENTE** | — |
| 3 | Quais **limites de redistribuição** existem (proibição de sublicenciamento da API, white-label, revenda de credenciais, limites de volume por subcliente)? | **PENDENTE** | — |
| 4 | Quais chamadas (erro, cache, resposta vazia, retry) são **faturáveis** por solução, e existe relatório oficial com granularidade suficiente para conciliação automática? | **PENDENTE** | — |
| 5 | Há restrições a **Termo de Autorização** de longa vigência, reapresentação após expiração do token diário do procurador, ou uso de A1 gerenciado pela plataforma? | **PENDENTE** | — |
| 6 | Ambiente e CNPJ contratante autorizados para **piloto** (homologação vs produção) e contatos oficiais de suporte comercial/técnico | **PENDENTE** | — |

## Estado operacional do gate

| Ambiente | Chamadas Integra Contador reais | Cobrança tenant por consumo SERPRO | Observação |
|----------|----------------------------------|------------------------------------|------------|
| Dev / CI | Apenas mock/fake/fixtures | Não | Sem certificado de produção em CI |
| Trial técnico interno | Somente se #1 e #6 tiverem aceite mínimo escrito; orçamento baixo e allowlist | Não (ou shadow) | Ledger em shadow se existir |
| **Piloto produtivo** | **Bloqueado** até #1–#4 (no mínimo) formalizados | Bloqueado até #2 formalizado | Kill switch global permanece o default |
| GA multi-tenant | Após piloto + conciliação | Conforme plano comercial e #2–#3 | — |

### Critério de saída do gate (definição de “DONE” da task 1.3)

1. Documento externo (parecer jurídico interno **e/ou** comunicação formal SERPRO) arquivado fora do git se confidencial, com **hash/identificador** e data registrados abaixo.
2. Tabela “O que FALTA” atualizada com status **CONFIRMADO** ou **NÃO APLICÁVEL** + referência.
3. Decisão explícita de go/no-go assinada por responsável comercial + jurídico (nomes/datas abaixo).
4. Flags de produção documentadas no runbook (quais env vars / configs liberar).

### Registro de evidência (preencher quando existir — não inventar)

| Campo | Valor |
|-------|--------|
| Data da solicitação ao SERPRO | *pendente* |
| Canal (e-mail, protocolo, reunião) | *pendente* |
| Identificador do contrato eletrônico | *pendente — não versionar segredos* |
| Parecer jurídico interno (id/data) | *pendente* |
| Decisão go/no-go piloto | **NO-GO** (default) |
| Responsável | *pendente* |

## Separação deliberada

| Camada | Quem fatura quem | O que o código pode fazer agora |
|--------|------------------|----------------------------------|
| SERPRO → software house | Fatura agregada do contrato | Modelar `serpro_contracts`, ledger e conciliação **após** desenho; **não** afirmar direito comercial |
| Software house → escritório | Plano MonitorHub (franquia/excedente) | Modelar assinatura e quotas; **não** acionar cobrança bancária no MVP |
| Escritório → contribuinte | Fora do produto | Sem portal de contribuinte |

## Proibições

- Não commitar PDF/contrato SERPRO com dados sensíveis no repositório público ou compartilhado sem política de retenção.
- Não copiar cláusulas “como se” fossem do SERPRO.
- Não habilitar `allow_all_offices` / produção Integra com base apenas neste checklist em branco.
- Não expor Consumer Key/Secret, PFX ou tokens em issues, logs ou este arquivo.

## Relacionados

- Design da change: `openspec/changes/build-complete-fiscal-monitoring-hub/design.md` (riscos e open questions).
- ADR controle vs dados: `docs/adr/005-control-plane-vs-data-plane.md`.
- Isolamento multi-tenant: `docs/ops/multi-tenant-isolation-checklist.md`.
