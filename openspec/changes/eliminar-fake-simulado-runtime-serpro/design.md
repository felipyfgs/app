## Context

O container registra clientes sintéticos no próprio `AppServiceProvider`: OAuth, gateway Integra, Autentica Procurador, procurações, caixa postal/DTE, parcelamentos, guias e mutações podem resolver doubles quando o ambiente é `testing`, o flag legado `SERPRO_USE_FAKE_CLIENTS` está ativo ou o driver é `simulated`. Há ainda um fallback particularmente incorreto: procurações com driver `disabled` resolvem um cliente Fake que devolve poderes ativos.

Os defaults fora de produção são `TRIAL` e `simulated`; a proteção forte existe principalmente para `APP_ENV=production`. Isso permite que um ambiente chamado homologação execute respostas fabricadas. O probe histórico também demonstrou que um client in-process pode declarar `simulated=false` e `sourceProvenance=SERPRO_REAL`, produzindo classificação positiva sem egress verificável.

Ao mesmo tempo, PHPUnit precisa de doubles determinísticos para testar timeout, redirect, 401, 304, mutação incerta e sanitização. Esses doubles não são homologação: devem existir somente no autoload de desenvolvimento/testes e ser instalados explicitamente pela suíte, nunca pelo provider de aplicação.

## Goals / Non-Goals

**Goals:**

- restringir todo driver SERPRO do runtime a `disabled|real`;
- deixar todas as capabilities `disabled` por default em qualquer `APP_ENV`;
- impedir resolução de cliente sintético pelo container de aplicação;
- mover doubles necessários para `Tests\Support`/`autoload-dev` e exigir bind explícito no teste;
- exigir prova `PRODUCTION_CANARY` verificável para `PASS_REAL_*`;
- manter `TRIAL` como demonstração oficial SERPRO, sem tratá-lo como homologação ou evidência produtiva;
- remover claims Fake/Simulated de homologação real em documentos e UI operacional.

**Non-Goals:**

- ligar HTTP real automaticamente ou alterar flags/allowlists do ambiente;
- executar OAuth, operação fiscal, canário ou mutação;
- remover campos históricos de banco nesta change; eles permanecem como evidência de origem inválida/quarentenada;
- remover `Http::fake`, `Queue::fake`, `Bus::fake`, factories e stubs locais dos testes;
- alterar clientes Fake de integrações não SERPRO.

## Decisions

### Runtime tem somente `disabled|real`

`SerproCapabilityDriver::Simulated` será removido. `CapabilityDriverResolver` rejeitará qualquer valor diferente de `disabled|real` em todos os ambientes, não somente em produção. Config e `.env.example` usarão `disabled` universalmente; o bloco `trial.use_fake_clients` e `SERPRO_USE_FAKE_CLIENTS` deixam de existir.

Alternativa rejeitada: manter `simulated` apenas em desenvolvimento. Isso conserva o mesmo caminho de aplicação que gerou evidência ambígua e permite divergência entre homologação e produção.

### Ambientes publicados são Trial e Produção

O `TRIAL` é um ambiente externo oficial, com gateway
`https://gateway.apiserpro.serpro.gov.br/integra-contador-trial/v1`, publicado
no Swagger de demonstração da SERPRO. Ele não usa clientes locais Fake/Simulated
e nunca satisfaz `PASS_REAL_*`. `PRODUCTION` continua usando o gateway oficial
produtivo configurado. `HOMOLOGATION` será removido porque a documentação pública
não publica URL nem credencial contratual para esse ambiente; ele não pode apontar
implicitamente ao gateway de produção.

O token do Trial será lido apenas de configuração operacional, nunca embutido no
código ou em artefato versionado, mesmo que a documentação de demonstração o
publique. A execução continuará opt-in por capability `real` e kill switch.

### Disabled nunca resolve cliente de sucesso

Interfaces que precisam representar capability desligada usarão implementação `Disabled*` que retorna erro fail-closed ou o executor central produzirá `CAPABILITY_DISABLED`. Em especial, procurações desligadas nunca poderão produzir poder `ACTIVE`, bearer sintético ou token de procurador.

Alternativa rejeitada: reutilizar um Fake e confiar no chamador para olhar `simulated`. Esse foi o mecanismo que permitiu promoção indevida.

### Doubles ficam fora de `App\`

