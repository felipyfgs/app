## Por quê

As operações produtivas de renúncia de vínculo do `PNRCONTADOR` aparecem no
catálogo e no executor central, mas as consultas, o comprovante e a situação
da solicitação ainda não têm adapter de domínio, projeção tenant-scoped nem
superfície de negócio. Isso impede o escritório de consultar o ciclo de
renúncia de forma auditável, mesmo mantendo toda mutação fiscal bloqueada.

## O que muda

- Criar monitoramento e consultas locais para `pnr_contador.consultar_renuncias`,
  `pnr_contador.emitir_comprovante` e `pnr_contador.situacao_renuncia`, com
  dados sanitizados, evidência no cofre e isolamento por `CurrentOffice`.
- Adicionar uma superfície de cliente para histórico, situação e descritores
  de comprovantes, usando execução explícita e sem depender de `office_id`
  enviado pelo navegador.
- Manter `pnr_contador.solicitar_renuncia` bloqueada: não haverá envio,
  habilitação de flag, canário, egress real automático ou mutação fiscal nesta
  change.

## Capacidades

### Novas capacidades

- `pnr-contador-monitoring`: consulta, projeção e visualização tenant-scoped
  das evidências não mutantes de renúncia no Integra Contador.

### Capacidades modificadas

- Nenhuma.

## Impacto

- Backend: catálogo PNR, adapter de monitoramento, codecs fail-closed,
  projeções/evidências e rotas same-origin protegidas por tenancy.
- Frontend: detalhe do cliente sob o arquétipo dashboard existente, com
  estados de vazio, bloqueio, carregamento e proveniência não produtiva.
- Testes: unitários de codec, feature de isolamento de tenant e testes de
  contrato de UI, todos sem rede e sem PFX/token.
- Non-goals: ativar `pnr_contador.solicitar_renuncia`, disparar `Declarar`,
  criar credenciais, ler/expor `senhas.txt`, chamar SERPRO real automático,
  alterar dados fiscais existentes ou promover `READY_PRODUCTION`.

### Dependências entre changes

- Nível: `C1`.
- Bases estáveis: catálogo reconciliado por
  `reconciliar-fontes-oficiais-serpro` (verify concluído) e specs principais
  `schema-conventions`.
- Depende de: `eliminar-fake-simulado-runtime-serpro`, contrato real-only,
  marco `apply` das tasks 1.1–4.2; relação **bloqueante**. A task 5.1 de
  evidência externa não é pré-requisito desta implementação offline.
- Depende de: `explorador-consultas-manuais-ui`, contratos de consulta manual
  e shell do painel, marco `archive`; relação **coordenada**.
- Desbloqueia: cobertura local das três operações PNR não mutantes e sua
  futura validação `PRODUCTION_CANARY` por operação.
- Paralelismo: pode avançar em paralelo com changes que não alterem o catálogo
  PNR, adapters de registro ou superfícies de cliente; não paralelizar com
  mudanças que disputem esses mesmos contratos.
