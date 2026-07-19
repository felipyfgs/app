# Separar categorias de clientes das categorias fiscais

Categorias de clientes pertencem à organização livre da carteira de cada escritório, enquanto categorias fiscais controlam cobertura, agenda e efeitos do monitoramento. Decidimos manter modelos separados porque reutilizar `FiscalCategory` acoplaria uma tag administrativa a comportamentos fiscais e tornaria mudanças futuras arriscadas; a duplicação deliberada do conceito nominal preserva os limites dos dois domínios.

## Consequências

Categorias de clientes não alteram monitoramento, obrigações ou histórico fiscal. O regime tributário atual continua sendo uma projeção cadastral distinta, e os períodos fiscais permanecem a fonte oficial de histórico.
