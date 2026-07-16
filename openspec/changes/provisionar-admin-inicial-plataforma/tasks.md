## 1. Seeder global demo

- [x] 1.1 Criar `PlatformAdminDemoSeeder` com guard próprio `local|testing`, valores determinísticos, transação e falha explícita para Office demo ausente/inativo ou colisão incompatível.
- [x] 1.2 Criar User e PlatformMembership exclusivamente globais na primeira execução, sem `selected_office_id`, OfficeMembership ou AccountActivation, preservando senha e identidade compatíveis nas execuções seguintes.
- [x] 1.3 Chamar o novo seeder no `DatabaseSeeder` depois da criação do Office demo e da assinatura, sem alterar o bloqueio de produção nem os usuários tenant existentes.

## 2. Testes do contrato demo

- [x] 2.1 Cobrir primeira execução e os dados exatos de `Admin Plataforma Demo` / `plataforma@example.com`, papel ativo, Office padrão, login e grupo Admin.
- [x] 2.2 Cobrir repetição com mesmos ids/contagens, preservação de hash de senha alterado e ausência de registros parciais.
- [x] 2.3 Cobrir colisão com OfficeMembership/grant incompatível, Office demo ausente/inativo e chamada direta do sub-seeder em `production`, sempre sem escalada de privilégio.
- [x] 2.4 Provar pela listagem/contagem da equipe que a fixture global não aparece no Office demo e não consome `max_users`.

## 3. Verificação

- [x] 3.1 Executar `cd backend && vendor/bin/pint --test && php artisan test --filter=PlatformAdminDemoSeederTest` e a suíte completa `php artisan test`, corrigindo qualquer regressão antes de marcar PASS.
- [x] 3.2 Executar `openspec validate provisionar-admin-inicial-plataforma --type change --strict --json` e manter todos os cenários verificáveis em CI, sem live ops.

## 4. Encerramento

- [ ] 4.1 Após aceite do software, sincronizar/arquivar a change e commitar no mesmo dia a main spec `seed-admin-plataforma-demo` e o histórico arquivado.
