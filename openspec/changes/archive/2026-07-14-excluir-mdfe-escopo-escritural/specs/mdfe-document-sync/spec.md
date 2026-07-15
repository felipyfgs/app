## REMOVED Requirements

### Requirement: Captura de MDF-e no catálogo
**Reason**: MDF-e não faz parte da escrituração atendida pelo produto.

**Migration**: Desabilitar canal, dispatch e exposição; preservar dados legados sem novas capturas.

### Requirement: Cursor e limites do canal MDF-e
**Reason**: Sem captura MDF-e não existe cursor operacional para manter ou avançar.

**Migration**: Ignorar cursores legados MDF-e e não os incluir em scheduler, elegibilidade ou painel.

### Requirement: Parse tolerante de leiaute MDF-e
**Reason**: O parser MDF-e deixa de integrar o pipeline operacional.

**Migration**: Manter classes legadas inertes; nenhum novo XML MDF-e é processado.
