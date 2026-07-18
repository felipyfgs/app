## Context

O catálogo oficial já classifica `pagtoweb.contaconsdocarrpg` como produção, leitura, rota `/Consultar`, versão `1.0`, poder `00004` e cobrança de consulta. O hub hoje resolve a coordenada pelo executor genérico, porém não possui codec de filtros, projeção sanitizada, endpoint tenant-scoped ou painel de negócio para a contagem retornada em `dados` como escalar JSON.

## Goals / Non-Goals

**Goals:**

- Criar uma consulta confirmada, limitada ao `CurrentOffice` e ao cliente pertencente a ele, para os filtros oficiais permitidos pelo serviço 7.3.
- Transformar a resposta escalar em observação e projeção persistíveis, sem gravar payload bruto nem identificadores de documentos.
- Oferecer leitura de histórico e disparo assíncrono seguro na surface de guias, usando a cadeia de autenticação, procuração e billing central.
- Cobrir o comportamento com Fake/Simulated, testes de contrato, autorização, UI e documentação de evidências mascaradas.

**Non-Goals:**

- Não habilitar capability real, não efetuar canário ou consulta externa automática e não acessar arquivos de credenciais.
- Não substituir a consulta detalhada 7.1, nem emitir o comprovante 7.2.
- Não aceitar `office_id` no request, não registrar corpo bruto, nem mostrar tokens, documentos fiscais, certificados ou segredos.

## Decisions

### Codec de filtro allowlist e retorno escalar

O codec aceitará somente os grupos documentados: `numeroDocumento`, `numeroDocumentoLista`, `codigoReceitaLista`, `codigoTipoDocumentoLista`, `intervaloDataArrecadacao` e `intervaloValorTotalDocumento`. Ele normalizará data, decimal e listas, exigirá ao menos um filtro e rejeitará combinações ambíguas ou chaves desconhecidas. A resposta será aceita apenas quando `dados` representar um inteiro não negativo; metadados de mensagem serão reduzidos a códigos/textos seguros.

Alternativa considerada: encaminhar o payload arbitrário para o executor genérico. Foi descartada porque permite campos não documentados, dificulta a retenção segura e não produz uma semântica estável para o monitor.

### Observação e projeção sanitizadas

Uma observação imutável registrará resultado, origem e tempo de consulta; a projeção por cliente manterá apenas a última contagem, status, horário e correlação segura. Filtros serão convertidos em uma forma resumida que não retenha números de documentos nem documentos fiscais completos.

Alternativa considerada: reutilizar a tabela de confirmação de pagamento 7.1. Foi descartada porque 7.3 não devolve itens de pagamento e a mistura esconderia a diferença entre detalhe e quantidade.

### Fluxo central e tenant-scoped

O controller usará as mesmas permissões `view`/`sync` de guias, `CurrentOffice`, feature de guias e o job central de operação SERPRO. O adapter enviará somente o filtro normalizado e a coordenada `PAGTOWEB/CONTACONSDOCARRPG73`; OAuth, `jwt_token`, autenticação de procurador, poder `00004`, kill switch e billing permanecem responsabilidade da infraestrutura central.

Alternativa considerada: invocar o transporte HTTP diretamente no controller. Foi descartada pois contornaria as salvaguardas de capability, billing e tokens.

### UI de detalhes do cliente

A UI será uma rota filha de cliente no módulo de guias, usando o mesmo arquétipo de settings das superfícies recentes. Ela exibirá o último resultado, histórico e formulário de filtro explícito; a confirmação de consulta comunicará que a operação é potencialmente bilhetável. Não haverá controles para ligar capability ou alterar configuração global.

## Risks / Trade-offs

- [Filtros oficiais evoluírem] → manter a URL oficial no catálogo e testes que rejeitam campos desconhecidos, atualizando o codec somente após revisão documental.
- [Consulta `/Consultar` ser cobrada] → exigir confirmação do usuário, classificação no catálogo e manter flags reais desligadas por padrão.
- [Dados de documento aparecerem em payload] → persistir somente agregados e resumos sanitizados; nunca refletir o payload de transporte.
- [Divergência entre histórico e estado remoto] → apresentar horários/origem e não inferir que a contagem substitui a lista de pagamentos.

## Mapa de dependências

```text
catálogo oficial + infraestrutura central de Integra Contador
                         │
                         ▼
codec/adapter/projeções PAGTOWEB 7.3 ──► controller e rotas ──► composable/painel/UI
                         │                                             │
                         └──────────────────► testes e evidências ◄─────┘
```

- Ownership da capability: esta change possui os novos artefatos de contagem e sua documentação; não altera contratos de RBAC ou catálogo compartilhado fora das entradas necessárias.
- Dependências ativas: `padronizar-autorizacao-multitenant` e `ui-template-fidelity-total` são coordenadas. Esta change consome `CurrentOffice` e o template estáveis, sem editar seus artefatos de planejamento.
- Marcos consumidores: backend antes do controller; controller antes do composable/painel; todos antes dos gates integrados. Rollout com migrations antes de rotas; rollback desabilita a surface e preserva observações como histórico local.

## Migration Plan

1. Criar tabelas aditivas e registrar a coordenada/billing conforme o catálogo canônico.
2. Publicar backend e UI com capabilities ainda fail-closed; o Fake/Simulated permanece a única via de testes.
3. Executar migrations, testes e gates completos. Se houver rollback, remover a rota/UI da release ou desligar a feature, sem apagar observações já auditáveis.

## Open Questions

- Nenhuma para o contrato offline. Uma chamada externa futura exige autorização operacional explícita, orçamento de bilhetagem e revisão de procuração/poderes antes de qualquer execução.
