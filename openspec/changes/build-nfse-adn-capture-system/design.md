## Context

O repositório contém apenas a configuração OpenSpec; a aplicação será criada do zero. O produto será usado por funcionários de um escritório contábil para capturar documentos compartilhados no Ambiente de Dados Nacional (ADN), sem acesso de clientes finais. A API externa usa mTLS e distribui documentos por NSU, não por data, enquanto certificados A1 e XMLs contêm material altamente sensível. O sistema deve operar inicialmente em uma única instalação local e sustentar mais de mil estabelecimentos com sincronização horária.

As principais restrições são: um certificado e-CNPJ A1 por raiz de cliente; cobertura somente do ADN; armazenamento local; CNPJ numérico ou alfanumérico; documentos fiscais originais imutáveis; e ausência de certificado de homologação no CI.

## Goals / Non-Goals

**Goals:**

- Entregar uma aplicação reproduzível em Docker Compose com API, painel, banco, filas e armazenamento privado.
- Isolar todos os dados por escritório desde a primeira migração, ainda que o MVP crie apenas um escritório.
- Proteger A1, senha e XML em repouso e durante o uso, sem materializar PEM ou chave privada no disco.
- Consumir a API oficial do ADN de modo idempotente, justo entre clientes e observável.
- Oferecer consulta rápida e exportação auditada sem alterar o XML recebido.
- Aceitar CNPJ alfanumérico e evoluções de leiaute sem bloquear definitivamente a distribuição.

**Non-Goals:**

- Automatizar o portal web, consultar APIs municipais ou prometer documentos não compartilhados no ADN.
- Emitir, cancelar ou manifestar NFS-e.
- Gerar DANFSe/PDF, planilhas, cobranças ou portal para o cliente do escritório.
- Implantar armazenamento em nuvem, KMS externo ou operação multi-escritório comercial no MVP.

## Decisions

### Monorepo com aplicações separadas no mesmo domínio

O código será organizado em `backend/` (Laravel 13/PHP 8.4) e `frontend/` (Nuxt 4/Nuxt UI 4). O Nuxt será uma SPA estática servida pelo Nginx, que encaminhará as rotas da API e do Sanctum ao PHP-FPM. Isso preserva limites claros entre interface e domínio, mas mantém cookies same-origin e elimina CORS e um processo Node em produção. Um Laravel com templates Vue embutidos foi rejeitado por acoplar o ciclo de build do painel à API; SSR foi rejeitado por não agregar valor a um painel interno autenticado.

### Layout baseado no template oficial Nuxt UI

O frontend usará como referência o template MIT `nuxt-ui-templates/dashboard`, fixado para estudo local no commit `0f30c09`. Serão reaproveitados os padrões de composição `UDashboardGroup`, sidebar recolhível e responsiva, `UDashboardPanel`, navbar, command palette, cards de indicadores, tabelas com filtros, slideover de alertas e modo claro/escuro. A navegação será adaptada para Dashboard, Clientes, Notas fiscais, Exportações, Sincronizações e Administração, sempre condicionada ao perfil. O código mock em `server/api` e os dados demonstrativos do template não serão incorporados; toda leitura usará composables tipados sobre a API Laravel/Sanctum. A cópia em `.reference/nuxt-dashboard-template` é material de estudo ignorado pelo Git, não uma dependência de runtime.

### Autenticação stateful e tenancy explícita

Laravel Fortify e Sanctum fornecerão sessão por cookie, CSRF e TOTP. Toda tabela de negócio terá `office_id`; o escritório ativo virá da associação autenticada, nunca de um identificador livre enviado pelo cliente. Policies e escopos de consulta aplicarão os perfis `ADMIN`, `OPERATOR` e `VIEWER`. Essa estrutura custa algumas colunas e testes extras, mas evita uma migração invasiva quando houver mais de um escritório.

### PostgreSQL como fonte de verdade e Redis para trabalho transitório

PostgreSQL armazenará cadastros, cursores, metadados, projeções e auditoria. Redis/Horizon executará sincronizações e exportações; não será fonte de verdade. O Scheduler selecionará registros vencidos em `sync_cursors` a cada minuto e aplicará um deslocamento determinístico ao longo da hora. Filas em banco foram rejeitadas porque não oferecem a mesma visibilidade, limitação e operação concorrente do Horizon.

### Cofre por criptografia de envelope

