## 1. Fundação do monorepo

- [x] 1.1 Criar `backend/` com Laravel 13/PHP 8.4 e `frontend/` com Nuxt 4.4, Nuxt UI 4.9 e TypeScript
- [x] 1.2 Criar Docker Compose com Nginx, PHP-FPM, PostgreSQL 17, Redis 8, Scheduler e workers Horizon
- [x] 1.3 Configurar Nginx para servir a SPA e encaminhar rotas Laravel/Sanctum no mesmo domínio
- [x] 1.4 Definir configuração de ambiente, healthchecks, volumes privados e comandos de desenvolvimento sem versionar segredos
- [x] 1.5 Registrar o vocabulário em `CONTEXT.md` e as decisões de API ADN, arquitetura same-origin e cofre em ADRs

## 2. Identidade, perfis e isolamento

- [x] 2.1 Instalar e configurar Fortify, Sanctum e `nuxt-auth-sanctum` com sessão stateful e CSRF
- [x] 2.2 Criar migrations e modelos de escritórios, usuários e associações com perfis `ADMIN`, `OPERATOR` e `VIEWER`
- [x] 2.3 Implementar resolução do escritório ativo, policies e escopos que não aceitem `office_id` livre da requisição
- [x] 2.4 Configurar TOTP e bloquear funções administrativas até a confirmação do 2FA
- [x] 2.5 Criar bootstrap seguro do primeiro escritório e administrador
- [x] 2.6 Cobrir login, CSRF, perfis, 2FA e isolamento entre escritórios com testes de feature

## 3. Clientes, estabelecimentos e CNPJ

- [x] 3.1 Criar migrations, modelos, factories e policies de clientes e estabelecimentos
- [x] 3.2 Implementar value object e validação de CNPJ numérico/alfanumérico, normalização textual e comparação de raiz
- [x] 3.3 Implementar endpoints paginados de criação, consulta e alteração de clientes
- [x] 3.4 Implementar cadastro manual de estabelecimentos com bloqueio de raiz incompatível e duplicidade por escritório
- [x] 3.5 Testar CNPJ com máscara, minúsculas, formato alfanumérico, dígitos inválidos e conflito de raiz
- [x] 3.6 Implementar consulta pública opcional de CNPJ pelo backend para sugerir a razão social no cadastro, com cache, limite, timeout e fallback manual

## 4. Cofre de certificados e objetos

- [x] 4.1 Definir a interface `SecureObjectStore` e implementar o adaptador de filesystem privado
- [x] 4.2 Implementar envelope XChaCha20-Poly1305 com chave de dados por objeto, chave mestra versionada e autenticação de metadados
- [x] 4.3 Integrar `nfephp-org/sped-common` atrás de um leitor de PFX para validar senha, titular, CNPJ, fingerprint e validade
- [x] 4.4 Criar migrations/modelos de credenciais e endpoint multipart restrito a administradores, sem rota de recuperação do PFX
- [x] 4.5 Implementar ativação e substituição atômica com invalidação criptográfica da credencial anterior
- [x] 4.6 Implementar estados e alertas de vencimento em 30, 7 e 1 dia e bloqueio após expiração
- [x] 4.7 Testar criptografia/adulteração, senha incorreta, raiz divergente, vencimento, rotação e ausência de segredos em respostas/logs

## 5. Cliente oficial do ADN

- [x] 5.1 Criar DTOs, enums e a interface `AdnContributorClient` para distribuição e eventos
- [x] 5.2 Implementar transporte cURL mTLS com PFX em memória, TLS 1.2+, verificação de cadeia/hostname e timeouts configuráveis
- [x] 5.3 Implementar chamadas `GET /DFe/{NSU}` e `GET /NFSe/{chave}/Eventos` com `cnpjConsulta` e `lote`
- [x] 5.4 Versionar fixtures sanitizadas dos estados oficiais, lotes GZip/Base64, alertas e rejeições
- [x] 5.5 Criar testes de contrato que impeçam a desativação da verificação TLS ou a criação de PEM temporário

## 6. Distribuição, cursores e filas

