## Context

Diagnóstico local: onboarding flag já ON; egress falha com `OFFICE_SEGREGATION` (seg=null); `serpro:prod-check` mostra todos os drivers `fixture` porque `FISCAL_PROFILE=dev`.

## Goals / Non-Goals

**Goals:** Permitir avaliação de consultas reais SERPRO Produção no stack Docker local.

**Non-Goals:** Mudar defaults fail-closed de imagens de produção; auto-promote em CI.

## Decisions

1. `FISCAL_PROFILE=production` no `.env` local (não `trial`) — usuário pediu produção real.
2. Setar `serpro_segregation_class=PRODUCTION` em offices `plataforma` e `contador`.
3. Documentar no `.env.example` com comentário explícito de risco bilhetagem.

## Risks / Trade-offs

- [Bilhetagem SERPRO acidental] → Mitigação: só local; kill switch permanece; credenciais ainda necessárias.
- [Fixture some e quebra demos] → Mitigação: documentar; reverter `FISCAL_PROFILE=dev` restaura fixture.

## Mapa de dependências

- Coordenada com `enable-serpro-prod-credentials-form` (formulário + flag onboarding).
