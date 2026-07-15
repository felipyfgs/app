# Fixtures CT-e (sanitizadas)

**Change:** `complete-cte-capture-with-distdfe-autxml-and-import` · task 1.5  
**Uso:** testes unitários/feature de parser, papéis, autXML, redação e import.  
**Regra:** nenhum certificado real, PFX, senha ou CNPJ de produção. Identidades ficcionais de 14 caracteres.

## Identidades de teste (canônicas)

| Papel | CNPJ fixture |
|-------|--------------|
| Emitente transportadora | `11222333000181` |
| Remetente | `11111111000111` |
| Destinatário | `22222222000122` |
| Expedidor | `33333333000133` |
| Recebedor | `44444444000144` |
| Tomador (toma4) | `34194865000158` |
| Escritório em `autXML` | `55666777000155` |
| CNPJ alfanumérico (teste) | `AB12345670001C` / `CD98765430001E` |

Chaves de acesso: 44 caracteres alfanuméricos sintéticos (não validadas contra DV real salvo quando o teste exigir).

## Arquivos

| Arquivo | Conteúdo |
|---------|----------|
| `procCTe_57_roles_all.xml` | cteProc 4.00 com rem/dest/exped/receb/toma4 distintos |
| `procCTe_57_toma3_rem.xml` | toma3 código remetente |
| `procCTe_57_issuer_only.xml` | apenas emit (cenário DistDFe do emitente — quarentena) |
| `procCTe_57_autxml_original.xml` | autXML do escritório, sem 44 noves |
| `procCTe_57_autxml_redacted.xml` | autXML com referência NF-e `999...` |
| `procCTe_57_alphanumeric_cnpj.xml` | emit/tomador com CNPJ alfanumérico |
| `procEventoCTe_cancel.xml` | cancelamento protocolado |
| `resCTe_summary.xml` | resumo |
| `dist_envelope_138_sample.xml` | envelope SOAP sanitizado cStat 138 (sem Base64 real grande) |

Modelo: **57**. Eventos e schemas 3.00/4.00 cobertos no nome/namespace.
