# Proposta: agendar validação oficial de procurações

## Por quê

A coluna de procuração já mostra a projeção persistida e recalcula localmente o vencimento. Falta um mecanismo controlado para renovar a evidência oficial periodicamente, sem transformar o scheduler em autorização implícita para chamadas à SERPRO.

## O que muda

- Adicionar um comando do scheduler que identifica procurações vencidas de verificação e despacha jobs de sincronização.
- Manter o mecanismo completamente desligado por padrão e exigir, ao mesmo tempo, configuração explícita, capability SERPRO habilitada, allowlist de escritórios e autorização apta para chamadas externas.
- Revalidar todas as proteções no job, para que um item já enfileirado não faça chamada se a permissão for retirada.
- Registrar decisão operacional sanitizada, sem credenciais, tokens ou documentos fiscais.

## Fora de escopo

- Habilitar TRIAL, PRODUÇÃO ou qualquer chamada externa agora.
- Criar ou renovar Termo, certificado, token de procurador ou procuração.
- Alterar a interface compacta de `/clients`.
