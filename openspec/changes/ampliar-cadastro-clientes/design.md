## Contexto

O sistema representa uma pessoa jurídica cliente pela raiz de oito caracteres do CNPJ e seus estabelecimentos pelos CNPJs completos. O banco atual armazena apenas `clients.name`, `clients.root_cnpj`, observações e estados booleanos, enquanto o formulário inicial recebe um CNPJ completo, conserva apenas a raiz e exige que o operador repita o identificador na etapa seguinte.

A integração existente consulta a CNPJ.ws exclusivamente pelo backend, limita o uso a três requisições por minuto e mantém cache por 24 horas. O fornecedor retorna dados da empresa/raiz e do estabelecimento, inclusive campos fora do escopo e QSA; o adaptador atual permite apenas CNPJ, razão social, nome fantasia e situação cadastral, e a interface aproveita somente CNPJ e razão social. A fonte declara defasagem possível e não suporta com segurança o novo formato alfanumérico, portanto seus dados não podem ser tratados como verdade nem como pré-condição do cadastro.

A mudança cruza schema, API, auditoria, sincronização e frontend. O detalhe de Cliente segue o arquétipo Settings do template fixado em `0f30c09`, e a criação deriva do modal de Customers. O modal permanecerá focado nos dados que o escritório precisa no onboarding, sem reproduzir toda a ficha pública, sem expor material de A1 e sem adicionar Node ao ambiente de produção.

## Objetivos / Não-objetivos

**Objetivos:**

- Separar semanticamente dados da raiz, do estabelecimento e dos contatos internos.
- Eliminar a redigitação do primeiro CNPJ por meio de criação transacional de Cliente e Estabelecimento.
- Usar a CNPJ.ws para sugerir um conjunto sanitizado, editável e datado de dados cadastrais.
- Preservar cadastro manual para falha externa e CNPJ alfanumérico.
- Separar estado do cadastro de habilitação da captura e impedir agendamento de entidades inelegíveis.
- Oferecer manutenção completa no padrão Settings, com permissões e responsividade existentes.
- Migrar registros atuais sem perder nome, raiz, estabelecimento, certificado, cursor ou documentos.

**Não-objetivos:**

- Armazenar resposta bruta, QSA, CPF, capital social, inscrições estaduais/municipais, SUFRAMA ou detalhes de Simples/MEI.
- Criar histórico temporal completo de endereços ou cadastros públicos.
- Consultar município, emitir/cancelar NFS-e ou alterar o protocolo ADN/NSU.
- Transformar contatos em CRM, disparar mensagens ou construir portal externo.
- Modificar o cofre, a ativação do A1 ou permitir recuperação de PFX, senha, chave privada ou PEM.

## Decisões

### 1. Manter três limites de domínio explícitos

`Client` representa a pessoa jurídica na raiz; `Establishment` representa cada inscrição completa; `ClientContact` representa uma pessoa ou canal interno usado pelo escritório.

Campos de `clients`:

- `legal_name` obrigatório, migrado do `name` atual;
- `display_name` opcional, usado como nome curto quando preenchido;
- `root_cnpj` imutável após criação;
- `legal_nature_code` e `legal_nature_name` opcionais;
- `company_size_code` e `company_size_name` opcionais;
- `is_active`, `inactive_reason` e `notes`;
- `registration_source` (`LEGACY`, `MANUAL` ou `CNPJ_WS`) e `registration_refreshed_at`.

Campos adicionais de `establishments`:

- `registration_status` normalizado (`ACTIVE`, `VOID`, `SUSPENDED`, `UNFIT`, `CLOSED` ou `UNKNOWN`), data e motivo;
- `activity_started_at`, CNAE principal em código e descrição;
- endereço corrente estruturado: CEP, tipo/logradouro, número, complemento, bairro, município, código IBGE, UF e país;
- `public_email` e `public_phone`, sempre identificados como contato público;
- `capture_enabled`, independente de `is_active`;
- origem e data de atualização cadastral.

