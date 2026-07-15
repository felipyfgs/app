# ADR 006 — Drivers por capacidade SERPRO e proveniência fiscal

## Status

Aceito (change `align-serpro-protocol-and-sitfis-monitoring`)

## Contexto

O núcleo Integra Contador já existe atrás de interfaces (`IntegraContadorClient`, `SerproContractAuthenticator`), mas o transporte divergia do protocolo oficial e misturava códigos internos com coordenadas SERPRO. Ainda não há contrato comercial nem smoke real. Precisamos desenvolver com determinismo, sem alegar validação produtiva, e sem permitir que simulação ou legado sem prova representem estado fiscal oficial.

Relacionado: [ADR 005](./005-control-plane-vs-data-plane.md) (controle vs dados), [ADR 003](./003-secure-object-vault.md) (cofre).

## Decisão

### 1. Driver por capacidade, sem fallback

Cada capacidade (ex.: SITFIS, Autentica Procurador) seleciona **exatamente um** driver:

| Driver | Uso |
|--------|-----|
| `disabled` | Capacidade desligada; falha fechada antes do transporte |
| `simulated` | Simulador determinístico **somente fora de produção** |
| `real` | Cliente HTTP oficial; exige contrato saudável |

Não há fallback automático entre drivers. Boot/preflight de produção **rejeita** `simulated`. `real` sem autenticação/representação válida falha fechado.

### 2. Identidade de domínio vs coordenadas de fio

- **`operation_key`**: identidade estável de domínio (jobs, ledger, APIs internas).
- **Coordenadas SERPRO**: manifesto versionado → projeção no banco; resolvidas no transporte, nunca aceitas do frontend.

Inventário completo (119 entradas oficiais) ≠ suporte. Estados da plataforma: `INVENTORIED` | `SIMULATED` | `IMPLEMENTED` | `PRODUCTION_VALIDATED`. Nesta change, apenas autenticação/representação e as duas operações SITFIS avançam além de inventariado; `PRODUCTION_VALIDATED` exige change posterior com smoke real.

### 3. Proveniência como invariante

Runs, evidências e snapshots registram proveniência `SIMULATED` | `SERPRO_REAL` | `UNVERIFIED` e estado de verificação. A origem é definida pelo driver no início do run e **não** pode ser promovida por payload. Legado sem prova migra para `UNVERIFIED` (auditável, fora do “estado atual” oficial).

### 4. Não-objetivos desta decisão

- Backoffice comercial, cobrança, planos ou impersonação de suporte (evolução futura própria).
- Simulador exposto a escritórios clientes em produção.
- Implementar os demais serviços inventariados como adapters reais.
- Declarar compatibilidade produtiva sem contrato e smoke.

## Alternativas rejeitadas

- **Cliente V2 paralelo** — permite uso acidental do legado.
- **Fallback real → simulado** — mascara indisponibilidade e contamina evidência.
- **Presumir legado como real ou apagá-lo** — esconde risco ou perde auditoria.

## Consequências

- Config por capacidade em `config/serpro.php` (ou equivalente); testes de preflight de produção.
- Manifesto versionado + importador idempotente; contract tests por versão de catálogo.
- APIs/UI expõem proveniência sanitizada; simulação não conta no ledger faturável.
- Ativação `real` em produção permanece gateada por contratação, evidência comercial e smoke.
