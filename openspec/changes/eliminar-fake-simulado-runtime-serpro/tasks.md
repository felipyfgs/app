## 1. N0 — Contrato de driver real-only

- [x] 1.1 Eliminar `SERPRO_USE_FAKE_CLIENTS` dos defaults operacionais, tornar todas as capabilities `disabled` por default e impedir fallback Fake quando a chave legada estiver ausente, mantendo temporariamente a leitura explícita de `simulated` até os consumidores serem removidos na task 2.1; cobrir os defaults fail-closed e zero HTTP.
  Depende de: change externa `reconciliar-fontes-oficiais-serpro`, marco `apply` das tasks 1.1, 2.1 e 2.2.

- [x] 1.2 Configurar `TRIAL` exclusivamente com o endpoint oficial de demonstração da SERPRO e token operacional externo, mantendo `PRODUCTION` no endpoint produtivo; remover `HOMOLOGATION` dos ambientes, defaults e contratos sem habilitar egress.
  Depende de: 1.1

## 2. N1 — Container e fronteiras fail-closed

- [x] 2.1 Isolar os bindings de doubles em um provider sob `Tests\Support`, remover do `AppServiceProvider` toda troca automática por Fake em `testing`/flag legada e fazer os contratos centrais (OAuth, gateway, Autentica Procurador e procurações) usarem somente `Disabled*|Http*`; o resolver passa a rejeitar `simulated` em qualquer ambiente e `disabled` nunca cria token, poder ou resposta de sucesso.
  Depende de: 1.1, 1.2

- [x] 2.2 Remover os fallbacks sintéticos de mailbox, DTE, parcelamentos, guias e mutações do runtime/provider; garantir que somente driver `real` alcança o executor HTTP e que ausência de capability falha fechada, preservando doubles apenas pelo provider `Tests\Support`.
  Depende de: 1.1, 1.2

## 3. N2 — Doubles isolados e produtores simulados removidos

- [x] 3.1 Migrar fisicamente os clientes programáveis necessários para `Tests\Support`/autoload-dev, adaptar imports da suíte e apagar as classes Fake/Simulated SERPRO correspondentes de `backend/app` sem reduzir a cobertura determinística.
  Depende de: 2.1, 2.2

- [x] 3.2 Remover dos adapters, schedulers, onboarding e projeções qualquer branch que produza nova origem simulada; remover então o case `Simulated` do enum, preservando leitura histórica apenas como bloqueio/quarentena e cobrindo a regra com testes.
  Depende de: 2.1, 2.2

## 4. N3 — Evidência real e comunicação operacional

- [x] 4.1 Endurecer `serpro:e2e-probe` para exigir proveniência completa `PRODUCTION_CANARY` e classificações `PASS_REAL_*`, removendo `PASS_BUSINESS` e o teste que forjava `simulated=false`/`SERPRO_REAL` in-process.
  Depende de: 3.1, 3.2

- [x] 4.2 Reclassificar ledgers, evidências piloto, inventários e superfícies operacionais para remover Fake/Simulated/Trial como homologação, marcar histórico insuficiente como bloqueado e distinguir testes offline da verificação documental HTTP real.
  Depende de: 3.2

- [x] 4.3 Remover por migração os registros explicitamente `SIMULATED` e suas projeções dependentes, sem apagar registros apenas `UNVERIFIED`; aplicar a migração e registrar a contagem pós-limpeza (local: 0 runs, 0 snapshots, 0 attempts).
  Depende de: 3.2

## 5. N4 — Gates integrados e evidência de prontidão

- [x] 5.1 Executar suites SERPRO/Integra focadas e completas, Pint, Architecture, OpenSpec estrito, varredura de zero binding/classe Fake/Simulated em runtime e `serpro:official-sources-verify` real 8/8; obter `VERDICT: PASS` independente sem chamar rota fiscal de negócio.
  Evidência em 18/07/2026: `vendor/bin/pint --test` (1.676 arquivos), Architecture e suítes Unit/Feature SERPRO passaram; `serpro:official-sources-verify` retornou 8/8 fontes públicas oficiais com `exit 0`; a varredura de `backend/app`, `bootstrap`, `config` e `routes` não encontrou binding/classe Fake/Simulated SERPRO de runtime; `npx openspec validate eliminar-fake-simulado-runtime-serpro --strict` passou. Revisão independente: `VERDICT: PASS`. Nenhuma rota fiscal de negócio foi chamada.
  Depende de: 4.1, 4.2, 4.3
