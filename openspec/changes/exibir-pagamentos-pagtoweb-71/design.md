## Context

O monitor já executa PAGTOWEB 7.3 para obter uma quantidade agregada de documentos de pagamento. A operação oficial PAGTOWEB 7.1 (`PAGAMENTOS71`, rota `Consultar`, estado `PRODUCTION`) é a fonte de itens individuais, exige o poder e-CAC `00004` quando o cliente é representado e pode ser bilhetável. O hub é multi-tenant, usa `CurrentOffice`, executa consultas por fila e deve falhar fechado quando a capacidade SERPRO não estiver habilitada.

## Goals / Non-Goals

**Goals:**

- Consultar PAGTOWEB 7.1 apenas sob solicitação explícita do usuário autorizado, com período, paginação limitada e o mesmo controle de operação central do Integra Contador.
- Exibir os pagamentos retornados como itens sanitizados: identificador de documento mascarado, tipo, data, valores e códigos de receita permitidos.
- Registrar a proveniência da execução e o período, mantendo uma projeção por cliente sem payload externo bruto.
- Manter isolamento por escritório, autorização, mensagens de erro seguras e telemetria sem dados fiscais sensíveis.

**Non-Goals:**

- Não realizar consultas externas automaticamente, habilitar `live`, alterar flags, ler arquivos de senha/PFX, nem emitir/compensar documentos.
- Não expor números de documento completos, CPF, CNPJ, tokens, certificados, XML ou resposta externa integral pela API ou nos logs.
- Não substituir a confirmação de uma guia específica já existente nem a contagem agregada PAGTOWEB 7.3.

## Decisions

### Adaptador específico para PAGTOWEB 7.1

O fluxo usará codec, adaptador e projetor próprios, chamando `SerproOperationService` com `operation_key` 7.1. Isso preserva o comportamento de confirmação de guia existente, que não tem paginação nem contrato de monitor. A alternativa de ampliar `SerproGuideEmissionClient` foi descartada porque mesclaria dois casos de uso e aumentaria o risco de persistir dados de documento em logs de emissão.

### Filtros mínimos e paginação no servidor

O endpoint aceitará intervalo de arrecadação obrigatório, filtros oficiais permitidos e página/tamanho limitados a no máximo 100 itens. O backend normalizará o payload antes da chamada e não aceitará filtro por número de documento nesta tela. Assim a UI atende à auditoria por período sem transformar a rota em mecanismo de busca de identificadores sensíveis.

### Projeção sanitizada e evidência controlada

Cada resultado conterá digest estável do número externo e uma versão mascarada para a UI, além dos campos fiscais necessários à leitura. A resposta bruta será descartada depois da decodificação; se o domínio exigir evidência integral para auditoria futura, ela deverá usar `SecureObjectStore` em mudança específica. Guardar o payload no banco foi descartado porque ampliaria a superfície de dados fiscais sensíveis.

### Contrato e UI separados da contagem 7.3

A tela de pagamentos incluirá a lista 7.1 e conservará a quantidade do 7.3 como referência independente. A rota e as projeções não compartilharão tabelas; isso torna a migração reversível e impede que uma falha da listagem apague a última contagem válida.

## Mapa de dependências

```text
schema-conventions + catálogo oficial local
                 │
cobrir-contagem-pagamentos-pagtoweb-73 (apply, coordenada)
                 │
exibir-pagamentos-pagtoweb-71
  ├─ catálogo/migrações/modelos/codec/adaptador
  ├─ rotas, job e autorização de tenant
  └─ tipos, composable, painel e testes
                 │
          gates integrados e evidência segura
```

`cobrir-contagem-pagamentos-pagtoweb-73` mantém ownership exclusivo do contrato, tabela e UI da contagem 7.3. Esta change só consome o adaptador central e o padrão de autorização após o marco `apply`; não altera seus arquivos. As migrações e a rota 7.1 podem avançar em paralelo aos ajustes finais de gate do 7.3, mas a validação integrada ocorrerá depois de ambos.

## Risks / Trade-offs

- [Consulta bilhetável acionada sem intenção] → botão manual, alerta na UI, período obrigatório, paginação limitada e flags/capacidade fail-closed.
- [Dados fiscais em logs ou API] → allowlist de campos, mascaramento antes da projeção e logs apenas de códigos, hashes e metadados de execução.
- [Documento retornado em formato inesperado] → codec tolerante somente a estruturas oficiais conhecidas; resposta inválida falha sem persistir itens parciais.
- [Poder de procuração ausente] → validação de capacidade e propagação de erro seguro, sem tentativa de contornar autenticação.
- [Divergência entre ambiente simulado e produção] → proveniência explícita na execução e nenhuma alegação de consulta real sem evidência externa autorizada.

## Migration Plan

1. Adicionar operação 7.1 e tabelas de projeção sanitizada em migrações reversíveis.
2. Implantar adaptador, endpoint autenticado e job sem habilitar capacidade live.
3. Publicar a tela de pagamentos com estado de proveniência e registros mascarados.
4. Executar testes/gates; uma consulta real só será feita depois de autorização operacional delimitada e de ambiente live configurado.
5. Em rollback, remover rotas/UI e reverter apenas as migrações desta change; a contagem 7.3 permanece íntegra.

## Open Questions

- A eventual retenção de evidência integral no cofre precisa de política de prazo e acesso antes de ser incluída.
- Uma consulta real de validação ainda requer cliente/poder autorizado, intervalo preciso e teto de chamadas/custo definidos pelo responsável operacional.
