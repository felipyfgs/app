## Context

O catálogo oficial já descreve `dte.consultar` como `idSistema=DTE`, `idServico=CONSULTASITUACAODTE111`, versão `1.0`, rota `/Consultar`, leitura faturável e poder e-CAC `00050`. O executor, ledger, kill switches, estados de readiness e aprovação dual já existem, mas o canário atual é genérico e pressupõe orçamento monetário. Esta change cria um único caminho produtivo quantitativo após a configuração global estar concluída.

O fluxo cruza duas autoridades sem misturá-las: o Proprietário escolhe o alvo pela plataforma; um `Office ADMIN` distinto confirma no contexto tenant. O resultado pertence ao Office. A execução live e a reconciliação com a Área do Cliente nunca rodam em CI.

## Goals / Non-Goals

**Goals:**

- permitir exatamente uma tentativa idempotente de `dte.consultar` no Office/cliente piloto;
- provar todos os gates globais e tenant imediatamente antes do transporte;
- exigir Proprietário e Office ADMIN distintos, inclusive contra conta dual;
- promover, após reconciliação manual, somente DTE `LIMITED` no mesmo Office com teto dez;
- garantir desativação imediata e visão global sanitizada.

**Non-Goals:**

- qualquer outra operação, Office, mutação, emissão ou declaração;
- retry automático de resultado incerto;
- exibir resposta fiscal ao Proprietário fora de membership real;
- consultar saldo/preço remoto ou automatizar a reconciliação;
- executar canário em CI, deploy, health ou preflight.

## Decisions

### 1. O alvo do canário é um registro server-side, não parâmetros livres

O Proprietário seleciona um Office ativo e um cliente pertencente a ele por comandos globais dedicados. O backend persiste o escopo imutável do pedido de canário; a execução não aceita `office_id`, operação, `idSistema`, `idServico`, rota ou payload de negócio do client. O Office ADMIN aprova por rota tenant usando o `CurrentOffice`, e o serviço compara esse contexto ao pedido.

Alternativa: body global com ids. Rejeitada por risco cross-tenant e por permitir alterar o escopo depois da aprovação.

### 2. Elegibilidade é recalculada antes do HTTP

O gate exige Production, versão ativa, OAuth recente, seis gates aceitos, A1 do Office válido, Termo aceito, procuração/poder `00050`, limites global e Office positivos, controles operacionais abertos, env kill aberto e Office classificado Production. Regressão entre aprovação e execução bloqueia.

Alternativa: snapshot único na criação. Rejeitada porque certificado, Termo, procuração, limite e switch podem mudar.

### 3. Uma tentativa, chave idempotente e resultado tenant

O pedido cria chave por instalação/ambiente/Office/cliente/operação. Reserva e dispatch são transacionais; replay devolve o estado durável sem novo HTTP. Timeout após dispatch fica `UNCERTAIN` e não tenta novamente. Resposta fiscal canônica fica no vault/registro tenant; o global recebe correlação, timestamps, status e quantidade.

### 4. Promoção limitada requer reconciliação humana explícita

Após sucesso, o Proprietário registra referência e resumo da reconciliação manual com o histórico da Área do Cliente, senha recente, frase, motivo e janela. O modo passa a `LIMITED` somente para o mesmo Office, operação fixa e máximo dez no ciclo. Nova tentativa fora desse escopo falha. Desativação fecha imediatamente o controle persistido; env kill continua prevalecendo.

Alternativa: promover automaticamente no sucesso. Rejeitada porque o ledger local deve ser conferido com a fonte contratual antes de ampliar consumo.

### 5. UI distribui ações conforme autoridade

A página global de configuração mostra criação, aprovação do Proprietário, execução e reconciliação sem payload fiscal. `/settings` do Office mostra convite/confirmar participação apenas a Office ADMIN no Office selecionado. A UI reutiliza Settings archetype; nenhum shell novo.

## Risks / Trade-offs

- [Cross-tenant pelo cliente] → relacionamento carregado pelo Office persistido e testes com cliente de outro tenant.
- [Conta dual cumpre dois papéis] → unique users no agregado e comparação de user id antes de aceitar segunda aprovação.
- [Retry duplica cobrança] → chave durável, reserva atômica e estado uncertain sem retry.
- [Global vê dado fiscal] → DTO global separado e testes de ausência de payload/campos canônicos.
- [Limite local diverge do SERPRO] → teto pequeno, alerta 80%, reconciliação manual obrigatória e kill imediato.
- [Feature/env abre catálogo inteiro] → operação/coordenadas hardcoded no gate DTE e todas as demais capabilities permanecem desligadas.

## Migration Plan

1. Criar schema/serviços do pedido DTE e promoção limitada com defaults fechados.
2. Publicar APIs globais/tenant e UI sem abrir switches.
3. Rodar testes offline de autorização, tenancy, gates, idempotência, ledger e redaction.
4. Configurar Production, Office/cliente piloto e obter as duas aprovações.
5. Em janela live, executar uma tentativa; se incerta/falha, manter bloqueado e reconciliar manualmente sem retry.
6. Após sucesso comprovado, registrar reconciliação e promover `LIMITED` ao mesmo Office/teto dez.

Rollback: desativar DTE no banco e, se necessário, forçar `SERPRO_KILL_SWITCH=true`; preservar ledger, resultado e evidências. Não apagar tentativa nem liberar outro Office.

## Open Questions

Nenhuma decisão de software bloqueante. Office/cliente piloto, aprovações, execução e referência do histórico são entradas live ops-gated.

