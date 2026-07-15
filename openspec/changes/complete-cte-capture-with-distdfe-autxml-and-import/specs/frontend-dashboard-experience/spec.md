## ADDED Requirements

### Requirement: Onboarding CT-e autXML no dashboard
A interface interna SHALL apresentar em Configurações um checklist CT-e para copiar o CNPJ completo canônico do escritório, orientar sua inclusão prévia em `autXML`, mostrar o A1 do escritório apenas por metadados seguros e habilitar o canal somente para ADMIN com 2FA recente. A UI MUST NOT oferecer alteração retroativa do XML nem automação de portal.

#### Scenario: Cliente ainda não configurado
- **WHEN** o escritório não observou CT-e válido daquele emitente com `autXML`
- **THEN** a UI mostra onboarding pendente, instrução copiável e fallback XML/ZIP sem prometer histórico

#### Scenario: A1 do escritório expirado
- **WHEN** a credencial necessária está expirada ou bloqueada
- **THEN** a UI desabilita ativação, mostra ação administrativa segura e não exibe PFX, senha ou PEM

#### Scenario: VIEWER acessa Configurações
- **WHEN** VIEWER abre a página
- **THEN** os estados são somente leitura e nenhuma ação de credencial ou flag é oferecida

### Requirement: Sincronizações CT-e distinguem cliente e escritório
A página de Sincronizações SHALL exibir cards separados para `CTE_DISTDFE` do cliente e `CTE_AUTXML_DISTDFE` do escritório, com estado, ambiente, cursor, `maxNSU`, última execução, próxima execução, fila, bloqueio e cobertura. Conteúdo fiscal bruto MUST NOT ser renderizado na saúde.

#### Scenario: Fila vazia saudável
- **WHEN** um stream recebe `cStat=137` e está no quiet obrigatório
- **THEN** o card mostra “fila alcançada” e o horário da próxima consulta, não erro genérico

#### Scenario: Consumo indevido
- **WHEN** ocorre `cStat=656`
- **THEN** o card mostra circuito aberto, prazo e recomendação sem botão de retry antes do desbloqueio

### Requirement: Documentos CT-e mostram papel, origem e qualidade
Listagem e detalhe de Documentos SHALL mostrar `CTE`, cliente/estabelecimento, papéis, direção, origem e qualidade por texto e ícone acessível, incluindo aviso para `AUTXML_REDACTED`. Filtros SHALL permitir CT-e, entrada/saída, papel, origem, qualidade e estado de cobertura.

#### Scenario: Cliente expedidor
- **WHEN** o usuário visualiza CT-e emitido por terceiro em que o cliente é expedidor
- **THEN** a linha mostra `Expedidor`, `Entrada`, `CTE_DIST_NSU` e `Original`

#### Scenario: Cliente emitente por autXML
- **WHEN** a aquisição é redigida e o cliente é emitente
- **THEN** a linha mostra `Emitente`, `Saída`, `AutXML do escritório` e `Cópia oficial com referências protegidas`

### Requirement: Import e pendências CT-e integrados
ADMIN e OPERATOR SHALL poder enviar XML/ZIP de CT-e no import em massa existente, acompanhar lote/item, reprocessar estados elegíveis e resolver `PENDING_IMPORT` ou quarentena dentro do próprio escritório. VIEWER SHALL permanecer somente leitura.

#### Scenario: Lote misto
- **WHEN** o operador envia ZIP com NF-e, NFC-e e CT-e de múltiplos clientes
- **THEN** a UI acompanha resultados por entrada e destaca importado, duplicado, sem vínculo, inválido e quarentena sem perder itens válidos

#### Scenario: Pendência resolvida por upload
- **WHEN** `cteProc` original válido é importado para período `PENDING_IMPORT`
- **THEN** a cobertura é atualizada depois da conclusão backend e a interface não mantém pendência fantasma

