## Context

A configuração produtiva já possui os blocos técnicos necessários: `SerproCredentialVersionService` cadastra, verifica, testa OAuth mTLS e promove versões; `SerproRolloutApprovalService` exige confirmação humana via HTTP; `SecureObjectStore` guarda PFX e segredos; `OfficeSerproAuthorizationService` mantém autorização, Termo e tokens; e os monitores executam por filas com gates fail-closed. A página `frontend/app/pages/admin/serpro/configuration.vue`, porém, apresenta esses blocos internos como várias operações manuais.

O onboarding simplificado atravessa frontend, API, cofre, credenciais globais da plataforma, autorização do tenant selecionado e filas de leitura. Ele não introduz outra forma de autenticação SERPRO: apenas compõe os serviços existentes em uma unidade de trabalho observável. Os principais interessados são o administrador da plataforma, o administrador do tenant selecionado, a operação fiscal, segurança/auditoria e suporte.

A implementação é C1 e consome a autorização canônica de `padronizar-autorizacao-multitenant` após seu marco `apply`. Até esse marco, desenho, contratos e testes isolados podem avançar, mas o novo controller não deve consolidar verificações pelos enums legados.

## Goals / Non-Goals

**Goals:**

- Reduzir a entrada permanente a Consumer Key, Consumer Secret, PFX, senha do PFX e um checkbox explícito de concessão.
- Executar cadastro, verificação do cofre, teste OAuth mTLS, confirmação de cutover e ativação como uma orquestração única, auditável, idempotente e recuperável.
- Associar a configuração ao `CurrentOffice`, sem aceitar `office_id` do navegador.
- Iniciar somente sincronizações de leitura que já estejam habilitadas e autorizadas, com Caixa Postal como primeira execução observável.
- Exibir progresso e falha por etapa sem devolver segredos ou detalhes externos sensíveis.

**Non-Goals:**

- Criar procuração, Termo, assinatura ou poder no e-CAC em nome do usuário.
- Substituir a confirmação recente de identidade exigida para uma ação sensível.
- Desligar kill switch, elevar capacidades, ampliar allowlists/assinatura ou ignorar orçamento.
- Habilitar mutações fiscais, emissão, transmissão, declaração ou canais outbound.
- Remover as telas técnicas de diagnóstico; elas deixam de ser o caminho principal, mas continuam disponíveis a suporte autorizado.

## Decisions

### 1. Um endpoint de comando e uma máquina de etapas persistida

Será criado um comando HTTP autenticado de onboarding produtivo, consumido pela tela de configuração. O request multipart conterá somente `consumer_key`, `consumer_secret`, `certificate`, `certificate_password` e `consent_granted=true`; ambiente será fixado pelo endpoint em `PRODUCTION` e o tenant virá de `CurrentOffice`.

O serviço orquestrador persistirá um registro sanitizado com chave idempotente, ator, tenant, estado e as etapas `VALIDATE_INPUT`, `STORE_PENDING`, `VERIFY_VAULT`, `TEST_OAUTH`, `CONFIRM_CUTOVER`, `ACTIVATE_AUTHORIZATION`, `QUEUE_READ_SYNC` e `COMPLETED`. Retentativas retomam a primeira etapa não concluída e nunca criam duas versões para o mesmo onboarding.

Alternativa rejeitada: manter seis chamadas coordenadas pelo navegador. Isso conserva a complexidade atual, dificulta rollback e permite perder o estado entre etapas.

### 2. O checkbox concede a operação, mas não fabrica autoridade externa

O texto do checkbox explicará que o ator autoriza o armazenamento cifrado dos materiais, o teste OAuth produtivo, a ativação da credencial e o início de consultas somente leitura potencialmente bilhetáveis. O aceite será versionado, com timestamp, ator, tenant, hash da versão do texto e correlação de auditoria.

