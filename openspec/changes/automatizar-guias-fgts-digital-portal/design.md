## Context

O eSocial BX cobre eventos e totalizadores, mas não oferece a Guia do FGTS Digital. O portal oficial é a única superfície documentada para consultar débitos, emitir/reimprimir guias, obter PDF/relatórios e verificar pagamento. Não há API M2M pública para esses fluxos. A autenticação usa Gov.br, certificado ICP-Brasil, perfil do titular ou procurador e pode exigir hCaptcha/desafio antifraude. O usuário autorizou explicitamente a resolução do hCaptcha pela API externa NopeCHA; essa integração é distinta de uma API oficial do FGTS Digital.

O hub já possui `ClientCredential`, vault, `FiscalMutationOperation`, `TaxGuide`, downloads autenticados, Horizon, scheduler fiscal e a página `/monitoring/fgts`. A change upstream `integrar-fgts-esocial-bx-oficial` adiciona a fonte read-only oficial eSocial e deve chegar ao marco `verify` antes de integrar os modelos, controllers e UI compartilhados.

O certificado A1 da G.A. Contabilidade poderá ser usado como identidade `PROCURADOR_PJ` somente para clientes com procuração válida e seleção explícita do perfil/empregador. O material encontrado em `.local/` é insumo local ignorado pelo Git; não será copiado para código, imagem, `.env.example`, logs ou fixtures.

## Goals / Non-Goals

**Goals:**

- Automatizar consulta de débitos/guias, reimpressão, download de PDF/relatórios e situação de pagamento no portal FGTS Digital.
- Emitir de forma idempotente guia rápida mensal, rescisória, consignado ou mista e guia parametrizada após preview e autorização.
- Autenticar com A1 do cliente ou do escritório procurador, selecionar perfil/empregador e guardar a sessão cifrada e vinculada à identidade.
- Isolar a navegação em processo RPA executado por Horizon, com contrato JSON versionado, hosts permitidos e artefatos auditáveis.
- Detectar alterações de contrato, indisponibilidade e desafio não suportado com códigos estáveis e sem inventar sucesso.
- Resolver hCaptcha no mesmo processo e contexto do navegador, usando a API externa somente com opt-in e orçamento verificáveis; proxy compartilhado é opcional e aproveitado quando configurado.
- Expor readiness, ações e histórico no contexto tenant da API e no painel existente.

**Non-Goals:**

- Pagar uma guia, confirmar ou acionar Pix/QR Code.
- Contornar validação de dispositivo ou desafio antifraude que não seja o hCaptcha contratado e explicitamente habilitado.
- Emitir sem autorização explícita ou política agendada opt-in.
- Inferir procuração, trocar perfil automaticamente quando ambíguo ou prestar parecer jurídico.
- Criar API pública não documentada, usar endpoints internos fora do fluxo controlado do navegador ou enviar credencial pela rede para sidecar.
- Adicionar serviços `mei`/`mei-worker`, restaurar `services/mei`, ligar flags produtivas por padrão ou versionar segredo/certificado/sessão.

## Decisions

### Adapter de portal versionado atrás de contrato interno

O Laravel dependerá de `FgtsDigitalPortalClient`, com implementações `DisabledFgtsDigitalPortalClient` e `ProcessFgtsDigitalPortalClient`. O processo RPA receberá uma requisição JSON versionada por stdin e responderá um único envelope JSON por stdout; stderr e logs serão sanitizados. Um driver de fixtures simulará o processo no CI.

O worker RPA usará Playwright/Chromium e page objects por módulo do portal. Rotas, seletores, headings e formatos reconhecidos ficarão em manifesto versionado. Resposta ausente, ambígua ou divergente será `PORTAL_CONTRACT_CHANGED`, nunca uma confirmação presumida.

### Runtime apenas no Horizon, sem serviço adicional

As imagens PHP terão targets opcionais `horizon-rpa-dev` e `horizon-rpa-prod` com Chromium, Python, Playwright e dependências ICP-Brasil. O serviço Horizon existente selecionará esse target; PHP-FPM, scheduler e nginx não receberão navegador. Não haverá porta, daemon HTTP ou serviço Compose novo.

O Laravel iniciará o processo com diretório temporário exclusivo `0700`, limite de tempo/memória e sem herdar variáveis sensíveis desnecessárias. PFX, senha e sessão entram apenas pelo stdin do processo filho e são liberados em `finally`; nenhum PEM/PFX será gravado. Downloads passam por stdout como descritores/base64 com limite estrito e são imediatamente cifrados no storage privado.

### Autenticação, perfil e resolução externa do hCaptcha

