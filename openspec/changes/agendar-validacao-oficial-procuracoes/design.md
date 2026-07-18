# Desenho: agendamento seguro de validação oficial de procurações

## Decisão

O comando `serpro:dispatch-due-procuracao-syncs` será registrado no scheduler, porém será no-op enquanto `SERPRO_PROCURACOES_SCHEDULER_ENABLED=false` (valor padrão). Ele só despachará clientes do ambiente configurado que cumprirem todos os requisitos abaixo:

1. o escritório aparece na allowlist explícita;
2. há autorização SERPRO existente no mesmo ambiente e seu estado permite chamadas externas;
3. o cliente tem projeção sem verificação, ou cuja última verificação excedeu a idade configurada;
4. o limite de lote da execução ainda não foi alcançado.

O comando não cria autorização nem snapshot. Sua saída contém apenas contagens e razões agregadas.

## Defesa em profundidade

O job `SyncClientProcuracaoJob` validará novamente:

- o flag do agendador e a allowlist do escritório;
- kill switches globais e capability `authorization` por `SerproJobFlagGuard`;
- autorização persistida, ambiente e condição para chamada externa.

Uma mudança de configuração entre o despacho e o consumo cancela o job antes de consultar a SERPRO. O job manual existente não muda de comportamento: as novas restrições de scheduler aplicam-se somente ao modo automático identificado no payload.

## Critério de atualização

`last_verified_at` é a fonte de idade. Ausência da projeção é considerada pendente, mas apenas clientes já selecionados pela autorização apta e allowlist. A expiração de `valid_to` continua sendo calculada localmente pela projeção da lista, sem depender do agendamento.

## Operação futura

Para ativar em TRIAL, um operador deverá configurar explicitamente o flag, ambiente, allowlist e capability compatível, após conferir contrato e bilhetagem. PRODUÇÃO exigirá a mesma configuração explícita e não será ativada por esta change.
