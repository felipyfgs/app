## Why

O painel já captura **NFS-e Nacional** via ADN e expõe o catálogo unificado **Documentos** (`/docs`) com kinds `NFSE`, `NFE`, `NFCE`, `CTE`, `MDFE` — mas só NFS-e tem fonte. O escritório contábil precisa dos **XML de mercadoria e transporte** (NF-e de entrada, eventos, CT-e, MDF-e) na mesma operação de triagem/export. Sem captura SEFAZ DistDFe e fluxos correlatos, a UI multi-tipo permanece vazia para tudo que não é serviço nacional.

## What Changes

- Introduzir **captura SEFAZ DistDFe (NF-e modelo 55)** por estabelecimento/raiz, com cursor NSU, mTLS A1, persistência no catálogo `kind=NFE` (+ eventos).
- Suportar **manifestação do destinatário** (ciência / confirmação / desconhecimento / não realizada) para desbloquear XML completo quando só chega resumo (`resNFe`).
- Captura de **CT-e** e **MDF-e** (distribuição/consulta oficiais) com kinds `CTE` e `MDFE`.
- Tratar **NFC-e** no catálogo quando o documento chegar por canal suportado (ou marcar explicitamente limites se DistDFe não cobrir o caso do escritório).
- Unificar **cursors, jobs, rate limit e vault** no mesmo monorepo (padrão ADN: página atômica, sem salto silencioso de NSU).
- Preencher projeções no catálogo `/docs` e export ZIP multi-tipo.
- Decisão de runtime: **cliente próprio** de DistDFe (espelho do ADN); `nfephp-org/sped-nfe` apenas como referência de contrato/fixtures — **não** como cliente de produção que materialize PEM ou desligue TLS (alinhado ao ADR 001).
- Reutilizar e-CNPJ A1 do vault por raiz (mesmo modelo de cliente/estabelecimento).

## Capabilities

### New Capabilities

- `sefaz-distdfe-sync`: distribuição NF-e por NSU (SOAP `NFeDistribuicaoDFe`), cursor, rate limit, decode docZip, bloqueios 137/656.
- `nfe-recipient-manifestation`: eventos de manifestação do destinatário e transição resumo → XML completo.
- `cte-document-sync`: captura/projeção de CT-e (e eventos relevantes) no catálogo.
- `mdfe-document-sync`: captura/projeção de MDF-e (e eventos relevantes) no catálogo.
- `multi-dfe-catalog-projection`: projeções e parsers por `DocumentKind` (NFE, NFCE, CTE, MDFE) alimentando o catálogo unificado e export.

### Modified Capabilities

- `fiscal-document-catalog`: kinds com captura real; projeção multi-tipo além de `nfse_notes`.
- `client-credential-management`: A1 da raiz serve ADN **e** SEFAZ DistDFe (mesmo vault; sem exposição de segredo).
- `adn-document-sync`: sem mudança de contrato ADN; coexistência de cursors ADN vs SEFAZ por estabelecimento.
- `frontend-dashboard-experience`: empty states de “em breve” removidos para kinds capturados; filtros e insights por tipo com dados reais.
- `xml-delivery`: export/download multi-kind (ZIP com pasta ou prefixo por tipo).
- `operations-dashboard`: inbox/saúde para cursors SEFAZ bloqueados e consumo indevido.

## Impact

- **Backend:** novos clients SOAP/mTLS, jobs Horizon, cursors multi-canal, parsers XML, projeções por kind.
- **Frontend:** `/docs` com kinds ativos; fluxos de manifestação (OPERATOR); health/syncs multi-canal.
- **Ops:** rate limit SEFAZ (cStat 137/138/656; ≥1h quiet; ≥2s no loop); smoke restrito; sem cert em CI.
- **Deps:** **sem** runtime `sped-nfe` (pesquisa: PEM em disco + TLS frágil); fixtures/dev apenas; produção = cliente próprio.
- **Dependência:** catálogo unificado (`unify-fiscal-documents-catalog`) com `DocumentKind` e `/docs`.
- **Pesquisa:** `research/sintese-tecnica.md` (5 frentes, 2026-07-14) embasa endpoints, MD-e e priorização NFC-e como gap.

## Não-objetivos

- Emissão, cancelamento ou CCe **pelo painel** (apenas captura e manifestação de destinatário necessária à captura).
- DANFE/DACTE/PDF (`sped-da`).
- Portais municipais legados / scraping.
- NFC-e de PDV como emissor do escritório.
- Multi-escritório SaaS, KMS cloud, portal do cliente final.
- Substituir ou reescrever o cliente ADN de NFS-e.
