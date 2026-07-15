## Why

A implementação atual do Integra Contador possui boa base de tenancy, cofre, filas e monitoramento, mas diverge do protocolo oficial em autenticação, headers, envelope, rotas, Termo do procurador e códigos de serviço. Como ainda não há contrato SERPRO nem clientes reais, este é o momento seguro para corrigir a fundação e tornar o SITFIS a primeira capacidade pronta para ativação, sem confundir simulação documental com validação produtiva.

## What Changes

- Corrigir o transporte mTLS/OAuth para processar `access_token` e `jwt_token`, enviar os headers oficiais e montar o envelope Contratante → Autor do Pedido → Contribuinte sem aceitar identidades livres do cliente HTTP.
- Introduzir coordenadas SERPRO explícitas e um manifesto versionado das 119 entradas oficiais, separando catálogo conhecido, suporte implementado e validação produtiva.
- Selecionar driver por capacidade (`disabled`, `simulated` ou `real`), com simulador determinístico restrito ao desenvolvimento e sem fallback silencioso.
- Validar o Termo assinado externamente conforme layout, XMLDSig e certificado, distinguindo validação local, aceitação SERPRO, simulação e rejeição.
- Implementar SITFIS 2.0 somente leitura com `SOLICITARPROTOCOLO91` em `/Apoiar`, `RELATORIOSITFIS92` em `/Emitir`, espera dinâmica, polling limitado, TTL, atualização diária e ação manual idempotente.
- Propagar proveniência `SIMULATED`, `SERPRO_REAL` ou `UNVERIFIED` por execução, evidência, snapshot, API e interface; registros legados sem prova ficam preservados e não representam estado fiscal atual.
- Alinhar o ledger às regras oficiais de faturabilidade, sem atribuir custo monetário enquanto não existir contrato e sem contar simulações como consumo.
- Ajustar as telas existentes de Configurações e SITFIS para estados acionáveis, progresso assíncrono, idade do resultado e notificações, mantendo o arquétipo oficial do dashboard.
- Atualizar o vocabulário de domínio e registrar a decisão arquitetural de drivers por capacidade e proveniência.

## Capabilities

### New Capabilities

- `serpro-protocol-conformance`: autenticação, headers, envelope, rotas, Termo, drivers e segurança do protocolo Integra Contador.
- `serpro-official-service-catalog`: manifesto oficial versionado, identidade de domínio, coordenadas de fio e estado de suporte da plataforma.
- `sitfis-official-monitoring`: fluxo oficial SITFIS 2.0, agendamento, TTL, polling, snapshots e experiência operacional.
- `fiscal-source-provenance`: proveniência verificável de execuções, evidências e snapshots, incluindo tratamento seguro do legado.
- `serpro-billability-classification`: regras oficiais de faturabilidade, correlação e separação entre contagem de consumo e preço contratual.

### Modified Capabilities

- `frontend-dashboard-experience`: as telas existentes passam a comunicar estado, bloqueio, atualização e proveniência do SITFIS sem expor detalhes ou segredos técnicos.

## Impact

- Backend Laravel: configuração SERPRO, autenticação, cliente Integra Contador, Termo XML, catálogo, migrations aditivas, SITFIS, scheduler, ledger e bindings.
- Frontend Nuxt: tipos e telas existentes de Configurações, carteira SITFIS e notificações.
- Dependência PHP dedicada à validação XMLDSig; fixtures oficiais sanitizadas e manifesto versionado passam a integrar a base.
- APIs atuais de autorização e SITFIS permanecem compatíveis e recebem campos adicionais sanitizados.
- A ativação produtiva continua bloqueada por contratação, evidência comercial/jurídica, credenciais reais, smoke somente leitura e conciliação oficial.

## Não-objetivos

- Implementar operações fiscais mutantes ou adapters reais das demais famílias do catálogo.
- Declarar compatibilidade produtiva sem contrato e smoke real.
- Oferecer dados simulados como produto para escritórios clientes ou permitir simulador em produção.
- Criar backoffice comercial, cobrança, assinatura, limites de plano ou acesso remoto de suporte; esses itens ficam como evolução futura própria.
- Custodiar o A1 do Autor do Pedido ou assinar o Termo em nome do escritório cliente.
