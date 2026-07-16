# nfe-autxml-office-distribution

## Purpose

Especificação `nfe-autxml-office-distribution` (sync change).

## Requirements

### Requirement: Escopo automático restrito à NF-e modelo 55
O canal `NFE_AUTXML_DISTDFE` MUST consumir exclusivamente NF-e modelo 55 do Ambiente Nacional em que o CNPJ completo canônico do escritório tenha sido incluído pelo emitente no grupo `autXML` antes da autorização. O sistema MUST NOT inserir ou alterar `autXML`, editar XML autorizado, prometer captura de NFC-e modelo 65 por esse canal ou usar uma rejeição como mecanismo de descoberta de documentos.

#### Scenario: NF-e 55 com autorização do escritório
- **WHEN** o Ambiente Nacional distribui uma NF-e modelo 55 na qual o escritório é terceiro autorizado em `autXML`
- **THEN** o sistema aceita o documento para validação, persistência e roteamento interno

#### Scenario: Emitente ainda não configurou autXML
- **WHEN** o cliente emite NF-e sem o CNPJ completo do escritório em `autXML`
- **THEN** o sistema não promete recuperar essa NF-e pelo stream e orienta o uso do import XML ou ZIP para o histórico ausente

#### Scenario: NFC-e modelo 65
- **WHEN** uma consulta pontual é feita com chave de modelo 65 ou um payload modelo 65 aparece indevidamente no stream
- **THEN** o sistema trata a RV 618 ou o payload como fora do contrato do canal, não o cataloga por essa origem e direciona NFC-e ao import XML ou ZIP ou a outro canal explicitamente contratado

#### Scenario: Tentativa de alteração retroativa
- **WHEN** um usuário solicita adicionar o escritório a uma NF-e já autorizada
- **THEN** o sistema rejeita a operação e informa que o conteúdo assinado não pode ser alterado depois da autorização

### Requirement: Ativação sem promessa de NSU retroativo
O sistema MUST ativar a geração de NSU do CNPJ-base do escritório por uma primeira chamada `distNSU` antes de considerar o onboarding concluído. Para novo usuário, o sistema MUST tratar o primeiro `cStat=137` como ativação esperada, registrar o instante de ativação, aguardar no mínimo uma hora antes da próxima chamada e MUST NOT afirmar que documentos anteriores ao primeiro acesso serão recuperados. A janela oficial de até 90 dias MUST ser tratada como retenção de documentos ou NSUs já disponíveis, não como criação retroativa de NSU.

#### Scenario: Primeiro acesso de novo usuário
- **WHEN** o escritório executa `distNSU` pela primeira vez e recebe `cStat=137`
- **THEN** o sistema registra o stream como ativado e sem histórico garantido, define a próxima tentativa para depois de uma hora e não classifica a resposta como falha

#### Scenario: Cliente configurado somente após ativação
- **WHEN** a ativação e a primeira espera obrigatória foram concluídas com saúde operacional
- **THEN** o sistema permite marcar o onboarding como pronto para que os emitentes passem a incluir o CNPJ completo canônico do escritório em `autXML`

#### Scenario: Solicitação de histórico anterior à ativação
- **WHEN** um operador solicita documentos emitidos antes do primeiro acesso `distNSU`
- **THEN** o sistema informa que não há garantia de NSU retroativo e oferece importação XML ou ZIP como fallback de cobertura

### Requirement: Continuidade da geração de NSU
O sistema MUST manter chamadas regulares de `distNSU` e MUST NOT permitir que o CNPJ-base fique 60 dias ou mais sem uso do serviço. Se houver inatividade superior a 60 dias, o sistema MUST assumir que a geração de NSU foi interrompida, marcar uma lacuna não recuperável em massa pelo stream e exigir reconciliação por import XML ou ZIP, sem avançar artificialmente o cursor.

#### Scenario: Heartbeat sem documentos
- **WHEN** o stream está alcançado e não há novos documentos
- **THEN** o Scheduler mantém o heartbeat em intervalo estritamente inferior a 60 dias, respeitando a espera mínima de uma hora após `cStat=137`

