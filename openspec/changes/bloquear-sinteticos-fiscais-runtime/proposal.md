## Why

Há dois produtores sintéticos alcançáveis pelas APIs do produto fora do núcleo SERPRO: FGTS/eSocial resolve universalmente um `FakeEsocialEventClient`, e `GERAR_DAS` pode criar guia `STUB-*` com vencimento inventado e resultado `SUCCESS` sem chamada externa. Em homologação real, ambos podem persistir estado fiscal que não veio de fonte oficial.

## What Changes

- **BREAKING** Tornar FGTS/eSocial `disabled` por default até existir client M2M oficial real explicitamente habilitado; caminho desabilitado retorna indisponibilidade e não persiste execução como sucesso vazio.
- **BREAKING** Retirar o binding runtime do client Fake e migrar o double programável para `Tests\Support`, com registro opt-in somente na suíte.
- **BREAKING** Desativar e remover a fabricação local de DAS `STUB-*`; mutação desligada passa a retornar bloqueio explícito sem guia, vencimento, evidência ou auditoria de sucesso.
- Retirar as superfícies que apresentam `guide-stubs` como resultado operacional e manter registros históricos apenas para identificação, quarentena e reconciliação controlada.
- Invalidar evidências e projeções eSocial com versão `fake-1`/origem simulada para KPI, prontidão e alegação de homologação.

Non-goals:

- implementar ou contratar uma nova API M2M eSocial;
- habilitar SERPRO real, SEFAZ outbound, mutações fiscais, credenciais ou egress;
- apagar silenciosamente registros históricos antes de identificá-los e reconciliá-los;
- remover doubles de framework isolados em testes;
- endurecer toda a proveniência fiscal/UI nesta change; a classificação transversal terá change própria.

## Capabilities

### New Capabilities

- `fgts-esocial-runtime-real-only`: runtime FGTS/eSocial fail-closed, sem client sintético, com double exclusivo de testes e legado fake inelegível.
- `das-emissao-real-only`: emissão DAS sem geração local de stub ou sucesso fabricado, preservando apenas bloqueio explícito e histórico quarentenado.

### Modified Capabilities

Nenhuma.

## Impact

- Backend: provider, contrato/client eSocial, serviço/job/adapter/persistência, config FGTS; adapter/hook/model/query/controller/rota de DAS e config de monitoramento.
- Frontend: remoção da consulta/link de `guide-stubs` como superfície operacional.
- Dados: registros `fake-1`, `simulated=true`, `STUB-*` e `is_external_call=false` permanecem identificáveis, mas não contam como evidência real nem resultado operacional válido.
- Segurança: defaults continuam OFF; a remoção de sintéticos não liga nenhuma integração externa.

### Dependências entre changes

- Nível: `C2`.
- Bases estáveis: contratos fiscais, cofre e `schema-conventions`.
- Depende de: `eliminar-fake-simulado-runtime-serpro`.
- Capability/contrato consumido: política runtime real-only, doubles em `Tests\Support` e provider de aplicação saneado.
- Marco exigido: `apply` das tasks 2.1 e 2.2.
- Relação: coordenada, porque `AppServiceProvider` e a infraestrutura de doubles são compartilhados e exigem writer único.
- Desbloqueia: homologação sem produtores sintéticos e a change transversal de proveniência fiscal real-only.
- Paralelismo: DAS pode avançar em arquivos próprios enquanto o provider SERPRO é validado; eSocial/provider só avança após o marco upstream.
