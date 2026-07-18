## Why

O monitor já lista as declarações DEFIS por meio da operação 142, mas ainda não
permite consultar, de forma explicitamente confirmada, a última declaração e o
recibo de um ano-calendário. A operação oficial 143 retorna dois PDFs em base64,
que exigem retenção exclusiva no cofre e uma superfície local sem conteúdo fiscal
bruto.

## What Changes

- Criar a capacidade de consultar `DEFIS/CONSULTIMADECREC143` por ano-calendário.
- Armazenar declaração e recibo apenas no `SecureObjectStore`, com descritores
  públicos locais, escopados por escritório e cliente.
- Adicionar endpoints autorizados, confirmação explícita, modal de histórico e
  download autenticado de artefatos locais.
- Registrar evidências sanitizadas, sem `idDefis`, PDFs base64, CPF/CNPJ ou tokens.

Não inclui chamada SERPRO de negócio em ambiente real, transmissão DEFIS 141,
consulta DEFIS 144, nem automação de download ou envio externo.

## Capabilities

### New Capabilities

- `defis-latest-declaration-monitoring`: consulta e disponibilidade local segura
  dos documentos da última DEFIS por ano-calendário.

### Modified Capabilities

- Nenhuma.

## Impact

- Backend: adapter Simples/MEI, codecs, cofre, projeção, API tenant-scoped e testes.
- Frontend: tipos, composable e interface da cápsula PGDAS-D no monitor.
- Operação SERPRO: `DEFIS` / `CONSULTIMADECREC143` / `/Consultar` / versão `1.0`,
  consulta potencialmente faturável e dependente do poder `00146` quando houver
  representação.

### Dependências entre changes

- Nível: `C1`.
- Bases estáveis: `schema-conventions` e a implementação aplicada da change
  `cobrir-consulta-declaracoes-defis-142`.
- Depende de: `cobrir-consulta-declaracoes-defis-142`; capability/contrato:
  projeção local DEFIS e confirmação de consulta; marco exigido: `verify`;
  relação: coordenada.
- Não depende das changes ativas de PGDAS-D/DCTFWeb, salvo os contratos já
  presentes no código compartilhado.
- Desbloqueia: a consulta específica DEFIS 144 usando uma referência de cofre
  projetada, sem reter `idDefis` em APIs ou logs.
- Paralelismo: pode avançar em paralelo com mudanças que não alterem os contratos
  de artefatos PGDAS-D nem a cápsula Simples/MEI.
