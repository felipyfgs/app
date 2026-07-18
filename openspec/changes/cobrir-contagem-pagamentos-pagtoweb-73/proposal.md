## Why

O monitor de guias possui as coordenadas oficiais do `PAGTOWEB/CONTACONSDOCARRPG73`, mas ainda não oferece uma consulta de negócio dedicada para contar documentos de arrecadação pagos. Isso impede que a equipe verifique, de forma tenant-scoped e auditável, a existência de pagamentos antes de navegar para detalhes ou comprovantes.

## What Changes

- Adicionar um monitor de contagem de pagamentos para a operação oficial `pagtoweb.contaconsdocarrpg` (`PAGTOWEB` / `CONTACONSDOCARRPG73`, rota `/Consultar`, versão `1.0`).
- Validar filtros oficiais sem aceitar campos arbitrários, persistir apenas observações sanitizadas e apresentar o resultado na área de guias do cliente.
- Integrar a operação ao fluxo central de autenticação, procuração e bilhetagem já existente, preservando flags fail-closed, logs seguros e a proibição de chamadas reais automáticas.
- Cobrir contrato, tenancy, erros, UI e modo Fake/Simulated com testes offline; documentar a evidência e a limitação operacional de consulta externa.

## Capabilities

### New Capabilities

- `monitor-contagem-pagamentos-pagtoweb`: Permite consultar e acompanhar, por cliente e escritório atual, a quantidade de documentos de arrecadação pagos retornada pelo serviço oficial PAGTOWEB 7.3.

### Modified Capabilities

- Nenhuma.

## Impact

- Backend: catálogo operacional, adapter/codec/projeções de guias, controller e rotas tenant-scoped, fake client e testes Laravel.
- Frontend: tipos, cliente HTTP, composable, painel e rota de detalhes do cliente com o arquétipo de settings já usado no monitor.
- Documentação: matriz de cobertura e evidências piloto sanitizadas.
- SERPRO: operação de leitura `CONSULTA`, potencialmente bilhetável em `/Consultar`; execução real permanece desabilitada por flags, kill switch e autorização operacional explícita.

### Dependências entre changes

- Nível: `C0`.
- Bases estáveis: catálogo oficial versionado, contrato central `IntegraContadorClient`, monitor de guias e `CurrentOffice`.
- Depende de: nenhuma change ativa; as changes de RBAC e de UI são coordenadas, mas esta implementação usa os contratos estáveis atuais sem aguardar seus marcos.
- Capability/contrato: `pagtoweb.contaconsdocarrpg`, `SerproOperationService`, `CurrentOffice` e surface de guias.
- Marco exigido: `apply` local desta change; relação: coordenada.
- Desbloqueia: uma superfície dedicada para a contagem oficial PAGTOWEB 7.3 e atualização da checklist de cobertura.
- Paralelismo: pode avançar em paralelo com changes ativas que não modifiquem os mesmos arquivos de guias, rotas de cliente ou matriz de cobertura.

### Não objetivos

- Não executar consulta real, trial ou produção automaticamente, nem consumir credenciais, certificados, senhas ou dados pessoais para esse fim.
- Não emitir comprovante, alterar pagamento, transmitir declaração ou habilitar capabilities reais.
- Não expor `office_id`, tokens, XML, CPF, CNPJ, certificado ou qualquer segredo em API, UI, testes, logs ou evidências.
