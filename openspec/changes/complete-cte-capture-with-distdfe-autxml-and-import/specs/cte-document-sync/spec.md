## MODIFIED Requirements

### Requirement: Captura de CT-e no catálogo
O sistema SHALL capturar documentos CT-e modelo 57 e eventos relevantes via `CTeDistribuicaoDFe` / `cteDistDFeInteresse`, autenticado com o A1 da raiz do cliente, quando o CNPJ completo consultado constar como remetente, destinatário, expedidor, recebedor ou tomador. O sistema SHALL preservar os bytes no vault, projetar `kind=CTE` e `source=SEFAZ` e MUST NOT promover como saída um CT-e principal cujo `emit/CNPJ` seja o próprio CNPJ consultado.

#### Scenario: CT-e recebido como tomador
- **WHEN** um `cteProc` bem-formado identifica o estabelecimento consultado como tomador e outro CNPJ como emitente
- **THEN** o original fica imutável no vault e o catálogo cria interesse `TAKER/IN` com número, partes e valor quando parseados

#### Scenario: CT-e recebido como expedidor
- **WHEN** o estabelecimento consultado aparece em `exped` e outro CNPJ aparece em `emit`
- **THEN** o sistema cria interesse `EXPEDITOR/IN` e disponibiliza o XML completo sem exigir manifestação

#### Scenario: CT-e do próprio emitente
- **WHEN** `emit/CNPJ` é igual ao CNPJ consultado no canal do cliente
- **THEN** o sistema não cria interesse `ISSUER/OUT`, preserva a anomalia em quarentena e orienta `autXML`, import ou entrega do emissor

### Requirement: Cursor e limites do canal CT-e
O sistema SHALL manter cursor independente por estabelecimento, ambiente e canal CT-e, SHALL usar o `ultNSU` retornado como autoridade depois da persistência integral da página e SHALL aplicar lock, rate limit, limite de páginas, quiet de pelo menos uma hora após fila vazia e circuito após `cStat=656`. `consNSU` MUST ser restrito a reparo de NSU conhecido e MUST NOT ser usado como varredura.

#### Scenario: Cursor CT-e isolado
- **WHEN** o NSU/cursor de NF-e DistDFe ou ADN avança
- **THEN** o cursor do canal CT-e não é alterado

#### Scenario: Página com até 50 documentos
- **WHEN** o serviço retorna `cStat=138`, `ultNSU`, `maxNSU` e `docZip`
- **THEN** o sistema persiste todos os itens antes de confirmar o novo `ultNSU`

#### Scenario: Fila vazia
- **WHEN** o serviço retorna `cStat=137` ou `ultNSU=maxNSU`
- **THEN** o cursor é preservado e a próxima consulta ocorre depois de no mínimo uma hora

#### Scenario: Consumo indevido
- **WHEN** o serviço retorna `cStat=656`
- **THEN** o sistema bloqueia novas chamadas daquele CNPJ-base e ambiente pelo período configurado sem avançar cursor

### Requirement: Parse tolerante de leiaute CT-e
O sistema SHALL extrair chave, protocolo, emitente, remetente, destinatário, expedidor, recebedor, tomador e `autXML`, SHALL criar somente papéis comprovados por igualdade exata do CNPJ completo e, em schema desconhecido, SHALL preservar o XML e marcar revisão sem pular o identificador de distribuição. O sistema MUST NOT assumir `TAKER` quando nenhum papel for encontrado.

#### Scenario: Todos os papéis conhecidos
- **WHEN** um CT-e contém identidades distintas em `rem`, `dest`, `exped`, `receb` e tomador
- **THEN** o parser produz os cinco papéis explicitamente e o projetor cria apenas os interesses pertencentes ao escritório atual

#### Scenario: Papel ausente ou ambíguo
- **WHEN** o CNPJ consultado não corresponde exatamente a nenhum papel elegível
- **THEN** o item é preservado em quarentena e nenhuma direção fiscal é inventada

#### Scenario: Schema CT-e desconhecido
- **WHEN** o lote contém XML bem-formado com schema ainda não mapeado
- **THEN** o XML original é persistido com `parse_status=REVIEW` e o NSU só avança depois desse destino durável

## ADDED Requirements

### Requirement: Eventos CT-e imutáveis e vinculados
O sistema SHALL preservar eventos CT-e distribuíveis como objetos imutáveis, vinculá-los por chave, tipo e sequência e atualizar somente projeções derivadas. Evento recebido antes do documento pai SHALL permanecer em quarentena resolvível sem ser descartado.

#### Scenario: Cancelamento após autorização
- **WHEN** um `procEventoCTe` de cancelamento protocolado referencia CT-e existente
- **THEN** o evento é preservado separadamente e a situação projetada do CT-e passa a cancelada

#### Scenario: Evento sem documento pai
- **WHEN** o evento íntegro chega antes de o CT-e principal estar disponível
- **THEN** o sistema conserva o evento e sua chave para reconciliação posterior sem criar documento principal sintético

