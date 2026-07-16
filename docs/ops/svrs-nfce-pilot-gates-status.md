# Status dos gates de piloto — SVRS NFC-e XML

**Change:** `add-svrs-nfce-outbound-xml-retrieval`  
**Data:** 2026-07-15  
**Ambiente:** implementação local / CI (sem A1 fiscal real)

## Resumo

| Task | Descrição | Status |
|------|-----------|--------|
| 14.1 | Decisão operacional/jurídica | **PENDENTE** — ver `svrs-nfce-legal-decision-pending.md` |
| 14.2 | Smoke mTLS restrito allowlisted | **BLOQUEADO** por 14.1 + ausência de chave/A1 real de piloto |
| 14.3 | Ingestão manual chave piloto | **BLOQUEADO** (14.2) |
| 14.4 | Comparação bytes vs PDV | **BLOQUEADO** (amostra real) |
| 14.5 | NFC-e cancelada | **BLOQUEADO** (documento real) |
| 14.6 | Piloto 1 raiz / auto-queue off | **BLOQUEADO** (14.1–14.3) |
| 14.7 | Ativar auto-queue | **BLOQUEADO** |
| 14.8 | Acompanhamento piloto | **BLOQUEADO** |
| 14.9 | Ampliar allowlist | **BLOQUEADO** |
| 14.10 | Matriz de cobertura | **FEITO** — `document-coverage-matrix.md` |

## Flags obrigatórias até liberação

```
SEFAZ_SVRS_NFCE_XML_RETRIEVAL_ENABLED=false
SEFAZ_SVRS_NFCE_XML_AUTO_QUEUE_ENABLED=false
SEFAZ_SVRS_NFCE_XML_PILOT_ALLOWLIST_ONLY=false
SEFAZ_SVRS_NFCE_XML_KILL_SWITCH=false
```

Implementação, testes com fixtures e UI estão prontos **atrás das flags**.  
Nenhum smoke com dados fiscais reais foi executado nesta sessão.

## Critério para desbloquear 14.2+

1. Parecer/decisão em `svrs-nfce-legal-decision-pending.md` (status APROVADO).
2. Uma raiz MA allowlisted + A1 válido **fora** do repositório.
3. Uma chave NFC-e 65 de teste/piloto.
4. Executar smoke com `RETRIEVAL_ENABLED=true`, `AUTO_QUEUE=false`, sem persistência automática no primeiro passo.
5. Registrar evidência sanitizada (sem CNPJ/chave/XML) em `docs/ops/svrs-nfce-smoke-*.md`.

## Handoff implementação NFE-55 resiliente (2026-07-16)

Tasks 13.x/14.x de smoke/piloto **READY_FOR_PILOT** (sem chamada SVRS real neste repositório).  
Defaults: auto-queue OFF, budgets preventivos, kill switch testável em código.  
Archive autorizado após sync de specs; ampliação de allowlist exige evidência formal.

