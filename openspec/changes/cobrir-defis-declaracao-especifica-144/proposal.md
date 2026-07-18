## Why

A operação oficial `DEFIS/CONSDECREC144` permite obter os PDFs de uma declaração
DEFIS específica, mas requer `idDefis`. O monitor já lista declarações pela
operação 142 e obtém a última pela 143, porém descarta esse identificador para
proteger dados fiscais; falta uma referência interna segura para a consulta
histórica específica.

## What Changes

- Criar uma referência opaca, tenant-scoped e armazenada no cofre para o
  identificador retornado pela DEFIS 142.
- Registrar e executar a operação 144 somente a partir dessa referência,
  persistindo os PDFs exclusivamente no cofre e expondo descritores públicos.
- Adicionar endpoints e interface para selecionar uma declaração listada,
  confirmar a consulta potencialmente faturável e baixar os artefatos locais.
- Validar codecs, isolamento tenant, autorização, fixtures Fake/Simulated e
  gates sem realizar chamada SERPRO de negócio real.

Non-goals: tráfego SERPRO real, transmissão DEFIS 141, aceitar `idDefis` bruto
do navegador, expor o identificador em API/log/banco público, automação
periódica, mutações fiscais ou envio externo.

## Capabilities

### New Capabilities

- `defis-specific-declaration-monitoring`: consulta segura de declaração DEFIS
  específica por referência opaca, com cofre, API tenant-scoped e interface.

### Modified Capabilities

- Nenhuma.

## Impact

- Backend: codec/projeção DEFIS 142, catálogo/operação 144, cofre, rotas,
  controllers, modelos/migrações e testes Laravel.
- Frontend: tipos, cliente API, composable, ações e modal do monitor PGDAS-D.
- Segurança: `SecureObjectStore`, `CurrentOffice`, `TenantAuthorization`,
  confirmação explícita e logs por allowlist.

### Dependências entre changes

- Nível: `C1`.
- Bases estáveis: `schema-conventions` e changes arquivadas.
- Depende de: `cobrir-consulta-declaracoes-defis-142` e
  `cobrir-defis-ultima-declaracao-143`; contrato: lista DEFIS sanitizada e
  padrão de artefatos no cofre; marco: `verify`; relação: `coordenada`.
- Desbloqueia: cobertura de leitura da operação `defis.consdecrec`.
- Paralelismo: não editar os artefatos da 142/143; pode avançar em paralelo a
  changes sem tocar DEFIS, catálogo Simples/MEI ou monitor PGDAS-D.
