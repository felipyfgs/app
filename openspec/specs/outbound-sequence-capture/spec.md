# Outbound Sequence Capture

## Purpose

Captura e reconciliação de saídas por sequência nNF (não NSU) no MA: semente, consulta de protocolo somente leitura, estados por número, limites, circuit breaker e fallback mutante desligado por padrão.

## Requirements

### Requirement: Perfil e semente por estabelecimento, modelo e série
O sistema SHALL permitir configurar captura de saída somente para estabelecimento com UF `MA`, ambiente explícito, modelo `55` ou `65` e série extraída de XML-semente autorizado, mantendo unicidade por `office_id`, estabelecimento, ambiente, modelo e série. A semente MUST conter assinatura e protocolo verificáveis, `cUF=21`, `tpNF=1`, emitente igual ao CNPJ completo do estabelecimento e idade máxima configurada, inicialmente 60 dias.

#### Scenario: Semente NF-e válida
- **WHEN** ADMIN ou OPERATOR envia `procNFe` autorizado modelo 55, emitente MA correspondente e série ainda não configurada
- **THEN** o sistema valida assinatura, protocolo, identidade e ambiente e prepara o perfil da série sem transmitir documento à SEFAZ

#### Scenario: Semente NFC-e válida
- **WHEN** ADMIN ou OPERATOR envia `procNFe` autorizado modelo 65 do estabelecimento MA
- **THEN** o sistema registra modelo, série, `nNF`, `tpEmis` e competência como base da reconciliação, sem exigir CSC para consulta não mutante

#### Scenario: XML sem protocolo
- **WHEN** o upload contém apenas `<NFe>` sem protocolo de autorização verificável
- **THEN** o sistema rejeita seu uso como semente e não cria cursor operacional

#### Scenario: Emitente ou UF divergente
- **WHEN** a semente pertence a outro CNPJ, outra UF ou outro escritório
- **THEN** o sistema rejeita a configuração sem revelar existência de cadastro externo

#### Scenario: Ativação governada
- **WHEN** o perfil validado é ativado para produção
- **THEN** o sistema exige ADMIN com 2FA recente, referência do mandato do cliente, CNPJ na allowlist e feature flag do canal ligada

### Requirement: Descoberta não mutante por consulta de protocolo
O sistema SHALL consultar primeiro `NFeConsultaProtocolo` com chave candidata persistida e certificado A1 do emitente, MUST usar o autorizador oficial correspondente ao modelo no ambiente selecionado e MUST NOT assinar ou transmitir NF-e/NFC-e nesse fluxo. Para MA, modelo 55 usa SVAN e modelo 65 usa SVRS.

#### Scenario: cStat 562 com chave verdadeira
- **WHEN** a consulta retorna `cStat=562` com `chNFe`
- **THEN** o sistema só aceita a descoberta após validar DV, UF 21, CNPJ, modelo, série, `nNF` e `tpEmis` contra o perfil e o número pesquisado

#### Scenario: Chave candidata coincide
- **WHEN** a consulta retorna situação fiscal da própria chave candidata com protocolo suficiente
- **THEN** o sistema registra a candidata como chave descoberta e preserva o cStat/protocolo sanitizado

#### Scenario: Documento não localizado
- **WHEN** a consulta retorna `cStat=217`
- **THEN** o número permanece como lacuna pendente e recebe próximo horário de tentativa, sem ser classificado como inexistente definitivo

#### Scenario: 562 sem chave concatenada
- **WHEN** o autorizador retorna `562` sem chave verdadeira utilizável
- **THEN** o sistema registra a limitação, bloqueia novas variações de `cNF` para aquele número e MUST NOT executar força bruta

#### Scenario: Resposta divergente
- **WHEN** a chave retornada não corresponde integralmente à UF, emitente, modelo, série, número ou tipo de emissão esperados
- **THEN** o sistema bloqueia a série, não avança a posição e gera incidente operacional sanitizado

#### Scenario: CNPJ e chave alfanuméricos
- **WHEN** o estabelecimento ou a chave usa caracteres alfanuméricos permitidos pelo leiaute vigente
- **THEN** o sistema mantém os valores como texto maiúsculo, calcula DV pela versão aplicável e nunca converte identificadores em número

