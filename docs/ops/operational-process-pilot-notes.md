# Notas de piloto — processos operacionais

## Limitações conscientes do MVP

- **Dias úteis / feriados:** regras usam apenas dias corridos no timezone do escritório.
- **Um departamento primário** por membership; múltiplos departamentos ficam para change futura.
- **Sem vínculo fiscal automático:** concluir tarefa não chama SERPRO/ADN/SEFAZ nem altera NSU/nNF.
- **Sem login de cliente final.**
- **Evidências:** allowlist PDF/PNG/JPEG/texto, teto 20 MiB, cofre cifrado; sem URL pública.
- **Export CSV** separado do ZIP fiscal; colunas allowlisted.

## Operação

- Seed local: `php artisan db:seed --class=OperationalWorkDemoSeeder`
- Métricas/logs: ações `work.*` no `AuditLogger` (contexto sanitizado).
- Retenção inicial sugerida: previews 30 min; exports CSV 48 h; evidências com quota configurável por escritório (definir com piloto).

## Smoke checklist (dois tenants)

1. Mesmo CNPJ/competência em dois offices — listagens e downloads não cruzam.
2. `PLATFORM_ADMIN` sem membership não acessa `/api/v1/work/*`.
3. `VIEWER` somente leitura; sem download de evidência nem export.
4. Geração concorrente: unique partial TEMPLATE evita duplicata.
5. Scanner de payload: sem `vault_object_id`, path, PFX, PEM, tokens.
