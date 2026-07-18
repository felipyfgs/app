# Ativar piloto SERPRO Trial — fluxo simples

## Objetivo

Ativar as credenciais Trial e preparar a emissão da segunda via do PGDAS-D do
cliente AUTO CENTER sem expor secrets e sem executar etapas manuais do
protocolo SERPRO.

## Fluxo normal

Na tela **Admin → SERPRO → Integração → Acesso**, selecione **Trial**, informe:

- certificado PFX/P12 do contratante;
- senha do certificado;
- Consumer Key;
- Consumer Secret.

Clique em **Salvar e ativar**.

No Trial, essa única ação cadastra a versão no vault, valida o certificado,
testa o OAuth mTLS e ativa a credencial. Os secrets são enviados uma vez e não
são devolvidos pela API. Produção continua usando cutover explícito e separado.

## Ativar o escritório contador

Com o A1 do autor já armazenado no vault, execute apenas:

```bash
docker compose exec -T --user www-data php \
  php -d memory_limit=1G scripts/activate_pilot_serpro.php
```

O script é idempotente e executa internamente:

1. configura o autor do office `contador`;
2. gera e assina o Termo em memória;
3. espera a fila concluir;
4. obtém o token do procurador;
5. sincroniza a procuração do AUTO CENTER.

Ele termina com código diferente de zero se qualquer etapa falhar. Não execute
como `root`, pois objetos do vault precisam pertencer ao usuário da aplicação.

### Somente se o A1 ainda não estiver no vault

Informe o path e a senha por variáveis de ambiente. Nunca grave a senha no
script, no runbook ou no histórico do shell.

```bash
read -rsp 'Senha do PFX: ' SERPRO_PILOT_PFX_PASSWORD
export SERPRO_PILOT_PFX_PASSWORD

docker compose exec -T --user www-data \
  -e SERPRO_PILOT_PFX_PATH=/var/www/html/scripts/piloto-contador.pfx \
  -e SERPRO_PILOT_PFX_PASSWORD \
  php php -d memory_limit=1G scripts/activate_pilot_serpro.php

unset SERPRO_PILOT_PFX_PASSWORD
```

O arquivo local deve permanecer ignorado pelo Git.

## Consultar PGDAS-D

Depois da ativação, a consulta inicial do cliente usa o contrato interno do
módulo, sem coordenadas SERPRO informadas pelo navegador:

```json
{
  "client_id": 123,
  "system_code": "INTEGRA_SN",
  "service_code": "PGDASD",
  "operation_code": "MONITOR",
  "dispatch": true
}
```

O catálogo resolve as operações oficiais:

- `pgdasd.consdeclaracao` → `PGDASD/CONSDECLARACAO13`, rota `Consultar`;
- `pgdasd.consdecrec` → `PGDASD/CONSDECREC15`, rota `Consultar`, para recuperar
  a declaração e o recibo da declaração escolhida.

## Verificação rápida

```bash
docker compose ps
curl -m 5 -sS -o /dev/null -w "%{http_code}\n" http://127.0.0.1:8080/up
```

Resultado esperado: `/up` retorna `200`; autorização tem A1, Termo e token; o
snapshot de procuração fica `AUTHORIZED`, `MISSING`, `EXPIRED` ou `UNVERIFIED`
com erro sanitizado. Nunca invente o poder `00146` quando a API não o comprovar.

## Restrições

- Não rodar `migrate:fresh` para ativar o piloto.
- Não versionar PFX, senhas, tokens, XML do Termo ou conteúdo do vault.
- Não usar fake/simulated como evidência de integração real.
- Não misturar ativação Trial com cutover de Produção.

Referências oficiais:

- [Obter procuração](https://apicenter.estaleiro.serpro.gov.br/documentacao/api-integra-contador/pt/solucoes/integra-procuracoes/procuracoes/servicos/obter_procuracao/)
- [Serviços x procurações](https://apicenter.estaleiro.serpro.gov.br/documentacao/api-integra-contador/pt/servicos_vs_procuracoes/)
