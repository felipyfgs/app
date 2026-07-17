## Why

Os fluxos produtivos de credenciais e kill switch SERPRO exigem atualmente dois `PLATFORM_ADMIN` distintos, o que se torna impossível quando a instalação possui um único proprietário global. A autorização precisa continuar forte, auditável e fail-closed sem reintroduzir uma segunda identidade com o mesmo papel.

## What Changes

- **BREAKING**: substituir a aprovação de dois `PLATFORM_ADMIN` por uma autorização do Proprietário da instalação com senha recentemente reconfirmada, confirmação explícita da operação, motivo e janela de mudança.
- Manter preflight do vault, validade mínima do certificado e OAuth mTLS real antes de ativar, substituir ou desbloquear contrato produtivo ou retirar o kill switch, sem chamada fiscal de negócio.
- Registrar decisão, ator único, escopo, motivo, janela, resultado e evidências sanitizadas em auditoria; nenhuma CLI ou job poderá fabricar aprovação humana.
- Preservar o canário faturável com duas pessoas distintas — o Proprietário da instalação e um `Office ADMIN` — sem permitir que uma conta dual aprove pelos dois papéis.
- Coordenar a entrega com `tornar-platform-admin-proprietario-unico`; não ativar a unicidade enquanto houver fluxo produtivo dependente de dois proprietários.
- Non-goals: executar chamadas SERPRO live, ligar flags ou kill switches, alterar mutações fiscais, armazenar segredos fora do vault, expor PFX/tokens/XML, tratar tickets externos ou decisões jurídicas/LGPD.

## Capabilities

### New Capabilities

Nenhuma.

### Modified Capabilities

- `serpro-credenciais-produtivas`: troca a regra de quatro olhos entre dois `PLATFORM_ADMIN` por confirmação reforçada e auditada do proprietário único, preservando todos os demais gates produtivos.

## Impact

- Backend Laravel: aprovações e cutover de versões de credenciais, retirada de kill switch, middleware de senha recente, auditoria e mensagens de API.
- Frontend Nuxt: confirmação explícita das operações produtivas e mensagens que hoje aguardam um segundo `PLATFORM_ADMIN`.
- Testes: cenários de cutover, kill switch, expiração de reconfirmação, preflight e proibição de aprovação fabricada; o canário `PLATFORM_ADMIN + Office ADMIN` permanece inalterado.
- Segredos e tenancy: materiais produtivos permanecem no `SecureObjectStore`, respostas seguem sanitizadas e nenhum `office_id` do cliente define escopo tenant.