- [x] 6.1 Criar migrations/modelos de cursores e execuções com estados, tentativas, timestamps e unicidade por estabelecimento/ambiente
- [x] 6.2 Criar migrations/modelos de documentos DF-e, interesses, notas e eventos com chaves idempotentes
- [x] 6.3 Implementar decodificação Base64/GZip, hash SHA-256 e persistência criptografada byte a byte
- [x] 6.4 Implementar parser versionado de NFS-e/eventos, papéis fiscais e situação derivada sem alterar o documento original
- [x] 6.5 Implementar transação de página que só avance o cursor após persistência completa
- [x] 6.6 Implementar job com lock por estabelecimento, máximo de 20 páginas e reenfileiramento justo
- [x] 6.7 Implementar rate limit e concorrência globais configuráveis, backoff com jitter e bloqueio após erros permanentes ou cinco falhas de decodificação
- [x] 6.8 Implementar Scheduler por minuto com distribuição determinística do ciclo horário e sincronização manual autorizada
- [x] 6.9 Testar idempotência, rollback parcial, duplicatas, fim da distribuição, 429/5xx, payload corrompido e ausência de starvation

## 7. Catálogo e APIs fiscais

- [x] 7.1 Implementar projeções consultáveis para chave, partes, papel, competência, emissão, valores e situação
- [x] 7.2 Preservar documentos bem-formados com XSD desconhecido em estado de revisão sem bloquear o cursor
- [x] 7.3 Implementar `GET /api/v1/notes` com cursor pagination e filtros combináveis
- [x] 7.4 Implementar detalhe e download de XML original por chave, aplicando escritório/perfil e auditoria
- [x] 7.5 Implementar endpoints de histórico de sincronização e disparo manual
- [x] 7.6 Cobrir notas emitidas, tomadas, intermediadas, eventos, competência versus emissão e acesso cruzado com testes

## 8. Exportações XML

- [x] 8.1 Criar migration/modelo de exportações com estados, filtros, expiração e ownership por escritório/usuário
- [x] 8.2 Implementar criação assíncrona de ZIP com opção de eventos e paths `CNPJ/AAAA-MM/papel/chave.xml`
- [x] 8.3 Implementar download privado autorizado e limpeza automática após 24 horas
- [x] 8.4 Remover artefatos parciais e registrar estado sanitizado quando uma exportação falhar
- [x] 8.5 Testar filtros, deduplicação, paths seguros, inclusão de eventos, expiração e isolamento entre escritórios

## 9. Painel Nuxt UI

- [x] 9.1 Adaptar do template oficial os padrões `UDashboardGroup`, sidebar responsiva, panels, command palette, notificações e tema, mantendo a licença MIT quando aplicável
- [x] 9.2 Criar fluxo de login/2FA e navegação Dashboard, Clientes, Notas, Exportações, Sincronizações e Administração condicionada ao perfil
- [x] 9.3 Implementar dashboard com cards de métricas, falhas, filas, validade de certificados e horários de atualização
- [x] 9.4 Implementar fluxo guiado cliente → estabelecimento → A1 → teste → primeira sincronização
- [x] 9.5 Implementar tabelas de clientes e notas com filtros server-side, colunas responsivas e paginação por cursor
- [x] 9.6 Implementar detalhe de nota, filtros separados de competência/emissão e download individual
- [x] 9.7 Implementar solicitação, acompanhamento e download de exportações
- [x] 9.8 Implementar telas/slideover de histórico, alertas e estados vazios/erro sem revelar informações sensíveis
- [x] 9.9 Remover mocks `server/api` do template e conectar todos os fluxos por composables tipados à API Laravel/Sanctum
- [ ] 9.10 Cobrir os fluxos principais com testes de componentes e o MCP oficial do Playwright em desktop e viewport móvel

## 10. Operação, segurança e aceite

- [x] 10.1 Implementar auditoria de autenticação, cadastros, certificados, sincronizações manuais, downloads e exportações
- [x] 10.2 Adicionar logs estruturados com correlação e redaction, métricas de Horizon/ADN/disco e healthchecks restritos
- [x] 10.3 Criar rotinas de backup e restauração de PostgreSQL e objetos criptografados, mantendo a chave mestra em procedimento separado
- [x] 10.4 Executar análise estática, testes backend/frontend e varredura automatizada de segredos nas imagens e artefatos
- [ ] 10.5 Executar teste de carga com mais de mil estabelecimentos sincronizados e comprovar ciclo completo em menos de 60 minutos
- [ ] 10.6 Executar smoke test mTLS na produção restrita com certificado dedicado e validar os três papéis fiscais
- [ ] 10.7 Realizar piloto progressivo com 5, 50 e depois todos os clientes, documentando métricas e critérios de rollback
