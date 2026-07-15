# Fixtures autXML (sanitizadas)

XML estruturais **sem** certificado real, **sem** assinatura ICP-Brasil e **sem** dados de produção.
Uso exclusivo em testes unitários/feature (CI). Modelos:

| Arquivo | Cenário |
|---------|---------|
| procNFe_55_autxml_ok.xml | Tag autXML com CNPJ do escritório |
| procNFe_55_autxml_multi.xml | Múltiplos autXML (incl. alfanumérico) |
| procNFe_55_autxml_missing.xml | Tag ausente |
| procNFe_55_autxml_divergent.xml | Tag divergente |
| procNFe_65_nfce_not_autxml_channel.xml | NFC-e 65 (não DistDFe autXML) |
| resNFe_summary_only.xml | Somente resumo |

Nunca commitar PFX/P12/PEM reais nesta pasta.