`client_contacts` terá `office_id`, `client_id`, nome, função, e-mail, telefone, indicador de WhatsApp, contato principal, recebimento futuro de alertas, observações, estado e soft delete. E-mail/telefone público do CNPJ não cria automaticamente um contato interno.

Alternativa rejeitada: colocar todos os campos em `clients`. Isso duplicaria endereço e nome fantasia para filiais e contrariaria o grão da fonte cadastral. Também foi rejeitado um JSON bruto porque dificultaria validação e carregaria dados fora do escopo.

### 2. Usar colunas para o cadastro operacional e não normalizar enriquecimentos sem uso

Os campos consultados e editados individualmente ficam em colunas tipadas. CNAEs secundários, inscrições estaduais, capital, QSA e Simples/MEI não serão persistidos nesta etapa. Se uma capacidade futura precisar filtrar ou operar esses conjuntos, ela deverá propor modelo próprio em vez de reutilizar payload externo.

O banco deverá garantir no máximo uma matriz não excluída por Cliente e no máximo um contato principal ativo por Cliente, usando índices parciais do PostgreSQL. A criação de uma primeira filial é válida; a matriz pode ser adicionada depois.

Alternativa rejeitada: criar tabelas para todos os objetos da CNPJ.ws. Isso aumentaria custo de migração e acoplamento sem função no produto de captura ADN.

### 3. Isolar a CNPJ.ws por contrato e DTO sanitizado

Será criado um contrato `CnpjRegistrationLookup` e uma implementação `CnpjWsRegistrationLookup`. O contrato devolverá um DTO próprio dividido em `client` e `establishment`; somente a lista permitida no design poderá entrar em cache, API, logs ou persistência. Respostas desconhecidas ou campos adicionais do fornecedor serão descartados.

O endpoint `GET /api/v1/cnpj/{cnpj}/lookup` manterá autorização de cadastro, aceitará apenas CNPJ numérico enquanto o fornecedor não documentar suporte alfanumérico e retornará:

```json
{
  "data": {
    "source": "CNPJ_WS",
    "source_updated_at": "...",
    "client": {
      "root_cnpj": "...",
      "legal_name": "...",
      "legal_nature_code": "...",
      "legal_nature_name": "...",
      "company_size_code": "...",
      "company_size_name": "..."
    },
    "establishment": {
      "cnpj": "...",
      "trade_name": "...",
      "is_matrix": true,
      "registration_status": "ACTIVE",
      "registration_status_at": "...",
      "registration_status_reason": null,
      "activity_started_at": "...",
      "main_cnae_code": "...",
      "main_cnae_name": "...",
      "address": {},
      "public_email": "...",
      "public_phone": "...",
      "source_updated_at": "..."
    }
  }
}
```

`source`, datas da fonte e `office_id` não serão aceitos como autoridade vinda do navegador. A criação consultará o DTO sanitizado já armazenado no cache pelo CNPJ; se ele não existir, o registro será marcado como `MANUAL`. Os valores canônicos continuam sendo validados e aceitos como escolha do operador, pois a prévia pode ser corrigida antes do envio.

Alternativas rejeitadas: persistir o payload bruto; fazer uma segunda chamada externa no `POST`; ou bloquear cadastro manual. A primeira viola minimização, a segunda duplica consumo do rate limit e a terceira torna o fornecedor uma dependência crítica.

### 4. Criar Cliente e primeiro Estabelecimento em uma transação

O `POST /api/v1/clients` passará a exigir CNPJ completo e razão social e aceitará os campos editáveis da raiz e do primeiro estabelecimento. Um serviço de aplicação validará o CNPJ uma vez, derivará a raiz, resolverá a proveniência do cache e criará ambos os registros dentro de `DB::transaction`.

