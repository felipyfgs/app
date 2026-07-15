## Context

O sistema atual foi concebido como painel interno de um escritório, embora a persistência e as policies já usem `office_id`. O modelo de negócio confirmado é diferente: a software house opera uma plataforma SaaS contratada por vários escritórios contábeis, e cada escritório monitora exclusivamente seus próprios contribuintes.

No Integra Contador existem três identidades distintas:

1. **Contratante:** a software house, titular do contrato SERPRO, do e-CNPJ usado no mTLS e das credenciais `Consumer Key`/`Consumer Secret`.
2. **Autor do Pedido de Dados:** o escritório contábil ou procurador que assina o Termo de Autorização e possui poderes perante os contribuintes.
3. **Contribuinte:** cliente final do escritório cujos dados serão consultados.

O SERPRO emite uma fatura agregada para a software house. A plataforma precisa atribuir cada operação faturável ao tenant correto, impedir consultas sem procuração/poder suficiente e preservar sigilo fiscal, LGPD, evidência e auditoria. Nem todos os módulos desejados têm a mesma cobertura oficial: Integra-SN, MEI, DCTFWeb, Parcelamentos, SITFIS, Caixa Postal, Procurações, Pagamentos e Sicalc possuem serviços documentados; FGTS Digital não possui API pública equivalente e só pode receber cobertura parcial por fontes oficiais do eSocial.

## Goals / Non-Goals

**Goals:**

- Operar um contrato SERPRO central da software house sem compartilhar suas credenciais com tenants.
- Transformar `Office` em tenant comercial e de segurança, preservando isolamento por `office_id` em todo dado fiscal.
- Modelar e validar a cadeia Contratante → Autor do Pedido → Contribuinte antes de cada chamada.
- Automatizar autenticação, renovação e elegibilidade de forma segura e observável.
- Registrar consumo imutável por tenant, serviço e classe faturável, com orçamento, franquia, alerta e conciliação.
- Oferecer monitoramento fiscal uniforme, orientado por eventos e evidências oficiais.
- Diferenciar cobertura completa, parcial, indisponível e desconhecida sem inventar dados.
- Preparar operações assistidas e mutantes com autorização, idempotência, 2FA recente e auditoria.

**Non-Goals:**

- Criar portal ou autenticação para contribuintes finais dos escritórios.
- Expor ou sublicenciar credenciais SERPRO, certificados, termos assinados ou tokens.
- Implementar cobrança bancária, gateway de pagamento ou emissão fiscal da assinatura no MVP.
- Automatizar portais humanos, CAPTCHA, Gov.br, cookies ou sessões de navegador.
- Prometer integração direta com FGTS Digital sem API pública oficial.
- Executar transmissões, adesões ou confissões de dívida no piloto somente leitura.
- Alterar o comportamento dos canais ADN/SEFAZ e seus cursores.

## Decisions

### 1. Separar plano de controle global e plano de dados dos tenants

Dados da plataforma terão escopo explícito:

- **Globais:** contrato SERPRO, credencial do contratante, catálogo/versionamento de preços, limites globais, consolidação de fatura, flags globais e associação de administradores da plataforma.
- **Do tenant:** assinatura/plano do escritório, identidade do Autor do Pedido, Termo de Autorização, token do procurador, procurações/poderes, clientes, execuções, evidências, consumo atribuído e dados fiscais.

Tabelas de negócio do tenant continuarão com `office_id` obrigatório. Tabelas globais não aceitarão `office_id` opcional como atalho; o escopo global será expresso pelo próprio tipo de tabela e por services/policies exclusivos. `PLATFORM_ADMIN` será uma autorização separada de `ADMIN`, `OPERATOR` e `VIEWER` do escritório e não concederá leitura implícita de conteúdo fiscal.

Alternativa rejeitada: duplicar contrato e credenciais SERPRO por escritório. Isso transfere contratação e faturamento aos clientes da plataforma, contraria o modelo comercial definido e multiplica segredos de alto impacto.

### 2. Manter `Office` como tenant contratante da plataforma

`Office` passará a representar uma empresa contábil assinante. Serão adicionados ciclo de vida e governança em `office_subscriptions` ou agregado equivalente: plano, estado (`TRIAL`, `ACTIVE`, `PAST_DUE`, `SUSPENDED`, `CANCELED`), datas, franquias e limites. Suspensão bloqueará novas chamadas externas e mutações, mas preservará leitura autorizada, exportação permitida, histórico e evidências conforme retenção.

Memberships continuarão associando funcionários aos escritórios. Um usuário poderá pertencer a mais de um escritório, mas deverá escolher apenas entre memberships válidas; troca de tenant será explícita, protegida e auditada. Nenhum `office_id` fornecido livremente pelo navegador será autoridade.

