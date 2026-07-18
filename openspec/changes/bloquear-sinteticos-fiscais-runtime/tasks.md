## 1. N0 — Bloqueio imediato dos produtores sintéticos

- [ ] 1.1 Criar `DisabledEsocialEventClient`, tornar o binding/config eSocial fail-closed em todos os ambientes e fazer rota/job/adapter retornarem indisponibilidade sem HTTP, run ou evidência de sucesso.
  Depende de: change externa `eliminar-fake-simulado-runtime-serpro`, marco `apply` das tasks 2.1 e 2.2.

- [ ] 1.2 Desligar por default o fallback DAS, remover a reclassificação `GERAR_DAS` como leitura e fazer mutação desabilitada retornar bloqueio sem criar `FiscalGuideStub`, identificador, vencimento, evidência ou auditoria `SUCCESS`.

## 2. N1 — Isolamento de testes e retirada de superfícies

- [ ] 2.1 Migrar `FakeEsocialEventClient` e builders para `Tests\Support`/autoload-dev, adaptar a suíte para provider opt-in e apagar a classe de `backend/app` sem reduzir cobertura offline.
  Depende de: 1.1

- [ ] 2.2 Retirar rota, controller/query, link de portfólio e consumer frontend de `guide-stubs`, mantendo leitura administrativa do legado apenas se necessária à reconciliação e cobrindo ausência da superfície operacional.
  Depende de: 1.2

- [ ] 2.3 Identificar e quarentenar evidências eSocial `fake-1`/simuladas e guias `STUB-*`/sem chamada externa, excluindo-as de KPI, prontidão e alegação real sem apagar marcadores antes da reconciliação.
  Depende de: 1.1, 1.2

## 3. N2 — Gates integrados

- [ ] 3.1 Executar suites focadas e completas backend/frontend, Pint, Architecture, OpenSpec estrito e varredura de zero binding/produtor Fake eSocial ou DAS stub alcançável em runtime; obter `VERDICT: PASS` independente com zero egress.
  Depende de: 2.1, 2.2, 2.3
