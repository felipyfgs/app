## ADDED Requirements

### Requirement: Tela de equipe permite localizar e administrar memberships do Office
A tela autenticada `/conta/equipe` SHALL apresentar as memberships retornadas para o `CurrentOffice` em uma grade responsiva de cards. Cada card MUST identificar nome, e-mail, papel e situação da membership e MUST oferecer somente as ações que o Office ADMIN real estiver autorizado a executar. A tela MUST permitir combinar pesquisa textual por nome ou e-mail com filtro pelos papéis `ADMIN`, `OPERATOR` e `VIEWER`, sem enviar ou aceitar `office_id` do cliente para definir o escopo.

#### Scenario: ADMIN visualiza a equipe do Office corrente
- **WHEN** um usuário com OfficeMembership `ADMIN` ativa acessa `/conta/equipe`
- **THEN** o sistema SHALL exibir em cards somente as memberships retornadas para o `CurrentOffice`, com nome, e-mail, papel, situação e ações permitidas

#### Scenario: Pesquisa e papel são combinados
- **WHEN** o ADMIN informa parte do nome ou e-mail e seleciona um papel
- **THEN** a tela SHALL exibir somente membros que atendam simultaneamente ao texto e ao papel selecionado

#### Scenario: Filtros não encontram membros
- **WHEN** a combinação de pesquisa e papel não corresponde a nenhuma membership carregada
- **THEN** a tela SHALL exibir um estado de nenhum resultado sem confundi-lo com equipe vazia ou falha da API

#### Scenario: Estados operacionais permanecem distinguíveis
- **WHEN** a listagem está carregando, vazia, falha ou retorna acesso negado
- **THEN** a tela SHALL apresentar um estado específico e acionável para a condição, preservando o indicador de vagas quando disponível

#### Scenario: Usuário sem ADMIN real não administra equipe
- **WHEN** um `OPERATOR`, `VIEWER` ou `PLATFORM_ADMIN` sem OfficeMembership `ADMIN` real tenta acessar ou operar a equipe
- **THEN** o sistema MUST ocultar ações administrativas e a API MUST permanecer responsável por negar listagem ou mutação não autorizada com `403`

#### Scenario: Layout se adapta à largura disponível
- **WHEN** a tela é exibida em viewport estreita ou larga
- **THEN** busca, filtro, ação primária e cards SHALL se reorganizar sem perda de conteúdo ou rolagem horizontal da página