#### Scenario: Inatividade superior a 60 dias
- **WHEN** o intervalo desde o último uso bem-sucedido de `distNSU` excede 60 dias
- **THEN** o sistema marca `NSU_GENERATION_GAP`, retoma a geração somente a partir da próxima ativação e não promete recuperar o período interrompido

#### Scenario: Reinício após inatividade
- **WHEN** a primeira consulta depois de mais de 60 dias retorna `cStat=137`
- **THEN** o sistema aguarda uma hora, mantém explícita a lacuna histórica e passa a aceitar somente NSUs gerados após a retomada

### Requirement: Stream único por raiz fiscal do escritório
O sistema MUST manter um único stream, cursor, lock e proprietário de consumo por combinação de `office_id`, raiz do CNPJ interessado, ambiente e canal, conservando também um único `query_cnpj` completo canônico para montar a requisição. Duas filiais da mesma raiz MUST NOT criar cursores independentes, pois a continuidade de geração é controlada oficialmente por CNPJ-base.

#### Scenario: Criação do cursor canônico
- **WHEN** o canal é ativado para um CNPJ completo do escritório
- **THEN** o sistema cria ou reutiliza o cursor da chave `office_id + cnpj_base + ambiente + canal` e grava o CNPJ completo canônico da requisição

#### Scenario: Segunda configuração da mesma raiz
- **WHEN** alguém tenta criar outro stream com CNPJ completo diferente, mas da mesma raiz, ambiente e canal
- **THEN** o sistema impede o segundo cursor e exige alteração controlada do `query_cnpj` no stream já existente

#### Scenario: Consumidores internos concorrentes
- **WHEN** dois workers tentam consultar o mesmo stream simultaneamente
- **THEN** somente o detentor do lock exclusivo executa a chamada e o outro reprograma seu trabalho sem consultar o Ambiente Nacional

#### Scenario: Consumidor externo detectado
- **WHEN** o retorno ou a sequência indica que outro software está consumindo o mesmo CNPJ-base
- **THEN** o sistema bloqueia o stream como `EXTERNAL_CONSUMER_CONFLICT`, preserva o cursor local e exige reconciliação controlada em vez de adotar cegamente um NSU externo

### Requirement: Consumo sequencial e persistência antes do avanço
O sistema MUST consultar sempre a partir do `ultNSU` persistido e processar os documentos em ordem crescente. Uma página inteira MUST ser persistida de forma idempotente — como documento válido, duplicata comprovada ou item duravelmente quarentenado — antes de o cursor avançar para o `ultNSU` retornado; nenhuma falha parcial pode produzir salto silencioso de NSU.

#### Scenario: Página localizada com cStat 138
- **WHEN** o serviço retorna `cStat=138`, `ultNSU`, `maxNSU` e até 50 `docZip`
- **THEN** o sistema valida e persiste todos os itens e somente então confirma atomicamente o novo cursor

#### Scenario: Nenhum documento com cStat 137
- **WHEN** o serviço retorna `cStat=137` ou `ultNSU` igual a `maxNSU`
- **THEN** o sistema marca o stream como alcançado e agenda a próxima consulta para não menos de uma hora

#### Scenario: Falha antes da persistência completa
- **WHEN** banco, cofre, armazenamento ou projeção falha antes de todos os itens da página terem destino durável
- **THEN** o sistema reverte ou mantém pendente o avanço e repete a mesma página de forma idempotente

#### Scenario: NSU repetido
- **WHEN** um NSU já persistido é recebido novamente
- **THEN** o sistema confirma a identidade do conteúdo, registra a repetição como idempotente e não duplica documento, evento ou interesse

### Requirement: Decodificação íntegra de docZip
O sistema MUST decodificar Base64 e GZip de cada `docZip`, respeitar o atributo `schema` e preservar os bytes XML descompactados com SHA-256. Falha de Base64, GZip ou ausência de bytes recuperáveis MUST impedir o avanço da página; após cinco falhas consecutivas para o mesmo stream e NSU, o sistema MUST bloquear o stream para intervenção, sem descartar ou pular o NSU.

#### Scenario: procNFe íntegro
- **WHEN** um `docZip` Base64/GZip válido declara schema `procNFe`
- **THEN** o sistema preserva os bytes, calcula SHA-256 e encaminha o XML integral para validação fiscal

