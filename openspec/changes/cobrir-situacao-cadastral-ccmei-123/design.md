## Context

O catálogo oficial classifica `CCMEI/CCMEISITCADASTRAL123` como leitura de
produção, na rota `/Consultar`, sem dados de entrada. A resposta contém uma
lista de CNPJ, situação cadastral e indicador de enquadramento MEI; os CNPJ
não são necessários para o monitor local e não devem ser persistidos.

## Goals / Non-Goals

**Goals:**

- Projetar status e enquadramento por cliente/escritório, com histórico idempotente.
- Oferecer consulta confirmada, leitura local e UI no painel do cliente.
- Rejeitar respostas vazias, ambíguas ou com valores inválidos antes da projeção.

**Non-Goals:**

- Não tratar o retorno como cadastro de estabelecimentos nem expor CNPJ.
- Não emitir certificado CCMEI ou liberar chamadas reais sem autorização.

## Decisions

- Criar codec dedicado, em vez de reutilizar o codec 122: os contratos oficiais
  e os campos permitidos são diferentes.
- Guardar somente `situacao` e `enquadrado_mei`; isso evita replicar CNPJ e
  permite o estado do monitor. Alternativa descartada: persistir a lista bruta
  criptografada, que não acrescenta função ao monitor atual.
- Reaproveitar as tabelas/projeções CCMEI somente se os campos representarem o
  certificado. Caso contrário, usar projeção própria para não misturar origem
  122 e 123.

## Risks / Trade-offs

- [Mais de um CNPJ no retorno] → projetar uma situação agregada conservadora e
  manter a quantidade, sem identificar empresas.
- [Contrato remoto evoluir] → allowlist estrita e falha fechada para campos
  obrigatórios ausentes.
- [Consulta bilhetável] → ação explícita e nenhuma execução automática.

## Mapa de dependências

```text
cobrir-consulta-dados-ccmei (verify)
  └─ cobrir-situacao-cadastral-ccmei-123 (C1)
```

Ownership: esta change possui o codec, projeção e UI do 123; preserva o
contrato do 122. Rollout: migração → adapter → API → UI. Rollback remove a
ação do frontend e mantém histórico local já gravado para auditoria.
