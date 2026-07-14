# ADR 003 — Cofre de objetos com criptografia de envelope

## Status

Aceito

## Contexto

Certificados A1, senhas e XMLs fiscais são altamente sensíveis. Criptografia apenas no filesystem do host ou materialização de PEM em disco expõe a chave privada. Backups de banco não devem conter a chave mestra.

## Decisão

- Interface `SecureObjectStore` com adaptador de filesystem privado no MVP.
- Envelope: chave de dados aleatória por objeto + XChaCha20-Poly1305; chave de dados embrulhada por `VAULT_MASTER_KEY` versionada.
- PFX e senha formam um único payload criptografado; uso em memória no transporte ADN.
- Sem rota de recuperação de certificado; metadados públicos (titular, validade, fingerprint, CNPJ) podem ser expostos conforme policy.
- Backup/restauração de objetos criptografados separado do procedimento da chave mestra.

## Consequências

- Perda da chave mestra torna objetos irrecuperáveis — exige procedimento operacional documentado.
- Migração futura para outro backend de objetos (ou KMS) passa pela interface, sem reescrever o domínio.
- Comprometimento do processo com acesso simultâneo à chave ainda é risco de host; criptografia protege repouso e backups comuns.