#### Scenario: Evento íntegro
- **WHEN** um `docZip` válido declara schema de evento distribuível, como `procEventoNFe`
- **THEN** o sistema preserva o evento e o relaciona ao documento pela chave de acesso sem substituir os bytes imutáveis da NF-e

#### Scenario: Base64 ou GZip inválido
- **WHEN** a decodificação de qualquer item da página falha
- **THEN** o sistema não avança o cursor, registra tentativa sanitizada e reprograma o mesmo NSU

#### Scenario: Quinta falha consecutiva de decodificação
- **WHEN** o mesmo ponto de consumo acumula cinco falhas consecutivas sem obter bytes XML íntegros
- **THEN** o sistema bloqueia o stream, alerta a operação e mantém o cursor no ponto anterior

### Requirement: XML integral para terceiro autXML
O sistema MUST tratar o escritório informado em `autXML` como terceiro autorizado, para o qual o Ambiente Nacional distribui a NF-e modelo 55 integral e os eventos permitidos, sem exigir manifestação do destinatário. O documento canônico somente SHALL ser considerado capturado quando contiver NF-e assinada e protocolo de autorização coerente; resumo `resNFe` não substitui `procNFe` para essa finalidade.

#### Scenario: Recebimento de NF-e processada
- **WHEN** o stream entrega `procNFe` com assinatura, chave e protocolo coerentes e situação autorizada
- **THEN** o sistema preserva o XML integral como documento canônico e registra a origem `AUTXML_DIST_NSU`

#### Scenario: Recebimento de evento posterior
- **WHEN** o stream entrega cancelamento, Carta de Correção ou outro evento distribuível de uma NF-e já conhecida
- **THEN** o sistema persiste o evento de forma imutável e atualiza apenas a projeção de situação correspondente

#### Scenario: Apenas resumo recebido como terceiro
- **WHEN** o payload para o papel `autXML` contém somente `resNFe`
- **THEN** o sistema não o contabiliza como XML integral capturado e o coloca em quarentena como anomalia de papel ou schema

### Requirement: Escritório simultaneamente destinatário
Se o mesmo CNPJ autorizado em `autXML` também for destinatário da NF-e, o sistema MUST reconhecer que o Ambiente Nacional pode não gerar o NSU integral até a manifestação do destinatário. O canal do escritório MUST NOT emitir Ciência da Operação nem qualquer manifestação para liberar o XML e MUST oferecer import XML ou ZIP para cobrir o documento.

#### Scenario: Documento conhecido com escritório também destinatário
- **WHEN** uma evidência importada ou uma consulta diagnóstica mostra que o CNPJ do escritório é simultaneamente terceiro `autXML` e destinatário
- **THEN** o sistema classifica a ausência do XML integral como dependente de manifestação, sem declarar perda definitiva ou disparar evento fiscal

#### Scenario: Tentativa de manifestação automática
- **WHEN** o fluxo de captura solicita manifestar a NF-e para liberar o download
- **THEN** o sistema rejeita a ação por incompatibilidade de finalidade e mantém o fallback por arquivo

### Requirement: Tratamento explícito das respostas oficiais
O sistema MUST mapear respostas oficiais sem tentar contorná-las: RV 593 bloqueia a credencial por divergência de CNPJ-base; eventual RV 618 confirma que o serviço não atende modelo diferente de 55; e RV 656 exige suspensão completa das consultas do CNPJ-base pelo período oficial. O canal MUST NOT executar `consChNFe`/`consNSU` como varredura, descoberta ou backfill, nem tentar outro certificado para contornar ausência de autorização.

#### Scenario: cStat 593
- **WHEN** o serviço informa que o CNPJ-base consultado difere do CNPJ-base do certificado
- **THEN** o sistema bloqueia o canal como configuração inválida, não avança cursor e exige correção da identidade ou credencial

#### Scenario: cStat 618
- **WHEN** o serviço rejeita uma chave ou payload por modelo diferente de 55
- **THEN** o sistema encerra a tentativa como fora do escopo autXML, não tenta usar o A1 do cliente e direciona a cobertura por arquivo