Clientes programáveis necessários aos testes serão movidos para `backend/tests/Support` sob namespace `Tests\Support`. O `AppServiceProvider` não terá branch `testing` nem import de double. Cada teste instalará seu double explicitamente no container após a aplicação subir.

`Http::fake()` no teste do verificador documental permanece: ele valida limites e falhas sem transformar o gate comum em dependência de internet. A evidência vigente será o comando real `serpro:official-sources-verify`, executado separadamente e sanitizado.

Alternativa rejeitada: chamar SERPRO real no PHPUnit. Isso expõe CI a rede, custo, credenciais e efeitos fiscais, além de não permitir reproduzir timeout/redirect de forma determinística.

### Proveniência é um conjunto verificável, não um booleano

O probe não aceitará `simulated=false`, `sourceProvenance=SERPRO_REAL` ou HTTP 200 isoladamente. `PASS_REAL_*` exigirá ambiente `PRODUCTION`, endpoint canônico contratado, contrato/credencial reais, run explicitamente marcado `PRODUCTION_CANARY`, tracker/hash/timestamp e ausência de simulação. Trial será sempre não elegível e nenhuma classificação histórica `PASS_BUSINESS` será promovida.

Alternativa rejeitada: renomear `PASS_BUSINESS` sem mudar o gate. A falha é de autoridade/proveniência, não de nomenclatura.

### Histórico é reclassificado, não apagado silenciosamente

Campos `simulated` e linhas históricas permanecem legíveis para auditoria, mas são exibidos como inválidos para prontidão. Escrita nova com proveniência simulada será proibida após a migração do runtime. Os `.md` manterão o inventário antigo somente em seção explicitamente supersedida.

## Mapa de dependências

```text
reconciliar-fontes-oficiais-serpro (C0, apply 1.1/2.1/2.2)
                    │
                    ▼
eliminar-fake-simulado-runtime-serpro (C1)
  N0 contrato/config disabled|real
        ├── N1 provider/clientes fail-closed
        ├── N1 doubles em Tests\Support
        └── N1 probe/proveniência real
                    │
                    ▼
  N2 docs/UI/histórico supersedido
                    │
                    ▼
  N3 gates integrados + verificação documental real
```

- Ownership upstream: manifestos, integridade de fontes e comando documental.
- Ownership desta change: drivers/bindings/runtime, doubles de testes, probe e claims de evidência.
- Arquivos compartilhados: `backend/config/serpro.php`, `SerproProductionContainmentTest.php` e ledgers; writer único e serialização obrigatória.
- O comando documental real pode rodar após apply 2.2; nenhuma rota de negócio participa do gate.

## Risks / Trade-offs

- [Muitos testes importam classes Fake de `App\`] → migrar por famílias e manter uma camada `Tests\Support` explícita antes de apagar cada classe de runtime.
- [Código histórico consulta o campo `simulated`] → preservar leitura defensiva e fazê-la bloquear; remover somente produtores de simulação nesta change.
- [Ambiente local deixa de mostrar dados sintéticos SERPRO] → UI deve exibir capability desabilitada/sem evidência; dados de demonstração fiscal não podem fingir SERPRO.
- [Remover branch `testing` revela dependências escondidas] → executar suites focadas por família e full backend gate antes do PASS.
- [Confusão entre verificador real e teste fake] → evidência final registra separadamente o comando HTTP real 8/8 e a suíte offline que cobre falhas.

## Migration Plan

1. Congelar inventário de bindings e claims atuais.
2. Remover `Simulated` do enum/resolver e mudar defaults para `disabled`.
3. Introduzir implementações `Disabled*` onde a interface não usa o executor central.
4. Mover doubles necessários para `Tests\Support` e adaptar binds explícitos da suíte.
5. Apagar classes Fake/Simulated do autoload `App\` após zero referência de runtime.
6. Endurecer probe e reclassificar documentos/UI.
7. Rodar testes, Pint, architecture checks, varredura de bindings e o verificador documental real.

Rollout: deploy permanece com kill switch/capabilities OFF. Habilitação `real` continua sendo operação externa separada.

Rollback: restaurar somente o código anterior não é seguro porque reabre resposta fabricada. Em incidente, manter capabilities `disabled`; doubles continuam disponíveis exclusivamente aos testes.

## Open Questions

- Nenhuma para o primeiro lote SERPRO. A remoção de clientes Fake de eSocial/SEFAZ/ADN será change separada para não misturar contratos externos.