Alternativa rejeitada: criar um deployment por escritório. Isso impede ganho de escala, consolidação do contrato SERPRO e operação comercial central.

### 3. Modelar o contrato SERPRO como recurso global único e versionado

`serpro_contracts` manterá identidade contratante, ambiente, estado, vigência, identificadores não secretos, referências do `SecureObjectStore` e timestamps de verificação. Apenas um contrato por ambiente poderá estar ativo. O material incluirá:

- e-CNPJ/PFX do contratante e senha em objetos seguros separados ou envelope único versionado;
- `Consumer Key` e `Consumer Secret`;
- Bearer/JWT temporários, sempre cifrados e renováveis;
- metadados sanitizados de certificado, sem PEM ou chave privada persistida em claro.

O transporte usará mTLS com PFX somente em memória, TLS mínimo 1.2 e verificação de hostname. Um contrato de domínio `IntegraContadorClient` receberá DTOs internos e nunca vazará payload bruto aos jobs. Autenticação será separada em `SerproContractAuthenticator`, permitindo cache seguro e renovação coordenada.

Alternativa rejeitada: guardar chaves em `.env` comum. A rotação, auditoria, backup e separação entre metadado e segredo seriam insuficientes.

### 4. Representar a autorização de cada escritório sem confundir certificados

`office_serpro_authorizations` terá `office_id`, identidade PF/PJ do Autor do Pedido, vigência do termo, hash, referência ao XML assinado no cofre, estado, referência ao token `autenticar_procurador_token`, expiração e último resultado de validação.

O certificado do contratante da API nunca assinará o Termo em nome do escritório. O termo deverá ser assinado pelo Autor do Pedido com e-CPF/e-CNPJ ICP-Brasil A1 ou A3. Serão suportados dois modos:

- **Assinatura externa:** o navegador/aplicativo local produz o XMLDSig; o servidor recebe apenas o XML assinado.
- **A1 gerenciado, opcional:** quando o escritório autorizar automação, o PFX do Autor será guardado no `SecureObjectStore` com finalidade própria e usado somente em memória para assinar/renovar o termo.

A3 permanece interativo e não será simulado como automação. O piloto deverá confirmar com o SERPRO se um Termo de Autorização de longa vigência pode ser reapresentado após expirar o token diário. Até essa confirmação, o scheduler tratará a renovação como capability com estratégia configurável, sem assumir reutilização silenciosa.

Alternativa rejeitada: reutilizar `ClientCredential` ou `OfficeCredential` de canais SEFAZ. Finalidades, titulares, riscos e regras de rotação são diferentes.

### 5. Validar procuração e poder por contribuinte e serviço

`tax_proxy_powers` registrará `office_id`, cliente/contribuinte, Autor do Pedido, código do serviço/poder, fonte, vigência, estado e evidência. A elegibilidade de uma operação exigirá simultaneamente:

1. assinatura do tenant ativa e dentro do limite operacional;
2. contrato SERPRO global saudável;
3. autorização do escritório válida;
4. contribuinte pertencente ao mesmo `office_id`;
5. procuração/poder compatível com o sistema e serviço;
6. cobertura oficial e ambiente habilitados;
7. permissão do usuário ou job;
8. orçamento/rate limit e circuit breaker disponíveis.

O corpo enviado ao SERPRO será montado a partir de registros persistidos da Contratante, Autor e Contribuinte. Identidades recebidas do frontend nunca substituirão esses registros. O `Integra-Procurações` será usado quando elegível, mas o sistema aceitará evidência verificada por outro canal oficial enquanto registrar fonte e validade.

### 6. Criar ledger imutável de consumo antes e depois da chamada

Cada operação criará uma reserva/execução com identificador idempotente. `serpro_api_usage_entries` registrará `office_id`, contribuinte, sistema, serviço, operação, classe (`CONSULTA`, `EMISSAO`, `DECLARACAO`, `NAO_FATURAVEL` ou `DESCONHECIDA`), quantidade, resultado, correlação, latência, versão de preço e custo estimado. O registro será finalizado com o resultado, inclusive em falha quando a regra contratual puder faturar a tentativa.

Preços serão versionados e terão vigência; não serão hardcoded no cliente HTTP. A consolidação agregará uso mensal da plataforma e produzirá rateio por tenant. Um processo de conciliação importará ou registrará o relatório/fatura oficial do SERPRO e manterá diferenças, sem reescrever o ledger original.

Franquias e alertas serão aplicados por escritório. Estouro poderá bloquear operações não essenciais conforme política do plano, mas nunca interromper uma transação já confirmada entre a chamada externa e sua persistência. O sistema usará Eventos de Última Atualização, cache e deduplicação para reduzir consultas sem valor.

