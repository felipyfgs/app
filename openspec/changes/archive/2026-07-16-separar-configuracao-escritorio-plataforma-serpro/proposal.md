## Why

O painel hoje mistura responsabilidades do escritório contábil com a contratação e a operação técnica da Integra Contador: o contador precisa informar dados internos do SERPRO, o certificado A1 é duplicado entre integrações e as telas `/settings` e `/admin` não refletem a separação desejada entre tenant e plataforma. Ao mesmo tempo, o produto ainda não possui uma regra comercial clara para as consultas dos monitores SERPRO nem o modelo de suporte privilegiado definido para administradores da plataforma.

Esta mudança estabelece uma única configuração institucional do escritório, automatiza internamente a autorização SERPRO, centraliza o A1 e transforma franquia e agendamento de monitores em regras explícitas de produto. Ela também formaliza a decisão de que administradores da plataforma podem operar qualquer escritório em contexto privilegiado, com rastreabilidade interna.

## What Changes

- Criar uma única área `/settings` do escritório para CNPJ, razão social, e-mail institucional, telefone institucional, consentimento técnico e credencial canônica e-CNPJ A1 (`.pfx`/`.p12` + senha).
- Reutilizar a credencial canônica por vínculo de finalidade autorizado, incluindo assinatura do Termo SERPRO e autXML, sem duplicar PFX ou senha e sem permitir leitura ou download do segredo.
- Automatizar, em agente interno tenant-scoped, a geração, assinatura, envio, renovação e sincronização do Termo/autorização SERPRO a partir do cadastro, do consentimento e do A1 do escritório.
- Remover do fluxo do contador campos e artefatos técnicos do SERPRO, incluindo autor do pedido, XML do Termo, tokens, OAuth, ETag, ambiente, poderes e credenciais globais; falhas técnicas ficam visíveis apenas para a plataforma, enquanto o escritório recebe somente pendências que consegue corrigir.
- Reservar `/admin/*` e as configurações do contrato plataforma–SERPRO para a plataforma; manter separados o contrato comercial SERPRO, o contrato/plano entre plataforma e escritório e o consentimento técnico para uso do A1.
- Sincronizar automaticamente as procurações já concedidas no e-CAC e exibir por cliente o estado `Autorizada`, `Sem procuração`, `Vencida` ou `Não verificada`, sem criação, importação ou override manual de procuração.
- **BREAKING**: permitir que todo `PLATFORM_ADMIN` selecione qualquer escritório e receba contexto privilegiado equivalente a `Office ADMIN`, sem associação fictícia, motivo ou sessão de acesso; registrar auditoria interna com administrador real, escritório e ação, sem expô-la ao escritório.
- **BREAKING**: retirar a exigência global de TOTP da navegação do `PLATFORM_ADMIN`; exigir reconfirmação de senha nas mutações fiscais e na substituição/remoção do A1. Flags, kill switches, orçamentos, limites e idempotência continuam obrigatórios e não podem ser ignorados.
- **BREAKING**: reservar `/admin/*` à plataforma e mover toda configuração do escritório para `/settings`, substituindo endpoints e componentes tenant-facing que hoje expõem a autorização técnica SERPRO.
- Definir franquia por cliente + monitor + período da assinatura: Starter com 5 consultas e até 100 clientes, Professional com 7 e até 150, Enterprise com 10 e até 200; acima de 200 clientes exige limite negociado configurado por `PLATFORM_ADMIN`, sem mudar o nome do plano.
- Dar a todos os planos acesso aos monitores implementados e habilitados; não vender créditos, excedentes ou rollover. A franquia se renova no aniversário da assinatura e consultas não usadas expiram.
- Conceder uma consulta inaugural gratuita por cliente + monitor após a ativação, registrada no ledger sem consumir a franquia mensal.
- Compartilhar a franquia entre consultas manuais e automáticas e executar, por padrão, uma consulta automática mensal por cliente + monitor. Cada monitor terá um dia 1–28 configurável para todo o portfólio do escritório, com distribuição determinística de carga e continuidade nos dias seguintes quando a fila não terminar no dia escolhido.
- Alertar no painel antes de repetir uma consulta recente, exibir o último horário e informar que a execução consome uma unidade; o servidor continua impondo intervalos oficiais e rate limits.
- Emitir no painel alertas de validade do A1 em 30, 7 e 1 dia, sem e-mail ou outro canal nesta entrega.
- Manter consultas/eventos NFS-e, SEFAZ e autXML em seus fluxos atuais de tempo real; a franquia comercial nova vale somente para monitores SERPRO.
- Marcar a implantação do acesso fiscal global como dependente de revisão jurídica de LGPD/sigilo fiscal, plano explícito de rollout e migração antes da ativação em produção.

