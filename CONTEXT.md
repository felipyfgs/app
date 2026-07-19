# Cadastro de Clientes

Este contexto nomeia os conceitos usados para organizar a carteira de clientes de um escritório e distingui-los dos conceitos fiscais oficiais.

## Linguagem

**Categoria de cliente**:
Classificação livre, reutilizável e exclusiva de um escritório, usada para organizar seus clientes. Um cliente pode ter várias categorias, inclusive categorias posteriormente arquivadas.
_Evitar_: Categoria fiscal, regime tributário, tag fiscal

**Atribuição de categoria**:
Vínculo entre uma categoria de cliente e um cliente do mesmo escritório.
_Evitar_: Enquadramento fiscal

**Categoria fiscal**:
Classificação controlada que define cobertura e comportamento do monitoramento fiscal.
_Evitar_: Categoria de cliente, tag de cliente

**Regime tributário atual**:
Projeção cadastral vigente do enquadramento tributário do cliente, usada para consulta e recorte da carteira. Não substitui o histórico fiscal oficial de vigências.
_Evitar_: Categoria de cliente, categoria fiscal, histórico de regime

**Categoria arquivada**:
Categoria de cliente indisponível para novas atribuições, mas mantida nos vínculos e filtros históricos até ser reativada ou removida do cliente.
_Evitar_: Categoria excluída
