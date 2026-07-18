## Context

`EsocialEventClient` está ligado diretamente a `FakeEsocialEventClient` em todos os ambientes. A fila vazia do double ainda retorna fetch bem-sucedido, e serviços, jobs e adapters podem persistir/projetar esse resultado. A versão default de evidência `fake-1` confirma que a simulação foi tratada como fonte normal do produto.

Em Simples/MEI, quando mutações estão desligadas, `GERAR_DAS` pode ser reclassificada como leitura local e chamar `DasGuideHookService`, que cria `FiscalGuideStub`, número `STUB-*`, vencimento derivado de relógio local e auditoria/result `SUCCESS`. Esse comportamento vem habilitado por default.

Os dois fluxos precisam ser bloqueados antes de uma limpeza nominal de classes e documentos. Marcadores históricos devem permanecer temporariamente para impedir promoção acidental e permitir reconciliação.

## Goals / Non-Goals

**Goals:**

- tornar eSocial indisponível/fail-closed sem provider oficial real;
- impedir que sync vazio ou sintético seja persistido como sucesso;
- manter o double eSocial exclusivamente em `Tests\Support` com uso opt-in;
- impedir qualquer nova criação de DAS `STUB-*` ou sucesso sem fonte externa;
- retirar superfícies operacionais que tratam stub como guia;
- excluir o legado sintético de evidência, KPI e prontidão.

**Non-Goals:**

- criar transporte eSocial real nesta change;
- ligar mutações/egress ou executar homologação externa;
- apagar tabelas/linhas históricas sem migração auditável;
- resolver todas as regras de proveniência fiscal de outros módulos.

## Decisions

### eSocial sem provider real resolve Disabled

Será introduzido `DisabledEsocialEventClient`, que retorna fonte indisponível de modo explícito. Esse será o binding runtime universal enquanto não houver implementação real contratada. Rotas/job poderão informar bloqueio, mas não persistir run/evidência como sucesso.

Alternativa rejeitada: manter Fake apenas em local/homologação. Isso conserva o mesmo caminho capaz de contaminar dados e divergir de produção.

### Double eSocial pertence ao autoload-dev

O client programável e seus builders de amostra serão movidos para `Tests\Support`. Testes que precisam de eventos registrarão provider explicitamente; `AppServiceProvider` não conhecerá o double.

Alternativa rejeitada: branch `environment('testing')` no provider de aplicação. A política real-only exige que o container normal seja idêntico em todos os ambientes.

### Mutação desligada nunca vira emissão local

`GERAR_DAS` com mutação/capability desligada retornará bloqueio tipado. O adapter não a reclassificará como `READ_ONLY`, e o hook não criará número, vencimento, evidência ou auditoria `SUCCESS`.

Alternativa rejeitada: conservar `FiscalGuideStub` com label mais forte. O problema é fabricar resultado fiscal, não apenas a apresentação.

### Histórico permanece somente para quarentena

Linhas eSocial `fake-1`/simuladas e guias `STUB-*`/sem chamada externa serão marcadas inelegíveis para evidência e omitidas de resultados operacionais/KPIs. A tabela/model legado só será removida depois de reconciliação/purga controlada.

Alternativa rejeitada: apagar os marcadores antes dos dados. Isso perderia a capacidade de identificar contaminação.

## Mapa de dependências

```text
eliminar-fake-simulado-runtime-serpro (C1, apply 2.1/2.2)
                         │
                         ▼
bloquear-sinteticos-fiscais-runtime (C2)
  N0 bloquear eSocial + bloquear criação DAS
          ├── N1 mover double eSocial para Tests\Support
          ├── N1 retirar superfícies guide-stubs
          └── N1 quarentenar legado sintético
                         │
                         ▼
  N2 gates integrados e varredura zero produtor sintético
```

- Ownership upstream: bindings SERPRO centrais/secundários e provider de doubles SERPRO.
- Ownership desta change: contrato eSocial, fabricação DAS e suas superfícies/legado.
- Arquivo compartilhado: `AppServiceProvider`; alterações eSocial serão serializadas após o marco upstream.
- DAS pode ser implementado em paralelo por não compartilhar arquivos do núcleo SERPRO.

## Risks / Trade-offs

- [Tela FGTS/eSocial deixa de exibir dados de demonstração] → mostrar indisponibilidade explícita; não substituir por estado vazio.
- [Testes dependem dos builders Fake] → mover integralmente builders e registrar provider apenas nos testes afetados.
- [Consumidores usam guide-stubs] → retirar rota/composable/link em conjunto e cobrir ausência de criação com feature tests.
- [Dados históricos continuam no banco] → marcá-los inelegíveis e documentar reconciliação; remoção física só após inventário.
- [Remoção de stub revela gap funcional de emissão] → o estado correto é bloqueado até a mutação real ser autorizada, não sucesso local.

## Migration Plan

1. Alterar defaults para disabled/false e cobrir zero HTTP/zero persistência.
2. Introduzir client eSocial Disabled e trocar o binding runtime.
3. Mover o double eSocial para `Tests\Support` e adaptar a suíte.
4. Remover branch/hook de fabricação DAS e superfícies `guide-stubs`.
5. Quarentenar registros sintéticos e excluí-los de KPI/prontidão.
6. Rodar gates backend/frontend, architecture, varreduras e OpenSpec estrito.

Rollout: comportamento inicial é bloqueio explícito; nenhuma flag real será habilitada.

Rollback operacional: manter módulos desabilitados. Não restaurar bindings/geradores sintéticos.

## Open Questions

- A API M2M oficial escolhida para eSocial será definida em change futura com fonte, contrato e credenciais próprios.
