# Matriz de compatibilidade CT-e (task 1.6)

**Change:** `complete-cte-capture-with-distdfe-autxml-and-import`  
**Data:** 2026-07-15

Fontes: NT 2015.002 (CTeDistribuicaoDFe), leiautes públicos de distribuição e `cteProc`, comportamento documentado de `autXML` no Ambiente Nacional.

## Distribuição (distDFeInt / retDistDFeInt)

| Item | Valor suportado | Notas |
|------|-----------------|-------|
| Serviço | `CTeDistribuicaoDFe` / `cteDistDFeInteresse` | SOAP 1.2 |
| Namespace WSDL | `http://www.portalfiscal.inf.br/cte/wsdl/CTeDistribuicaoDFe` | config `sefaz.cte.namespace` · env `SEFAZ_CTE_NAMESPACE` |
| SOAPAction | `…/CTeDistribuicaoDFe/cteDistDFeInteresse` | `sefaz.cte.soap_action` · `SEFAZ_CTE_SOAP_ACTION` |
| Endpoint produção (default) | `https://www1.cte.fazenda.gov.br/CTeDistribuicaoDFe/CTeDistribuicaoDFe.asmx` | `sefaz.cte.production` · `SEFAZ_CTE_DISTDFE_URL` |
| Endpoint homologação (default) | `https://hom1.cte.fazenda.gov.br/CTeDistribuicaoDFe/CTeDistribuicaoDFe.asmx` | `sefaz.cte.homologation` · `SEFAZ_CTE_DISTDFE_URL_HOM` |
| Layout distribuição | **1.00** | `sefaz.cte.layout_version` · `SEFAZ_CTE_LAYOUT_VERSION` |
| Consulta sequencial | `distNSU` / `ultNSU` (15 dígitos) | fluxo do Scheduler |
| Consulta pontual | `consNSU` / `NSU` (15 dígitos) | **somente reparo de NSU conhecido** |
| Consulta por chave | **não existe** (`consChCTe`) | proibido no produto |
| Identidade | CNPJ completo 14 (numérico ou alfanumérico uppercase) | CPF se aplicável no contrato |
| Ambiente | `tpAmb` 1 produção / 2 homologação | |
| cUFAutor | UF do autor da consulta (padrão config) | |
| Runtime | Cliente próprio mTLS (`HttpSefazCteDistDfeClient`) | **sem** lib comunitária de CT-e como transporte |
| Confirmação live | Smoke/readiness task **3.9** | `docs/ops/cte-prod-smoke-runbook.md` — **PENDING** até execução humana |

### cStat de resposta (canal)

| cStat | Significado operacional | Ação do sistema |
|-------|-------------------------|-----------------|
| 138 | Documentos localizados | Persistir página → avançar para `ultNSU` |
| 137 | Nenhum documento | Quiet ≥1h; não avançar além do retornado se vazio |
| 108 / 109 | Serviço paralisado | Retryable / quiet |
| 593 | Certificado não vinculado ao CNPJ | Permanente / bloquear stream |
| 656 | Consumo indevido | Circuito por CNPJ-base+ambiente; proibir retry precoce |

## Documentos distribuídos (docZip / schema)

| schemaFamily | Leiaute típico | Tratamento |
|--------------|----------------|------------|
| `procCTe` / `cteProc` | **3.00** e **4.00** | Documento principal modelo **57**; projeção completa |
| `CTe` | envelope sem prot (raro em dist) | Parse tolerante; promover só com protocolo quando exigido |
| `resCTe` | resumo | Marcar `is_summary`; não equivale a XML completo |
| `procEventoCTe` / `retEventoCTe` | eventos protocolados | Segunda passagem; vincular por chave/tipo/seq |
| desconhecido / bem-formado | XSD futuro | `parse_status=REVIEW`; preservar bytes; NSU avança só após custódia |

### Modelo

| Modelo | Escopo desta change |
|--------|---------------------|
| **57** (CT-e) | Completo (papéis, projeção, import, autXML) |
| 67 (CT-e OS) e demais | `REVIEW` / `UNSUPPORTED` — preservar, sem projeção 57 |

## Papéis no `cteProc` (modelo 57)

| Grupo XML | Papel domínio | Direção no cliente |
|-----------|---------------|--------------------|
| `emit` | `ISSUER` | `OUT` (somente canais emitente: autXML/import/push — **não** DistDFe do próprio CNPJ) |
| `rem` | `SENDER` | `IN` |
| `dest` | `RECIPIENT` | `IN` |
| `exped` | `EXPEDITOR` | `IN` |
| `receb` | `RECEIVER` | `IN` |
| `toma3` (código) / `toma4` (explícito) | `TAKER` | `IN` |
| `autXML` | autorização de terceiro | comprova escritório no canal central; não é papel do cliente |

## Qualidade do artefato e assinatura

| Origem | Qualidade típica | Assinatura |
|--------|------------------|------------|
| DistDFe dos 5 papéis não-emitentes | `ORIGINAL` | `VALID` se XMLDSig ok |
| Import / push de `cteProc` íntegro | `ORIGINAL` | `VALID` / `INVALID` |
| autXML sem redação `999...` | `AUTXML_ORIGINAL` | `VALID` se ok |
| autXML com chaves 44×`9` nos grupos previstos | `AUTXML_REDACTED` | `VALID` ou `NOT_VERIFIABLE_OFFICIAL_REDACTION` (smoke decide) |
| alteração não oficial / bytes divergentes | quarentena | não promove canônico |

## Eventos suportados (projeção)

| Tipo típico | Efeito na projeção |
|-------------|--------------------|
| Cancelamento (ex.: 110111) | status `CANCELLED` no CT-e pai |
| Outros protocolados | preservar + metadados; status derivado quando mapeado |
| Evento antes do pai | quarentena resolvível; sem descartar NSU |

## Compatibilidade com código legado

| Comportamento antigo | Pós-change |
|----------------------|------------|
| Fallback `TAKER` quando papel desconhecido | **Removido** → quarentena |
| `ISSUER/OUT` via DistDFe do próprio cliente | **Proibido** → `UNEXPECTED_OWN_ISSUER_DOCUMENT` |
| Único `fiscal_role` na projeção | projeção compatível; **autoridade** nos interesses multi-papel |
| Só `distByNsu` | alias/`distByLastNsu` + `findByNsu` |
