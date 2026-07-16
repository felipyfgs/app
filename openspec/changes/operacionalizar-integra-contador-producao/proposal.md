## Why

O contrato e a autenticação mTLS/OAuth2 já foram comprovados, mas a integração ainda não está pronta para operar com clientes reais: o Termo de Autorização implementado diverge do layout oficial, o pedido `ENVIOXMLASSINADO81` está em formato incompatível e os controles comerciais permitem produção sem teto ou classificação conhecida. Como as credenciais foram expostas durante a configuração e a documentação oficial foi atualizada em 2026, o go-live precisa de uma etapa própria, auditável e fail-closed antes de qualquer chamada faturável.

## What Changes

- Rotacionar e administrar credenciais produtivas e certificados do contratante com transição controlada, teste de cadeia TLS e evidência sanitizada, sempre pelo vault.
- Implementar geração, assinatura, validação e envio do Termo de Autorização conforme o layout/XMLDSig oficial, cobrindo A1 gerenciado e XML assinado externamente para A1/A3.
- Corrigir o `Autentica Procurador` para enviar `dados` com `xml` em Base64, interpretar token/ETag/Expires/304 e distinguir validação local de aceite do SERPRO.
- Modelar, por `Office`, o onboarding do contador/procurador, sua vigência e os poderes e-CAC exigidos por serviço, sem aceitar `office_id` do cliente HTTP.
- Adequar identidades, codecs, banco e UI ao CNPJ alfanumérico e bloquear os pontos do Termo/Eventos ainda conflitantes até confirmação oficial do SERPRO.
- Criar uma escada de testes sem cobrança — fixtures e Trial, cadeia TLS, OAuth, `/Apoiar` e `/Monitorar` — e liberar uma chamada faturável somente por aprovação explícita, orçamento positivo e canário delimitado.
- **BREAKING**: em ambiente produtivo, operação de billability desconhecida, sem preço vigente, sem orçamento, sem autorização aceita ou sem gate de rollout passará a ser negada; `shadow_mode` e fail-open não poderão tornar uma chamada real elegível.
- Adicionar estado de prontidão, preflight, observabilidade, conciliação de consumo, alertas e runbooks para rotação, expiração, revogação, incidente e rollback.
- Tornar catálogo, filas e cobertura de adapters verificáveis: uma operação só será declarada implementada quando possuir codec, fixture, poder, driver e teste compatíveis com a fonte oficial.
- Manter drivers reais e flags mutantes desligados por padrão; habilitação ocorrerá por capacidade e por `Office`, após os gates desta mudança.

### Non-goals

- Não alterar NFS-e ADN, canais SEFAZ MA/SVRS/autXML ou contratos externos a Integra Contador.
- Não habilitar declarações, transmissões, opções tributárias ou qualquer outra mutação fiscal.
- Não executar chamada faturável durante a implementação ou a validação automática; o primeiro canário faturável continua sendo uma decisão operacional separada e explícita.
- Não certificar juridicamente a plataforma como software house nem substituir procurações/poderes e-CAC exigidos pela Receita Federal.
- Não substituir revisão jurídica/LGPD, confirmação tarifária ou esclarecimentos formais do SERPRO sobre documentação conflitante.

## Capabilities

### New Capabilities

- `serpro-credenciais-produtivas`: ciclo de vida seguro de Consumer Key/Secret, e-CNPJ do contratante, mTLS, cadeia TLS e rotação sem exposição.
- `serpro-termo-procurador`: layout, assinatura XMLDSig, validação, envio e cache do Termo de Autorização/Autentica Procurador.
- `serpro-onboarding-procuracoes`: autorização por escritório, vínculo com clientes e verificação dos poderes e-CAC necessários às operações.
- `serpro-go-live-controlado`: política fail-closed de cobrança, escada de testes gratuitos, orçamento, canário, rollout e rollback.
- `serpro-operacao-observavel`: prontidão, telemetria sanitizada, conciliação de consumo, alertas, evidências e runbooks operacionais.

### Modified Capabilities

Nenhuma. `openspec/specs/` ainda não contém specs principais; esta mudança é um follow-up operacional do change concluído `integrar-serpro-monitoramento-completo`, sem arquivá-lo implicitamente.

## Impact

- Backend Laravel: contrato/credenciais globais de plataforma; autorização tenant-scoped; gateway, catálogo, ledger, jobs Horizon, scheduler, comandos de preflight e auditoria.
- API: rotas globais sob `PLATFORM_ADMIN` + TOTP e rotas do escritório sob Sanctum, `EnsureActiveUser`, `EnsureOfficeContext`, papel `ADMIN` e 2FA; respostas nunca expõem PFX, senha, tokens, XML bruto ou IDs internos do vault.
- Frontend Nuxt: onboarding e prontidão em telas autenticadas, reutilizando `panel-ui` → `ui-archetype` e o shell existente.
- Dados: novas versões/evidências de credencial, Termo, poderes, preços, orçamento, gates e conciliação, isoladas por `Office` quando tenant-scoped.
- Operação: rotação obrigatória das credenciais expostas antes do go-live, validação da cadeia de certificados vigente, configuração produtiva fail-closed e runbooks de incidente/rollback.