O resolvedor escolherá primeiro A1 ativo do próprio cliente. O A1 do escritório somente será elegível quando houver política explícita do office, identidade configurada como `PROCURADOR_PJ` e vínculo de procuração ativo para o cliente. A escolha nunca será baseada apenas no nome/CNPJ do arquivo.

A sessão autenticada será um blob cifrado no vault, vinculado a `office_id`, credencial/fingerprint, tipo de perfil, CNPJ-alvo e versão do contrato. Expiração, troca de credencial ou divergência invalida a sessão. Cookies/tokens não serão retornados pela API nem serializados nos logs.

Quando o Gov.br apresentar hCaptcha e o driver `nopecha` estiver explicitamente habilitado, o próprio worker extrairá da página a `sitekey`, URL exata, `rqdata` quando presente, cookies e user-agent. O worker chamará `POST https://api.nopecha.com/token/`, consultará o resultado pelo identificador retornado e aplicará o token de uso único no callback/formulário do desafio sem reiniciar o contexto do navegador.

O proxy é opcional no contrato da Token API. Quando configurado, o Chromium e o job do provider MUST usar exatamente o mesmo proxy autenticado, pois o Gov.br pode vincular a resposta ao IP de resolução. Sem proxy, o worker ainda MUST chamar a API externa e tentar aplicar o token; a eventual reapresentação do desafio será `CAPTCHA_TOKEN_REJECTED`, nunca sucesso presumido. Proxy malformado continua bloqueando antes do egress. A chave do provider, credenciais do proxy e token resolvido entram somente no envelope privado por stdin, não são persistidos e não aparecem em stdout, API ou logs. Tentativas e custo possuem limite configurável; o default permanece `disabled` e uma resposta consumida não é reutilizada.

Após aplicar o token, ausência visual do widget não comprova autenticação. O worker só emitirá `SESSION_READY` depois de observar marcador autenticado do FGTS Digital e validar que a URL/identidade selecionada pertencem ao fluxo esperado. Reapresentação do hCaptcha gera `CAPTCHA_TOKEN_REJECTED`; validação de dispositivo ou antifraude diferente continua como `HUMAN_CHALLENGE_REQUIRED`. Um endpoint autorizado ainda poderá registrar sessão obtida por agente local controlado, sujeita às mesmas validações e expiração.

### Operações de leitura e artefatos

Consulta de débitos/guias, situação de pagamento, reimpressão e downloads são operações read-only. O parser extrairá somente campos allowlisted: empregador, competência, tipo, vencimento, total, número da guia, status e metadados de documento.

PDFs e relatórios serão validados por assinatura/mime/tamanho, cifrados no disco privado e expostos pelo descriptor/download fiscal autenticado existente. `TaxGuide` receberá origem `FGTS_DIGITAL_PORTAL` e metadados estáveis; dedupe usará número oficial e, na ausência deste, hash do documento dentro do mesmo cliente/competência/tipo.

### Emissão como mutação em duas fases

Guia rápida e parametrizada seguirão `preview -> authorize -> enqueue -> execute`. O preview é read-only e retorna seleção, valores e uma impressão digital. A autorização expira, é tenant-scoped e assina o fingerprint do preview. Políticas agendadas precisam de opt-in explícito, escopo de clientes/tipos/limites e podem ser desligadas pelo kill switch.

Cada execução reserva chave de idempotência por office+cliente+competência+tipo+seleção. Antes de emitir, consulta guias existentes; se encontrar equivalência, persiste/reutiliza a guia e conclui como `REUSED`. Após o clique final, reconcilia número/valor/documento; resultado ambíguo vira `RECONCILIATION_REQUIRED` e não repete automaticamente.

`FiscalMutationOperation` será reutilizado para autorização, auditoria e estado. Dados específicos do portal ficarão em tabelas FGTS tenant-scoped; payloads sensíveis permanecem cifrados.

### Concorrência, recuperação e observabilidade

Um lock distribuído por office+identidade+perfil+CNPJ-alvo serializa sessão e navegação. Outro lock por idempotency key protege emissão. Falha de lock é retryable antes do egress. Sessões inválidas podem ser renovadas uma vez; desafios humanos e resultados pós-clique ambíguos não recebem retry automático.

O ledger registra operação, timestamps, estado, código sanitizado, versão do contrato, IDs internos e hashes; não guarda PFX, senha, cookies, token, HTML bruto ou CNPJ completo. Métricas distinguem leitura, mutação, reutilização, desafio humano, mudança de contrato e falha externa.

### API, scheduler e Nuxt

Rotas Sanctum permanecem sob tenant: coverage/readiness, consulta/sync, previews, autorização/emissão, runs e download. Nenhum `office_id` informado pelo cliente HTTP será confiado. Papéis de escrita são mais restritos que leitura.

