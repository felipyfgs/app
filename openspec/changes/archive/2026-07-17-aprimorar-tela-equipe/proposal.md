## Why

A gestão de equipe já existe em `/conta/equipe`, mas a apresentação em lista dificulta localizar e comparar colaboradores conforme a equipe cresce. A tela deve oferecer uma visão em cards, pesquisa e filtro por papel, mantendo as regras atuais de acesso e as ações administrativas do Office.

## What Changes

- Evoluir a tela existente de equipe para uma grade responsiva de cards de colaboradores, sem criar uma rota paralela.
- Disponibilizar pesquisa por nome ou e-mail e filtro por papel (`ADMIN`, `OPERATOR` e `VIEWER`), combináveis entre si.
- Exibir em cada card identidade, e-mail, papel, situação da membership e menu de ações já suportadas.
- Preservar estados de carregamento, vazio, nenhum resultado, erro e acesso negado, além do indicador de vagas do plano.
- Preservar criação, alteração de papel, regeneração de ativação, desativação e reativação com as confirmações e permissões atuais.
- Manter a tela dentro do shell e da navegação canônica de Conta, adaptada do arquétipo de settings do template fixado.
- Manter o isolamento pelo `CurrentOffice`; nenhum `office_id` fornecido pelo cliente será aceito para definir o escopo.

### Non-goals

- Adicionar telefone, avatar persistido ou novos campos ao cadastro de usuário.
- Criar departamentos, histórico de ações ou novas operações de backend.
- Alterar papéis, limites do plano, regras de senha recente ou fluxo de ativação.
- Ativar feature flags, canais SEFAZ ou executar operações SERPRO/live smoke.
- Tratar tickets externos, revisão jurídica ou LGPD nesta change.

## Capabilities

### New Capabilities

Nenhuma.

### Modified Capabilities

- `cadastro-ativacao-offices-usuarios`: acrescentar o contrato de apresentação, pesquisa e filtragem da equipe do Office na tela autenticada existente.

## Impact

- Frontend: `frontend/app/pages/settings/team.vue`, `frontend/app/components/settings/TeamList.vue` e testes unitários da superfície de equipe.
- API: reutiliza `/api/v1/office/members` e suas mutações, sem mudança de contrato prevista.
- Segurança e tenancy: acesso continua restrito a OfficeMembership `ADMIN` real no `CurrentOffice`; `PLATFORM_ADMIN` sem membership não recebe acesso implícito.
- Dependências e runtime: nenhuma dependência nova; permanece Nuxt 4 SPA estática servida por nginx.
