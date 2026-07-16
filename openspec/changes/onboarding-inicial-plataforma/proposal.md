## Why

Uma instalação produtiva nova não possui usuário capaz de acessar `/admin/*`, e o único bootstrap real disponível exige terminal e cria uma conta dual vinculada a um Office. A plataforma precisa de um onboarding web único, seguro e exclusivamente global para que o primeiro `PLATFORM_ADMIN` possa entrar e cadastrar o primeiro Office pelo fluxo administrativo normal.

## What Changes

- Adicionar `/onboarding` e APIs públicas de status/conclusão disponíveis somente antes do primeiro bootstrap produtivo.
- Persistir o nome da organização e o encerramento irrevogável do onboarding em uma configuração global singleton.
- Criar atomicamente somente o primeiro usuário ativo/verificado e sua `PlatformMembership` `PLATFORM_ADMIN`, sem Office, assinatura, OfficeMembership ou AccountActivation.
- Proteger a conclusão com flag default OFF, token de deploy transportado apenas pelo fragmento da URL e body, HTTPS produtivo, CSRF, rate limit e respostas `no-store`.
- Autenticar o primeiro administrador após o commit e direcioná-lo ao wizard existente `/admin/offices/new`.
- Adaptar login, shell e hub global ao estado legítimo de `PLATFORM_ADMIN` sem Office, ocultando superfícies tenant até existir contexto.
- Ao cadastrar o primeiro Office, preencher atomicamente o `default_office_id` ausente do administrador inicial, sem criar `OfficeMembership` nem alterar `users.selected_office_id`; o contexto passa a resolver automaticamente quando o Office for ativado.
- Manter o seeder demo restrito a `local/testing` e o comando `app:bootstrap-office` fora do fluxo web.
- Non-goals: criar ou ativar Office, assinatura ou usuários tenant; enviar credenciais; habilitar flags/canais SEFAZ/SERPRO; realizar chamadas externas, live smoke, revisão jurídica ou alterar o bootstrap CLI nesta change.

## Capabilities

### New Capabilities

- `onboarding-inicial-plataforma`: Onboarding produtivo único do primeiro administrador global, configuração da organização e estado de plataforma sem Office.

### Modified Capabilities

- `perfis-plataforma-escritorio`: admitir `default_office_id` nulo somente no estado transitório em que ainda não existe Office e exigir convergência automática no primeiro cadastro.

## Impact

- **Backend:** migration/model de configuração global, serviço transacional, configuração de ambiente, controller e duas rotas públicas Laravel/Sanctum.
- **Frontend:** nova página Nuxt `/onboarding`, cliente/tipos de API e ajustes de redirect, navegação e empty state em `/admin`.
- **Segurança:** novo segredo operacional em `.env.prod`, sem persistência ou logging; fechamento permanente para bases já inicializadas ou após o primeiro sucesso.
- **Compatibilidade:** usa o wizard de Office entregue por `cadastrar-ativar-offices-usuarios`; não altera tenancy nem aceita `office_id` do cliente.
- **Testes:** contratos backend de disponibilidade, atomicidade e sigilo; unit/E2E frontend de formulário, redirects, estado sem Office e responsividade.