O scheduler só enfileira políticas opt-in quando readiness está pronta; desafio humano suspende novas emissões do vínculo até resolução. `/monitoring/fgts` mostra fonte eSocial e portal separadamente, readiness, guias, pagamentos e ações. A Central de Guias incorpora `FGTS_DIGITAL_PORTAL` no mesmo contrato visual e de download.

## Mapa de dependências

```text
integrar-fgts-esocial-bx-oficial @ verify
                    │
N0 config + schema + contratos + runtime processual
                    │
N1 RPA: auth/perfil ─ consulta/download ─ preview/emissão
                    │
N2 Laravel: vault + locks + ledger + persistence + jobs/API
                    │
N3 Nuxt: FGTS + Central de Guias
                    │
N4 gates, fixtures e rollout restrito
```

- Ownership: domínio em `apps/api/app/Services/FgtsDigital`, processo em `apps/api/rpa/fgts_digital`, migrations/models FGTS próprios e UI em superfícies de monitoring existentes.
- Bases estáveis: credenciais/vault, mutation ledger, `TaxGuide`, descriptor/download, Horizon e scheduler.
- Relação bloqueante: binding compartilhado, persistência integrada e UI só avançam após `verify` da change eSocial BX; fixtures e contrato do processo podem avançar isoladamente.
- Rollout: schema/código sob driver `disabled`; depois fixture, portal restrito controlado e, por fim, coortes explicitamente autorizadas.

## Risks / Trade-offs

- [Portal não possui contrato público estável] → page objects/manifesto versionados, fixtures sanitizadas e falha `PORTAL_CONTRACT_CHANGED` antes de mutações.
- [Gov.br vincula hCaptcha ao IP/contexto] → solver executado no worker com URL/cookies/user-agent reais e, quando disponível, proxy idêntico no Chromium e no provider; sem proxy, a tentativa direta pela API externa pode ser rejeitada e será reportada sem inventar sessão.
- [Token é rejeitado ou desafio não é hCaptcha] → `CAPTCHA_TOKEN_REJECTED` ou `HUMAN_CHALLENGE_REQUIRED`, sem declarar sessão e sem retry ilimitado.
- [Procuração inexistente ou escopo insuficiente] → preflight e seleção estrita; sem fallback silencioso entre identidades.
- [Clique final confirmado mas resposta perdida] → consultar/reconciliar antes de retry; estado `RECONCILIATION_REQUIRED` impede duplicação.
- [Sessão/PFX vazam por processo ou arquivo] → stdin, diretório efêmero, storage cifrado, logs allowlisted e testes negativos de serialização.
- [Browser aumenta imagem e superfície de ataque] → dependências só no target Horizon RPA, imagens fixadas e egress limitado aos hosts oficiais.
- [Mudança concorrente no portal] → lock por identidade/perfil/alvo e invalidação de sessão por versão do contrato.
- [Status de pagamento defasado] → mostrar `checked_at`, preservar `UNKNOWN` e nunca executar Pix.
- [Certificado da G.A. expira/revoga] → readiness por fingerprint/validade e invalidação imediata de sessões vinculadas.

## Migration Plan

1. Aplicar schema e código com `FGTS_DIGITAL_DRIVER=disabled` e sem material de certificado em deploy.
2. Instalar runtime somente no target Horizon RPA; validar protocolo/fixtures, secret scan e ausência de novos serviços.
3. Concluir e verificar eSocial BX; habilitar `fixture` em CI/local e validar Central de Guias.
4. Cadastrar a identidade da G.A. pelo fluxo seguro do vault, declarar procurações por cliente e executar readiness em coorte controlada.
5. Configurar o solver NopeCHA em coorte controlada, opcionalmente com proxy compartilhado, comprovar `SESSION_READY` por marcador autenticado e habilitar apenas consultas/downloads; usar sessão humana quando houver desafio não suportado.
6. Habilitar emissão manual por preview/autorização e, depois, políticas agendadas opt-in com limites.
7. Rollback desliga driver/kill switch e cancela novos jobs; sessões são revogadas, mas ledger e artefatos fiscais permanecem para auditoria.

## Open Questions

- O tempo de vida real da sessão Gov.br/FGTS Digital será medido em coorte; o sistema adotará TTL conservador configurável até haver evidência.
- Novos tipos de guia ou módulos do portal exigirão fixture, parser e requisito próprios antes de entrar no catálogo automático.
- Se o MTE publicar API oficial de guia, uma change futura poderá adicionar provider M2M e manter o portal apenas como fallback explicitamente configurado.
