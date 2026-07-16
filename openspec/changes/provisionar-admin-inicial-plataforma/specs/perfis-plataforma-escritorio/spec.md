## MODIFIED Requirements

### Requirement: Identidade global compacta
Quando o contexto global estiver ativo, o seletor SHALL exibir o perfil `PLATFORM_ADMIN` e o nome do Office corrente em linhas visualmente distintas. O menu SHALL identificar o perfil global no rodapé e indicar explicitamente o Office em que ele está atuando. O painel MUST NOT renderizar banner privilegiado persistente nem explicações de arquitetura sobre o contexto.

#### Scenario: Plataforma consulta seu Office próprio
- **WHEN** o `PLATFORM_ADMIN` abre o seletor no Office `Plataforma`
- **THEN** o gatilho distingue o perfil do Office e o menu mostra `Plataforma` como contexto corrente

#### Scenario: Plataforma troca para o contador genérico
- **WHEN** o administrador seleciona `Contador Genérico`
- **THEN** o perfil continua `PLATFORM_ADMIN`, o nome do contexto muda para `Contador Genérico` e nenhuma terceira fixture sentinela aparece no seletor