A resposta conterá `data.client` e `data.establishment`. Qualquer falha reverterá ambos. Se a raiz já existir no escritório atual, a API não criará estabelecimento silenciosamente: retornará erro acionável com o identificador do Cliente existente, desde que autorizado, para a interface conduzir à seção Estabelecimentos. Raízes e CNPJs duplicados em outro escritório não serão revelados.

O contrato é deliberadamente incompatível com o `name` atual; backend e frontend serão implantados juntos. `PATCH /clients/{id}` e `PATCH /establishments/{id}` serão ampliados com listas permitidas, e contatos terão endpoints aninhados no Cliente com políticas de mesmo escritório.

Alternativa rejeitada: manter duas submissões. Ela preservaria a janela de Cliente sem estabelecimento e a redigitação que motivaram a mudança.

### 5. Tratar situação cadastral, estado interno e captura como conceitos diferentes

- `registration_status` descreve o retrato da fonte pública e pode estar desatualizado.
- `is_active` descreve se Cliente ou Estabelecimento permanece operacional no escritório.
- `capture_enabled` descreve a autorização explícita para sincronizar aquele estabelecimento.

Um estabelecimento consultado com situação diferente de `ACTIVE` será criado com captura desabilitada e aviso. `ADMIN` ou `OPERATOR` poderá habilitar a captura após revisão, registrando motivo auditável. Cadastro manual com situação `UNKNOWN` não será bloqueado automaticamente.

Despacho agendado e disparo manual deverão exigir Cliente ativo, Estabelecimento ativo, `capture_enabled`, credencial válida e cursor não bloqueado. A UI não oferecerá disparo quando alguma dessas condições falhar e explicará o motivo sem permitir editar NSU.

Alternativa rejeitada: reutilizar `is_active` para tudo. Isso impediria conservar registros históricos enquanto a captura estivesse pausada e tornaria o estado ambíguo.

### 6. Manter a criação focada no modal e a manutenção em Settings

O acionamento `Novo cliente` continuará abrindo `ClientCreateModal`, baseado em `.reference/nuxt-dashboard-template/app/components/customers/AddModal.vue`. O formulário exibirá CNPJ, razão social, nome fantasia, contato responsável com e-mail/telefone/WhatsApp, notas, campos adicionais e, somente para `ADMIN` com 2FA, arquivo PFX e senha do A1.

A consulta pelo CNPJ preencherá razão social e nome fantasia editáveis. Os demais dados públicos sanitizados poderão continuar sendo persistidos como sugestão técnica, mas não ocuparão o onboarding. Falha da fonte ou CNPJ alfanumérico conservará o modal para preenchimento manual. O contato interno opcional será enviado no mesmo `POST /clients` e criado na mesma transação de Cliente e Estabelecimento; contato público nunca será convertido automaticamente.

Campos adicionais serão linhas com rótulo, tipo `TEXT` ou `SECRET` e valor. Texto poderá ser devolvido nas respostas autorizadas. Segredo será armazenado no `SecureObjectStore` com AAD de escritório, cliente e chave estável, e a API devolverá somente metadados e `has_value`; não haverá rota de recuperação. Substituição grava novo objeto antes de invalidar o anterior, com compensação em falha. Notas não aceitarão o papel de cofre.

Como o endpoint de A1 depende do Cliente criado, a interface primeiro conclui a transação de Cliente, Estabelecimento, Contato e campos adicionais e depois chama a ativação existente do certificado. Se a validação do PFX falhar, o cliente permanece criado, o material sensível é limpo da memória e a interface conduz à seção Certificado A1 para nova tentativa, sem afirmar rollback do cadastro básico.

O detalhe `/clients/:id` continuará baseado em `pages/settings.vue` e manterá a ordem `Resumo`, `Cadastro`, `Estabelecimentos`, `Certificado A1` e `Sincronização`. `Cadastro` usará cards do formulário `settings/index.vue` para raiz, estado, contatos e campos adicionais. `Estabelecimentos` manterá a lista e abrirá criação curta ou edição completa sem alterar a gramática do template.

