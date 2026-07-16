# Gates futuros — alinhamento protocolo SERPRO / SITFIS

Change: `align-serpro-protocol-and-sitfis-monitoring` (2026-07-15).

## Concluído nesta change

- Manifesto oficial 119 entradas + validador/importador
- Coordenadas SITFIS 2.0 (`SOLICITARPROTOCOLO91` / `RELATORIOSITFIS92`, poder `00002`)
- OAuth oficial (`access_token` + `jwt_token`), headers e rotas funcionais
- Drivers por capacidade (`disabled` / `simulated` / `real`) com bloqueio de simulação em produção
- Termo com XMLDSig; estados LOCAL_VALIDATED ≠ SERPRO_ACCEPTED
- Proveniência SIMULATED / SERPRO_REAL / UNVERIFIED em runs/evidências/snapshots
- Ledger: rotas/status não faturáveis e simulações sem consumo
- UI Settings/SITFIS com estados acionáveis e proveniência sanitizada

## Gates que permanecem externos (não fecham nesta change)

1. **Contratação SERPRO Integra Contador** — evidência comercial/jurídica formal
2. **Credenciais reais** no cofre (e-CNPJ contratante, Consumer Key/Secret, mTLS)
3. **Smoke somente leitura** em homologação/produção com Termo real aceito
4. **Conciliação** de fatura oficial vs ledger (preço permanece DESCONHECIDO até tabela contratual)
5. **`PRODUCTION_VALIDATED`** no catálogo — exige change posterior com smoke real
6. Backoffice comercial, cobrança, planos e impersonação de suporte — evolução futura própria

## Ativação segura

```
# Produção: SITFIS desabilitado por padrão
SERPRO_CAPABILITY_SITFIS=disabled
SERPRO_CAPABILITY_AUTENTICA_PROCURADOR=disabled
SERPRO_USE_FAKE_CLIENTS=false
```

Após smoke: `SERPRO_CAPABILITY_SITFIS=real` e revalidar OpenSpec/ops.
