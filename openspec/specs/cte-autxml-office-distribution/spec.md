# cte-autxml-office-distribution

## Purpose

Especificação `cte-autxml-office-distribution` (sync change).

## Requirements

### Requirement: Captura CT-e como terceiro autorizado
O sistema SHALL consultar `CTeDistribuicaoDFe` pelo canal `CTE_AUTXML_DISTDFE` usando o CNPJ completo canônico e o A1 ativo do escritório, ambos pertencentes à mesma base fiscal, e SHALL aceitar CT-e modelo 57 somente quando o CNPJ do escritório estiver presente no grupo `autXML`. O sistema MUST NOT usar credencial de cliente nesse canal nem inserir ou alterar `autXML`.

#### Scenario: CT-e emitido por cliente com autXML
- **WHEN** o Ambiente Nacional distribui `cteProc` modelo 57 cujo emitente é estabelecimento ativo do escritório e cujo `autXML` contém o CNPJ completo consultado
- **THEN** o sistema valida, preserva e projeta o documento como saída do cliente emitente com aquisição `CTE_AUTXML_DIST_NSU`

#### Scenario: Escritório ausente de autXML
- **WHEN** o payload não contém o CNPJ completo canônico do escritório em `autXML`
- **THEN** o sistema não atribui o documento a cliente, preserva-o em quarentena do escritório e não afirma autorização de acesso

#### Scenario: Credencial do cliente no canal do escritório
- **WHEN** um job tenta executar `CTE_AUTXML_DISTDFE` com A1 pertencente a cliente
- **THEN** o sistema bloqueia a execução antes da chamada, não expõe o material do certificado e registra configuração inválida sanitizada

### Requirement: Stream único do escritório por CNPJ-base
O sistema MUST manter um único cursor, lock e proprietário de consumo por `office_id`, CNPJ-base interessado, ambiente e canal `CTE_AUTXML_DISTDFE`, conservando o CNPJ completo canônico usado no pedido. Filiais da mesma raiz MUST NOT criar sequências independentes.

#### Scenario: Ativação do stream
- **WHEN** ADMIN com 2FA recente habilita o canal para a identidade fiscal do escritório
- **THEN** o sistema cria ou reutiliza o cursor canônico sem zerar NSU já confirmado

#### Scenario: Dois workers concorrentes
- **WHEN** dois workers tentam consultar o mesmo stream simultaneamente
- **THEN** somente o detentor do lock executa a chamada e o outro reprograma sem consumir o serviço

#### Scenario: Segunda identidade da mesma raiz
- **WHEN** alguém tenta abrir outro cursor no mesmo ambiente com CNPJ completo diferente da mesma raiz
- **THEN** o sistema impede o segundo stream e exige alteração controlada da identidade canônica existente

### Requirement: Persistência integral antes do avanço do NSU
Uma página `cStat=138` do stream `CTE_AUTXML_DISTDFE` MUST ter todos os `docZip` decodificados e destinados de forma durável como documento, duplicata comprovada, evento ou quarentena antes de o cursor avançar para o `ultNSU` retornado. Falha Base64, GZip, cofre ou transação MUST impedir o avanço, e cinco falhas consecutivas no mesmo ponto MUST bloquear o stream.

#### Scenario: Página válida
- **WHEN** todos os itens de uma página são preservados e a transação conclui
- **THEN** o cursor avança atomicamente para o `ultNSU` da resposta e conserva `maxNSU`

#### Scenario: Item sem cliente conhecido
- **WHEN** o XML é íntegro, mas `emit/CNPJ` não corresponde univocamente a estabelecimento do escritório
- **THEN** os bytes ficam em quarentena resolvível no mesmo commit e o cursor pode avançar sem atribuição indevida

#### Scenario: Falha de decode
- **WHEN** qualquer `docZip` não pode ser recuperado por Base64 e GZip
- **THEN** a página não avança, a falha é registrada sem payload e o mesmo ponto é reagendado

