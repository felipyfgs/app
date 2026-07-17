## ADDED Requirements

### Requirement: Grade DCTFWeb fiel à referência
O sistema SHALL renderizar a cápsula DCTFWeb com exatamente oito colunas, nesta ordem: Situação, Últ. Declaração, Ações, Enviar, Cliente, Rastreio de envio, Última Busca e Histórico de Busca, sem seleção ou coluna adicional.

#### Scenario: Estrutura da grade
- **WHEN** o usuário abre `/monitoring/dctfweb` na cápsula DCTFWeb
- **THEN** a tabela apresenta as oito colunas na ordem normativa, com Cliente em duas linhas e scroll horizontal quando necessário

#### Scenario: Cápsula MIT independente
- **WHEN** o usuário alterna para MIT
- **THEN** o sistema usa contrato e renderer MIT próprios e não reutiliza as colunas DCTFWeb

### Requirement: Indicadores operacionais acessíveis
O sistema SHALL exibir badges retangulares para Situação e Últ. Declaração, botões compactos com nomes acessíveis para Ações/Rastreio/Histórico e switch Enviar compatível com o papel do usuário.

#### Scenario: Linha com declaração e histórico
- **WHEN** uma linha possui declaração, rastreio e histórico locais
- **THEN** a UI mostra competência `MM/AAAA`, razão social e CNPJ mascarado, data `DD/MM/AAAA` e habilita os respectivos botões

#### Scenario: Linha sem dados locais
- **WHEN** uma linha não possui declaração, rastreio ou histórico
- **THEN** a UI mostra `–` em badge neutro e desabilita ações sem destino local

### Requirement: Consulta oficial única
O sistema SHALL usar `dctfweb.consrecibo` (`DCTFWEB/CONSRECIBO32/1.0`) com `categoria`, `anoPA` e `mesPA`, categoria principal `GERAL_MENSAL` (`40`) e uma única chamada agendada por cliente e competência congelada.

#### Scenario: Execução mensal
- **WHEN** o scheduler cria uma execução DCTFWeb
- **THEN** o `progress` preserva o PA esperado no fuso do escritório e o adapter envia somente a consulta de recibo com payload oficial

#### Scenario: Falha da consulta
- **WHEN** `CONSRECIBO32` falha
- **THEN** a execução termina sem fallback automático para relatório, XML, DARF ou transmissão

### Requirement: Documento DCTFWeb protegido
O sistema SHALL decodificar `PDFByteArrayBase64` de forma estrita, limitar o documento a 10 MiB, exigir assinatura PDF e armazenar os bytes no `SecureObjectStore` antes de persistir metadados. O sistema MUST NOT gravar Base64 em banco, log ou API.

#### Scenario: PDF válido
- **WHEN** uma resposta produtiva contém PDF válido
- **THEN** o sistema versiona o artefato e persiste somente descritores, hash e metadados sanitizados

#### Scenario: Documento inválido ou excessivo
- **WHEN** Base64, tamanho ou assinatura são inválidos
- **THEN** o sistema rejeita o artefato, mantém estado `UNVERIFIED` e não grava Base64 em banco, log ou API

### Requirement: Estado declaratório fail-closed
O sistema SHALL projetar `CURRENT`, `NO_MOVEMENT_VALID`, `DUE_WITHIN_DEADLINE`, `OVERDUE_NOT_FOUND` ou `UNVERIFIED` sem promover atraso a partir de ausência não verificada.

#### Scenario: Declaração encontrada
- **WHEN** uma consulta produtiva retorna PDF válido para o PA
- **THEN** o estado é `CURRENT`, ou `NO_MOVEMENT_VALID` quando o parser confirma sem movimento

#### Scenario: Ausência dentro do prazo
- **WHEN** a ausência é produtiva, a obrigação é aplicável e o prazo ainda não venceu
- **THEN** o estado é `DUE_WITHIN_DEADLINE`

#### Scenario: Ausência após prazo verificado
- **WHEN** a ausência é produtiva após o último dia útil, a obrigação é aplicável e o calendário oficial está verificado
- **THEN** o estado é `OVERDUE_NOT_FOUND`

#### Scenario: Evidência insuficiente
- **WHEN** a resposta é simulada, falha ou é ambígua, ou obrigação/calendário não estão confirmados
- **THEN** o estado é `UNVERIFIED`

#### Scenario: Sem movimento persistente
- **WHEN** existe declaração anterior válida sem movimento e não há evidência de retomada
- **THEN** o sistema preserva `NO_MOVEMENT_VALID` sem inventar nova declaração mensal

### Requirement: Histórico e downloads estritamente locais
O sistema SHALL fornecer histórico tenant-scoped com categorias, observações, declarações e descritores de documentos, sem realizar chamada SERPRO ao abrir a UI.

#### Scenario: Abrir histórico
- **WHEN** o usuário abre Histórico de Busca, Rastreio ou menu da linha
- **THEN** os dados são lidos localmente e a resposta declara `serpro_called: false`

#### Scenario: Download autorizado
- **WHEN** o usuário solicita documento pertencente ao escritório atual
- **THEN** o backend lê o cofre com autorização e entrega o arquivo sem expor caminho interno

#### Scenario: Acesso cruzado
- **WHEN** usuário tenta acessar cliente ou artefato de outro escritório ou envia `office_id`
- **THEN** o sistema responde 404/422/403 sem revelar dados do outro tenant

### Requirement: Comunicação DCTFWeb somente template
O sistema SHALL isolar preferências e rastreio em `module_key=dctfweb`, `submodule_key=dctfweb`, com `execution_mode=TEMPLATE_ONLY` e `automatic_effective=false`.

#### Scenario: Alterar Enviar
- **WHEN** `ADMIN` ou `OPERATOR` altera o switch com versão corrente
- **THEN** o sistema persiste apenas a intenção e incrementa `lock_version`, sem provider, fila, mensagem ou evento externo

#### Scenario: Usuário somente leitura
- **WHEN** `VIEWER` visualiza ou tenta alterar Enviar
- **THEN** o switch aparece desabilitado e a API de escrita responde 403

### Requirement: Monitoramento não oferece mutações fiscais
O sistema MUST NOT oferecer na grade DCTFWeb ações de transmitir declaração, encerrar MIT ou gerar DARF.

#### Scenario: Menu da linha
- **WHEN** usuário autorizado abre Ações
- **THEN** o menu contém apenas navegação, configuração e documentos locais, sem ações fiscais mutantes
