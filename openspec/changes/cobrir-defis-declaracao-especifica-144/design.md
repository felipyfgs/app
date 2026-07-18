## Context

A DEFIS 142 devolve `idDefis`, mas o monitor atual normaliza somente ano e tipo
e descarta o valor. A DEFIS 144 exige exatamente esse identificador para
retornar os PDFs de uma declaração específica. A mudança precisa preservar a
proibição de receber ou devolver o identificador pelo navegador.

## Goals / Non-Goals

**Goals:**

- Guardar o `idDefis` recebido da 142 exclusivamente no `SecureObjectStore` e
  associá-lo a uma referência opaca por escritório, cliente e declaração.
- Enfileirar a 144 somente por `reference_id` pertencente ao cliente do
  `CurrentOffice`, validar o retorno e persistir os dois PDFs no cofre.
- Expor somente ano, tipo, estado e descritores de download ao frontend.

**Non-Goals:**

- Chamada SERPRO real, emissão/transmissão, entrada de `idDefis` bruto,
  migração retrospectiva de listas 142 já existentes, scheduler ou parsing do
  conteúdo dos PDFs.

## Decisions

1. A 142 passa a emitir internamente um campo efêmero `declaration_reference`
   para o projector; o campo é removido antes de snapshot/evidência/API. O
   valor real é serializado no cofre com AAD de escritório e SHA-256, enquanto
   a tabela mantém apenas o `vault_object_id` opaco. Alternativa rejeitada:
   hash do `idDefis` no banco, pois continuaria sendo um identificador
   correlacionável e não serviria para a 144.
2. A UI recebe apenas o id numérico local da referência, nunca o valor SERPRO.
   A consulta 144 exige confirmação explícita e autoriza o cliente antes de
   resolver a referência. Alternativa rejeitada: formulário livre com
   `idDefis`, pois amplia exposição e permite enumeração.
3. A 144 reutiliza o codec documental 143 para os PDFs e um registro de
   artefato separado, preservando origem e run. Alternativa rejeitada: reutilizar
   o artefato da 143, pois uma declaração histórica tem identidade e auditoria
   distintas.

## Mapa de dependências

```text
cobrir-consulta-declaracoes-defis-142 (verify)
  └─ cobrir-defis-ultima-declaracao-143 (verify)
       └─ cobrir-defis-declaracao-especifica-144 (C1)
```

Ownership desta change: referência segura DEFIS, coordenada 144 e superfície de
consulta específica. Ela não altera os artefatos OpenSpec das changes upstream;
modifica apenas o código compartilhado indispensável. Rollout: migração →
projeção 142 → endpoint 144 → UI. Rollback desabilita a ação 144 e mantém
evidências já armazenadas sob retenção do cofre.

## Risks / Trade-offs

- [Referência sem conteúdo de cofre] → falha fechada, sem chamar SERPRO.
- [Resposta 142 sem idDefis] → mantém lista sanitizada, mas não cria ação 144.
- [Download cruzado] → lookup por escritório e cliente, autorização tenant e
  AAD vinculada ao office.
- [Cobrança acidental] → POST confirmado; GET local não consulta SERPRO.

## Migration Plan

1. Criar tabela de referências opacas e migrar somente dados novos da 142.
2. Implementar codec, catálogo, post-consulta, rotas e interface da 144.
3. Rodar Fake/Simulated e gates; homologação real permanece externa e explícita.

## Open Questions

- Nenhuma para a implementação local; a documentação oficial define `idDefis`
  como Number, mas o codec aceitará apenas dígitos canônicos para evitar perda
  de precisão em identificadores grandes.
