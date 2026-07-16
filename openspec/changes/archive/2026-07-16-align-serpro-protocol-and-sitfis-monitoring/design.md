## Context

O MonitorHub já possui contrato global SERPRO, cofre, autorização por escritório, ledger, filas, snapshots e telas fiscais. A auditoria documental identificou que o transporte implementado não representa o protocolo vigente: o OAuth usa endpoint e chave de JWT divergentes, o cliente não envia todos os headers, as URLs não usam as rotas funcionais, `pedidoDados` é montado de forma incompatível e códigos internos são tratados como coordenadas oficiais. O Termo é validado apenas por presença estrutural e vários bindings são fakes incondicionais.

Ainda não há contratação do Integra Contador nem clientes reais. A entrega deve, portanto, permitir desenvolvimento determinístico e construir o caminho real conforme a documentação oficial, mas não declarar aceitação produtiva antes dos gates externos. O contrato permanece global da software house; Autor, Termo, procurações, runs, evidências e snapshots permanecem no plano de dados com `office_id` obrigatório.

## Goals / Non-Goals

**Goals:**

- Tornar o núcleo de transporte compatível por construção com autenticação, envelope, headers e semântica de respostas oficiais.
- Separar identidade estável de domínio das coordenadas mutáveis do SERPRO.
- Disponibilizar catálogo oficial completo como inventário e SITFIS como primeira capacidade implementada.
- Garantir paridade contratual entre cliente real e simulador interno, com proveniência inequívoca.
- Preservar APIs, filas, snapshots, isolamento e telas úteis existentes.
- Corrigir validação local do Termo e impedir que validação local ou simulada seja tratada como aceite SERPRO.
- Contabilizar chamadas conforme regras oficiais sem inventar preço.

**Non-Goals:**

- Implementar os outros 97 serviços produtivos, operações mutantes ou ampliar cobertura funcional de módulos existentes.
- Executar smoke real, contratar o serviço ou liberar escala produtiva.
- Expor simulador a escritórios clientes em produção.
- Custodiar A1 do Autor, assinar Termo ou criar portal do contribuinte.
- Criar gestão comercial de escritórios, cobrança, planos ou impersonação de suporte.

## Decisions

### 1. Corrigir o núcleo atual atrás das interfaces existentes

`SerproContractAuthenticator`, `IntegraContadorClient` e os serviços de monitoramento permanecem os pontos de extensão. O cliente HTTP será corrigido em vez de criar uma versão paralela. Jobs entregarão uma `operation_key` e dados de negócio; um resolvedor de catálogo obterá rota, `idSistema`, `idServico`, `versaoSistema` e poder oficial.

Alternativas rejeitadas: cliente V2 paralelo, que permitiria uso acidental do legado, e reescrita do monitoramento, que descartaria isolamento, filas e persistência já úteis.

### 2. Modelar uma operação em duas identidades

Cada operação terá uma chave interna estável e coordenadas oficiais versionadas. O manifesto será a fonte revisável; o banco será uma projeção consultável. Os campos oficiais não serão aceitos do frontend nem construídos pelos jobs. O catálogo financeiro continuará separado, relacionado pela chave interna.

O manifesto inventariará 119 entradas e registrará estado oficial (`PRODUCTION`, `PROSPECTION`, `UNDER_CONSTRUCTION`, `CANCELED`) e estado da plataforma (`INVENTORIED`, `SIMULATED`, `IMPLEMENTED`, `PRODUCTION_VALIDATED`). Somente autenticação/representação e as duas operações SITFIS poderão avançar além de inventariado nesta change.

### 3. Selecionar driver por capacidade e falhar fechado

Um resolvedor selecionará `disabled`, `simulated` ou `real` para cada capacidade. SITFIS poderá usar o simulador apenas fora de produção. O boot/preflight de produção rejeitará `simulated`; `real` sem contrato saudável falhará fechado. Não haverá fallback automático entre drivers.

O simulador será determinístico e responderá com os mesmos DTOs e estados do cliente real. Cenários abrangerão sucesso, processamento, cache, rate limit, indisponibilidade e contrato desconhecido, sem persistir segredos ou alegar origem oficial.

### 4. Implementar o protocolo oficial como envelope tipado

O autenticador usará `https://autenticacao.sapi.serpro.gov.br/authenticate`, Basic com Consumer Key/Secret, mTLS do e-CNPJ contratante, `role-type: TERCEIROS` e processará `access_token`, `jwt_token`, tipo e expiração.

Chamadas de negócio usarão a base do Integra Contador e apenas `/Apoiar`, `/Consultar`, `/Declarar`, `/Emitir` ou `/Monitorar`. Enviarão Bearer, `jwt_token`, `autenticar_procurador_token` quando exigido e `X-Request-Tag` determinístico de até 32 caracteres. `pedidoDados.dados` será sempre string: vazia quando o serviço exigir ou JSON codificado uma única vez. Respostas preservarão status HTTP, status de negócio, mensagens, `ETag`, `Expires`, correlação e `dados` parseado, sem logar conteúdo fiscal.

### 5. Distinguir validação local do Termo e aceitação externa

