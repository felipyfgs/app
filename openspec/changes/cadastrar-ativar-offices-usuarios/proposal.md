## Why

Hoje não existe um fluxo completo para a plataforma cadastrar um Office, escolher seu plano e entregar o primeiro acesso, nem para o escritório gerir sua equipe dentro do limite contratado. O cadastro precisa nascer sem iniciar franquia ou período comercial antes de uma ativação comprovada e sem depender de envio de e-mail nesta etapa.

## What Changes

- Permitir que `PLATFORM_ADMIN`, após reconfirmar a senha, crie um Office com perfil institucional, plano obrigatório e primeiro usuário `OfficeRole::ADMIN` em uma única operação.
- Criar Office e assinatura em `PENDING_ACTIVATION`, com períodos e franquias nulos/inativos até a conclusão transacional do primeiro acesso.
- Oferecer dois meios explícitos de entrega, também reutilizáveis para membros adicionais:
  - link manual fixo ao e-mail, de uso único e válido por sete dias;
  - senha provisória gerada pelo sistema, exibida uma única vez, válida por sete dias e substituída obrigatoriamente antes do acesso ao painel.
- Ativar usuário, membership, Office e assinatura atomicamente; iniciar o período comercial e as franquias no instante da ativação, nunca na criação.
- Permitir regenerar a ativação, revogando imediatamente o segredo anterior sem alterar o e-mail vinculado.
- Permitir que `PLATFORM_ADMIN` cadastre novos administradores globais com Office padrão, sem membership de Office e sem exibição em equipes.
- Permitir que `OfficeRole::ADMIN` cadastre, liste, altere papel e desative membros `ADMIN`, `OPERATOR` e `VIEWER` no próprio Office, respeitando o limite de usuários do plano e preservando ao menos um ADMIN ativo.
- Restringir os novos fluxos a um único Office por usuário. Memberships legadas em múltiplos Offices continuam existentes e operacionais, mas não podem ser ampliadas por essas APIs.
- Armazenar tokens apenas como hash; nunca registrar links, tokens ou senhas em logs, filas ou auditoria. Respostas que exibem o segredo uma única vez usam `Cache-Control: no-store`.
- Criar wizard de cadastro e telas de ativação/equipe com rótulos diretos e sem cards explicativos ou alertas informativos persistentes.

### Non-goals

- Enviar e-mail ou qualquer mensagem externa.
- Ativar Office ou usuário por agendamento.
- Criar checkout, editar catálogo de planos, cobrar ou integrar provedor de pagamento.
- Remover ou fundir memberships legadas em múltiplos Offices.
- Permitir que um novo usuário pertença a vários Offices ou que um administrador global seja incluído na equipe.
- Habilitar operações fiscais, flags ou canais SERPRO/SEFAZ.

## Capabilities

### New Capabilities

- `cadastro-ativacao-offices-usuarios`: criação de Office e administradores globais, ativação por link ou senha provisória, início comercial transacional e gestão tenant-safe da equipe.

### Modified Capabilities

Nenhuma main spec existente muda diretamente; o início do período comercial durante a ativação complementa a capability ainda ativa `franquia-agendamento-monitor-serpro`.

## Impact

- **Backend**: estados de Office/assinatura/usuário, registros de ativação, serviços transacionais, limites de plano, autenticação de primeiro acesso e APIs globais, tenant-scoped e públicas.
- **Frontend**: wizard em Admin, entrega manual de credencial, ativação pública, troca obrigatória de senha e gestão da equipe em Settings, seguindo `panel-ui` → `ui-archetype`.
- **Dados**: hashes e expiração de ativações, estados `PENDING_ACTIVATION`, datas comerciais nulas antes da ativação e constraints para novos vínculos de um único Office.
- **Segurança/tenancy**: Office sempre resolvido no servidor; `office_id` do cliente é ignorado; segredos nunca são recuperáveis e respostas sensíveis não podem ser armazenadas em cache.
- **APIs**: criação de Office, administrador global e membros, regeneração de ativação, inspeção/aceite de link e primeira troca de senha; correções de contrato compartilhadas dependem da change anterior.
- **Testes**: criação atômica, limites, isolamento, token expirado/reutilizado/regenerado, senha provisória, concorrência de ativação, início da assinatura, menus e fluxos e2e.
- **Sequência**: depende de `individualizar-perfis-plataforma-escritorio`; as duas changes devem ser validadas estritamente e aplicadas nessa ordem.
