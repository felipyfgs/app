## Context

O catálogo oficial local contém `mit.listaapuracoes` com rota `Consultar` e
estado produtivo. A cápsula MIT já projeta a apuração individual e a situação
de encerramento, mas não recebe uma lista tipada de apurações. A infraestrutura
de `CurrentOffice`, capability `dctfweb`, caller central, cofre e histórico
DCTFWeb já existe na change ativa `integrar-monitoramento-dctfweb`.

## Goals / Non-Goals

**Goals:**

- Fechar a consulta não mutante 317 ponta a ponta com fixture offline.
- Representar somente metadados/projeções necessários na UI MIT do escritório
  atual.
- Garantir que erro, resposta simulada e artefato de outro office não virem
  dado exibível nem download autorizado.

**Non-Goals:**

- Emitir guia 313, encerrar apuração 314 ou transmitir declaração 310.
- Criar chamada real automática, fallback silencioso para HTTP ou dependência
  nova.
- Expor XML, bytes, hash, identificador ou caminho do cofre.

## Decisions

### Adapter e DTO próprios para 317

O request terá campos oficiais validados (`anoApuracao`, e filtros opcionais
documentados pelo catálogo/fixture) e um adapter próprio. Não será reutilizado
o adapter 316: listar e consultar uma apuração têm semânticas e payloads
distintos. Resposta desconhecida falha fechada e produz erro sanitizado.

### Projeção local é a fonte da tela

O POST de consulta passa pelo caller/capability central e persiste uma projeção
tenant-scoped. A UI lê somente a API de carteira/histórico já persistida; abrir
modal ou aba não faz coleta. Isso separa a ação potencialmente bilhetável da
visualização local.

### Superfície MIT estende o renderer existente

Os itens retornados entram no resumo MIT em vez de criar rota/coluna paralela.
O renderer mantém as colunas e a responsividade do arquétipo atual, mas oferece
quantidade e detalhe de apurações já guardadas. Ação de download usa
`download_path` da API; MIME e nome são preservados pelo descritor sanitizado.

### Dependência coordenada com DCTFWeb

Esta change consome contratos já aplicados de `integrar-monitoramento-dctfweb`.
Não altera seus artefatos OpenSpec. Arquivos compartilhados de código serão
editados em lote único e verificados com os testes das duas superfícies.

## Mapa de dependências

`integrar-monitoramento-dctfweb (apply)` →
`cobrir-consultas-mit-dctfweb (C1)`: o primeiro fornece caller, tenancy e
histórico; este adiciona a listagem 317. Não há outro consumidor. A mudança
não roda em paralelo com alteração no mesmo renderer/controller; auditorias e
testes em arquivos diferentes podem rodar em paralelo. Rollback desabilita o
adapter/rota por capability e mantém evidências já guardadas inacessíveis sem
autorização.

## Risks / Trade-offs

- [Payload oficial incompleto em fixture] → validar formato, registrar erro
  sanitizado e não promover projeção.
- [Consulta cobrada em produção] → testes usam fake/simulated; canário exige
  decisão humana, allowlist, orçamento unitário e somente leitura.
- [Mistura de offices] → toda query e download recebem o office somente de
  `CurrentOffice`, com teste de negação cross-tenant.
- [XML tratado como PDF] → streaming usa MIME/nome sanitizados da evidência e
  testa os dois tipos.

## Migration Plan

1. Adicionar DTO/adapter/mapa e fixtures/testes offline.
2. Projetar e expor resumo MIT tenant-scoped.
3. Atualizar UI e testes; executar gates direcionados.
4. Ativar somente capability simulated em desenvolvimento. Produção permanece
   sem mudança de flag.

## Open Questions

Nenhuma. Se a homologação oficial não disponibilizar resposta semântica de 317,
o endpoint continua validado por fixture e qualquer canário real é uma decisão
operacional separada.
