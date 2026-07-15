# Status formal SEFAZ-MA — recuperação de XML de saída

**Change:** `build-ma-outbound-nfe-nfce-capture` · task 1.2  
**Atualizado:** 2026-07-15

## Perguntas enviadas / a registrar

1. O contador autorizado consegue obter, na plataforma de download, os mesmos pacotes de cada cliente sem credencial humana compartilhada?
2. Os pacotes NF-e 55 e NFC-e 65 contêm sempre o `procNFe` original assinado e protocolado (inclusive cancelados)?
3. Existe contrato, documentação ou autorização escrita de **máquina-a-máquina** para solicitar/acompanhar/baixar pacotes de forma assíncrona?

## Resposta formal

**Ainda não há resposta formal escrita da SEFAZ-MA no repositório.**

Fontes públicas consultadas (notícia de plataforma de download, portais SVAN/SVRS) **não** descrevem API M2M estável nem autorização de automação de portal.

## Decisão registrada

| Código | Valor |
|--------|-------|
| `M2M_STATUS` | **`NO_GO_M2M`** |
| `SEFAZ_MA_M2M_RETRIEVAL_ENABLED` | `false` (default, invariante até contrato formal) |
| Canal permitido | Ingestão **assistida** (`ASSISTED`) de ZIP/XML obtido pelo operador no portal oficial |
| Proibido | RPA, scraping, automação Gov.br/SEFAZNET, CAPTCHA, cookie de sessão |

## Próximo passo

Quando houver ofício/e-mail/contrato da SEFAZ-MA autorizando M2M, atualizar este arquivo com referência, data e escopo; só então reavaliar G4.

## Descobertas técnicas (XML automático)

O que já se sabe sobre consulta vs. download de `procNFe`, DistDFe 641/618 e caminhos alternativos (ERP, autXML, ASSISTED) está em:

→ **`docs/ops/ma-outbound-xml-auto-discovery.md`**
