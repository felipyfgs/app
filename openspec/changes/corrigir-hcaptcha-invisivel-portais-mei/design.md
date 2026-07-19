## Context

Os handlers PGMEI e DASN-SIMEI hoje carregam a página, validam o formulário, preenchem o CNPJ e chamam `captcha_required()`. Essa função retorna verdadeiro para qualquer `.h-captcha` ou iframe do fornecedor. Nos portais atuais, porém, esses elementos fazem parte da integração invisível carregada desde o início; o desafio só pode ser decidido pelo hCaptcha após a ação normal do usuário. Como o solver manual é fail-closed, o fluxo retorna `CAPTCHA_EXHAUSTED` antes do primeiro clique e nunca permite a aprovação automática do portal.

A correção permanece em `services/mei/`, reutiliza o browser/contexto efêmero e os gates já implementados e não altera a postura de segurança. O `docapi` serviu apenas para confirmar que uma navegação comum pode deixar o callback invisível seguir seu curso; suas flags de anti-detecção não são parte desta solução nem evidência de que o CAPTCHA sempre será aprovado.

## Goals / Non-Goals

**Goals:**

- Diferenciar integração passiva de hCaptcha, desafio efetivamente visível e sucesso automático inferido por checkpoint do portal.
- Fazer exatamente uma ação de submissão da identificação por execução e classificar seu resultado com deadline limitado.
- Compartilhar a mesma máquina de estados entre PGMEI e DASN-SIMEI, deixando seletores de sucesso e validação específicos em cada handler.
- Preservar `submitted=false` enquanto nenhuma ação fiscal mutante tiver começado e impedir replay após `submitted=true`.
- Cobrir o comportamento com páginas locais determinísticas e permitir smoke live somente sob guardas explícitas.

**Non-Goals:**

- Burlar CAPTCHA, reduzir deliberadamente sinais de automação ou adicionar `--disable-blink-features=AutomationControlled`, fingerprint spoofing ou sessão humana remota.
- Garantir que todo CNPJ ou ambiente será aprovado sem desafio.
- Habilitar NoPeCHA, live egress, operações ou orçamento por padrão.
- Repetir automaticamente a submissão de identificação, recarregar a página para buscar outro score ou ressubmeter uma emissão de DAS.
- Consultar Optantes, ampliar o catálogo de operações, transmitir DASN-SIMEI ou emitir outra mutação fiscal.
- Registrar CNPJ, sitekey, token, payload do CAPTCHA, cookies ou HTML integral em logs/telemetria.

## Decisions

1. **Modelar estado do CAPTCHA, não presença de seletor.** Um detector retorna estados internos equivalentes a `ABSENT`, `INTEGRATION_READY` e `CHALLENGE_ACTIVE`. `.h-captcha`, textarea de resposta e iframe invisível indicam apenas `INTEGRATION_READY`; `CHALLENGE_ACTIVE` exige evidência interativa/visível, como frame de desafio visível e habilitado ou mensagem explícita de falha do CAPTCHA. A aprovação automática não depende de introspecção do fornecedor: ela é inferida quando o checkpoint de sucesso do portal vence a corrida. Alternativa rejeitada: remover toda detecção e aguardar apenas timeout, pois isso esconderia desafios reais e tornaria o erro menos acionável.

2. **Centralizar a submissão de identificação numa máquina de estados com clique único.** Um helper recebe o botão, checkpoints de sucesso, marcadores de validação e deadline. Depois do preenchimento e das validações locais, ele registra internamente a intenção e clica uma única vez. Em seguida aguarda, sem `networkidle`, o primeiro resultado semântico: sucesso, validação suportada, desafio ativo ou deadline. PGMEI deixa de clicar `#continuar` em `_open_emission()` e `_active_debt()`; DASN-SIMEI deixa de clicar `#identificacao-continuar` depois de `_identify()`. Alternativa rejeitada: manter o clique espalhado nos handlers, pois facilita regressão com clique duplo e decisões de CAPTCHA divergentes.

3. **Solver somente após evidência de desafio efetivo.** Se `CHALLENGE_ACTIVE` vencer a corrida, o contrato atual de flags, operação allowlisted, chave, custo e orçamento decide se um único job externo pode ser criado. Sem autorização ou resolução válida, o resultado é `CAPTCHA_EXHAUSTED`. Quando autorizado, o token é aplicado no mesmo contexto efêmero e o helper aguarda o callback/checkpoint pendente sem reload nem novo clique de submissão. O contrato não promete que todos os layouts do fornecedor aceitem retomada; ausência de checkpoint no deadline continua fail-closed. Alternativa rejeitada: resolver preventivamente todo widget invisível, pois aumenta custo, envia dados a terceiro sem necessidade e reproduz o falso positivo atual.

4. **Separar ação pública de identificação de efeito fiscal.** O helper pode manter `identification_attempted=true` apenas em memória e métricas de baixa cardinalidade, mas não altera o campo público `submitted`. Para falhas de identificação, CAPTCHA ou drift anteriores a `#btnEmitirDas`, o erro conserva `submitted=false`; somente o ponto de mutação fiscal já definido pode produzir `submitted=true`/`UNCERTAIN`. Alternativa rejeitada: reaproveitar `submitted` para o POST de identificação, porque isso bloquearia fallback seguro e confundiria consulta pública com emissão fiscal.