Alternativa rejeitada: calcular custo somente a partir da fatura mensal. Isso impede atribuição verificável por tenant, alertas antecipados e investigação de divergências.

### 7. Usar um núcleo fiscal orientado a execução, snapshot e evidência

O núcleo terá conceitos compartilhados:

- `fiscal_categories` e `office_fiscal_category_links`;
- `fiscal_monitoring_schedules` e competências;
- `fiscal_monitoring_runs` com origem manual, agendada ou por evento;
- `fiscal_snapshots` imutáveis e versionados;
- `fiscal_findings` e `fiscal_pending_items` normalizados;
- `fiscal_evidence_artifacts` com bytes/hash, origem e retenção;
- chaves idempotentes por tenant, contribuinte, sistema, serviço, competência e evento.

Respostas externas serão convertidas em DTOs específicos antes de alcançar modelos. Evidência oficial será preservada; projeções normalizadas poderão ser refeitas sem alterar o artefato original. Estados mínimos serão `UP_TO_DATE`, `PENDING`, `PROCESSING`, `ATTENTION`, `ERROR`, `NOT_APPLICABLE`, `UNKNOWN`, `UNSUPPORTED` e `BLOCKED`.

### 8. Separar adapters por solução oficial

O backend terá adapters sob uma fachada comum, sem fingir que todos os serviços têm o mesmo contrato:

- Integra-SN/MEI para PGDAS-D, DEFIS, Regime de Apuração, PGMEI, CCMEI e DASN-SIMEI;
- Integra-DCTFWeb/MIT;
- Integra-Parcelamento;
- Integra-SITFIS assíncrono;
- Integra-CaixaPostal/DTE;
- Integra-Procurações;
- Integra-Pagamento/Sicalc;
- Web Services/eventos oficiais do eSocial para cobertura parcial de FGTS.

Cada adapter declarará operações, mutabilidade, procurações exigidas, classe faturável, cache, TTL, rate limit e estratégia de idempotência. O catálogo será versionado para acompanhar mudanças do SERPRO sem condicional espalhado pelo domínio.

### 9. Preferir eventos e agendamento justo entre tenants

Eventos de Última Atualização serão persistidos e deduplicados antes de disparar consultas direcionadas. Varreduras periódicas funcionarão como reconciliação, com espalhamento determinístico por `office_id` e contribuinte. Haverá limites simultâneos globais do contrato e limites por tenant, fila justa, retry com jitter, respeito a `Retry-After`, circuit breaker por solução e kill switch global/por escritório.

O scheduler não iniciará operação se autorização, procuração, plano, orçamento ou cobertura estiverem inválidos. Jobs carregarão identificadores internos e revalidarão o contexto antes da chamada, evitando que uma alteração entre enqueue e execução atravesse tenants.

### 10. Tratar operações mutantes como fase separada

Consulta e monitoramento serão liberados antes de emissão/transmissão. Operações mutantes exigirão:

- catálogo marcando a operação como mutante;
- papel autorizado e 2FA recente;
- confirmação mostrando contribuinte, competência, efeito e custo estimado;
- chave de idempotência e janela contra repetição;
- snapshot prévio, evidência da requisição/resultado e auditoria;
- bloqueio por plano, procuração, cobertura, kill switch ou estado incerto.

Timeout após envio será tratado como resultado incerto e reconciliado antes de permitir nova tentativa. O sistema não interpretará ausência de resposta como falha segura para repetir.

### 11. Limitar FGTS Digital ao que a fonte oficial comprovar

O módulo de FGTS usará eventos, recibos e totalizadores eSocial aplicáveis, como S-5003, S-5013 e fechamento S-1299, para indicar base conhecida, fechamento e divergências detectáveis. A UI rotulará a cobertura como parcial e não exibirá guia, pagamento ou pendência do portal como se tivessem sido consultados.

Ausência de API pública manterá estados `UNSUPPORTED`/`UNKNOWN` e orientação operacional. Scraping não será fallback.

### 12. Expor uma UI tenant-aware baseada no template oficial

O Nuxt continuará SPA same-origin. O shell exibirá o escritório ativo e, para usuários com múltiplas memberships, permitirá troca apenas entre tenants autorizados. Navegação, command palette e ações usarão a mesma matriz de permissões do backend.

O Dashboard Fiscal mostrará totais, pendências, autorizações, saúde, consumo/franquia e última atualização. Cada módulo terá tabela server-side, filtros reproduzíveis na URL, estados de cobertura e detalhe mestre–cliente. Dados globais do contrato SERPRO não serão expostos aos escritórios; somente saúde sanitizada, consumo atribuído e limites do próprio plano.

### 13. Auditar sem registrar sigilo fiscal ou material criptográfico

