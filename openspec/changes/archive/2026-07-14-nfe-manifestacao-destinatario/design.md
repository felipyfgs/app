## Context

- Papel do produto: **inbound de XML + entrega** (catálogo `/docs`, download, ZIP), interno do escritório contábil.
- DistDFe piloto (client 8) já grava resumos e alguns fulls; resumos pedem **ciência** para desbloquear `procNFe` de forma consistente.
- O escritório **não quer** o fluxo principal “manifestar na SEFAZ”; quer o arquivo. MD-e conclusiva fica **disponível** se alguém precisar.

## Goals / Non-Goals

**Goals:**
1. Maximizar **XML completo no vault** para download/export.
2. Ciência (210210) como **desbloqueio técnico** (manual e/ou job opt-in por cliente).
3. API/UI **opcional** para conclusivas (210200/220/240) e ciência manual.
4. Flag off por default; smoke só o necessário no piloto 8.

**Non-Goals:**
- Inbox obrigatória de “pendências de manifestação fiscal”.
- Automação de desconhecimento/confirmação.
- Treinar o usuário a “sempre desconhecer depois da ciência”.
- Substituir o ERP fiscal do cliente.

## Product framing

```
┌─────────────────────────────────────────┐
│  Primário: CAPTURAR → GUARDAR → ENTREGAR │
│  DistDFe → vault → /docs download/export │
└──────────────────┬──────────────────────┘
                   │ se só resumo
                   ▼
┌─────────────────────────────────────────┐
│  Técnico: CIÊNCIA (210210) + reconsulta  │
│  (opt-in / sob demanda) → procNFe        │
└──────────────────┬──────────────────────┘
                   │ se o operador quiser
                   ▼
┌─────────────────────────────────────────┐
│  Opcional: conclusivas MD-e              │
│  confirmação / desconhecimento / etc.    │
└─────────────────────────────────────────┘
```

## Decisions

### D1 — Ciência a serviço da entrega de XML

- Ciência existe **porque** a SEFAZ trava o full do destinatário, não porque o produto seja de MD-e.
- Preferências de produto:
  1. Se DistDFe já trouxe `procNFe` → **não** enviar ciência.
  2. Se só `resNFe` e flag on → permitir **“Obter XML completo”** (dispara ciência + reconsulta) com copy clara: *não confirma a operação*.
  3. Auto-ciência por cliente: opt-in, só notas dentro do prazo de 10 dias, só se full ausente.

### D2 — Conclusivas = capacidade, não jornada default

- Confirmação / desconhecimento / não realizada: endpoints + UI em seção **“Manifestação (opcional)”**.
- Sem chips de triagem forçando conclusiva no home.
- Sem bulk de conclusiva no MVP desta change.
- Copy: impacto fiscal é do **contribuinte**; o painel só envia se o operador pedir.

### D3 — Cliente SEFAZ e segurança

- `SefazNfeManifestationClient` próprio; mTLS A1 do **cliente**; sem PEM em disco.
- Fila `manifest-nfe` separada do DistDFe de captura.

### D4 — Estados (entrega-first)

| Status interno | Significado para o usuário |
|----------------|----------------------------|
| `SUMMARY_ONLY` / resumo | XML completo ainda não no vault |
| `FETCHING_FULL` | Ciência enviada / reconsulta em curso |
| `FULL_AVAILABLE` | Pronto para download/export (**sucesso de produto**) |
| `OPTIONAL_*` conclusiva | Só se usaram a capacidade opcional |

Manter `manifestation_status` alinhado, mas labels de UI priorizam **“XML completo”** vs **“somente resumo”**.

### D5 — API

```
POST /api/v1/documents/{accessKey}/manifestations
  { "type": "CIENCIA" | "CONFIRMACAO" | "DESCONHECIMENTO" | "NAO_REALIZADA",
    "justification"?: string,
    "purpose"?: "UNLOCK_XML" | "FISCAL" }   // opcional, default UNLOCK_XML para CIENCIA

GET detalhe: is_summary, has_full_xml, manifestation_status, download_url
```

### D6 — Reconsulta

- Após ciência: job tenta obter `procNFe` (cursor ou consChNFe com throttle).
- Sucesso = XML no vault + download liberado full.
- Timeout: status de “XML ainda não disponível; tente depois / reconsultar” — **sem** empurrar desconhecimento.

### D7 — Piloto 8

- Habilitar flag só no ambiente local/piloto.
- Smoke: 1 chave só-resumo → “Obter XML completo” → verificar download `procNFe`.
- **Não** executar desconhecimento em nota comercial real só para testar.

## Risks / Trade-offs

| Risco | Mitigação |
|-------|-----------|
| Usuário achar que ciência = confirma compra | Copy explícita no botão e no modal |
| Escopo voltar a “produto de MD-e” | Tasks e UI: download first |
| 656 por reconsulta | Rate limit; preferir DistDFe sequencial |
| Auto-ciência indesejada | Opt-in por cliente; default off |

## Migration Plan

1. Flag off → deploy.
2. Smoke unlock XML no client 8.
3. Conclusivas opcionais na UI (pode ser mesmo PR ou follow-up curto).
4. Rollback: flag off (eventos já aceitos na SEFAZ permanecem).

## Open Questions

- Auto-ciência default no piloto: **off** (botão “Obter XML completo” basta no início).
- Expor conclusivas já no primeiro apply: **sim, API + UI secundária**, sem automatizar.