5. **Usar fixtures de fluxo, não mocks do hCaptcha remoto.** As páginas locais simulam widget passivo com transição para sucesso, desafio visível, validação e ausência de checkpoint. Contadores no DOM ou interceptação Playwright comprovam um único clique/submit. Testes unitários cobrem classificação do detector e testes de handler cobrem PGMEI e DASN-SIMEI de ponta a ponta sem rede. Um probe live é evidência adicional e só pode usar CNPJ de teste autorizado, flags/allowlist explícitas e saída sanitizada. Alternativa rejeitada: tornar o portal live requisito de CI, pois ele é instável, externo e pode exibir desafio por risco.

6. **Manter telemetria sanitizada e limitada.** Métricas/eventos podem expor apenas operação catalogada, estado final (`auto_approved`, `challenge`, `validation`, `drift`), uso/custo já previsto do driver e duração. Não há sitekey, token, CNPJ, conteúdo do desafio ou snapshot da página. Isso permite medir se a correção obtém aprovações reais sem aumentar a superfície de dados sensíveis.

## Mapa de dependências

```text
automatizar-servicos-publicos-mei (C1, verify concluído)
  └─ mei-public-portal-services
       └─ corrigir-hcaptcha-invisivel-portais-mei (C2)
            ├─ detector + máquina de estados
            ├─ adaptação PGMEI e DASN-SIMEI
            └─ fixtures/testes → gates integrados → smoke live opcional e guardado
```

- A upstream é bloqueante no marco `verify`, já satisfeito. Enquanto permanecer ativa, este change é dono apenas do delta de `mei-public-portal-services`; não reescreve seus artefatos, proposal, design ou tasks.
- Arquivos compartilhados previstos: `services/mei/src/mei/operations/captcha.py`, `pgmei.py`, `dasn.py` e seus testes/fixtures. Nenhuma implementação deve ocorrer em paralelo com edição ainda aberta desses mesmos arquivos pela upstream.
- Detector/helper e novas fixtures podem ser implementados em paralelo quando houver ownership de arquivos distinto; a adaptação dos handlers depende do contrato do helper. O gate integrado depende de todas as adaptações.
- Rollout preserva defaults OFF. Rollback restaura o código anterior e desliga live egress; nenhuma migração de dados ou reversão de esquema é necessária.

## Risks / Trade-offs

- [O fornecedor altera o DOM ou torna o desafio opaco] → detectar somente sinais positivos conhecidos, usar deadline e retornar `PORTAL_DRIFT`/`CAPTCHA_EXHAUSTED` sem tentar adivinhar sucesso.
- [O sucesso e o desafio aparecem quase juntos] → priorizar checkpoint de sucesso válido do portal; nunca considerar apenas token/callback como sucesso do negócio.
- [O clique envia a identificação, mas a resposta se perde] → não clicar novamente na mesma execução; encerrar fail-closed com `submitted=false` fiscal e permitir que a política externa decida uma nova tentativa independente.
- [Solver autorizado não retoma o callback pendente] → aguardar deadline no mesmo contexto e falhar sem segundo submit; adaptar callback somente com fixture e probe controlado que comprovem o contrato.
- [A classificação visual gera falso positivo] → exigir visibilidade/interatividade e testes para iframe/widget presente mas oculto.
- [Smoke live expõe identificador ou cria volume indevido] → executar apenas manualmente com dado autorizado, uma operação allowlisted, uma tentativa e relatório sanitizado; CI permanece offline.

## Migration Plan

1. Introduzir detector/helper e fixtures sem alterar defaults de egress ou solver.
2. Migrar PGMEI e DASN-SIMEI para o clique único e remover os cliques de identificação duplicados dos fluxos posteriores.
3. Rodar testes Python, lint/typecheck e testes de regressão que comprovem ausência de solver no auto-pass e ausência de replay.
4. Implantar com live egress desligado; habilitar primeiro em ambiente controlado e operação allowlisted.
5. Quando houver CNPJ de teste autorizado, executar uma única consulta live sanitizada e observar checkpoint/estado final. Falha ou desafio não autoriza flexibilizar as guardas.

Rollback: desligar live egress imediatamente e reverter os arquivos do change. Como não há migração persistente nem alteração de contrato externo obrigatório, jobs novos voltam ao comportamento anterior; jobs em execução devem terminar no contexto efêmero atual, sem replay.

## Open Questions

- Os seletores exatos de desafio visível e das mensagens de validação serão confirmados com fixtures baseadas no HTML público sanitizado e, quando autorizado, por probe live; sinais desconhecidos permanecem fail-closed.
- A retomada pós-token do hCaptcha invisível pode variar por portal. A implementação só dará suporte ao callback que for comprovado sem segundo submit; o restante continua `CAPTCHA_EXHAUSTED`.

