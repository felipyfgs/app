## 1. Contrato e backend

- [x] 1.1 Registrar fixture sanitizada e testes do request `anoCalendario`,
  coordenada 104, Base64 válida/inválida e limites, sem HTTP real. Grok:
  `019f7389-9916-7571-904a-462387b3e34a` (delegado).
- [x] 1.2 Criar alias, catálogo, codec, adapter e projeção idempotente com
  evidência no cofre; manter 101/102/103 inalteradas. Grok: delegado.
- [x] 1.3 Expor POST explícito, GET local e download autorizado por
  `CurrentOffice`, recusando `office_id`. Grok: delegado.

## 2. UI

- [x] 2.1 Adicionar composable, tipos e modal de histórico local, sem texto
  Base64 ou acesso ao cofre. Grok: revisão solicitada antes do merge.
- [x] 2.2 Adicionar confirmação de coleta potencialmente faturável e download
  same-origin. Grok: revisão solicitada antes do merge.

## 3. Verificação

- [x] 3.1 Rodar Pint e testes Laravel focais de auth, tenancy, codec,
  idempotência e download fake/simulated. Grok: execução delegada.
- [x] 3.2 Rodar lint, testes Nuxt, typecheck, geração e artefatos. Grok:
  execução/revisão delegada.
- [x] 3.3 Revisar `git status`, `git diff --check`, OpenSpec e atualizar a
  matriz com ambiente, resultado e sessões Grok.
