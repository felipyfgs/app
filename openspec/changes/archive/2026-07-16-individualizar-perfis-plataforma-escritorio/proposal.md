## Why

O painel já permite que administradores da plataforma selecionem escritórios, mas ainda mistura identidade global com membership do tenant, concede poderes excessivos no módulo Work, mantém gates de TOTP espalhados e usa avisos redundantes em `/settings`. Precisamos tornar os perfis Plataforma e Escritório previsíveis antes de ampliar o cadastro de escritórios e usuários.

## What Changes

- Manter uma única aplicação e sidebar: usuários de Escritório veem os módulos normais; `PLATFORM_ADMIN` vê os mesmos módulos mais o grupo global `Admin`.
- Resolver no servidor um Office padrão para cada `PLATFORM_ADMIN`, sem criar `OfficeMembership`, confiar em `office_id` do cliente ou inserir o administrador global na equipe do escritório.
- Exibir somente o selo compacto `Plataforma · <Office>` no seletor e remover o banner de contexto privilegiado.
- **BREAKING**: tornar o primeiro bootstrap uma conta dual, com `PLATFORM_ADMIN` e membership `OfficeRole::ADMIN` no primeiro Office; administradores globais criados depois não recebem membership.
- **BREAKING**: limitar o acesso global ao módulo Work a suporte somente leitura. Criar, executar, reivindicar, atribuir, comentar, anexar evidências e exportar exigem membership real no Office.
- **BREAKING**: remover TOTP/2FA como gate de login e autorização para Plataforma e Escritório. Ações sensíveis exigem reconfirmação da senha do próprio usuário, válida por quinze minutos, além dos demais gates aplicáveis.
- Mover Departamentos de `/admin/departments` para `/settings/departments`; reservar todas as rotas `/admin/*` e `/api/v1/platform/*` a `PLATFORM_ADMIN`.
- Corrigir o contrato de `GET /api/v1/platform/offices` para uma resposta única e tipada, compartilhada pelo seletor e pelo Admin.
- Tornar `/settings` conciso: remover o aviso “Configuração unificada em implantação”, reduzir estados normais a badge/toast/empty state e reservar `UAlert` a erro real, bloqueio acionável ou risco imediato.
- Impedir repetição de explicações sobre certificado, cofre ou ausência de download; concentrar consequências de trocar CNPJ ou remover A1 somente no modal de confirmação, em até duas frases.

### Non-goals

- Criar Offices, planos, ativações ou novos usuários; isso pertence à change `cadastrar-ativar-offices-usuarios`.
- Enviar e-mail, WhatsApp ou SMS, ou ativar usuários em horário agendado.
- Alterar o modelo comercial dos planos, franquias ou cobrança SERPRO.
- Habilitar feature flags, canais fiscais ou chamadas SERPRO reais.
- Expor auditoria interna, detalhes de `CurrentOffice`, vault, OAuth, tokens ou implementação ao usuário final.

## Capabilities

### New Capabilities

- `perfis-plataforma-escritorio`: identidade dual do bootstrap, Office padrão global sem membership, navegação por perfil, acesso Work somente leitura e reconfirmação de senha.
- `ui-configuracao-concisa`: regras verificáveis para textos, alertas, empty states e confirmações de `/settings`, incluindo Departamentos.

### Modified Capabilities

- `serpro-onboarding-procuracoes`: permitir contexto global server-side sem membership e substituir 2FA por reconfirmação recente de senha nas ações sensíveis.
- `serpro-monitoramento-familias`: substituir TOTP recente por reconfirmação de senha sem enfraquecer os demais gates de mutações.
- `serpro-credenciais-produtivas`: substituir TOTP por reconfirmação de senha para consulta e operação de credenciais/contratos globais, preservando quatro olhos quando exigido.
- `serpro-go-live-controlado`: substituir TOTP/2FA por reconfirmação de senha nas aprovações separadas do canário faturável.
- `serpro-operacao-observavel`: registrar o mecanismo de confirmação aplicável, sem manter 2FA como requisito de auditoria.

## Impact

- **Backend**: bootstrap, `PlatformMembership`, resolução de `CurrentOffice`, sessão, Fortify/Sanctum, middleware e policies de Work, SERPRO e ações globais; contrato de listagem de Offices.
- **Frontend**: autenticação, sidebar, seletor, remoção do banner, rotas Admin/Settings, Departamentos e limpeza das superfícies de configuração.
- **Dados**: referência de Office padrão para administradores globais e backfill determinístico, sem criar memberships; nenhuma membership legada será removida.
- **Segurança**: senha recente por quinze minutos, invalidação da janela em logout/troca de senha e manutenção de assinatura, flags, allowlist, orçamento, idempotência, rate limit e kill switch fail-closed.
- **Testes**: bootstrap dual, Office padrão, ausência de membership global, fronteira Work, login sem TOTP, senha recente, menus, selo compacto, ausência do banner e regressão textual de `/settings`.
- **Sequência**: esta change deve ser aplicada antes de `cadastrar-ativar-offices-usuarios` e reconciliada com a change concluída `separar-configuracao-escritorio-plataforma-serpro` antes do archive.
