## Contexto

O `EventosAtualizacaoFlowService` persiste runs duráveis e já controla espera,
TTL, rate limit, retry e consumo one-shot. A auditoria do catálogo oficial
identificou duas lacunas e uma divergência: ambas as operações de obtenção
requerem `protocolo` e `evento`, enquanto o motor atual envia apenas o
protocolo; o envelope atual só suporta um contribuinte PF/CNPJ, não o lote
tipo 3/4; e a página PJ diverge entre `eventValue` na tabela e `evento` no
exemplo JSON.

O model atual também possui `toSanitizedArray()` inadequado para navegador,
pois inclui IDs internos, protocolo, correlação e chaves das operações. A API
nova não reutilizará esse método.

## Objetivos / Não objetivos

**Goals:**

- Fazer o adapter enviar os nomes de campos oficiais PF/PJ e usar somente o
  protocolo armazenado no servidor para obter o resultado.
- Expor uma projeção pública mínima, tenant-scoped e uma UI manual de cliente
  que acompanha a máquina de estados sem polling automático.
- Preservar flags, rate limit, `CurrentOffice`, RBAC, idempotência e consumo
  one-shot existentes.

**Non-Goals:**

- Não guardar nem retornar a matriz `elementos`, NI, CPF/CNPJ, protocolo,
  correlação, payload, XML, token ou segredo.
- Não habilitar Trial/produção, mutações, scheduler ou polling no navegador.
- Não alterar os contratos globais de autorização em
  `padronizar-autorizacao-multitenant`.

## Decisões

### Contrato do fluxo por tipo de pessoa e lote

`solicitar PF` receberá um valor de evento e usará um envelope de lote com
`contribuinte.tipo=3`; PJ usará tipo 4. A obtenção carregará `evento` e
`protocolo` somente da run persistida e enviará ambos ao executor, com o tipo
de lote correspondente e número vazio conforme a referência oficial. A UI
nunca recebe nem envia o protocolo ou NI.

O nome de campo PJ permanecerá uma decisão explícita de contrato: a fonte
oficial hoje é contraditória e não autoriza selecionar silenciosamente
`eventValue` ou `evento`. Sem reconciliação oficial versionada, o adapter pode
ser testado com fixture declarada, mas Trial e produção ficam bloqueados.

Alternativas rejeitadas: encaixar a lista no `businessData`/`dados` do envelope
atual ou expor as chaves/protocolo ao frontend. A primeira viola o contrato de
envelope e a segunda permite cruzar runs e viola a autoridade do servidor.

### Projeção pública separada

Um presenter/DTO retornará apenas `id` opaco, tipo PF/PJ, fase, status,
intervalo de disponibilidade, proveniência, contagem do lote, resumo sem PII,
erros sanitizados e datas. Consultas serão filtradas por `CurrentOffice` e
cliente. A resposta final só indicará que o resultado foi consumido; a matriz
de eventos continua fora da API até existir um caso de uso com máscara e
permissão própria.

Alternativa rejeitada: adaptar `SerproEventosRun::toSanitizedArray()`, pois
outros consumidores internos podem depender de seus campos e o nome induz uma
segurança que ele não oferece para a borda HTTP.

### Ações explícitas e sem polling

O GET de histórico é somente local. A solicitação cria uma run apenas após
confirmação do usuário com permissão de sincronização. A obtenção é um POST
explícito para uma run ainda pendente; quando a janela oficial não chegou, o
backend retorna a projeção sem egress. A UI oferece atualização manual e exibe
rate limit, bloqueio, espera, conclusão e erro.

Alternativa rejeitada: polling no componente. Ele aumentaria chamadas e poderia
ultrapassar a política oficial de espera.

## Mapa de dependências

```text
contrato oficial 13.1–13.4
       ↓
adapter PF/PJ + projeção pública (backend)
       ↓
API CurrentOffice/RBAC
       ↓
painel do cliente + testes Nuxt
       ↓
ledger e gates integrados
```

- Ownership desta change: fluxo de eventos, controller/rotas, contratos Nuxt e
  painel do cliente.
- Arquivos coordenados: `CurrentOffice`, `TenantAuthorization`, catálogo e
  configuração de capability não serão editados aqui.
- `padronizar-autorizacao-multitenant` permanece coordenada; esta change usa as
  permissões atuais e não antecipa seu cutover.

## Riscos / Trade-offs

- [Contrato PJ é contraditório] → registrar a divergência, atualizar snapshot
  somente com fonte oficial reconciliada e bloquear Trial/produção até então.
- [Resultados contêm NI e datas de eventos] → não persistir/expor matriz bruta;
  usar apenas resumo sanitizado já armazenado.
- [Obtenção é one-shot] → manter lock transacional e não permitir retry do
  navegador após consumo.
- [Cliente pode não ter evento aplicável] → tratar retorno vazio/sem atualização
  como resultado de negócio, não como falha de integração.

## Plano de migração

Não há migração de banco prevista. A implantação adiciona adapter, API e UI
atrás das flags existentes, com defaults desligados. Rollback remove as rotas e
o painel; as runs já persistidas continuam internas e não são apagadas.

## Questões em aberto

- A SERPRO precisa confirmar se a solicitação PJ usa `eventValue` ou `evento`.
  Sem essa resposta, não há implementação de egress Trial/produção para PJ.
