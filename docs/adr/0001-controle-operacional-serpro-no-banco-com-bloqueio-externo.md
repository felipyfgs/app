# ADR 0001: Controle operacional SERPRO no banco com bloqueio externo

- Status: Aceito
- Data: 2026-07-16
- Decisores: Proprietário da plataforma e engenharia

## Contexto

O SERPRO exige configuração e promoção controladas, com trilha de auditoria, rotação de credenciais, gates externos e limites. Variáveis de ambiente são adequadas para segredos de bootstrap e contenção emergencial, mas alterações nelas dependem de acesso ao host/deploy e não preservam por si só ator, motivo, janela ou histórico operacional.

Também precisamos garantir que uma mudança de deploy nunca habilite tráfego faturável por acidente. O sistema já possui controles persistidos e um `SERPRO_KILL_SWITCH` externo.

## Decisão

O estado operacional normal do SERPRO será persistido no banco e alterado somente por APIs/serviços autorizados e auditados. Isso inclui versões ativas, gates, limites, modos de rollout e kill switches operacionais.

`SERPRO_KILL_SWITCH=true` será uma trava externa emergencial e prevalente: seu valor será combinado por OR com os bloqueios persistidos. `false` apenas remove essa trava externa e nunca ativa contrato, credencial, capability, Office ou operação. Segredos de infraestrutura e bootstrap continuam fora do banco conforme sua fronteira própria.

## Consequências

### Positivas

- Mudanças normais registram ator, motivo, janela e estado anterior.
- O painel pode desativar tráfego imediatamente sem deploy.
- Operadores do host preservam uma contenção independente mesmo com banco/painel indisponíveis.
- Restore/redeploy não interpreta env aberto como promoção.

### Negativas

- Readiness precisa reconciliar duas fontes e explicar qual bloqueio prevaleceu.
- Backup/restore do banco inclui o estado operacional e exige revisão antes de reabrir o env kill.
- Cache de leitura deve ser apenas espelho do banco e nunca fonte que reabra operação após flush.

## Alternativas consideradas

### Toda configuração no `.env`

Rejeitada porque exige deploy para operações rotineiras, dificulta auditoria e mistura habilitação com configuração de infraestrutura.

### Todo controle apenas no banco

Rejeitada porque remove a trava independente disponível durante incidente, indisponibilidade da aplicação ou suspeita de comprometimento.

### Env como override bidirecional

Rejeitada porque `false` poderia contornar um bloqueio persistido ou habilitar produção após mudança de configuração.

## Verificação

- Testes devem provar que env `true` sempre bloqueia e env `false` nunca promove.
- O status sanitizado deve informar a origem efetiva do bloqueio sem expor configuração sensível.
- Gates de transporte devem consultar o estado efetivo imediatamente antes do HTTP.

