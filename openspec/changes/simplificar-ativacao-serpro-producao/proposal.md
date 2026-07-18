## Why

A ativação produtiva da Integra Contador expõe hoje detalhes internos de credenciais, versões, aprovação e cutover, tornando uma configuração operacional comum difícil e sujeita a erro. O sistema deve oferecer um onboarding único em que o administrador informa apenas Consumer Key, Consumer Secret, certificado PFX, senha do certificado e concede o aceite explícito; as validações, o armazenamento seguro e a ativação ficam orquestrados pelo backend.

## What Changes

- Criar um fluxo único de “Ativar SERPRO em produção” com Consumer Key, Consumer Secret, upload do PFX, senha do PFX e checkbox obrigatório de concessão/consentimento.
- Ocultar do fluxo principal os detalhes de versão de credencial, verificação do cofre, teste OAuth, aprovação técnica e cutover, mantendo-os como etapas internas auditáveis e fail-closed.
- Validar certificado, titularidade/identidade contratante e conexão OAuth mTLS antes de promover a credencial; em falha, preservar a configuração ativa anterior e mostrar uma orientação objetiva sem expor segredos.
- Armazenar Consumer Secret, PFX, senha, tokens e demais materiais exclusivamente no `SecureObjectStore`, com respostas e logs sanitizados.
- Vincular a ativação ao tenant obtido por `CurrentOffice`, criar ou atualizar a autorização produtiva de forma idempotente e iniciar a primeira sincronização somente de recursos de leitura habilitados, incluindo Caixa Postal.
- Preservar kill switch, autorização por capacidade, limites de custo, procuração e poderes e-CAC; o checkbox registra a concessão operacional, mas não cria nem substitui procuração oficial.
- Manter as mutações fiscais e canais outbound fora da ativação simplificada e desligados por padrão.

Não são objetivos desta change: conceder procuração automaticamente no e-CAC; substituir assinatura, Termo ou poderes exigidos pela SERPRO; remover kill switches, orçamento, assinatura do produto ou isolamento de tenant; habilitar declaração, emissão, transmissão ou outra mutação fiscal; expor credenciais globais a usuários do tenant; emitir parecer jurídico; ou executar canais outbound.

## Capabilities

### New Capabilities

- `serpro-onboarding-producao-simplificado`: formulário mínimo, consentimento explícito, orquestração segura e idempotente da credencial produtiva, ativação tenant-scoped e disparo inicial de consultas somente leitura.

### Modified Capabilities

Nenhuma. As regras permanentes de `schema-conventions` permanecem inalteradas.

## Impact

- Backend Laravel: endpoint e serviço orquestrador de onboarding, validação do PFX, integração com `SecureObjectStore`, OAuth mTLS, versionamento/cutover de credenciais, autorização SERPRO, auditoria e jobs iniciais de sincronização.
- Banco e cofre: metadados sanitizados de execução/consentimento no banco; todos os materiais secretos e certificados permanecem fora do banco comum e dos backups usuais.
- Frontend Nuxt: substituição do fluxo técnico de configuração por uma experiência guiada e compacta, com progresso por etapa e erros acionáveis.
- Operação SERPRO: possibilidade explícita de egress produtivo e bilhetável somente após confirmação, validações e gates existentes; nenhuma chamada externa é disparada ao apenas abrir ou revisar a tela.
- Segurança e tenancy: `CurrentOffice` é a única autoridade de tenant; `office_id`, segredo, PFX, senha, token, XML e payload externo bruto não entram no contrato público.

### Dependências entre changes

- **Nível:** C1.
- **Bases estáveis:** `schema-conventions`, `SecureObjectStore`, contratos atuais de credencial/rollout SERPRO e catálogo oficial versionado.
- **Depende de:** `padronizar-autorizacao-multitenant`, capability `tenant-access-governance`, marco `apply`, relação `bloqueante`, para consumir a autorização canônica de `platform_admin`/tenant sem consolidar novamente os papéis legados no novo onboarding.
- **Desbloqueia:** ativação operacional simplificada dos monitores SERPRO produtivos e a sincronização inicial da Caixa Postal, sem tornar esta change dependência obrigatória de suas projeções fiscais.
- **Condições de paralelismo:** especificação, desenho e testes isolados podem avançar em paralelo; a integração dos guards, controllers e componentes compartilhados de administração só avança após o cutover de autorização da dependência e deve ser coordenada com mudanças ativas que editem a mesma tela de configuração SERPRO.