Uma interface `SecureObjectStore` armazenará objetos privados no filesystem local. Cada objeto receberá uma chave de dados aleatória e será criptografado com XChaCha20-Poly1305; a chave de dados será embrulhada pela `VAULT_MASTER_KEY`, versionada e mantida fora do banco e dos backups comuns. PFX e senha formarão um único payload criptografado. O transporte ADN usará o PFX em memória com as opções BLOB do libcurl, hostname verificado e TLS 1.2 ou superior. O sistema não terá rota para recuperar certificados. Criptografia apenas pelo filesystem e PEM temporário foram rejeitados por deixarem a chave privada exposta ao host.

### Adaptador ADN próprio e contrato tipado

O domínio dependerá de `AdnContributorClient`, com operações `distribution(cnpjConsulta, lastNsu, lote)` e `events(accessKey)`. A implementação HTTP será pequena, auditável e baseada no contrato oficial. `nfephp-org/sped-common` será usado somente para interpretar o PFX e seus metadados; clientes ADN comunitários não serão dependências de runtime. Respostas externas serão convertidas em DTOs e enums internos antes de alcançar jobs ou modelos.

### Persistência imutável com projeções reconstruíveis

O XML descompactado será preservado byte a byte, identificado por SHA-256 e criptografado. `dfe_documents` representa o documento original; `document_interests` representa o NSU e o papel de cada estabelecimento; `nfse_notes` e `nfse_events` são projeções consultáveis. Isso permite deduplicar uma nota que interessa a dois clientes do mesmo escritório sem perder seus cursores distintos. Falha de XSD ou versão desconhecida gera alerta de parsing, mas não descarta um XML bem-formado; falha de Base64/GZip impede o avanço do cursor.

### Cursor atômico e processamento justo

Cada estabelecimento terá um cursor por ambiente, iniciado em zero. Uma transação persistirá toda a página e só então avançará o NSU. Restrições únicas tornam retries idempotentes. Um job processará no máximo 20 páginas antes de se reenfileirar; haverá um lock por estabelecimento, quatro requisições simultâneas e rate limit global inicial de quatro chamadas por segundo. Ao alcançar o fim, `next_sync_at` será uma hora depois. 429/5xx receberão backoff com jitter; erros permanentes e cinco falhas consecutivas de decodificação bloquearão o cursor, sem salto silencioso de documento.

### Exportação temporária por job

Downloads individuais serão entregues por controller autenticado. ZIPs serão montados por job em área privada, com paths determinísticos por CNPJ, competência, papel e chave de acesso. O arquivo expirará em 24 horas e será baixado por rota autorizada; o filesystem nunca ficará publicamente exposto.

## Risks / Trade-offs

- **Documento municipal ausente no ADN** → Exibir claramente a cobertura e não usar fallback por scraping.
- **Mudança do contrato ou XSD oficial** → Versionar fixtures e parsers, preservar o XML desconhecido e monitorar falhas de interpretação.
- **Perda da chave mestra** → Documentar backup separado e executar teste de restauração antes de dados reais; sem a chave, os objetos são irrecuperáveis.
- **Comprometimento do host local** → Criptografia reduz exposição em disco, mas não protege um processo com acesso simultâneo à chave; exigir host dedicado, atualizações e acesso restrito.
- **Backfill volumoso monopolizar filas** → Limitar páginas por job, espalhar agendamentos e separar filas de sincronização e exportação.
- **Certificado vencido ou de raiz divergente** → Validar antes da ativação, alertar em 30/7/1 dias e bloquear somente os estabelecimentos afetados.
- **Biblioteca PFX incompatível com CNPJ alfanumérico** → Encapsular a leitura do certificado e cobrir formatos numérico e alfanumérico com testes próprios.
- **Evolução independente do template visual** → Copiar apenas padrões/componentes necessários, manter a atribuição MIT quando houver porção substancial e não depender do repositório de referência no build.

## Migration Plan

1. Criar infraestrutura, schema, escritório inicial e primeiro administrador com 2FA.
2. Implantar cofre e validar backup/restauração sem dados fiscais reais.
3. Validar mTLS e os três papéis fiscais na produção restrita com certificado dedicado.
4. Pilotar cinco raízes, observar backfill e ajustar limites sem mudar garantias de idempotência.
5. Expandir para cinquenta raízes e depois para todos os clientes cadastrados manualmente.
6. Em rollback, interromper Scheduler e workers, preservar banco/objetos criptografados e retornar à versão anterior das imagens; migrações destrutivas não serão usadas no MVP.

## Open Questions

Não há decisão funcional bloqueante. Antes da liberação, o smoke test oficial deve confirmar que o ambiente restrito distribui documentos dos papéis emitente, tomador e intermediário conforme o manual vigente; uma divergência bloqueará a liberação, sem habilitar automação do portal como fallback.