### Requirement: Tratamento conservador de fila e consumo indevido
O canal SHALL usar `distNSU` para consumo sequencial, SHALL aguardar no mínimo uma hora após `cStat=137` ou fila alcançada e MUST abrir circuito por pelo menos uma hora após `cStat=656`, contado da tentativa mais recente. `consNSU` SHALL ser permitido apenas para NSU conhecido em reparo; `consChCTe` e varredura de NSU MUST NOT existir.

#### Scenario: Fila alcançada
- **WHEN** a resposta informa `cStat=137` ou `ultNSU` igual a `maxNSU`
- **THEN** o sistema preserva o cursor e agenda a próxima chamada para não menos de uma hora

#### Scenario: Consumo indevido
- **WHEN** o serviço retorna `cStat=656`
- **THEN** todas as chamadas CT-e daquele CNPJ-base e ambiente ficam suspensas até o fim do circuito

#### Scenario: Reparo pontual
- **WHEN** existe NSU conhecido, ausente e elegível para reconciliação
- **THEN** o sistema pode usar `consNSU` sob orçamento conservador e registra a tentativa sem alterar o cursor sequencial antes da persistência

### Requirement: Roteamento por emitente e interesses adicionais
Cada CT-e aceito pelo canal do escritório MUST ser associado pelo CNPJ completo de `emit` a estabelecimento ativo do mesmo `office_id` e SHALL criar interesse `ISSUER/OUT`. Outros estabelecimentos do escritório que apareçam como remetente, destinatário, expedidor, recebedor ou tomador SHALL receber interesses adicionais `IN`, sem duplicação do documento canônico.

#### Scenario: Emitente e tomador pertencem ao escritório
- **WHEN** o emitente corresponde ao cliente A e o tomador corresponde ao cliente B do mesmo escritório
- **THEN** um único documento é preservado com interesse `ISSUER/OUT` para A e `TAKER/IN` para B

#### Scenario: Emitente de outro escritório
- **WHEN** `emit/CNPJ` corresponde somente a cadastro pertencente a outro `office_id`
- **THEN** esse cadastro não é usado, nenhum dado do outro escritório é revelado e o item permanece isolado no office dono do stream

### Requirement: Qualidade da cópia autXML
O sistema MUST preservar os bytes recebidos do Ambiente Nacional e SHALL classificar a aquisição como `AUTXML_ORIGINAL` quando íntegra e sem redação ou `AUTXML_REDACTED` quando chaves relacionadas nos grupos oficiais vierem substituídas por 44 noves. O sistema MUST NOT reconstruir referências, sobrescrever um original existente nem apresentar cópia redigida como byte a byte igual ao XML do emitente.

#### Scenario: Referências substituídas oficialmente
- **WHEN** o CT-e recebido diretamente pelo canal oficial contém `99999999999999999999999999999999999999999999` nas referências previstas para terceiro `autXML`
- **THEN** o sistema preserva os bytes, registra `AUTXML_REDACTED` e exibe a limitação no catálogo

#### Scenario: Original já importado
- **WHEN** a mesma chave já possui `cteProc` original importado e o stream entrega derivado redigido com hash diferente
- **THEN** o original permanece canônico e o derivado é preservado como aquisição relacionada sem substituição silenciosa

#### Scenario: Alteração incompatível
- **WHEN** a assinatura ou o conteúdo diverge sem corresponder ao padrão oficial de redação e à origem mTLS comprovada
- **THEN** o sistema coloca o artefato em quarentena e não o disponibiliza como XML fiscal capturado

### Requirement: Sem automação de portal
O sistema MUST usar somente o Web Service oficial máquina-máquina para a captura automática CT-e e MUST NOT automatizar Portal Nacional, Portal SVRS, navegador, hCaptcha ou gov.br. Recuperação humana por portal SHALL terminar em upload XML/ZIP pelo fluxo assistido, sem reutilizar sessão ou cookie do usuário.

#### Scenario: Web Service sem documento emitido
- **WHEN** um CT-e emitido não foi disponibilizado pelo stream `autXML`
- **THEN** o sistema mantém pendência de importação e não tenta portal ou CAPTCHA como fallback automático
