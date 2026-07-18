## Por quê

As quatro operações de `EVENTOSATUALIZACAO` já têm um motor interno para
solicitar, aguardar e obter resultados assíncronos, porém não possuem uma API
tenant-scoped nem uma tela de negócio. Assim, o escritório não consegue iniciar
uma solicitação manual, acompanhar a janela oficial de processamento ou agir
sobre uma falha sem recorrer a mecanismos internos.

## O que muda

- Expor o fluxo de eventos PF/PJ por uma API local autorizada, com histórico
  sanitizado por cliente e sem aceitar `office_id`, protocolo ou coordenadas
  SERPRO do navegador.
- Criar uma superfície operacional no detalhe do cliente para iniciar uma
  solicitação manual, acompanhar estado, aguardar o tempo oficial e atualizar
  um resultado pendente de modo explícito.
- Corrigir a projeção pública para não revelar protocolo, correlação, IDs de
  escritório/cliente, chaves de operação ou conteúdo bruto da resposta.
- Cobrir o ciclo assíncrono, RBAC, tenancy, limite, bloqueio, ausência de
  egress em leituras e os estados de interface com testes locais.

## Capacidades

### Novas capacidades

- `eventos-atualizacao-operacional`: solicitação manual, acompanhamento local
  e obtenção segura dos eventos assíncronos PF/PJ da Integra Contador.

### Capacidades modificadas

- Nenhuma.

## Impacto

- Backend: `EventosAtualizacaoFlowService`, `SerproEventosRun`, controller,
  rotas e testes Laravel. A integração continua passando pelo executor central
  e pelas flags, rate limit e controles já existentes.
- Frontend: contratos API, composable e painel no detalhe do cliente, seguindo
  `panel-ui` e `ui-archetype`.
- Documentação: ledger das quatro operações 13.1 a 13.4, sem promovê-las a
  prontas para produção.

### Dependências entre changes

- Nível: `C0`.
- Bases estáveis: catálogo oficial reconciliado e o fluxo
  `EventosAtualizacaoFlowService` já presente no código.
- Depende de: nenhuma change ativa bloqueante. `padronizar-autorizacao-multitenant`
  é coordenada: esta change consome as garantias atuais de `CurrentOffice` e
  `TenantAuthorization`, sem alterar o contrato de RBAC.
- Marco exigido: `apply` do motor existente, já presente no código.
- Relação: coordenada.
- Desbloqueia: homologação Trial controlada e, após autorização, canário de
  produção das quatro operações.
- Paralelismo: não disputar arquivos de catálogo, autorização central, ledger
  ou migrations com outras changes; backend e frontend podem avançar em
  sequência após a definição da projeção pública.

### Não objetivos

- Não executar Trial ou produção, não mudar flags, nem habilitar polling
  automático.
- Não criar mutações fiscais, parecer jurídico, envio externo ou coleta de
  identificadores fiscais no navegador.
- Não expor protocolo, CPF/CNPJ, payload bruto, PFX, token, XML ou segredo.
