## Context

O serviço oficial `DEFIS/CONSDECLARACAO142` usa a rota `Consultar`, não recebe
dados de negócio e devolve em `dados` uma lista de ano, identificador da DEFIS,
tipo e data/hora. A lista é potencialmente faturável; por isso não pode ser
acionada em GET, abertura de tela ou atualização automática.

## Goals / Non-Goals

**Goals:**

- Normalizar apenas `anoCalendario` e `tipo` em uma projeção local por Office e
  Client, com histórico idempotente e imutável.
- Manter a resposta original, `idDefis`, data/hora e identificadores do
  contribuinte fora de JSON público, logs e evidência operacional.
- Criar POST confirmado e GET exclusivamente local, ambos resolvendo o cliente
  por `CurrentOffice`, além de modal com estados de carregamento, vazio e erro.

**Non-Goals:**

- Executar tráfego real, transmitir DEFIS ou implementar 141, 143 e 144.
- Inferir entrega de uma declaração a partir da mera existência de um ano em
  outro serviço, ou guardar `idDefis` para encadear chamadas futuras.

## Decisions

- O codec aceita somente lista oficial e tipos `1` a `4`; estrutura ambígua ou
  ano inválido falha antes de persistir.
- A data/hora de transmissão não é usada: a documentação não define semântica
  suficiente para expor a data sem o identificador associado. A observação
  local registra quando o hub observou a lista.
- O projector cria uma observação por conjunto sanitizado (ano/tipo/proveniência)
  e atualiza a projeção atual; não apaga períodos ausentes, para não transformar
  falha parcial em exclusão de histórico.
- O POST requer `confirmed: true`; capability, allowlist e kill switch centrais
  continuam sendo a autoridade de execução.

## Risks / Trade-offs

- [Cobrança acidental] → confirmação explícita, GET local e flags desligadas.
- [Vazamento cross-tenant] → `CurrentOffice`, rejeição de `office_id` e teste
  de cliente estrangeiro.
- [Retorno com PII] → codec/evidência por allowlist, nunca serialização do body.
- [Sem chave para 143/144] → essas operações terão mudança própria e cofre
  dedicado se a documentação exigir encadeamento por identificador.

## Migration Plan

1. Aplicar as migrações aditivas de catálogo e das tabelas de observação e
   projeção DEFIS.
2. Implantar com capability de `simples_mei` e kill switch ainda fail-closed;
   somente depois da autorização operacional a equipe poderá promover a
   capability real pelo fluxo já existente.
3. Em rollback, desabilitar a capability. As tabelas e o histórico já gravado
   permanecem somente leitura e não exigem reversão destrutiva.

## Mapa de dependências

```text
catálogo + documentação oficial -> codec -> projeção/API -> UI/testes -> gates
```

A mudança reutiliza somente o executor central, `SimplesMeiAdapter` e a
capability `simples_mei`; não altera transportes, OAuth ou flags globais.
Rollback é a capability desligada: nenhuma nova coleta é enfileirada e o
histórico local preservado permanece somente leitura.

## Open Questions

- Nenhuma para o fluxo offline: a página oficial e o catálogo confirmam a
  coordenada, rota, versão, retorno de lista e poder `00146` quando aplicável.
