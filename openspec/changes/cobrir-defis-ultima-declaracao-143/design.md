## Context

A operação oficial `DEFIS/CONSULTIMADECREC143` recebe `ano` e devolve o
identificador da última DEFIS, o recibo e a declaração em PDF base64. A
listagem 142 já existe no monitor, mas deliberadamente não armazena `idDefis`.
Os PDFs não podem passar por logs, banco público, payload de API ou frontend.

## Goals / Non-Goals

**Goals:**

- Consultar explicitamente a 143 por ano-calendário, usando as coordenadas e
  elegibilidade oficiais.
- Persistir os bytes somente no `SecureObjectStore` e expor descritores locais
  sem identificador fiscal, base64 ou conteúdo de PDF.
- Garantir isolamento por `CurrentOffice`, autorização e download autenticado.

**Non-Goals:**

- Chamada real de negócio, transmissão DEFIS 141, implementação da 144,
  automação periódica, retenção de `idDefis` ou renderização/extração do PDF.

## Decisions

1. Um codec fail-closed aceitará `ano`, `recibo` e `declaracao` em base64 e
   descartará `idDefis` antes de qualquer projeção. Isso reduz a superfície de
   dados fiscais e mantém a 144 como uma capability própria.
2. Um registro de artefato tenant-scoped manterá apenas metadados permitidos
   (ano, tipo, digest, referência de cofre, observação e origem). Os bytes vão
   diretamente ao `SecureObjectStore`, seguindo o padrão de artefatos fiscais
   existente. Alternativa rejeitada: usar uma coluna binária ou JSON em banco.
3. A leitura será sempre local; o POST confirmado apenas enfileira a consulta.
   A UI usa o mesmo padrão da 142 e abre downloads por endpoint autenticado.
4. A 143 será representada por operação de monitoramento própria, evitando que
   `DEFIS/CONSULTAR` continue sempre resolvendo para a coordenada 142.

## Mapa de dependências

```text
cobrir-consulta-declaracoes-defis-142 (verify)
  └─ cobrir-defis-ultima-declaracao-143 (C1)
       └─ futura DEFIS 144
```

Esta change consome apenas a projeção e os padrões de segurança da 142. Ela não
altera artefatos de contrato pertencentes à change 142, e os gates de catálogo e
monitor Simples/MEI são coordenados após as duas mudanças aplicadas.

## Risks / Trade-offs

- [Resposta com base64 malformado ou grande] → decoder estrito, limite de bytes
  e rollback transacional antes de publicar descritores.
- [Consulta faturável por acidente] → POST com confirmação, sem coleta em GET e
  sem scheduler.
- [Vazamento por download cruzado] → busca por escritório e cliente correntes,
  autorização e referência de cofre jamais retornada.
- [Perda de correlação para a 144] → a 143 não retém `idDefis`; a 144 terá
  design separado com referência interna distinta, se necessária.

## Migration Plan

1. Criar tabelas e índices tenant-scoped para descritores/observações.
2. Implantar codec, projector, rotas e UI com flags existentes ainda fechadas.
3. Validar Fake/Simulated e gates. Rollback remove somente descritores locais;
   objetos de cofre são purgados pela rotina segura associada, nunca via API.

## Open Questions

- Nenhuma para a implementação local. A homologação real permanece dependente de
  autorização operacional explícita e teto de consumo.