`ADMIN` e `OPERATOR` editam cadastro; `VIEWER` recebe a mesma informação em modo somente leitura. Gestão de A1 permanece exclusiva ao `ADMIN` com segundo fator. URL da seção, erros 422, loading, fallback, preservação de valores e viewports de 390×844/360 px permanecem requisitos verificáveis.

Alternativas rejeitadas: reproduzir toda a ficha pública no modal, criar uma página exclusiva de onboarding ou gravar senhas em notas/texto. As duas primeiras aumentam a carga para o fluxo cotidiano; a última viola as regras do cofre e de não exposição.

### 7. Auditar sem registrar dados proibidos

Criação e alteração registrarão entidade, campos alterados, usuário, fonte cadastral e mudanças de habilitação da captura. Logs não conterão payload externo, QSA, CPF, PFX, senha, chave privada, PEM, e-mail/telefone completos quando a mensagem puder ser expressa por identificador interno, nem dados de outro escritório.

Fixtures de testes usarão pessoas jurídicas sintéticas ou o contrato HTTP simulado; CI não consultará CNPJ.ws nem ADN real.

## Riscos / Trade-offs

- [Dados externos podem estar desatualizados] → Exibir origem e data, permitir edição, usar `UNKNOWN` e nunca substituir silenciosamente valores internos.
- [Limite de três consultas por minuto ou indisponibilidade] → Manter cache sanitizado, rate limiter, timeout curto, mensagens sanitizadas e fallback manual.
- [Mudança de schema do fornecedor] → Isolar em adaptador, mapear lista permitida, tolerar opcionais e falhar de forma sanitizada quando faltar identidade mínima.
- [Migração pode reinterpretar `name`] → Copiar integralmente o valor para `legal_name`, deixar `display_name` nulo e revisar fixtures/listas que usavam `name`.
- [Novos filtros de elegibilidade podem interromper sincronizações existentes] → Backfill `capture_enabled` a partir de `is_active`, testar cursores existentes e implantar após relatório de pré-migração.
- [Dados atuais podem ter mais de uma matriz] → Detectar duplicidades antes do índice parcial e interromper a migração com relatório, sem escolher matriz silenciosamente.
- [Campos opcionais podem alongar o modal] → Manter os dados básicos visíveis e recolher A1/campos adicionais em blocos progressivos, com altura limitada e rolagem interna responsiva.
- [Falha do A1 após criar o cliente] → Informar criação parcial de forma explícita, limpar PFX/senha e conduzir à seção A1 para repetir apenas a ativação.
- [Contato público pode ser confundido com contato operacional] → Rótulos explícitos e entidade separada; nunca criar `ClientContact` automaticamente.

## Plano de migração

1. Executar backup e relatório pré-migração para nomes vazios, raízes/CNPJs duplicados, múltiplas matrizes e cursores ligados a entidades inativas.
2. Renomear `clients.name` para `legal_name`, adicionar os campos novos como anuláveis, criar `client_contacts` e adicionar índices após validação dos dados existentes.
3. Marcar registros atuais com origem `LEGACY`; deixar `display_name` e enriquecimentos nulos; preencher `capture_enabled` com o estado operacional vigente para não interromper capturas válidas.
4. Implantar contrato/DTO da consulta, serviço transacional, endpoints, políticas, auditoria e filtros do Scheduler.
5. Implantar frontend no mesmo release do contrato incompatível, começando pelo modal de criação e detalhe Settings.
6. Executar testes, seed sintético e piloto com poucas raízes numéricas e um CNPJ alfanumérico em modo manual antes de ampliar uso.

Depois de dados reais serem gravados nos novos campos, rollback por `down` destrutivo não será usado. A reversão segura será código compatível com as colunas adicionais ou restauração do backup realizado antes da migração.

## Questões em aberto

Nenhuma questão bloqueante. Suporte futuro da CNPJ.ws ao CNPJ alfanumérico deverá ser confirmado em mudança própria antes de habilitar consulta externa para esse formato.
