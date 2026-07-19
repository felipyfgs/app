# MEI

Microserviço interno (Playwright) solicitado pela API Laravel em `apps/api`.
Não publicar a API diretamente na internet.

Por padrão, `MEI_AUTOMATION_LIVE_EGRESS_ENABLED=false`. O modo fixture pode
executar localmente `pgmei.gerardaspdf`, `pgmei.gerardascodbarra`,
`pgmei.dividaativa` e `dasnsimei.consultimadecrec`, além de `fixture.health`.
O egress live exige habilitação explícita e continua fail-closed para captcha e
drift do portal.

## hCaptcha invisível

A presença de `.h-captcha`, iframe ou campo `h-captcha-response` significa apenas
que a integração invisível foi carregada. O worker submete a identificação uma
única vez e aguarda um checkpoint semântico. Ele só considera CAPTCHA bloqueante
quando há desafio visível ou rejeição explícita; nesse caso, o solver permanece
subordinado a driver, chave, allowlist da operação, custo e orçamento. O fluxo não
recarrega a página nem repete a identificação.

### Browser (alinhado ao docapi)

O launch segue o padrão comprovado em
[`felipyfgs/docapi`](https://github.com/felipyfgs/docapi) `app/browser.py` +
`Dockerfile`:

- Google Chrome (`MEI_AUTOMATION_BROWSER_CHANNEL=chrome`)
- headed (`MEI_AUTOMATION_BROWSER_HEADLESS=false`) sob Xvfb no container
- args: `--disable-blink-features=AutomationControlled`, `--no-sandbox`,
  `--disable-dev-shm-usage`

Playwright Chromium headless puro costuma receber `13896 - Impedido por proteção
Captcha. Comportamento de Robô.` mesmo com a flag. O contorno correto (NoneCap/
NoPeCHA) é token out-of-band: ler sitekey, obter `P1_…`, injetar campos +
`setResponse`/`data-callback` e, no 13896, um único re-clique após o inject.
NoPeCHA permanece OFF por padrão; em local, habilite `CAPTCHA_DRIVER=nopecha`,
allowlist, budget e chave via `.env` (nunca commitada). Para Enterprise/IP-bind,
configure `MEI_AUTOMATION_NOPECHA_PROXY_URL` com o mesmo egress do browser
(`http://user:pass@host:port`).

Os estados sanitizados são `auto_approved`, `validation_rejected`,
`captcha_exhausted` e `portal_drift`. CNPJ, sitekey, token, cookies, HTML e conteúdo
do desafio não podem ser copiados para logs ou evidências.

### Probe live controlado

O probe é opcional e deve usar somente um cliente/CNPJ autorizado para teste. Faça
uma consulta pública não mutante (`pgmei.dividaativa` ou
`dasnsimei.consultimadecrec`) pela UI/API Laravel normal; não chame o sidecar
diretamente e não use emissão de DAS como smoke.

1. Em ambiente controlado, habilite `MEI_AUTOMATION_ENABLED=true`,
   `MEI_AUTOMATION_LIVE_EGRESS_ENABLED=true`, mantenha
   `MEI_AUTOMATION_FIXTURE_ENABLED=false` e limite
   `MEI_AUTOMATION_OFFICE_ALLOWLIST` ao escritório de teste.
2. Direcione somente a operação escolhida para `portal_then_serpro` por
   `MEI_AUTOMATION_PROVIDER_PGMEI_DEBT` ou
   `MEI_AUTOMATION_PROVIDER_DASN_HISTORY`. Preserve
   `MEI_AUTOMATION_NOPECHA_ENABLED=false`, salvo autorização separada e explícita.
3. Reinicie somente `php`, `horizon`, `mei` e `mei-worker`, confirme que a UI está
   no escritório allowlisted e dispare uma única consulta para o cliente de teste.
4. Registre apenas operação, provider, estado final, código de erro, duração e custo
   do solver. Não registre o identificador nem o payload retornado.
5. Smoke sidecar (opcional):  
   `MEI_SMOKE_CNPJ=############## docker compose exec -T mei python /app/scripts/smoke_live_identify.py`
6. Restaure os providers para `serpro`, desligue live egress e remova a allowlist ao
   terminar, independentemente do resultado.

Um desafio ou drift no probe é um resultado válido e fail-closed; não autoriza nova
tentativa automática, stealth ou flexibilização dos defaults.

Na emissão PGMEI, o input aceita `competencies` (`YYYY-MM`) e `due_date`
opcional (`YYYY-MM-DD`). Sem `due_date`, o worker usa a data corrente em
`America/Sao_Paulo`. O fluxo seleciona as competências, preenche a data,
aguarda `Atualizar Valores` e só então executa uma única submissão
`Apurar/Gerar`.

## Fronteiras operacionais

- Horizon executa domínio fiscal, SERPRO, SEFAZ, polling e ingestão no vault.
- Celery executa somente os jobs de browser deste serviço.
- Laravel usa Redis DB `/0`/`/1`; o MEI usa DB `/4` com estado sujeito a TTL.
- Os containers FastAPI/Celery ficam somente na rede interna e não publicam portas.
- Postgres no Laravel é a fonte de verdade; este serviço não recebe DB nem vault.

O contrato completo está em
[`docs/architecture/mei-stack-boundaries.md`](../../docs/architecture/mei-stack-boundaries.md).
