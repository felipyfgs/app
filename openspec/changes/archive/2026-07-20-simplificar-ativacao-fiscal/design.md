## Context

O runtime fiscal possui hoje decisões redundantes em `FeatureFlags`, capabilities SERPRO, allowlists de escritórios, guards de jobs e telas de configuração. Isso produz estados contraditórios e exige alterações de `.env` para desenvolver. O sistema já dispõe de tenancy, auditoria, vault, catálogo SERPRO, fila fiscal, onboarding e snapshots de procuração; a mudança consolida esses componentes sem expor segredos e sem conceder acesso fiscal implícito ao `PLATFORM_ADMIN`.

## Goals / Non-Goals

**Goals:**

- Tornar módulos consultivos disponíveis por padrão depois do onboarding.
- Resolver perfil, kill switch, restrições, readiness e elegibilidade em um serviço central, fail-closed.
- Permitir que apenas a plataforma aplique exceções globais ou por escritório pela UI.
- Revalidar a decisão no início de toda execução remota e recuperar coletas após liberação.
- Automatizar A1, Termo, token, procurações por cliente e coleta inicial do escritório.
- Preservar dados armazenados e o isolamento de tenants durante qualquer bloqueio.

**Non-Goals:**

- Habilitar `FISCAL_MUTATION`, transmissão, adesão ou alteração fiscal.
- Executar SERPRO live durante testes ou inventar respostas para cenários de demonstração.
- Substituir o contrato SERPRO global por credenciais por tenant.
- Criar webhook para Caixa Postal/Eventos, importação manual de procurações ou override de poderes.
- Enviar alertas por canais outbound nesta change; serão produzidos estados/notificações internas consumíveis pela UI.

## Decisions

### 1. Um resolvedor de disponibilidade com resultado explicável

`FiscalModuleAvailabilityService` recebe módulo, escritório e, quando aplicável, operação/cliente. Ele retorna uma decisão com `allowed`, `state` e `reason_code`, aplicando nesta ordem: kill switch, restrição global, restrição do escritório, política do perfil, readiness e elegibilidade. O serviço lança uma exceção tipada apenas na fronteira de execução; listagens usam o mesmo resultado para apresentar estado.

Alternativa considerada: manter helpers booleanos por subsistema. Rejeitada porque não garante precedência única nem permite à UI explicar o bloqueio.

### 2. Restrição persistida como exceção, não feature flag

`fiscal_module_controls` armazena uma linha única por módulo/escopo. Ausência de linha ou `restricted=false` significa disponível. O controller administrativo valida o catálogo canônico de módulos, registra ator/motivo/instante e gera evento de auditoria. A liberação exige autenticação recente e agenda recuperação para os escritórios alcançados pela mudança.

No SQLite e demais bancos, unicidade global com `office_id NULL` será garantida por índices parciais/compostos compatíveis e por transação no serviço, sem depender apenas da semântica de `NULL` de um índice comum.

Alternativa considerada: armazenar uma matriz completa de módulos ativos. Rejeitada porque recria estado positivo que precisa ser inicializado e sincronizado para cada tenant.

### 3. Perfil classifica operação, driver escolhe transporte

`FISCAL_PROFILE` define `dev`, `trial` ou `production`; `FISCAL_KILL_SWITCH` precede tudo. A classe da operação é derivada de metadados do catálogo (`READ`, `DOCUMENT_GENERATION`, `FISCAL_MUTATION`). `dev` usa fixture e não acessa rede; `trial` só aceita cenários oficiais implementados; `production` aceita apenas `READ`. Mutações ficam bloqueadas nos três perfis.

As capabilities e flags antigas continuam sendo lidas apenas como compatibilidade por uma versão, com aviso de depreciação, mas não podem habilitar algo negado pelo resolvedor.

Alternativa considerada: mapear cada módulo para `fake|real|disabled` no ambiente. Rejeitada porque mistura transporte, produto e contenção em dezenas de flags.

### 4. Guard duplo para execução assíncrona

O dispatcher verifica disponibilidade antes de enfileirar e o `handle()` verifica novamente. Um job bloqueado termina sem chamada externa, registra motivo auditável e incrementa o contador administrativo. Scheduler e execução manual passam pela mesma facade.

Alternativa considerada: apenas retirar jobs pendentes da fila quando restringir. Rejeitada por ser sujeita a corrida e dependente do backend de fila.

