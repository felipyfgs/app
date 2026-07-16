## Context

O runtime já possui contrato SERPRO global, OAuth/mTLS, vault, ledger, catálogo canônico e módulos fiscais, porém apenas Autentica Procurador e SITFIS têm coordenadas e caminho HTTP confiáveis. O manifesto atual preserva a contagem de 119 por meio de coordenadas provisórias; adapters de Caixa Postal, procurações, guias e parcelamentos continuam fake, enquanto DCTFWeb/MIT e Simples/MEI ainda podem cair no caminho legado sem `operation_key`.

A documentação oficial define um envelope comum e cinco rotas funcionais, mas os schemas de `pedidoDados.dados`, poderes e comportamento assíncrono variam por operação. O produto é multi-escritório: contrato/mTLS e catálogo pertencem ao plano global; autorização, payload fiscal, projeções e consumo pertencem ao `Office` ativo.

## Goals / Non-Goals

**Goals:**

- Tornar as 119 entradas rastreáveis à documentação oficial e as 98 produtivas executáveis por adapters tipados.
- Centralizar protocolo, autenticação, autorização, retry, faturamento e sanitização em um gateway único.
- Substituir fontes fake/legadas por drivers explícitos `disabled|simulated|real`, sem fallback silencioso.
- Integrar Cadastro/Vínculos e e-Processo ao mesmo read model do monitoramento.
- Manter mutações implementadas, auditáveis e desligadas até todos os gates operacionais.

**Non-Goals:**

- Habilitar automaticamente produção ou declarar o smoke mTLS como aprovado.
- Implementar operações em prospecção, construção ou canceladas.
- Alterar FGTS/eSocial, ADN, SEFAZ, cursores fiscais ou criar portal de contribuinte.
- Expor credenciais globais, Termo, tokens, XML bruto ou payload SERPRO em APIs de tenant.

## Decisions

### 1. Catálogo documental versionado antes do runtime

O arquivo versionado em `backend/resources/serpro/` será a entrada imutável do importador e conterá uma linha por operação. Cada linha carregará coordenadas, rota, versão, estado oficial, autenticação, poder, mutabilidade, faturamento, política assíncrona, módulo, schemas e fontes com hash. Um validador recusará placeholders, duplicidades, contagens divergentes e operação executável sem fonte/schema.

Alternativa rejeitada: manter seeds dispersos em migrations, pois isso já produziu coordenadas conflitantes e impede auditoria documental.

### 2. `operation_key` como única capacidade pública do gateway

`IntegraRequest` passará a exigir `operation_key`, identidades tipadas e dados de negócio. Rota, sistema, serviço, versão, headers e política de autorização virão exclusivamente do catálogo. O caminho legado que aceita coordenadas será removido. Um gateway HTTP comum aplicará codecs tipados por operação/família.

Alternativa rejeitada: um cliente HTTP por operação, que duplicaria autenticação, sanitização e tratamento de falhas em 98 classes.

### 3. Cadeia de identidade e autorização orientada por metadado

Contratante será sempre o e-CNPJ do contrato global ativo. Autor e contribuinte usarão um value object com `tipo` explícito e NI textual uppercase. `auth_mode` e `required_proxy_power` decidirão se o token do procurador e o poder e-CAC são obrigatórios; não haverá regra global exigindo token em toda operação. Tokens e ETags sensíveis ficam no `SecureObjectStore`/cache protegido.

### 4. Máquina de estados HTTP e faturamento central

O gateway permitirá somente `/Apoiar`, `/Consultar`, `/Declarar`, `/Emitir` e `/Monitorar`, serializará `dados` uma vez e usará header allowlist. 202/204/304 produzirão resultado pendente/cacheado com espera derivada dos headers. Em 401 o par OAuth será invalidado e haverá uma única nova tentativa com a mesma tag; timeout ambíguo nunca repetirá mutação. 429/503 serão reagendados por Horizon.

A classificação de consumo usará rota e HTTP: Apoiar/Monitorar são gratuitos; 204/304/400/401/404/429/500/503 não faturam; 200/202/403 nas demais rotas entram no ledger conforme a classe da operação.

### 5. Adapters por família e projeções tenant-scoped

Cada família registrará adapters no `FiscalAdapterRegistry`, sempre construindo `IntegraRequest` por `operation_key`. Resultados serão persistidos idempotentemente com `office_id`, `client_id`, chave oficial e versão de evidência. Catálogo, preços e contrato continuam globais; controllers tenant não importarão modelos globais diretamente.

Jobs usarão Horizon, locks por office/cliente/operação e drivers de capacidade por família. `simulated` continuará proibido em produção e não contará como evidência real.

### 6. Mutações implementadas e fail-closed

Operações mutantes usarão o fluxo central existente de preflight/intenção. A chamada real exigirá flag da operação, allowlist do office, assinatura writable, papel ADMIN, TOTP recente, confirmação explícita, elegibilidade, idempotência, orçamento, contrato saudável e kill switch aberto. Nenhum scheduler criará intenção mutante.

### 7. APIs e UI de Cadastro/Vínculos e Processos fiscais

As novas APIs ficarão sob Sanctum, usuário ativo, `EnsureOfficeContext`, TOTP admin e assinatura writable, como o núcleo fiscal atual. O `office_id` será sempre retirado da sessão. As páginas `/monitoring/registrations` e `/monitoring/tax-processes` copiarão o arquétipo de lista do template; as seções do cliente copiarão Settings. O frontend consumirá `useApi()` e não receberá material global/sensível.

## Risks / Trade-offs

- [Mudança da documentação oficial] → snapshot com URL/hash, versões efetivas e revalidação automatizada antes de promoção.
- [Vazamento entre offices] → escopo obrigatório, chaves únicas incluindo `office_id` e testes com o mesmo CNPJ em dois offices.
- [Token/ETag em log ou resposta] → allowlist de campos públicos, sanitização central e testes de varredura de segredos.
- [Cobrança duplicada em retry] → request tag/idempotência estável, uma tentativa após 401 e nenhuma repetição automática após timeout mutante.
- [Grande volume de operações] → entrega por família com driver e kill switch independentes.
- [UI sugerir suporte inexistente] → estado do catálogo exposto de forma sanitizada e ações ocultas enquanto a capacidade não estiver `real`.

## Migration Plan

1. Importar nova versão do catálogo e encerrar versões provisórias sem apagar histórico.
2. Implantar gateway e codecs com todos os drivers `disabled`.
3. Promover famílias em sequência: autorização/SITFIS; Caixa Postal/DCTFWeb/MIT; Simples/MEI/guias; parcelamentos; Cadastro/Vínculos/e-Processo.
4. Criar e preencher projeções por refresh explícito; não fazer varredura mutante.
5. Habilitar `real` somente para offices canários após testes, evidência comercial/legal e smoke restrito.
6. Em rollback, desligar driver/kill switch e manter catálogo, ledger e projeções para auditoria.

## Open Questions

Nenhuma decisão de produto pendente. As 21 operações não produtivas serão apenas catalogadas e bloqueadas.