A confirmação `OWNER_CONFIRMATION` continuará ocorrendo no contexto HTTP autenticado. Se a senha não tiver sido confirmada recentemente, a SPA usará o fluxo padrão de reconfirmação antes de reenviar o comando; a tela principal não ganhará frase técnica, motivo ou janela. Internamente, o serviço criará e consumirá a aprovação de `CREDENTIAL_CUTOVER` com motivo e frase canônicos derivados do onboarding, sem permitir CLI/job fabricar o ator.

O aceite não declara que existe procuração. Se Termo, token de procurador ou poder e-CAC exigido estiver ausente ou inválido, a credencial poderá ficar tecnicamente conectada, mas a etapa de leitura ficará `ACTION_REQUIRED`, indicando exatamente a pendência oficial.

Alternativa rejeitada: considerar o checkbox equivalente à procuração e-CAC. O hub não tem autoridade para conceder esse poder e produziria falsa elegibilidade.

### 3. Composição dos serviços existentes e cutover conservador

O orquestrador reutilizará `SerproCredentialVersionService` para validar o PFX contra o CNPJ contratante, escrever no cofre, reler, testar OAuth mTLS no endpoint canônico e executar o cutover. Uma versão ativa anterior só será aposentada dentro da transação final, depois de todas as provas técnicas obrigatórias.

Em falha anterior ao cutover, a versão candidata será marcada como falha/retirada e os objetos parciais serão limpos conforme a política do cofre; a versão ativa anterior permanece utilizável. Em falha posterior ao cutover, o sistema não alternará credenciais automaticamente sem evidência: marcará o onboarding para recuperação e oferecerá rollback autorizado para a versão anterior ainda íntegra.

Alternativa rejeitada: sobrescrever a versão ativa diretamente. Isso elimina rollback e pode interromper todos os tenants que compartilham o contrato da plataforma.

### 4. Credencial global e autorização tenant-scoped permanecem conceitos distintos

Consumer Key/Secret e PFX representam o contrato produtivo da plataforma e permanecem em modelos de credencial globais, acessíveis apenas ao papel global autorizado. O mesmo comando exige contexto explícito de tenant para criar ou atualizar a `OfficeSerproAuthorization` de `CurrentOffice`, sem copiar os segredos globais para o tenant.

Quando a plataforma já possuir credencial produtiva ativa equivalente, uma retentativa não criará nova versão: validará o fingerprint e seguirá para a autorização do tenant. Outros tenants não serão ativados implicitamente.

Alternativa rejeitada: armazenar um par de chaves em cada `Office`. Isso duplica segredo contratual, aumenta superfície de vazamento e mistura contratação da plataforma com representação fiscal do tenant.

### 5. Primeira sincronização é assíncrona, somente leitura e sujeita aos gates atuais

Após o cutover e a autorização local, o orquestrador despachará um job idempotente para Caixa Postal e, depois, os demais monitores de leitura já permitidos pelo contrato, feature flags, capability, allowlist, orçamento, procuração/poder e kill switches. A conclusão do onboarding não dependerá do tempo da SERPRO; a UI mostrará `ACTIVE_SYNC_PENDING`, `ACTIVE`, `ACTION_REQUIRED` ou `FAILED` com atualização de estado local.

Nenhum GET, montagem da tela ou retry de UI executará chamada SERPRO. A chamada OAuth acontece apenas no POST confirmado; operações de negócio são realizadas pelos jobs autorizados e registradas no ledger/bilhetagem.

Alternativa rejeitada: consultar toda a carteira sincronicamente durante o submit. Isso cria timeout, custo imprevisível e falha tudo por causa de um único cliente sem poder.

### 6. Contrato público sanitizado e observabilidade por correlação

A resposta exporá apenas ID opaco do onboarding, estado, etapa, timestamps, metadados mascarados do certificado/chave, resultado técnico resumido e ações requeridas. Validation exceptions, auditoria e métricas usarão códigos estáveis; Consumer Secret, PFX, senha, bearer, JWT, XML e payload bruto serão removidos antes de log, banco ou resposta.

Erros serão agrupados em entrada/certificado, cofre, OAuth, aprovação, autorização oficial, gate operacional e sincronização. Isso permite suporte sem reexibir o material informado.

