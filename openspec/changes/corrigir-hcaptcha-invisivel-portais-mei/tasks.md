## 1. N0 — Base e ownership confirmados

- [x] 1.1 Confirmar que o `verify` de `automatizar-servicos-publicos-mei` continua concluído, registrar a baseline dos testes do `services/mei/` e reservar ownership dos arquivos compartilhados antes de editar.
  Depende de: change `automatizar-servicos-publicos-mei`, marco `verify` (relação bloqueante).

## 2. N1 — Sinais e fixtures determinísticos

- [x] 2.1 Substituir a detecção booleana por estados que diferenciem integração passiva e desafio efetivo, com testes unitários para widget/iframe oculto, desafio visível e mensagem explícita.
  Depende de: 1.1.
- [x] 2.2 Criar fixtures sanitizadas PGMEI e DASN-SIMEI para auto-pass, desafio, validação e ausência de checkpoint, incluindo contadores que comprovem uma única submissão.
  Depende de: 1.1.

## 3. N2 — Máquina de identificação e operação guardada

- [x] 3.1 Implementar helper de identificação com clique único e deadline semântico para sucesso, validação, desafio e drift, incluindo solver autorizado no mesmo contexto e testes isolados sem rede.
  Depende de: 2.1, 2.2.
- [x] 3.2 Documentar no `services/mei/README.md` o comportamento do hCaptcha invisível e um procedimento de probe live com egress, operação e CNPJ de teste explicitamente autorizados, saída sanitizada e limite de uma tentativa.
  Depende de: 2.1.

## 4. N3 — Handlers públicos adaptados

- [x] 4.1 Migrar os fluxos PGMEI de emissão e dívida ativa para o helper, remover o clique de identificação posterior e testar auto-pass, desafio, drift, `submitted=false` pré-mudança e ausência de replay após emissão.
  Depende de: 3.1.
- [x] 4.2 Migrar a consulta DASN-SIMEI para o helper, remover o clique de identificação posterior e testar auto-pass, desafio, validação, drift e submissão única.
  Depende de: 3.1.

## 5. N4 — Regressão integrada do contrato

- [x] 5.1 Cobrir PGMEI e DASN-SIMEI em testes live-shaped locais, comprovando zero chamadas ao solver no auto-pass, no máximo um job externo no desafio autorizado, nenhum segundo submit e ausência de dados sensíveis nos resultados/logs exercitados.
  Depende de: 4.1, 4.2.

## 6. N5 — Gates e evidências de prontidão

- [x] 6.1 Executar `pytest`, `ruff check`, `mypy` do `services/mei/` e a validação OpenSpec, registrando todos os comandos e resultados sem depender do portal live.
  Depende de: 5.1.
- [x] 6.2 Revisar o diff e produzir evidência sanitizada dos quatro desfechos; se houver CNPJ de teste e autorização explícita, anexar também um único probe live, sem tornar sua ausência motivo para relaxar defaults ou bloquear os gates offline.
  Depende de: 5.1.
