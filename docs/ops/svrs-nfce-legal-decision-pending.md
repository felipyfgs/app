# Decisão operacional/jurídica — SVRS NFC-e (pendente)

**Change:** `add-svrs-nfce-outbound-xml-retrieval` · task 14.1  
**Status:** **PENDENTE** — bloqueia auto-queue e piloto em produção.

## Itens a registrar antes do primeiro auto-queue

1. Autorização escrita ou parecer sobre uso automatizado do formulário SVRS.
2. Limites de taxa assumidos (design D6) e canal de contato SVRS/SEFAZ-MA.
3. Mandato/referência do escritório piloto e raízes allowlisted.
4. Aceite de que indisponibilidade/mudança de HTML = fallback assistido, sem SLA.

Até esta decisão, manter:

```
SEFAZ_SVRS_NFCE_XML_RETRIEVAL_ENABLED=false
SEFAZ_SVRS_NFCE_XML_AUTO_QUEUE_ENABLED=false
```

Smoke mTLS restrito (14.2) só com chave allowlisted e **sem** persistência automática.
