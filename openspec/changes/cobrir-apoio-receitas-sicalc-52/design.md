## Context

`CONSULTAAPOIORECEITAS52` é uma operação `SICALC` de produção, versão `2.9`,
na rota `/Apoiar`, não faturável. Recebe somente `codigoReceita` e devolve a
receita e as extensões com grupos de campos obrigatórios, opcionais e
informações para os serviços de DARF.

## Goals / Non-Goals

**Goals:** validar entrada mínima, persistir um resumo sanitizado por
escritório/cliente/código de receita e permitir consulta manual explícita.

**Non-Goals:** consolidar valores, emitir DARF, disponibilizar código de
barras/PDF, ou guardar a resposta bruta e identificadores fiscais.

## Decisions

- A projeção armazena apenas código, descrição, extensões e booleanos/labels
  de atributos permitidos; não copia contribuinte ou envelope SERPRO.
- A consulta exige `confirmed=true`, `CurrentOffice` e permissão fiscal de
  sincronização; a leitura do histórico usa permissão de visualização.
- O `codigoReceita` é normalizado como string numérica curta e nunca é aceito
  de uma projeção de outro tenant.
- A resposta ambígua falha fechada e não promove projeção.

## Risks / Trade-offs

- A Receita pode alterar atributos e extensões; cada observação preserva a
  versão sanitizada para auditoria e a projeção aponta para a última válida.
- Mesmo sendo `/Apoiar` não faturável, a chamada externa permanece opt-in e
  sujeita às flags/capability do hub.

## Rollout

Migração → adapter/codec → API → painel. Rollback remove a ação nova sem
alterar observações já registradas.
