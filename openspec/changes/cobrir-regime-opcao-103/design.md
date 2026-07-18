## Context

A operação 103 pertence à mesma família das consultas 102 e 104 já cobertas
localmente, mas ainda depende de coordenadas genéricas. O resultado pode conter
informação fiscal; portanto a leitura não deve disparar coleta e o transporte
real permanece fail-closed.

## Goals / Non-Goals

**Goals:**

- Criar um contrato tipado e fail-closed para `CONSULTAROPCAOREGIME103`.
- Persistir apenas uma projeção mínima associada ao escritório e cliente
  correntes e expor histórico local autorizado.
- Garantir confirmação explícita antes de enfileirar a consulta potencialmente
  faturável.

**Non-Goals:**

- Executar HTTP SERPRO real, alterar opção de regime ou reutilizar payloads das
  operações 101, 102 ou 104.
- Expor identificação fiscal, payload bruto, token, Base64 ou caminho interno
  do cofre.

## Decisions

- O adapter reutilizará apenas o executor central e as flags de
  `simples_mei`; não injetará cliente de transporte.
- O codec aceitará somente os campos oficiais necessários ao monitor. Campos
  ausentes, ambíguos ou fora do tipo esperado falharão antes da projeção.
- O POST usará cliente resolvido por `CurrentOffice`; GET e modal só lerão a
  projeção. A UI do monitor existente será a superfície de ação, para manter o
  padrão de 102/104.

Alternativas recusadas: uma rota genérica ocultaria o contrato e uma consulta
no carregamento do modal tornaria a cobrança implícita.

## Risks / Trade-offs

- [Contrato oficial divergente] → confirmar coordenada, entrada e resposta na
  documentação oficial antes de implementar e manter fixture sanitizada.
- [Cobrança acidental] → confirmação explícita, capability desligada por
  padrão, allowlist e kill switch continuam obrigatórios.
- [Vazamento cross-tenant] → resolver cliente somente por `CurrentOffice` e
  testar ausência para office estrangeiro.

## Mapa de dependências

```text
102 verify ─┐
104 verify ─┼─> 103 contrato/projeção ─> API/UI ─> gates
catálogo ───┘
```

As changes 102 e 104 são upstream coordenadas já verificadas; esta change não
altera seus codecs nem migrações. Os arquivos compartilhados
`SimplesMeiAdapter`, `SimplesMeiController`, página PGDAS-D e matriz exigem
revisão integrada antes do rollout. Rollback: desabilitar a capability; a
projeção local deixa de receber atualizações sem executar efeitos fiscais.

## Open Questions

- Nenhuma para a implementação offline: a documentação oficial confirma
  `anoCalendario`, retorno JSON em `dados`, poder `00060` quando representando
  e artefatos Base64 que não podem sair do cofre.
