## Why

O runtime mantém clientes e drivers `Fake`/`Simulated` dentro de `backend/app`, e o probe histórico consegue aceitar metadados locais como se fossem evidência SERPRO real. Isso conflita com a decisão operacional atual: homologação e prontidão só podem usar API real com proveniência verificável; simulação não pode existir como caminho do produto nem produzir alegação de sucesso.

## What Changes

- **BREAKING** Remover `simulated` do contrato de drivers SERPRO; o runtime aceitará somente `disabled|real`, com default `disabled` em todos os ambientes.
- **BREAKING** Restringir os ambientes SERPRO a `TRIAL` e `PRODUCTION`: `TRIAL` usará exclusivamente o gateway oficial de demonstração da SERPRO, e `HOMOLOGATION` deixará de ser aceito como rótulo local sem endpoint contratual.
- **BREAKING** Retirar bindings, clientes e respostas Fake/Simulated do código carregado em produção; doubles necessários ficarão limitados ao namespace/autoload de testes e não poderão ser resolvidos pelo container da aplicação fora da suíte.
- Tornar autenticação, procurações, mailbox, DTE, parcelamentos, guias e mutações fail-closed quando a capability não estiver `real`, sem fallback para resposta fabricada.
- Endurecer o probe para que `simulated=false` ou `sourceProvenance=SERPRO_REAL` isolados nunca produzam PASS; somente evidência vinculada a ambiente, endpoint, contrato e proveniência `PRODUCTION_CANARY` poderá gerar `PASS_REAL_*`.
- Reclassificar ou remover dos ledgers e documentos operacionais toda alegação Fake/Simulated como homologação real; `TRIAL` continuará identificado como demonstração oficial, porém inelegível para evidência produtiva.
- Manter doubles de infraestrutura estritamente em testes (`Http::fake`, `Queue::fake`, stubs de certificado e transporte), sem confundi-los com API de runtime ou evidência externa.

Non-goals:

- habilitar capability `real`, kill switch, allowlist, credencial, canário ou egress fiscal;
- executar rota SERPRO de negócio, Trial ou mutação fiscal;
- apagar campos históricos antes de migração e reconciliação auditáveis;
- remover factories/doubles genéricos do Laravel que nunca integram o runtime;
- alterar integrações não SERPRO (SEFAZ, ADN, eSocial) nesta change.

## Capabilities

### New Capabilities

- `serpro-runtime-real-only`: contrato de runtime SERPRO restrito a `disabled|real`, isolamento de doubles em testes e classificação de evidência real baseada em proveniência verificável.

### Modified Capabilities

Nenhuma.

## Impact

- Backend: `AppServiceProvider`, `SerproCapabilityDriver`, `CapabilityDriverResolver`, config/env example, clientes/adapters Integra, autenticação/procurações, probe E2E e testes associados.
- Documentação: ledger de cobertura, evidências piloto, inventário histórico e notas OpenSpec afetadas pela política supersedente.
- Compatibilidade: valores `simulated` e `HOMOLOGATION` passam a ser inválidos; dados históricos continuam legíveis apenas para quarentena/reclassificação, nunca para nova execução.
- Segurança: defaults permanecem OFF e a remoção de Fake não liga automaticamente HTTP real.

### Dependências entre changes

- Nível: `C1`.
- Bases estáveis: catálogo oficial versionado, protocolo SERPRO e `schema-conventions`.
- Depende de: `reconciliar-fontes-oficiais-serpro`.
- Capability/contrato consumido: `serpro-fontes-oficiais`, manifesto validado e comando documental read-only.
- Marco exigido: `apply` das tasks 1.1, 2.1 e 2.2.
- Relação: coordenada; os arquivos compartilhados (`backend/config/serpro.php`, testes de contenção e ledger) terão writer único e execução serializada.
- Desbloqueia: retomada das homologações reais e do probe `PRODUCTION_CANARY` sem caminho Fake/Simulated.
- Paralelismo: somente auditorias read-only e arquivos sem ownership compartilhado podem avançar em paralelo; implementação de config/provider/probe/docs é serial.
