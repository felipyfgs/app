# Matriz de cobertura — captura entrada/saída

Documento operacional: o que cada **kind** e **direction** usa para obter XML no escritório.

| Kind | Direction | Canal primário | Canal complementar | Flag / pré-requisito |
|------|-----------|----------------|--------------------|----------------------|
| **NFS-e** | OUT (prestador) | ADN `ISSUER` | — | ADN + e-CNPJ A1 |
| **NFS-e** | IN (tomador/intermediário) | ADN `TAKER` / `INTERMEDIARY` | — | ADN + e-CNPJ A1 |
| **NF-e** | IN (destinatário / autXML) | DistDFe SEFAZ | Ciência unlock (XML full) se só resumo | `SEFAZ_DISTDFE_ENABLED`; unlock: `SEFAZ_MANIFEST_ENABLED` + change `nfe-manifestacao-destinatario` |
| **NF-e** | OUT (emitente) | **Import XML** / pacote MA assistido (UF=MA) | Consulta protocolo por `nNF` (só chave); M2M MA se G4; autXML escritório (change própria) | DistDFe **não** entrega nota própria ao emitente (cStat 641). Ver `ma-outbound-xml-auto-discovery.md` |
| **NFC-e** | OUT | **Import XML** / pacote MA assistido (UF=MA); **SVRS DownloadXMLDFe por chave** (piloto, flags off) | Consulta protocolo por `nNF` (só chave); ERP/PDV | Sem DistDFe nacional de 65 (cStat 618). Canal SVRS: `SEFAZ_SVRS_NFCE_XML_*` — só modelo 65/MA; **NF-e 55 sem este canal**. Ver `svrs-nfce-enablement-matrix.md` |
| **NFC-e** | IN | Raro / import se necessário | — | MVP: não capturar via DistDFe genérico |
| **CT-e** | IN (5 papéis) | `CTE_DISTDFE` cliente (opt-in) | — | `SEFAZ_CTE_ENABLED`; ver `cte-coverage-and-channels-runbook.md` |
| **CT-e** | OUT (emitente) | `CTE_AUTXML_DISTDFE` office | Import / `EMITTER_PUSH` | `SEFAZ_CTE_AUTXML_*` allowlist; DistDFe do próprio emitente **não** entrega o principal |
| **MDF-e** | — | **Fora do escopo** desta entrega | — | Flag existe no config mas sem client/projeção |

## Direção (`direction`)

| Valor | Significado | Derivação típica |
|-------|-------------|------------------|
| `IN` | Entrada (custo / recebimento) | `TAKER`, `INTERMEDIARY`, DistDFe de interesse |
| `OUT` | Saída (receita / emissão) | `ISSUER`, import de XML emitido |
| `UNKNOWN` | Não classificado | Papel ausente |

Backfill: `php artisan documents:backfill-direction`.

## Regras de ouro

1. **Um capturador DistDFe por A1** (evitar 656).
2. **Não avançar NSU** se decode falhar; bloquear após 5 falhas.
3. **Import** só armazena XML já autorizado — **não emite** nota.
4. Download **prefere XML completo** (`is_summary=false`) sobre resumo.
5. Export ZIP: `{entrada|saida}/{nfse|nfe|nfce|cte}/{cnpj}/{YYYYMM}/{chave}.xml` — sem pasta de papel (ISSUER/TAKER); competência compacta.

## Onboarding do cliente

1. Cadastrar cliente + estabelecimento + e-CNPJ A1.
2. Habilitar ADN (NFS-e entrada e saída via papel).
3. Se NF-e de entrada: DistDFe + eventual unlock de full.
4. Se NF-e/NFC-e de **saída**:
   - genérico: exportar XML do ERP e importar (Documentos / import saídas);
   - **UF=MA**: perfil outbound + semente + consulta por `nNF` (descoberta de chave) + pacote oficial assistido até haver M2M/ERP automático — ver `ma-outbound-xml-auto-discovery.md`.
5. CT-e/MDF-e: ligar flags só após smoke.

## Status de implementação (change `capture-entrada-saida-completo`)

| Área | Status |
|------|--------|
| Direction em projeções + API/UI | Implementado |
| Import saídas NF-e/NFC-e | Implementado |
| Prefer full no download | Implementado |
| Unlock ciência SEFAZ | API unlock + client (ver change de manifestação); smoke B.3 no pilot |
| CT-e DistDFe | Implementado (flag off por default) |
| MDF-e | Desconsiderado nesta change |