Auditoria registrará ator, tenant, ação, alvo interno, resultado, horário, IP/correlação e motivo. Logs e métricas não conterão PFX, senha, chave privada, PEM, `Consumer Secret`, tokens, Termo XML, procuração bruta, mensagem fiscal, relatório ou identificadores fiscais completos como labels.

Administradores da plataforma não terão uma rota genérica de impersonação. Qualquer suporte futuro com acesso fiscal deverá ser proposto separadamente como fluxo break-glass, com consentimento, prazo e auditoria reforçada.

## Risks / Trade-offs

- [Contrato público não esclarece todos os direitos de uso/reprecificação em SaaS] → obter confirmação comercial/jurídica escrita do SERPRO antes do piloto produtivo e manter cobrança da plataforma separada da representação do custo SERPRO.
- [Termo/token do procurador exige renovação frequente] → validar reapresentação do termo no piloto, suportar A1 gerenciado opcional e alertar antecipadamente quando assinatura interativa for necessária.
- [Comprometimento do certificado global afeta todos os tenants] → cofre com envelope, acesso mínimo, rotação, kill switch, observabilidade, backup protegido e runbook de revogação.
- [Credencial A1 do escritório aumenta responsabilidade da plataforma] → finalidade exclusiva, consentimento, isolamento no cofre, uso apenas em memória e preferência por assinatura externa quando operacionalmente viável.
- [Uma fatura global dificulta rateio exato] → ledger pré/pós-chamada, tabela de preços versionada, correlação e conciliação sem sobrescrever eventos originais.
- [Tenant ruidoso consome limite global] → quotas por plano, fila justa, rate limit global+tenant e alertas antes de bloqueio.
- [Mudanças no catálogo/procurações do SERPRO invalidam regras] → catálogo versionado, adapter por solução, contract tests e feature flags.
- [Operação fiscal mutante pode ser repetida após timeout] → idempotência, estado incerto e reconciliação obrigatória antes de retry.
- [Cobertura parcial ser interpretada como regularidade total] → vocabulário explícito, evidência por fonte e proibição de inferir “em dia” quando há dados desconhecidos/não suportados.
- [Mudança para multi-escritório amplia superfície de vazamento] → testes negativos sistemáticos de `office_id`, policies, escopos, filas, cache keys, storage paths, exports e métricas.

## Migration Plan

1. Atualizar contexto de domínio e decisões arquiteturais para reconhecer o SaaS multi-escritório, sem habilitar chamadas externas.
2. Criar preflight de unicidade/consistência de `office_id`, memberships, objetos seguros e backups; executar restore drill antes de dados fiscais reais.
3. Adicionar plano de controle global, administração separada e ciclo de vida do tenant, mantendo todos os escritórios existentes ativos por migração determinística.
4. Criar contrato SERPRO global e carregar credenciais pelo cofre, sem rota de recuperação; validar mTLS/OAuth2 no trial/mock.
5. Criar onboarding de Autor do Pedido, Termo XML e procurações; testar assinatura A1/A3 e renovação do token.
6. Implementar ledger de consumo e rodá-lo em shadow mode no trial, sem gerar cobrança ao tenant.
7. Implementar núcleo fiscal e adapters somente leitura, iniciando por eventos, procurações e um subconjunto de consultas.
8. Executar piloto produtivo restrito a um escritório, poucos contribuintes e orçamento baixo; comparar ledger com relatório SERPRO.
9. Liberar dashboards e consultas por coortes, mantendo kill switches e observabilidade.
10. Liberar guias assistidas e, somente após aprovação específica, operações mutantes por serviço.

Rollback: flags globais e por tenant interromperão novos jobs; filas poderão ser drenadas sem apagar snapshots, evidências ou ledger. Credenciais poderão ser desativadas/revogadas mantendo metadados de auditoria. Migrations serão aditivas no início; remoção de premissas do modo single-office ocorrerá apenas depois de piloto e backup verificável.

## Open Questions

- O contrato eletrônico vigente permite expressamente o uso como insumo de SaaS multi-tenant e a cobrança de franquia/excedente aos escritórios?
- O mesmo Termo XML assinado e ainda vigente pode ser reapresentado depois de expirar o `autenticar_procurador_token`, ou é necessária nova assinatura/data?
- Qual relatório oficial de consumo/faturamento pode ser obtido para conciliação automática e qual é sua granularidade?
- Quais chamadas com erro, cache ou resposta vazia são faturáveis em cada solução?
- Haverá API oficial futura para FGTS Digital que altere a cobertura parcial desta change?
- A primeira versão comercial exigirá troca de escritório por usuário com múltiplas memberships ou um usuário permanecerá vinculado a um único tenant?