O parser lerá os atributos oficiais de `sistema`, `dataAssinatura`, `vigencia`, `destinatario` e `assinadoPor`. Uma biblioteca XMLDSig dedicada verificará referência enveloped, digest SHA-256, canonicalização e assinatura RSA-SHA256 usando o X.509 embutido. OpenSSL verificará validade, uso de chave, titular e correspondência CPF/CNPJ. XSD oficial versionado será aplicado sem rede.

A validação local produz `LOCAL_VALIDATED`; somente retorno real de `AUTENTICAPROCURADOR/ENVIOXMLASSINADO81` produz `SERPRO_ACCEPTED`. O simulador produz `SIMULATED`. Cadeia ICP-Brasil e revogação permanecem sujeitas à validação definitiva do SERPRO; falha local é rejeição, indisponibilidade de prova externa nunca vira aceite.

### 6. Implementar SITFIS como fluxo assíncrono oficial

A solicitação usará `SITFIS/SOLICITARPROTOCOLO91/2.0` em `/Apoiar`, com `dados` vazio. A emissão usará `SITFIS/RELATORIOSITFIS92/2.0` em `/Emitir`, com o protocolo no JSON escapado e poder `00002`.

O estado persistido guardará protocolo protegido, fase, próxima tentativa e correlação. `tempoEspera` do corpo e do `ETag` prevalecerá sobre defaults; 202/204 reencaminharão sem busy wait; limites de polling produzirão erro operacional recuperável. Uma consulta diária será distribuída deterministicamente. A ação manual reutilizará snapshot dentro de 24 horas e informará a próxima atualização, sem opção de força nesta change.

### 7. Tornar proveniência um invariante persistido

Runs, evidências e snapshots receberão proveniência `SIMULATED`, `SERPRO_REAL` ou `UNVERIFIED` e estado de verificação. O driver define a origem no início do run e ela não pode ser promovida por payload. Registros existentes serão migrados para `UNVERIFIED`; continuam auditáveis, porém consultas de estado atual exigirão origem explícita compatível com o ambiente.

Alternativas rejeitadas: presumir legado como real, presumir todo legado como fake ou apagá-lo.

### 8. Classificar faturabilidade pela operação e pelo resultado

O ledger relacionará entradas à `operation_key`. `/Apoiar` e `/Monitorar`, simulações e respostas 204, 304, 400, 401, 404, 429, 500 e 503 serão não faturáveis. Demais respostas usarão a classe versionada da operação e permanecerão `DESCONHECIDA` quando não houver regra. Preço e custo ficam nulos até existir versão contratual; nenhuma tabela shadow será apresentada como preço real.

### 9. Evoluir as telas existentes sem criar nova arquitetura visual

Configurações preserva o arquétipo Settings; SITFIS preserva lista + detalhe derivados do template. As APIs serão enriquecidas com proveniência, verificação, idade, próxima atualização, capacidade de refresh, bloqueio e correlação sanitizada. A UI mostrará linguagem operacional, acompanhará o run sem bloquear e criará notificação interna ao concluir. Detalhes técnicos serão progressivos e nunca incluirão token, Termo ou payload bruto.

## Risks / Trade-offs

- [Documentação oficial pode mudar antes da contratação] → manifesto com fonte/data, diff verificável e contract tests por versão.
- [Cliente real não pode ser comprovado agora] → estado máximo `IMPLEMENTED`; `PRODUCTION_VALIDATED` exige change posterior com smoke real.
- [Validação local não reproduz toda a PKI do SERPRO] → separar estados e considerar o aceite externo como autoridade definitiva.
- [Manifesto completo pode sugerir cobertura inexistente] → estado oficial separado de estado da plataforma e adapters ausentes falham como não suportados.
- [Migração pode ocultar snapshots legados no estado atual] → preservar histórico e expor `UNVERIFIED` apenas em auditoria/diagnóstico.
- [Polling assíncrono pode gerar duplicidade] → idempotência por escritório, contribuinte, operação e janela; protocolo persistido e requeue.

## Migration Plan

1. Adicionar dependência XMLDSig, manifesto e testes de integridade do catálogo.
2. Criar migrations aditivas para coordenadas oficiais, `operation_key`, proveniência e verificação; preencher legado como `UNVERIFIED` sem editar migrations aplicadas.
3. Corrigir autenticador, envelope, resposta e bindings; manter capacidade SITFIS em `simulated` somente no desenvolvimento e `disabled` por padrão em produção.
4. Substituir os códigos SITFIS internos pelas coordenadas resolvidas do catálogo e habilitar scheduler/TTL.
5. Enriquecer APIs e adaptar as telas existentes.
6. Rodar contract tests, suíte completa, build e validação OpenSpec.
7. Em change futura: contratar, configurar cofre, validar Termo real, habilitar canário `real`, executar smoke e conciliar consumo.

Rollback: desabilitar a capacidade SITFIS e manter os novos campos/tabelas; migrations não apagam evidências nem reclassificam legado como real. O cliente legado não será reativado como fallback.

## Open Questions

- Qual versão comercial/preço será contratada e qual relatório oficial permitirá conciliação? Resolver somente após contratação.
- A confirmação formal do SERPRO autoriza o modelo SaaS multi-escritório e a representação de franquia/excedente? Permanece gate externo bloqueante.
