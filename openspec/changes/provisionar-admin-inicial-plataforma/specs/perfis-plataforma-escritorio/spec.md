## MODIFIED Requirements

### Requirement: Identidade global compacta
Quando o contexto global estiver ativo, o gatilho do seletor SHALL seguir a composição de uma linha do `TeamsMenu` e exibir somente o nome do Office corrente, com avatar e chevron. O overlay SHALL ser responsivo e mais largo que o gatilho quando houver espaço, SHALL oferecer busca por nome ou slug e SHALL manter nomes e descrições legíveis. O rodapé SHALL indicar visualmente o contexto com escudo, `Plataforma` e, quando diferente, o nome do Office corrente, sem exibir ostensivamente o enum `PLATFORM_ADMIN` nem a frase `Atuando em:`. O papel técnico e o Office ativo SHALL permanecer disponíveis semanticamente para tecnologia assistiva. O painel MUST NOT renderizar borda privilegiada no gatilho, banner privilegiado persistente nem explicações de arquitetura sobre o contexto.

#### Scenario: Plataforma consulta seu Office próprio
- **WHEN** o `PLATFORM_ADMIN` abre o seletor no Office `Plataforma`
- **THEN** o gatilho mostra `Plataforma` em uma única linha e o rodapé mostra apenas escudo e `Plataforma`, preservando o papel técnico no texto acessível

#### Scenario: Plataforma troca para o contador genérico
- **WHEN** o administrador seleciona `Contador Genérico`
- **THEN** o papel continua `PLATFORM_ADMIN`, o rodapé mostra discretamente `Plataforma · Contador Genérico` e nenhuma terceira fixture sentinela aparece no seletor

#### Scenario: Plataforma pesquisa um escritório
- **WHEN** o administrador abre o seletor e informa parte do nome ou slug na busca
- **THEN** o overlay amplo filtra as opções correspondentes sem ocultar o rodapé do perfil e informa um estado vazio quando não houver resultado
