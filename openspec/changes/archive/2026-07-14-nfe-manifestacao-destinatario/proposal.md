## Why

O produto do escritório é **capturar e entregar XML** (catálogo + download/export), não gerir o ciclo fiscal completo de Manifestação do Destinatário. No DistDFe, o destinatário muitas vezes só recebe **resumo** até existir **ciência (210210)** — sem isso o full (`procNFe`) não chega e a entrega fica incompleta. Precisamos de um caminho **técnico** para obter o XML completo e, se no futuro o operador quiser, **poder** registrar conclusivas — sem transformar o painel num “manifestador SEFAZ” obrigatório.

## What Changes

- **Objetivo primário:** garantir **XML completo no vault** e entrega (download/export) ao escritório.
- **Ciência (210210)** como **meio de desbloqueio** do `procNFe` quando só há resumo — preferência por **automação opt-in / job técnico** (não “workflow de negócio” forçado).
- **MD-e conclusiva** (confirmação, desconhecimento, operação não realizada): **disponível** na API/UI para OPERATOR/ADMIN, mas **secundária**, sem bulk, sem default automático, sem ser requisito para “usar o produto”.
- Cliente próprio RecepcaoEvento4 (mTLS A1 do **cliente**, vault), flag `SEFAZ_MANIFEST_ENABLED`.
- Após ciência: reconsulta DistDFe → persistir `procNFe` → status de entrega “XML disponível”.
- UI: destacar **download/export**; ações de manifestação em área secundária (“avançado” / opcional).
- Piloto: cliente 8 (MULTICAR); seeds demo fora de escopo.

## Capabilities

### New Capabilities

- `nfe-xml-unlock-via-ciencia`: obter `procNFe` via ciência técnica + reconsulta DistDFe, para entrega de XML.
- `nfe-optional-manifestation`: API/UI opcional de conclusivas (e ciência manual se flag on), sem obrigatoriedade operacional.

### Modified Capabilities

- `fiscal-document-catalog`: projeção NF-e com resumo vs completo; foco em pronta entrega do XML.
- `xml-delivery`: download/export prioriza `procNFe` quando existir; resumo só se full indisponível.
- `frontend-dashboard-experience`: Documentos enfatiza download; MD-e conclusiva opcional/colapsada.
- `client-credential-management`: A1 do cliente para ciência/MD-e quando habilitado.

## Impact

- Backend: client de evento SEFAZ, job de reconsulta, flags; não exige processo diário de “manifestar tudo”.
- Frontend: CTA principal = baixar/exportar XML; MD-e = capacidade disponível.
- Ops: smoke no client 8 — ciência só se necessário para full; **não** smoke de desconhecimento em nota legítima.
- Dependência: DistDFe P1 já no monorepo (`capture-multi-dfe-sefaz`).

## Não-objetivos

- Tornar o escritório responsável por **política fiscal** de confirmação/desconhecimento em massa.
- Obrigar o operador a manifestar para usar o catálogo.
- Auto-confirmação / auto-desconhecimento.
- Emissão/cancelamento de NF-e.
- A1 do escritório assinando MD-e do cliente.
- Portal scraping.
