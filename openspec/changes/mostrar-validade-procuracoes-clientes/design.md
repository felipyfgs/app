## Contexto

`ClientProcuracaoSync` e `ClientProcuracaoSnapshot` já armazenam a projeção de
uma sincronização oficial e os poderes por cliente. A tela `/clients` já
possui coluna e badge, porém `ClientController::index` não inclui esses dados
na resposta. O estado também não é reavaliado no momento da leitura quando
`valid_to` já passou.

## Objetivos / Não objetivos

**Goals:**

- Mostrar um resumo de procuração confiável, sanitizado e tenant-scoped na
  lista de clientes.
- Calcular `authorized`, `expiring`, `expired`, `missing` e `unverified` a
  partir da projeção local, tratando vencimento como regra temporal local.
- Usar o mesmo padrão visual de resumo de certificado e preservar o arquétipo
  `customers.vue` do dashboard.

**Non-Goals:**

- Não iniciar sync Integra ao listar clientes nem mudar o agendamento existente.
- Não expor evidência, poder, identificador fiscal, autor, token ou `office_id`
  como autoridade HTTP.
- Não transformar uma evidência manual em autorização válida.

## Decisões

### Resolvedor único e somente de leitura

Um serviço dedicado recebe modelos já carregados e devolve uma projeção mínima.
Ele prefere a projeção canônica `ClientProcuracaoSync`; usa snapshot por
ambiente somente como fallback quando a canônica não existir. A decisão mantém
o controller sem regra fiscal e elimina divergência entre listagem e detalhe.

Alternativa rejeitada: mudar o status persistido durante o GET. A leitura não
deve produzir write nem mascarar a data da última verificação oficial.

### Vigência calculada na borda de domínio

`authorized` com `valid_to` no passado é projetado como `expired`, sem consulta
externa. `valid_to` dentro de 30 dias vira `expiring`, preservando o estado
sincronizado para auditoria. Sem projeção é `unverified`; sync que informa
ausência permanece `missing`.

Alternativa rejeitada: inferir autorização por qualquer `TaxProxyPower ACTIVE`.
Somente a projeção criada pela sincronização oficial é suficiente para a coluna
operacional do cliente.

### Contrato HTTP mínimo

`GET /clients` e `GET /clients/{client}` retornam apenas status, validade e
última verificação. Não há rota nova nem parâmetro de ambiente/office no
navegador. A coluna usa esses três campos e não busca dados adicionais.

## Mapa de dependências

```text
projeção oficial existente
       ↓
resolvedor local de validade (backend)
       ↓
serialização de clientes + testes tenant
       ↓
badge/tabela customers.vue + Vitest
       ↓
gates integrados
```

- Ownership: esta change altera a projeção de lista e sua apresentação.
- `padronizar-autorizacao-multitenant` é coordenada: políticas e
  `CurrentOffice` existentes continuam sendo consumidos, sem alteração de RBAC.
- Nenhum arquivo de catálogo, capability ou sincronização remota é compartilhado.

## Riscos / Trade-offs

- [Projeção antiga] → exibir `unverified` quando não houver evidência local;
  nunca afirmar autorização por inferência.
- [Vencimento sem novo sync] → a regra local marca `expired` imediatamente e
  mantém a última verificação visível.
- [Tela dispara egress] → o composable de lista continua GET local; testes
  rejeitam qualquer ação automática de sync.

## Plano de migração

Não há migration. O deploy adiciona campos já opcionais ao contrato da lista;
versões antigas da UI os ignoram. Rollback remove o resolvedor e a projeção sem
apagar a evidência de procuração existente.

## Questões em aberto

Nenhuma para o cálculo local. A periodicidade de uma sincronização externa
automática exige autorização operacional e fica fora desta change.