#### Scenario: cStat 656
- **WHEN** qualquer modalidade de consulta retorna consumo indevido
- **THEN** o sistema suspende todas as consultas daquele CNPJ-base e ambiente por pelo menos uma hora contada da tentativa mais recente, sem avançar cursor

#### Scenario: Nova tentativa antes do desbloqueio
- **WHEN** um worker tenta consultar antes de completar a hora de suspensão por cStat 656
- **THEN** o Scheduler impede a chamada para não reiniciar a contagem oficial de bloqueio

### Requirement: Roteamento por emitente e quarentena por escritório
Cada NF-e integral recebida MUST ser vinculada pelo CNPJ completo do emitente a um estabelecimento ativo do mesmo `office_id` do stream. Documento sem vínculo único, com modelo, ambiente, direção, chave, assinatura, protocolo ou schema incompatível MUST ser preservado em quarentena privada e criptografada, sem entrar no catálogo de cliente e sem ser exposto a outro escritório.

#### Scenario: Emitente vinculado univocamente
- **WHEN** `emit/CNPJ` corresponde a exatamente um estabelecimento ativo do escritório e `ide/tpNF=1`
- **THEN** o sistema cria o interesse de saída desse estabelecimento e projeta o documento no catálogo do escritório

#### Scenario: Emitente desconhecido ou ambíguo
- **WHEN** nenhum estabelecimento ou mais de um vínculo elegível corresponde ao CNPJ completo do emitente
- **THEN** o sistema guarda bytes, NSU, hash e motivo em quarentena do próprio escritório e não cria interesse fiscal até resolução autorizada

#### Scenario: Documento de outro office
- **WHEN** o emitente pertence a estabelecimento cadastrado em outro `office_id`
- **THEN** o sistema MUST NOT usar esse cadastro para rotear o documento e mantém a ocorrência isolada na quarentena do escritório dono do stream

#### Scenario: Anomalia persistível na página
- **WHEN** o XML foi decodificado e preservado, mas falha em validação de schema, assinatura, protocolo, modelo, ambiente ou direção
- **THEN** o sistema registra quarentena durável e pode considerar esse item persistido para concluir atomicamente a página, sem catalogá-lo

### Requirement: Idempotência e proveniência imutável
O sistema MUST impor unicidade de NSU dentro do stream e de chave e tipo no repositório fiscal, preservar bytes originais e SHA-256 e registrar a aquisição `AUTXML_DIST_NSU` sem sobrescrever documento canônico já obtido por outra origem. Divergência de bytes para a mesma identidade fiscal MUST gerar conflito e quarentena.

#### Scenario: Documento já importado manualmente
- **WHEN** a mesma chave e os mesmos bytes já existem por `MANUAL_XML` ou `MANUAL_ZIP`
- **THEN** o sistema reutiliza o documento canônico e acrescenta a proveniência `AUTXML_DIST_NSU` sem duplicar o XML

#### Scenario: Mesma chave com bytes divergentes
- **WHEN** o stream entrega conteúdo cujo identificador fiscal coincide com documento existente, mas o SHA-256 diverge
- **THEN** o sistema não sobrescreve nenhum conteúdo, cria conflito auditável e mantém o novo payload em quarentena

### Requirement: Observabilidade sem conteúdo sensível
O sistema MUST expor saúde, ativação, CNPJ canônico mascarável, ambiente, `ultNSU`, `maxNSU`, atraso, bloqueio, códigos oficiais, contagens e motivos de quarentena sanitizados, mas MUST NOT expor PFX, senha, chave privada, PEM, XML bruto ou dados fiscais integrais em logs, métricas, auditoria ou respostas comuns de saúde.

#### Scenario: Consulta de saúde do stream
- **WHEN** um usuário autorizado consulta o painel operacional
- **THEN** o sistema apresenta estado e metadados necessários para ação dentro do `office_id`, sem segredo ou XML bruto

#### Scenario: Alerta de erro oficial
- **WHEN** ocorre 593, 618, 640, 641, 656, lacuna superior a 60 dias ou bloqueio de decodificação
- **THEN** o sistema emite alerta com código, stream e ação recomendada, sem certificado, senha ou payload fiscal
