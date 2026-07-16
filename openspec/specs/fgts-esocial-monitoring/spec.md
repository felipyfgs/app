# fgts-esocial-monitoring Specification

## Purpose

Sincronizado a partir de `build-complete-fiscal-monitoring-hub` (2026-07-15).

## Requirements

### Requirement: Cobertura FGTS é explicitamente parcial
O sistema SHALL rotular o módulo como monitoramento parcial baseado em fontes oficiais do eSocial e MUST NOT afirmar integração direta com FGTS Digital, suas guias, pagamentos ou pendências do portal.

#### Scenario: Usuário abre FGTS Digital
- **WHEN** a página é carregada sem API pública oficial do FGTS Digital
- **THEN** a UI apresenta fonte, última atualização, cobertura disponível e limitações por texto, não apenas tooltip

### Requirement: Eventos e totalizadores eSocial preservam origem
O sistema SHALL processar recibos e eventos oficialmente disponíveis, incluindo S-5003, S-5013 e fechamento S-1299 quando aplicáveis, vinculando versão, competência, trabalhador/estabelecimento permitido e evidência.

#### Scenario: Totalizador recebido
- **WHEN** retorno oficial contém totalizador FGTS válido
- **THEN** o sistema cria snapshot da base conhecida sem inferir emissão ou pagamento de guia

### Requirement: Fechamento não equivale a recolhimento
O sistema MUST representar fechamento do eSocial, totalização, emissão de guia e pagamento como estados independentes e SHALL deixar os estados não cobertos como `UNKNOWN` ou `UNSUPPORTED`.

#### Scenario: S-1299 aceito
- **WHEN** o fechamento é confirmado, mas não existe fonte oficial de guia/pagamento integrada
- **THEN** o sistema mostra fechamento confirmado e guia/pagamento como não consultados

### Requirement: Divergências usam apenas evidência disponível
O sistema SHALL criar alerta quando eventos/totalizadores oficiais conhecidos forem inconsistentes entre si, identificando escopo e fonte, sem declarar débito do portal não consultado.

#### Scenario: Totalizador ausente após fechamento
- **WHEN** fechamento confirmado não possui totalizador esperado dentro da janela definida
- **THEN** o sistema gera `ATTENTION` para revisão e não inventa valor de FGTS devido

### Requirement: Portal humano não é fallback
O sistema MUST NOT usar scraping, Gov.br, CAPTCHA, cookie, procuração de sessão ou automação de navegador para completar dados ausentes do FGTS Digital.

#### Scenario: Informação existe somente no portal
- **WHEN** operador solicita dado sem fonte M2M oficial
- **THEN** o sistema apresenta `UNSUPPORTED` e orientação manual sem armazenar credenciais humanas
