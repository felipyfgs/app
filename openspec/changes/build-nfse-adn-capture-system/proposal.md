## Why

O escritório contábil precisa obter e organizar, de forma recorrente e auditável, os XMLs de NFS-e de mais de mil estabelecimentos sem depender da navegação manual no Emissor Nacional. A API oficial do Ambiente de Dados Nacional (ADN), autenticada com certificado A1 de cada raiz de CNPJ, permite automatizar essa rotina com maior segurança e previsibilidade operacional.

## What Changes

- Criar uma aplicação interna multiusuário, inicialmente para um escritório, com isolamento de dados por escritório e perfis de administrador, operador e consulta.
- Permitir o cadastro manual de clientes, matrizes e filiais, com CNPJ numérico ou alfanumérico.
- Armazenar certificados A1 e XMLs fiscais em um cofre local criptografado, sem permitir a recuperação do certificado pela aplicação.
- Integrar diretamente com a API de distribuição para contribuintes do ADN por mTLS, sem automação do portal web.
- Sincronizar documentos por NSU a cada hora, com filas, idempotência, limitação de concorrência, retentativas e bloqueio explícito de estabelecimentos com falhas permanentes.
- Indexar NFS-e e eventos para consulta por cliente, estabelecimento, papel fiscal, competência, emissão e situação.
- Disponibilizar painel operacional, download individual do XML e exportações ZIP assíncronas com expiração.
- Registrar ações sensíveis em trilha de auditoria e expor alertas de certificado, sincronização, filas e armazenamento.

## Capabilities

### New Capabilities

- `office-access-control`: autenticação interna, 2FA, perfis e isolamento dos dados por escritório.
- `client-credential-management`: cadastro de clientes e estabelecimentos, validação de CNPJ e ciclo de vida seguro de certificados A1.
- `adn-document-sync`: integração mTLS com o ADN, cursores NSU, agendamento, processamento idempotente e tratamento de falhas.
- `fiscal-document-catalog`: armazenamento imutável, interpretação de NFS-e/eventos e consulta paginada dos documentos fiscais.
- `xml-delivery`: download auditado de XML e criação, expiração e entrega segura de exportações ZIP.
- `operations-dashboard`: indicadores de operação, alertas, histórico de sincronizações e trilha de auditoria.

### Modified Capabilities

Nenhuma. O repositório ainda não possui especificações funcionais publicadas.

## Impact

- Novo monorepo com API Laravel 13, painel Nuxt 4/Nuxt UI 4 e proxy Nginx no mesmo domínio.
- PostgreSQL para dados transacionais e projeções, Redis/Horizon para filas e Scheduler para a cadência horária.
- Armazenamento privado local criptografado, preparado para migração futura por meio de uma interface de objetos seguros.
- Dependência externa da API oficial do ADN, dos esquemas NFS-e versionados e de certificados ICP-Brasil A1 fornecidos por cada cliente.
- Novos endpoints REST versionados para clientes, estabelecimentos, sincronizações, notas, exportações e resumo operacional.
- Operação local via Docker Compose, com rotinas de backup/restauração antes do uso com dados reais.
