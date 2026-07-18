## ADDED Requirements

### Requirement: o agendador deve permanecer inativo por padrão

O sistema MUST registrar a rotina de despacho, mas NÃO DEVE enfileirar nem realizar consulta externa quando o agendador de procurações estiver desabilitado.

#### Scenario: configuração padrão

- **DADO** que nenhuma variável de ativação foi definida
- **QUANDO** a rotina agendada executar
- **ENTÃO** ela termina com sucesso sem despachar jobs e sem chamar adaptadores SERPRO.

### Requirement: o despacho automático deve exigir autorização explícita em camadas

O sistema MUST despachar sincronização automática somente para escritório presente na allowlist, com authorization capability não desabilitada e autorização SERPRO apta no mesmo ambiente.

#### Scenario: escritório fora da allowlist

- **DADO** que o agendador está habilitado, mas o escritório não integra a allowlist
- **QUANDO** a rotina executar
- **ENTÃO** nenhum job desse escritório é criado.

#### Scenario: autorização inapta

- **DADO** que o escritório está na allowlist, mas não possui autorização apta para chamadas externas
- **QUANDO** a rotina executar
- **ENTÃO** nenhum job é criado e nenhuma autorização é criada automaticamente.

### Requirement: o job automático deve revalidar a permissão

O job despachado automaticamente MUST revalidar flags, allowlist, capability e autorização antes da sincronização oficial.

#### Scenario: permissão removida após despacho

- **DADO** que um job automático foi enfileirado
- **E** que a capability, allowlist ou flag foi removida antes de seu consumo
- **QUANDO** o job executar
- **ENTÃO** ele não consulta a SERPRO e registra resultado sanitizado de bloqueio.

### Requirement: a seleção deve respeitar periodicidade e limite

O comando MUST selecionar apenas projeções sem verificação ou cuja idade atingiu o intervalo configurado, respeitando o limite máximo do lote.

#### Scenario: projeção recente

- **DADO** que a procuração foi verificada dentro do intervalo configurado
- **QUANDO** a rotina executar
- **ENTÃO** o cliente não é reenfileirado.
