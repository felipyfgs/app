# Baseline de vazamento de segredos — canal autXML e import em massa

**Change:** `add-office-autxml-and-bulk-xml-import` · task 1.8  
**Data:** 2026-07-15  
**Uso:** detectar regressões de XML, PFX, senha, PEM, stack trace, path temporário ou referência de vault em logs/respostas/métricas.

## Superfícies a monitorar

| Superfície | Exemplos de vazamento proibido |
|------------|--------------------------------|
| Respostas JSON da API | `vault_object_id`, XML bruto, PEM, senha, path `/tmp/…` |
| Logs (`storage/logs`, Docker) | PFX base64, senha, stack trace com conteúdo fiscal |
| Payloads de fila (Horizon/Redis) | bytes de XML, path de spool, secretos |
| Auditoria (`audit_logs`) | fingerprint ok; nunca PFX/senha/PEM/`vault_object_id` |
| Métricas/labels | chave de acesso completa como label de alta cardinalidade |
| Frontend (DOM/toast/console) | XML, stack PHP, paths, segredos |
| Fixtures de CI | certificado real de homologação/produção |

## Padrões proibidos (scanner)

Strings / regex baselined para testes e revisão:

```
-----BEGIN
PRIVATE KEY
BagData
#PKCS12
application/x-pkcs12
vault_object_id
/tmp/
sys_get_temp_dir
password
pfx_password
-----END CERTIFICATE-----
```

Além de:

- trechos literais de fixtures `procNFe`/`nfeProc` em logs de erro;
- `stack` / `trace` / `exception` com `file_get_contents` de PFX;
- paths absolutos de spool (`storage/app/import-spool`, vault disk).

## Baseline atual (pré-implementação)

| Check | Estado observado |
|-------|------------------|
| `DocumentImportController` | Retorna contadores e metadados de item (filename sanitizado relativo); sem vault id |
| Credenciais de cliente | API de metadados sem download de PFX (padrão existente) |
| DistDFe job | Logs com cStat/xMotivo; não deve logar `docZip` |
| Testes MA / import | Fixtures sintéticas (sem A1 real) |

## Como revalidar

1. Rodar testes de feature que assertam ausência de padrões em JSON de erro/sucesso.
2. Após smoke local, `grep` em logs do container `php`/`horizon` pelos padrões acima.
3. Revisar payload de jobs enfileirados (somente IDs opacos).

## Resultado esperado desta change

Nenhuma superfície listada expõe o material acima. Falhas de decode/processamento usam códigos estáveis e mensagens sanitizadas.
