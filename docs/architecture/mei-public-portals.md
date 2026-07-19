# Contrato exploratorio dos portais publicos MEI

Datas da exploracao: 2026-07-18 e 2026-07-19

## Escopo seguro

A exploracao usou Chromium Playwright headless sem informar CNPJ, resolver captcha ou submeter formulario. Foram coletados somente URL/path, status HTTP, titulo, versao visivel, action/method dos formularios e nomes/tipos dos controles. HTML bruto, valores antiforgery, cookies, imagens e conteudo do hCaptcha nao foram persistidos.

## PGMEI

- URL oficial: `https://www8.receita.fazenda.gov.br/SimplesNacional/Aplicacoes/ATSPO/pgmei.app/Identificacao`
- HTTP `200`; titulo `PGMEI - Programa Gerador de DAS do Microempreendedor Individual`.
- Versao observada: `3.17.0`.
- Form principal: `POST /SimplesNacional/Aplicacoes/ATSPO/pgmei.app/Identificacao/Continuar`.
- Campos: `__RequestVerificationToken` oculto e `cnpj` texto com `autocomplete=off`.
- Acao semantica: botao `Continuar`; dois iframes hCaptcha observados.

## DASN-SIMEI

- URL oficial: `https://www8.receita.fazenda.gov.br/SimplesNacional/Aplicacoes/ATSPO/dasnsimei.app/Identificacao`
- HTTP `200`; titulo `DASN SIMEI - Declaracao Anual do Simples Nacional - Microempreendedor Individual`.
- Versao observada: `2.11.0`.
- Form principal: `POST /SimplesNacional/Aplicacoes/ATSPO/dasnsimei.app/Identificacao/Continuar`.
- Campos: `__RequestVerificationToken` oculto e `cnpj` texto, `maxlength=18`, `inputmode=numeric`, `autocomplete=off`.
- Acao semantica: botao `identificacao-continuar`; dois iframes hCaptcha observados.

`inputmode=numeric` nao prova rejeicao server-side, mas exige checkpoint anterior ao POST para CNPJ alfanumerico. Se a pagina ou a validacao live aceitar somente digitos, o provider deve retornar `PORTAL_CNPJ_FORMAT_UNSUPPORTED` com `submitted=false`.

## Fixtures locais

As paginas posteriores nao foram acessadas porque isso exigiria CNPJ real e captcha. Os arquivos em `services/mei/tests/fixtures/` sao documentos sinteticos, sem copia de conteudo fiscal, usados como contrato executavel para estados de competencia, divida ativa, historico resumido e recibo integral. Mudancas confirmadas em smoke live devem atualizar a versao do parser e as fixtures antes de habilitar rollout.

## Atualizacao de implementacao

- Probes Playwright em 2026-07-19 abriram as duas URLs oficiais, validaram antiforgery/campos/botoes e detectaram hCaptcha. Foi usado identificador sintetico somente para preencher o DOM; nenhum formulario foi submetido. PGMEI e DASN terminaram `CAPTCHA_EXHAUSTED` com `submitted=false`.
- O fluxo PGMEI implementado segue identificacao, menu de emissao, `#anoCalendarioSelect`, competencia no formato `YYYYMM`, `#btnEmitirDas` e endpoint `/emissao/imprimir`. Um teste local de navegador confirma uma unica passagem por cada etapa e uma unica emissao.
- O PDF e baixado pela mesma sessao efemera e validado por assinatura, EOF, tamanho e SHA-256. Para codigo de barras, o PDF e renderizado com limite de paginas/pixels e o codigo linear e decodificado sem cast numerico.
- DASN usa `#iniciar-ano-calendario` e extrai dos radios ano, status, tipo, situacao especial, data, pendencia e disponibilidade de recibo. Sem recibo PDF validado, a cobertura permanece `SUMMARY`.

## Referencias comportamentais

- `felipyfgs/docapi`: confirmou os controles DASN e os atributos `data-*`; como o repositorio nao apresenta licenca explicita, nenhum codigo foi copiado.
- `engmsilva/scraping-das-mei`: referencia MIT para a sequencia atual de emissao PGMEI. A implementacao local usa Playwright Python, checkpoints fail-closed e nao adota headers artificiais, digitacao humana, stealth, screenshots fiscais ou repeticao de navegacao.

NoPeCHA esta implementado somente como transporte opcional. Os defaults efetivos sao flag OFF, allowlist vazia e orcamento zero; quando explicitamente autorizado, ha um unico job externo por execucao e polling limitado, sem persistir chave, sitekey ou token em resultado/log.
