## ADDED Requirements

### Requirement: Preferir XML completo na entrega
O sistema SHALL, no download e na exportação de NF-e, preferir o documento `procNFe` (completo) quando existir no vault; se só houver resumo, entregar o resumo e indicar na API/UI que o full ainda não está disponível (e, se a flag permitir, apontar a ação de obter XML completo).

#### Scenario: Download com full
- **WHEN** o usuário autorizado baixa uma NF-e que tem procNFe
- **THEN** o stream é o XML completo, não o resumo

#### Scenario: Download só resumo
- **WHEN** só existe resNFe
- **THEN** o download do resumo é permitido e a resposta/UI sinaliza limitação