Alternativa rejeitada: devolver a resposta original da SERPRO para diagnóstico. Ela pode conter dados e tokens incompatíveis com o contrato público seguro.

## Mapa de dependências

```text
padronizar-autorizacao-multitenant (C0, apply)
                 |
                 v
simplificar-ativacao-serpro-producao (C1)
  N0 contrato + persistência sanitizada
        |
        +------------------+
        v                  v
  N1 backend          N1 frontend
        \                  /
         v                v
       N2 integração e gates
```

- Ownership desta change: capability `serpro-onboarding-producao-simplificado`, novo endpoint/orquestrador, formulário principal e testes correspondentes.
- Ownership do upstream: capabilities `tenant-access-governance` e `tenant-lifecycle`; esta change consome suas permissões e contexto, sem editar seus specs.
- Arquivos compartilhados prováveis: `backend/routes/api.php`, configuração/policies de plataforma, `frontend/app/pages/admin/serpro/configuration.vue`, tipos e factory da API. A integração nesses pontos fica bloqueada até o `apply` upstream e deve preservar alterações concorrentes.
- Rollout: migrations e backend compatível primeiro; frontend simplificado depois; ativação por feature flag/allowlist; canário OAuth sem operação fiscal; por fim job de Caixa Postal de um tenant autorizado.
- Rollback: desligar a flag do onboarding, manter os endpoints técnicos anteriores, cancelar jobs ainda não executados e reativar a credencial anterior por aprovação válida quando o cutover já tiver ocorrido.

## Risks / Trade-offs

- [Um único botão aparenta remover controles de segurança] → manter reconfirmação recente, auditoria, aprovação HTTP, gates e texto de consentimento explícito, ocultando apenas a mecânica técnica.
- [PFX válido pertence a outro contratante] → comparar CNPJ do certificado com o contrato e bloquear antes de persistir a versão como verificável.
- [Falha entre cofre e banco deixa objeto órfão] → compensação idempotente e rotina de limpeza por IDs opacos, sem registrar conteúdo.
- [Cutover global afeta tenants existentes] → teste OAuth obrigatório, transação final, versão anterior preservada e rollout gradual por flag.
- [Usuário interpreta aceite como procuração] → mensagem inequívoca e estado `ACTION_REQUIRED` quando a autoridade e-CAC faltar.
- [Sincronização inicial gera custo inesperado] → somente leitura, orçamento/allowlist/capability existentes, ledger e despacho assíncrono idempotente.
- [Dependência de RBAC muda durante implementação] → bloquear integração final até `padronizar-autorizacao-multitenant` atingir `apply` e testar a permissão canônica, sem fallback permissivo.

## Migration Plan

1. Adicionar a persistência sanitizada e o orquestrador atrás de feature flag OFF, sem remover endpoints atuais.
2. Integrar a autorização canônica após o marco `apply` de `padronizar-autorizacao-multitenant` e validar isolamento por `CurrentOffice`.
3. Publicar o endpoint e a nova interface apenas para administradores autorizados na allowlist inicial.
4. Executar um canário de validação PFX/cofre/OAuth sem operação fiscal e comprovar logs sanitizados.
5. Ativar um tenant com procuração válida, verificar cutover e acompanhar o job idempotente de Caixa Postal no ledger.
6. Expandir gradualmente a disponibilidade; manter o fluxo técnico como diagnóstico durante a estabilização.

Rollback: desabilitar a feature flag interrompe novos onboardings sem apagar evidências. Jobs pendentes são cancelados pelos gates em tempo de execução; credencial anterior pode ser restaurada pelo fluxo autorizado existente; objetos candidatos não promovidos são limpos com trilha de auditoria.

## Open Questions

- Qual texto jurídico/operacional final e qual versão devem compor o checkbox de concessão antes do rollout?
- A sincronização inicial padrão ficará restrita à Caixa Postal ou incluirá outros monitores de leitura explicitamente selecionados na configuração da plataforma?
- Qual janela de validade será adotada para retomar um onboarding interrompido sem solicitar novamente os materiais secretos?