### Requirement: Estado durável por nNF sem semântica de NSU
O sistema MUST manter cursor dedicado por série e estado único por `office_id`, estabelecimento, ambiente, modelo, série e `nNF`; MUST NOT reutilizar `last_nsu` nem representar `nNF` como NSU. A posição só SHALL avançar após persistência atômica do resultado do número.

#### Scenario: Resultado persistido antes do avanço
- **WHEN** uma consulta encontra chave ou classifica lacuna pendente
- **THEN** estado, tentativa, candidata, resposta sanitizada e próximo passo são confirmados na mesma transação antes de atualizar `discovery_position`

#### Scenario: Timeout ambíguo
- **WHEN** a consulta termina sem resposta conclusiva
- **THEN** o sistema preserva a mesma chave candidata e `cNF`, não avança a posição e reagenda reconciliação idempotente

#### Scenario: Lacuna esgota tentativas
- **WHEN** um número completa dez tentativas espaçadas sem chave nem XML
- **THEN** passa a `EXHAUSTED_VISIBLE`, continua listado como lacuna e não é descartado ou marcado capturado

#### Scenario: Descoberta avança com recuperação pendente
- **WHEN** a chave é validada e persistida, mas o XML ainda não foi obtido
- **THEN** o estado fica `XML_PENDING`, a posição pode seguir para o próximo número e a pendência continua em fila própria

#### Scenario: Concorrência na mesma série
- **WHEN** dois jobs tentam processar o mesmo estabelecimento, ambiente, modelo e série
- **THEN** apenas um obtém o lock e o outro termina sem duplicar tentativa ou avançar posição

### Requirement: Descoberta não equivale a captura documental
O sistema MUST distinguir `KEY_DISCOVERED`, `XML_PENDING`, `XML_CAPTURED` e `COMPLETE`; MUST NOT criar documento baixável no catálogo apenas com chave ou protocolo de consulta. Toda chave descoberta SHALL ser encaminhada ao adaptador de recuperação MA e permanecer pendente até o XML original ser validado e persistido.

#### Scenario: Apenas chave disponível
- **WHEN** a consulta 562 revela a chave, mas nenhuma fonte entrega o XML completo
- **THEN** a lacuna aparece na operação como recuperação pendente e não como documento disponível para download

#### Scenario: XML completo recuperado
- **WHEN** o adaptador MA confirma persistência do XML original com assinatura e protocolo
- **THEN** o número passa a `COMPLETE` e a projeção NFE/NFCE OUT fica disponível no catálogo

### Requirement: Agendamento, limites e circuit breaker
O sistema SHALL distribuir perfis elegíveis deterministicamente, tentar lacunas no máximo a cada doze horas, limitar inicialmente cada execução a dez números e aplicar no máximo 1 rps global para MA, além de lock por série e limitador por raiz/IP. Valores MAY ser reduzidos por configuração, mas MUST NOT ser ampliados sem nova validação operacional.

#### Scenario: Execução alcança limite
- **WHEN** o job processa dez números sem bloqueio
- **THEN** encerra de modo idempotente e reprograma o restante sem loop contínuo

#### Scenario: Consumo indevido
- **WHEN** qualquer consulta retorna `cStat=656` ou bloqueio equivalente
- **THEN** o sistema bloqueia a série e o canal da raiz, interrompe novos jobs e cria alerta crítico sem retry imediato

#### Scenario: Flag desligada
- **WHEN** `SEFAZ_MA_OUTBOUND_ENABLED` ou `SEFAZ_MA_PROTOCOL_QUERY_ENABLED` está desligada
- **THEN** scheduler e ação manual MUST NOT realizar chamada externa e preservam estados existentes

### Requirement: Fallback mutante com autorização excepcional
O sistema MUST manter qualquer inutilização, transmissão 539, autorização ou cancelamento atrás de `SEFAZ_MA_MUTATING_PROBE_ENABLED=false` por padrão. Produção SHALL exigir cumulativamente parecer fiscal/jurídico vigente, mandato do cliente, ADMIN com 2FA recente, CNPJ allowlisted, série e período fechados, coordenação registrada com ERP/PDV e kill switch testado.

