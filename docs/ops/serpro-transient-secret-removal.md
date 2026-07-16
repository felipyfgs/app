# Remoção segura de cópias transitórias (PFX/PEM/segredos)

Após importação verificada no vault, **não** deixe material em workspace, imagem Docker ou home do operador.

## Escopo

- Arquivos `.pfx`, `.p12`, `.pem`, `.key`
- Exports de token/Bearer
- PDF contratual com dados sensíveis
- Dumps SQL e cópias de `/var/vault`

## Procedimento (operador)

1. Confirmar fingerprint/metadados na API sanitizada (`has_pfx`, `fingerprint_sha256`).
2. Remover arquivo local:
   ```bash
   shred -u -n 3 /caminho/seguro/contrato.pfx 2>/dev/null || rm -f /caminho/seguro/contrato.pfx
   ```
3. Limpar lixeira/clipboard/histórico de shell se a senha foi colada (preferir prompt interativo).
4. Não commitar: `.gitignore` bloqueia `*.pfx`, `*.pem`, `secrets/`, vault e dumps.
5. Registrar auditoria da importação (já automática) — **sem** anexar o arquivo ao ticket.

## Build / CI

- Contexto Docker **não** deve copiar `secrets/`, `*.pfx`, vault host.
- `fiscal-model:secret-scan` e script `docker/ops/secret-scan.sh` falham em artefatos suspeitos no tree (nomes/caminhos, sem imprimir conteúdo).

## Pós-incidente

Se material transitório vazou em log/chat: seguir runbook de credencial comprometida e rotacionar.
