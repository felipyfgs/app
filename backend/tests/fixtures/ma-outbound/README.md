# Fixtures MA outbound (sanitizadas)

Sem certificado real, CSC real, PFX ou chave privada.

| Arquivo | Uso |
|---------|-----|
| `procNFe_55_out_ma.xml` | Semente / pacote NF-e 55 |
| `procNFe_65_out_ma.xml` | Semente / pacote NFC-e 65 |
| `consulta_562_com_chave.xml` | Descoberta por 562 |
| `consulta_562_sem_chave.xml` | Bloqueio de força bruta |
| `consulta_217.xml` | Lacuna pendente |
| `consulta_100_autorizada.xml` | Chave candidata ok |
| `consulta_656.xml` | Circuit breaker |
| `consulta_561.xml` / `613` / `526` | Resultado limitado |
| `rejeicao_539.xml` | Fallback mutante (CI fake) |
| `inutilizacao_102.xml` / `241` | Saga mutante |
| `cancelamento_101.xml` | Evento cancelamento |
| `nfe_sem_protocolo.xml` | Rejeição de semente |
