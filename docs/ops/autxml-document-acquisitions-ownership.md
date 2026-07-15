# Ownership de `document_acquisitions` — coordenação autXML × MA outbound

**Change ativa:** `add-office-autxml-and-bulk-xml-import` · task 1.4  
**Change relacionada:** `build-ma-outbound-nfe-nfce-capture`  
**Data:** 2026-07-15  
**Status:** resolvido

## Decisão

A tabela `document_acquisitions` é **owned e criada** pela change MA outbound:

| Item | Valor |
|------|--------|
| Migration criadora | `backend/database/migrations/2026_07_15_030000_create_ma_outbound_capture_tables.php` |
| Enum de fontes | `App\Enums\DocumentAcquisitionSource` |
| Model | `App\Models\DocumentAcquisition` |
| Teste de schema | `backend/tests/Feature/Outbound/OutboundSchemaTest.php` |

## Ordem de aplicação

1. **Sempre aplicar primeiro** a migration MA (`2026_07_15_030000_…`) se o banco ainda não tiver `document_acquisitions`.
2. A change `add-office-autxml-and-bulk-xml-import` **não cria** a tabela de novo.
3. Esta change **apenas estende** a tabela e o enum com:
   - fontes `AUTXML_DIST_NSU`, `MANUAL_XML`, `MANUAL_ZIP` (refinando o legado `IMPORT` se necessário);
   - FKs opcionais para item de batch / NSU real do cursor do escritório;
   - índices tenant-aware adicionais, se faltarem.

## Proibições

- Não criar uma segunda tabela com o mesmo papel (ex.: `xml_acquisitions`, `import_acquisitions`).
- Não renomear `document_acquisitions` sem migration de transição coordenada com MA.
- Não inventar NSU sintético para aquisições manuais; proveniência manual usa batch item / metadata.

## Verificação local (2026-07-15)

- Migration MA já presente no repositório e schema de testes confirma `Schema::hasTable('document_acquisitions')`.
- Change MA ainda ativa em `openspec/changes/build-ma-outbound-nfe-nfce-capture` (não arquivada); ownership permanece com a migration MA até eventual archive/sync.
