## MODIFIED Requirements

### Requirement: APIs tenant-scoped de Cadastro e vínculos
O sistema SHALL oferecer listagem global do `CurrentOffice`, detalhe por cliente e refresh explícito de Cadastro/Vínculos. O office MUST ser derivado da sessão comum ou da seleção privilegiada server-side de `PLATFORM_ADMIN`, nunca de body ou query.

#### Scenario: Office no request
- **WHEN** o cliente HTTP enviar `office_id` em query ou body
- **THEN** o valor SHALL ser ignorado e somente o `CurrentOffice` SHALL definir o escopo

#### Scenario: Plataforma seleciona office sem membership
- **WHEN** um `PLATFORM_ADMIN` selecionar um office pelo fluxo privilegiado e consultar Cadastro/Vínculos
- **THEN** a API SHALL usar esse `CurrentOffice` e auditar o ator real sem criar membership

### Requirement: APIs tenant-scoped de Processos fiscais
O sistema SHALL oferecer listagem global do `CurrentOffice`, detalhe por cliente e refresh explícito de e-Processo, com isolamento e paginação server-side. Recursos de outro office MUST permanecer inacessíveis, exceto quando um `PLATFORM_ADMIN` tiver selecionado explicitamente aquele office pelo contexto privilegiado.

#### Scenario: Processo de outro office
- **WHEN** um usuário solicitar processo fora de seu `CurrentOffice`
- **THEN** a API SHALL responder como recurso inacessível sem revelar sua existência

#### Scenario: Administrador global troca pelo seletor
- **WHEN** um `PLATFORM_ADMIN` selecionar o office proprietário e repetir a consulta
- **THEN** a API SHALL autorizar conforme suas capacidades efetivas e auditar a leitura internamente

### Requirement: Páginas do monitoramento baseadas no template
As rotas `/monitoring/registrations` e `/monitoring/tax-processes` SHALL copiar o arquétipo de lista do template fixado, com navegação, filtros, loading, vazio, erro e carregamento server-side. As listas aplicáveis SHALL mostrar estado de procuração, último snapshot, saldo do monitor e próximo agendamento sem expor detalhes técnicos SERPRO.

#### Scenario: Lista sem dados
- **WHEN** a API retornar uma carteira vazia
- **THEN** a página SHALL exibir o estado vazio canônico sem mocks ou dados fabricados

#### Scenario: Cliente sem procuração
- **WHEN** a sincronização oficial classificar um cliente como sem procuração
- **THEN** a lista SHALL exibir `Sem procuração` e orientar regularização no e-CAC sem oferecer importação ou override

### Requirement: Ações e dados públicos seguros
A UI MUST NOT exibir ações mutantes bloqueadas, contrato global, PFX, senha, Termo, tokens, XML bruto, poderes de outros offices ou diagnóstico técnico SERPRO. O tenant SHALL visualizar somente ações que pode corrigir; `PLATFORM_ADMIN` em contexto privilegiado poderá executar ações tenant de ADMIN, mas detalhes globais continuarão restritos a `/admin/*`.

#### Scenario: Operação mutante desabilitada
- **WHEN** a capacidade mutante estiver desligada
- **THEN** a interface SHALL omitir a ação e a API SHALL permanecer fail-closed

#### Scenario: Onboarding falha por credencial global
- **WHEN** uma falha técnica da plataforma impedir a autorização SERPRO
- **THEN** o tenant SHALL receber estado sanitizado e a plataforma SHALL ver o diagnóstico somente em sua área restrita

## ADDED Requirements

### Requirement: Configuração do escritório concentrada em settings
Toda configuração do `Office` SHALL ficar em uma única experiência `/settings`, construída a partir do arquétipo Settings do template fixado, com seções de perfil, consentimento, A1 e agenda de monitores. `/admin/*` MUST ser reservado à plataforma, e a UI do escritório MUST NOT solicitar autor do pedido, XML do Termo, OAuth, token, ambiente ou poderes SERPRO.

#### Scenario: Administrador abre configurações
- **WHEN** um `OfficeRole::ADMIN` acessa `/settings`
- **THEN** ele SHALL gerenciar dados institucionais, consentimento, certificado e agendas sem visualizar campos técnicos SERPRO

#### Scenario: Usuário de office tenta abrir admin
- **WHEN** um usuário sem `PLATFORM_ADMIN` navegar para `/admin/*`
- **THEN** o sistema SHALL negar a rota sem revelar configuração global

