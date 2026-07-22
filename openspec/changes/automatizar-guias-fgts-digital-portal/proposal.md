## Why

O eSocial BX entrega eventos e totalizadores, mas não emite nem disponibiliza a Guia do FGTS Digital. Soluções de mercado automatizam essa lacuna acessando o portal com certificado/procuração; o hub precisa oferecer o mesmo resultado com isolamento tenant, artefatos auditáveis e resolução controlada do hCaptcha pela API externa autorizada.

## What Changes

- Adicionar provider `portal_browser` fail-closed para autenticar no Gov.br/FGTS Digital com A1 do cliente ou do escritório procurador, selecionar perfil e empregador autorizado e manter sessão apenas no vault.
- Automatizar consulta de débitos/guias, situação de pagamento, reimpressão e download de PDF/relatórios, além da emissão idempotente de guia rápida mensal, rescisória, consignado ou mista.
- Automatizar guia parametrizada mediante filtros de débitos, preview e autorização explícita ou política agendada opt-in; a automação não efetuará o pagamento Pix.
- Executar o navegador somente em jobs Horizon com runtime RPA próprio da imagem do worker, PFX/senha via stdin e diretórios efêmeros; não criar serviço Compose novo nem restaurar `mei`/`mei-worker`.
- Resolver hCaptcha pela API externa NopeCHA somente por opt-in, com `sitekey`, URL, cookies, user-agent e `rqdata` descobertos no navegador; proxy de mesma saída é opcional e, quando configurado, é compartilhado com o browser para aumentar a aceitação em hCaptcha Enterprise. Desafios não suportados continuam como `HUMAN_CHALLENGE_REQUIRED`.
- Persistir ledger, sessão e PDFs/relatórios cifrados, deduplicar por número/hash da guia e expor downloads autenticados pelo contrato fiscal existente.
- Integrar API tenant, scheduler, `/monitoring/fgts` e Central de Guias com readiness, emissão, consulta, pagamento, histórico e diagnósticos sanitizados.
- Adicionar testes de contrato do portal por fixtures, processo RPA fake, autorização de mutação, persistência, API e Nuxt, além dos gates de API, web, imagem e Compose.
- Non-goals: pagar a guia/acionar Pix, contornar validação de dispositivo ou desafio antifraude não suportado, emitir sem autorização ou política opt-in, parecer jurídico sobre procurações, SERPRO live, flags produtivas ligadas por padrão, canais SEFAZ, serviços `mei`/`mei-worker` e targets de backup/restore indisponíveis.

## Capabilities

### New Capabilities

- `fgts-digital-guide-automation`: Autenticação/procurações no portal oficial, consulta, emissão controlada, reimpressão/download, situação de pagamento, sessão protegida, auditoria, scheduler e UI.

### Modified Capabilities

- `monitoring-guides-central-read-model`: Incluir GFD emitida/consultada como fonte real da lista unificada, com documento e situação de pagamento do portal.

## Impact

- API Laravel: domínio FGTS, credenciais de cliente/escritório, vault, processos RPA, jobs Horizon, mutations/idempotência, modelos/migrations, controllers, downloads e testes.
- Runtime: target de imagem Horizon com Chromium, Playwright e NSS; nenhuma porta ou serviço Compose adicional.
- Web Nuxt: página `/monitoring/fgts`, Central de Guias, tipos/composables, modal de preview/autorização e testes.
- Sistemas externos: Gov.br, `certificado.sso.acesso.gov.br`, módulos oficiais do FGTS Digital e API de token NopeCHA explicitamente habilitada; nenhum uso de endpoint FGTS inventado como API pública.

### Dependências entre changes

- Nível: `C1`.
- Bases estáveis: `ClientCredential`, credencial/vault do escritório, `FiscalMutationOperation`, `TaxGuide`, downloads autenticados, Horizon e o domínio FGTS atual.
- Depende de: `integrar-fgts-esocial-bx-oficial`, capability `fgts-esocial-bx-automation`, marco `verify`, relação `bloqueante`, para consolidar coverage/readiness/estados e serializar arquivos FGTS compartilhados.
- Desbloqueia: políticas de envio automático de GFD por WhatsApp/e-mail e cobertura futura de fluxos especiais do portal sem duplicar autenticação.
- Pode avançar em fixtures/runtime isolados enquanto o upstream é aplicado, mas binding, controller, modelos compartilhados e UI FGTS só serão integrados depois do `verify` upstream; não edita artefatos da change dependida.