### 5. Procuração é evidência oficial por par cliente–escritório

O onboarding deriva o outorgado do A1 do escritório e enfileira `PROCURACOES/OBTERPROCURACAO41` para cada cliente ativo como outorgante. Os quatro campos do catálogo são obrigatórios. A resposta atualiza `TaxProxyPower` e `ClientProcuracaoSnapshot`, incluindo `dtexpiracao` convertido em `America/Sao_Paulo`; sistemas concedidos são resolvidos pela matriz local completa e evidências desconhecidas são preservadas.

Snapshot com até sete dias é reutilizado. Ausente ou antigo é atualizado automaticamente antes de consulta; inexistência/expiração bloqueia apenas operações que exigem o poder. Verificação local diária atualiza estados e gera alertas internos em 30, 7 e 1 dia sem nova chamada desnecessária.

Alternativa considerada: uma consulta genérica apenas pelo contador. Rejeitada porque o endpoint oficial exige outorgante e outorgado.

### 6. UI separa governança de configuração do escritório

A página de plataforma segue o arquétipo de lista administrativa do dashboard fixado: resumo global, tabela de módulos e matriz filtrável por escritório. A UI nunca recebe segredos. O escritório mantém páginas acessíveis e dados históricos visíveis; configura A1/consentimento e agenda, mas não ativa módulos.

## Mapa de dependências

```text
N0 schema + perfil + catálogo
 ├─> N1 resolvedor + API administrativa
 └─> N1 readiness/procurações
       └─> N2 jobs + scheduler + consultas + onboarding
             └─> N3 UI plataforma/escritório
                   └─> N4 gates integrados
```

- Ownership `fiscal-module-governance`: perfil, política, controles, resolvedor, API e UI da plataforma.
- Ownership `fiscal-office-readiness`: fluxo A1/Termo/token/procurações/coleta e estados do escritório.
- Não há upstream ativo. Changes concluídas são bases estáveis e não serão reabertas.
- Os blocos N1 podem avançar em paralelo depois do schema, mas N2 só é liberado quando os contratos dos dois blocos estiverem estáveis.
- Rollout: migration/configuração com kill switch ligado, validação administrativa, ativação do perfil e desligamento explícito do kill switch. Rollback religa o kill switch antes de reverter aplicação; a tabela de controles pode permanecer sem impacto em versões anteriores.

## Risks / Trade-offs

- [Mapeamento incompleto de operações para módulos/classes] → validar todas as chaves usadas no runtime e falhar fechado para classe ou módulo desconhecido.
- [Corrida entre liberação/restrição e job] → revalidar no `handle()` e registrar a decisão usada.
- [Tempestade de recuperação após liberação global] → despachar em lotes e usar unicidade/idempotência da fila fiscal.
- [SQLite e unicidade com `NULL`] → índice global específico e transação de upsert no serviço.
- [Credencial válida, mas procurações numerosas] → onboarding assíncrono com progresso e primeira coleta somente dos clientes elegíveis.
- [Compatibilidade durante migração] → avisos das flags antigas por uma versão; kill switch oferece contenção imediata.
- [Produção não gera guias novas] → manter documentos existentes legíveis e mostrar bloqueio explícito de política, sem tentar fallback mutável.

## Migration Plan

1. Publicar migration, enums/config e bloqueio central de mutações com `FISCAL_KILL_SWITCH=true` no rollout.
2. Publicar resolvedor e APIs, mantendo leitura transitória das flags antigas apenas para diagnóstico/depreciação.
3. Migrar jobs, scheduler e consultas manuais para o resolvedor; validar que nenhum caminho remoto contorna a facade.
4. Publicar onboarding/procurações e executar backfill assíncrono por escritório sem substituir evidência manual (que deixa de ser fonte válida).
5. Publicar UI e validar perfil, credenciais e controles; desligar kill switch pela operação autorizada.
6. Na versão seguinte, remover flags antigas dos `.env.example` e o adaptador de compatibilidade.

Rollback: ligar kill switch, interromper novos dispatches, manter tabelas/dados e voltar o binário. Nenhum rollback apaga snapshots, procurações ou documentos já armazenados.

## Open Questions

- Nenhuma decisão de produto bloqueia a implementação. A entrega assume que autenticação recente já possui ou receberá um marcador server-side reutilizável; não será aceita uma confirmação somente do cliente.