#### Scenario: Gate incompleto
- **WHEN** qualquer requisito de autorização mutante está ausente
- **THEN** o sistema recusa inutilização, emissão, cancelamento ou teste 539 sem materializar A1/CSC

#### Scenario: Lacuna histórica livre
- **WHEN** inutilização preventiva aprovada retorna `cStat=102`
- **THEN** o sistema registra o número inutilizado, encerra a sonda desse número e MUST NOT transmitir nota para ele

#### Scenario: Número comprovadamente usado
- **WHEN** inutilização preventiva de lacuna histórica retorna `cStat=241`
- **THEN** o sistema pode marcar `NUMBER_PROVEN_USED`, mas só poderá executar spike 539 se todos os demais gates permanecerem válidos

#### Scenario: Série ativa ou próximo número
- **WHEN** o alvo é o próximo número de uma série usada por ERP/PDV ou pertence a período não fechado
- **THEN** o sistema MUST rejeitar inutilização e sonda mutante

#### Scenario: Autorização inesperada
- **WHEN** uma transmissão experimental retorna autorização `100` ou `150`
- **THEN** o sistema persiste imediatamente XML e protocolo como documento fiscal real, aciona circuit breaker global, bloqueia a série e abre cancelamento emergencial somente quando permitido

#### Scenario: Cancelamento falho ou ambíguo
- **WHEN** o cancelamento emergencial não é confirmado por protocolo
- **THEN** o sistema mantém `FISCAL_INCIDENT`, impede qualquer novo probe e gera alerta crítico para intervenção humana

#### Scenario: CSC por modelo
- **WHEN** fallback mutante é aprovado para modelo 65
- **THEN** CSC e ID CSC do estabelecimento/ambiente são exigidos do cofre; modelo 55 MUST NOT solicitar ou usar CSC

### Requirement: Segurança, tenancy e auditoria do motor
O sistema MUST derivar `office_id` da sessão/job, obter A1 e CSC somente via `SecureObjectStore`, manter PFX e chave privada apenas em memória e registrar toda configuração, ativação, reset, consulta, descoberta, mutação e kill switch sem material sensível ou XML bruto nos logs.

#### Scenario: Office forjado
- **WHEN** uma API recebe `office_id` ou estabelecimento fora do contexto autenticado
- **THEN** o sistema ignora/rejeita o valor e não revela perfil, série, chave ou lacuna de outro escritório

#### Scenario: Consulta de configuração
- **WHEN** usuário autorizado abre o perfil de captura
- **THEN** a resposta contém somente presença/estado de A1 e CSC, nunca PFX, senha, CSC, ID secreto, PEM, chave privada ou `vault_object_id`

#### Scenario: Reset de sequência
- **WHEN** ADMIN com 2FA recente solicita reset com motivo
- **THEN** o sistema audita ator, série, posição anterior/nova e motivo, preserva todos os estados históricos e não repete mutação fiscal concluída

#### Scenario: VIEWER tenta mutar
- **WHEN** VIEWER tenta configurar, ativar, resetar, disparar consulta ou operar kill switch
- **THEN** recebe 403 e nenhum job ou alteração é criado

### Requirement: Coexistência com canais e emissores existentes
O sistema SHALL manter importação de saídas, ADN, DistDFe e captura por sequência independentes. Bloqueio deste motor MUST NOT alterar cursor NSU de outro canal, e documento já importado SHALL poder receber nova proveniência sem duplicar conteúdo no vault.

#### Scenario: Canal outbound bloqueado
- **WHEN** uma série MA entra em `BLOCKED`
- **THEN** jobs ADN e DistDFe do estabelecimento continuam conforme sua própria elegibilidade e cursores

#### Scenario: Documento previamente importado
- **WHEN** o pacote oficial contém o mesmo XML já importado manualmente
- **THEN** o vault permanece idempotente e a aquisição MA é registrada sem apagar a origem de importação

