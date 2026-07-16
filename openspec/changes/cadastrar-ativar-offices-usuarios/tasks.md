## 1. Estados, persistência e segurança

- [x] 1.1 Aplicar primeiro a change `individualizar-perfis-plataforma-escritorio` e confirmar seus gates antes de habilitar qualquer rota desta change. (Dependente 20/20 com gates 6.1–6.3 marcados; implementação e rotas desta change já presentes no working tree.)
- [x] 1.2 Adicionar estados `PENDING_ACTIVATION`, lifecycle de `password_change_required`, hash-sentinela não autenticável e `account_activations` com propósito, método, hash, validade, consumo, revogação e geração; preservar estados/memberships legados e a coluna de senha obrigatória em testes de migration up/down.
- [x] 1.3 Implementar geração/hash/comparação de token e senha provisória, resposta de exibição única, `Cache-Control: no-store` e redaction de requests/logs/auditoria/telemetria; validar com testes de varredura de segredos.

## 2. Domínio de criação e ativação

- [x] 2.1 Implementar serviço transacional/idempotente de criação com nome/slug server-side, perfil institucional exato, plano obrigatório, primeiro ADMIN e ativação pendente; cobrir rollback, ausência de fila e replay com `credential_delivery=regeneration_required` sem duplicar recurso ou segredo.
- [x] 2.2 Implementar inspeção e conclusão do link manual fixo ao e-mail, uso único e sete dias, com token no body, senha permanente, `password_change_required=false` e sessão nova somente pós-commit; testar válido, inválido, expirado, reutilizado e enumeração.
- [x] 2.3 Implementar primeiro acesso por e-mail + senha provisória + nova senha, substituindo o sentinela sem aceitar provisória/sentinela no login comum ou criar sessão antes da troca; testar expiração, flag e mesmo desfecho autenticado do link.
- [x] 2.4 Implementar ativação do primeiro ADMIN sob locks, iniciando Office, assinatura, período e franquias no mesmo instante; testar concorrência, rollback e que membro adicional não reinicia o período.
- [x] 2.5 Implementar regeneração que preserva e-mail e correção separada de primeiro ADMIN, administrador global e membro enquanto pendentes, revogando gerações e removendo a conta exclusiva nunca ativada após auditoria sanitizada; testar reutilização do endereço antigo, senha recente e negação pós-ativação.
- [x] 2.6 Implementar criação/ativação e listagem/detalhe de `PLATFORM_ADMIN` pendente com Office padrão e sem OfficeMembership, rejeitando qualquer e-mail existente; testar recuperação para regenerar, ausência na equipe e no limite do plano.

## 3. Gestão tenant-safe da equipe

- [x] 3.1 Centralizar criação/reativação de OfficeMembership sob lock, rejeitando qualquer e-mail existente na criação e impedindo novos usuários multi-Office ou dual sem índice destrutivo; testar concorrência, usuário órfão, mensagens sem vazamento e troca legada preservada.
- [x] 3.2 Implementar contagem atômica de membros ativos + pendentes contra `max_users` 5/25/200, excluindo desativados e plataforma; testar corrida pela última vaga.
- [x] 3.3 Implementar listagem, criação, alteração de `ADMIN|OPERATOR|VIEWER`, desativação, reativação e regeneração exigindo OfficeMembership ADMIN real no `CurrentOffice`; descartar `office_id`, negar Plataforma sem membership, revogar sessões, exigir nova ativação sem outro grant, preservar senha/grants legados e impedir rebaixar/desativar o último ADMIN.

## 4. APIs e painel

- [x] 4.1 Adicionar GET/list/detail e mutações globais para Offices, ativação, correção do primeiro ADMIN e administradores, com `EnsurePlatformAdmin`, senha recente, throttling e resources sanitizados que localizem pendências sem segredo; cobrir contratos feature/policy.
- [x] 4.2 Adicionar endpoints públicos de link/primeiro acesso e tenant-scoped de equipe/reativação, reescopando route models e liberando `/activate` e `/first-access` no middleware Nuxt; cobrir sem sessão, `401/403/404/409/422/429`, `no-store` e ausência de `office_id` confiável.
- [x] 4.3 Usando `panel-ui` → `ui-archetype`, criar lista/detalhe de Offices pendentes e `/admin/offices/new` com stepper direto, correção do primeiro ADMIN, resultado único e recuperação por regeneração após fechamento/timeout; incluir administradores globais sem cards ou avisos redundantes.
- [x] 4.4 Criar `/activate` e `/first-access` no layout de autenticação, remover o token do fragmento imediatamente, rotacionar a sessão somente pós-commit e usar o mesmo destino nos dois métodos; testar acesso público, 360 px, teclado/leitor de tela e erros acionáveis.
- [x] 4.5 Criar `/settings/team` pelo arquétipo Settings/Members, com vagas, busca, papel/status compacto, desativação/reativação e ações autorizadas; testar Plataforma ausente da lista e recebendo `403` sem membership real.

## 5. Verificação e encerramento

- [ ] 5.1 Executar `cd backend && vendor/bin/pint --test && php artisan test` e, com a stack iniciada, `./docker/ops/verify.sh --full`; obter PASS incluindo ativação concorrente, transação, plano, tenancy, ausência de mensagem enfileirada e varredura de segredos.
- [ ] 5.2 Executar em `frontend/` `pnpm run lint`, `pnpm run typecheck`, `pnpm run test`, `pnpm run generate` e `pnpm run test:e2e`; cobrir ambos os métodos, rotas públicas, fragmento removido, recuperação/regeneração, equipe, ausência de cards/`UAlert` informativos e nenhuma tentativa de envio de e-mail.
- [ ] 5.3 Validar `openspec validate cadastrar-ativar-offices-usuarios --strict`; após aceite real do software e da change dependente, sincronizar/arquivar e commitar os artefatos OpenSpec no mesmo dia.
