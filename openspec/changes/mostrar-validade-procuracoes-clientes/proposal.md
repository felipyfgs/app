## Por quê

A lista de clientes já possui a coluna visual de Procuração, mas a API não
projeta o estado sincronizado para ela. Isso faz o escritório perder a visão
operacional de uma autorização ativa, vencida, ausente ou ainda não verificada,
mesmo quando a evidência oficial já existe no banco.

## O que muda

- Projetar para cada cliente o estado sanitizado de procuração e-CAC, data da
  última verificação e validade, sempre no escopo do `CurrentOffice`.
- Reavaliar localmente a vigência ao consultar a lista: uma autorização ativa
  cuja validade passou vira `expired` imediatamente, sem chamar a SERPRO.
- Completar a coluna existente em `/clients` com badge, data de vencimento e
  orientação operacional equivalente ao resumo de certificado digital.
- Manter a sincronização oficial como ação controlada; abrir a lista nunca
  chama serviço fiscal, nem envia identificadores ou parâmetros técnicos.

## Capacidades

### Novas capacidades

- `validade-procuracoes-clientes`: projeção tenant-scoped e interface de lista
  para acompanhar validade e estado operacional de procurações oficiais.

### Capacidades modificadas

- Nenhuma.

## Impacto

- Backend: resolvedor de estado de procuração, `ClientController` e testes de
  projeção/tenancy sem egress.
- Frontend: tipos, badge e tabela de `/clients`, usando `panel-ui` e o
  arquétipo `customers.vue` já copiado no produto.
- Não altera a sincronização Integra-Procurações, credenciais, Termo, flags,
  RBAC central ou contratos de produção.

### Dependências entre changes

- Nível: `C0`.
- Bases estáveis: `ClientProcuracaoSync`, `ClientProcuracaoSnapshot` e a coluna
  já presente em `/clients`.
- Depende de: nenhuma change ativa; consome o contrato já aplicado de
  sincronização oficial de procurações.
- Marco exigido: `apply` da projeção já existente.
- Relação: coordenada com `padronizar-autorizacao-multitenant`, sem alterar seu
  contrato de permissões.
- Desbloqueia: acompanhamento operacional dos clientes antes de consultas
  Integra que exigem procuração.
- Paralelismo: não editar `CurrentOffice`, autorização central, catálogo SERPRO
  ou o mecanismo de sincronização remota nesta change.

### Não objetivos

- Não executar consulta real automática, Trial, produção, mutação fiscal ou
  habilitar capability/flag.
- Não expor CNPJ/CPF completo, protocolo, token, XML, PFX, evidência bruta ou
  `office_id` como autoridade no navegador.
- Não permitir importação ou override manual de procuração.