### Non-goals

- Suportar e-CPF, A3 ou assinatura externa do Termo nesta entrega.
- Criar procurações no e-CAC, importar procurações manualmente ou permitir override do resultado oficial.
- Implementar compra de créditos, top-up, excedente faturado, rollover ou override de franquia de consultas.
- Alterar quotas ou cadência dos canais NFS-e, SEFAZ ou autXML em tempo real.
- Habilitar operações SERPRO reais ou mutantes que continuam protegidas por feature flags; testes automatizados não devem realizar chamadas reais.
- Criar notificações por e-mail, WhatsApp ou SMS.

## Capabilities

### New Capabilities

- `configuracao-escritorio-unificada`: cadastro institucional, consentimento versionado e credencial A1 canônica do escritório com gestão segura, alertas e vínculos de finalidade.
- `acesso-global-platform-admin`: seleção global de escritório, contexto privilegiado integral, reconfirmação de senha e auditoria interna de ações do administrador da plataforma.
- `franquia-agendamento-monitor-serpro`: franquias comerciais por cliente e monitor, consulta inaugural, renovação por período da assinatura e agendamento mensal distribuído.

### Modified Capabilities

- `serpro-gateway-seguro`: a autorização técnica passa a ser derivada e mantida automaticamente com a credencial canônica e o consentimento do escritório, sem configuração técnica pelo tenant.
- `serpro-monitoramento-familias`: os monitores passam a respeitar franquia comercial, consulta inaugural, execução automática mensal e bloqueios por procuração sincronizada, além do ledger técnico existente.
- `serpro-cadastro-processos-ui`: telas, APIs e isolamento entre escritórios passam a considerar a configuração unificada e a exceção explícita de contexto privilegiado do `PLATFORM_ADMIN`.

## Impact

- **Backend/domínio**: `Office`, identidade fiscal, credenciais, consentimentos, autorizações SERPRO, procurações, assinatura/entitlements, agendamentos, contexto de tenant, policies, middleware, filas e auditoria.
- **Frontend**: reconstrução de `/settings`, reserva de `/admin/*` à plataforma, seletor global de escritórios, estados de certificado/procuração/franquia e confirmações de ações sensíveis.
- **Dados/migração**: consolidação das cópias de A1 existentes em uma credencial canônica, migração de limites de plano e agendas, invalidação controlada de autorizações incompatíveis e backfill de estados/ledgers.
- **Segurança/compliance**: mudança de fronteira de acesso fiscal para `PLATFORM_ADMIN`, remoção do TOTP global, reconfirmação de senha em ações sensíveis, trilha interna e revisão jurídica obrigatória antes do rollout.
- **Operação**: automação de onboarding/renovação SERPRO, distribuição de filas, telemetria separada entre unidade comercial e chamada técnica, runbooks e rollout por flags.
- **Compatibilidade OpenSpec**: esta change substitui requisitos conflitantes das **main specs** (ex.: `serpro-onboarding-procuracoes`, `serpro-termo-procurador`, papéis de `PLATFORM_ADMIN` vs Office) promovidas no archive `2026-07-16-operacionalizar-integra-contador-producao`; reconciliar via deltas MODIFIED antes do apply e não reabrir go-live live na mesma change.
