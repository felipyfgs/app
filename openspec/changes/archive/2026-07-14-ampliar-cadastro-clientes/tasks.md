## 1. Pré-migração e modelo de dados

- [x] 1.1 Criar verificação somente leitura para nomes vazios, raízes/CNPJs duplicados, múltiplas matrizes e cursores ligados a Cliente ou Estabelecimento inativo, com saída sanitizada para decidir se a migração pode prosseguir
- [x] 1.2 Criar migração PostgreSQL que renomeia `clients.name` para `legal_name`, adiciona os campos cadastrais de Cliente e Estabelecimento e preserva os registros atuais com origem `LEGACY`
- [x] 1.3 Criar `client_contacts` com `office_id`, vínculo ao Cliente, canais, flags operacionais, timestamps e soft delete, sem aceitar escritório fornecido pelo navegador
- [x] 1.4 Adicionar índices e restrições para raiz/CNPJ no escritório, uma matriz não excluída por Cliente e um contato principal ativo por Cliente, fazendo a migração falhar com diagnóstico em vez de corrigir dados silenciosamente
- [x] 1.5 Preencher `capture_enabled` dos estabelecimentos existentes a partir do estado vigente e verificar que certificados, cursores, interesses e documentos mantêm seus vínculos
- [x] 1.6 Atualizar enums, casts, fillables, relações, factories e seed sintético para o novo grão Cliente–Estabelecimento–Contato
- [x] 1.7 Cobrir migração, rollback seguro antes de dados novos e invariantes do schema com testes automatizados em PostgreSQL

## 2. Contrato sanitizado de consulta de CNPJ

- [x] 2.1 Criar o contrato `CnpjRegistrationLookup` e DTOs tipados separados em dados de Cliente, Estabelecimento, endereço e proveniência
- [x] 2.2 Implementar `CnpjWsRegistrationLookup` com mapeamento explícito de razão social, natureza jurídica, porte, matriz/filial, situação, datas, CNAE principal, endereço e contato público
- [x] 2.3 Normalizar situação cadastral para `ACTIVE`, `VOID`, `SUSPENDED`, `UNFIT`, `CLOSED` ou `UNKNOWN` e tolerar campos opcionais e valores futuros sem persistir conteúdo desconhecido
- [x] 2.4 Manter timeout, limite de três requisições por minuto e cache configurável contendo somente o DTO sanitizado, sem payload bruto, QSA, CPF, capital, inscrições ou Simples/MEI
- [x] 2.5 Ampliar `GET /api/v1/cnpj/{cnpj}/lookup` para resposta aninhada e datada, preservando autorização, mensagens sanitizadas e fallback manual para falha externa ou CNPJ alfanumérico
- [x] 2.6 Cobrir sucesso, opcionais ausentes, schema externo inesperado, 404, 429, timeout, cache, CNPJ alfanumérico e ausência de campos proibidos com testes unitários e de feature usando HTTP simulado

## 3. Cadastro transacional e APIs de manutenção

- [x] 3.1 Criar requests/validações de Cliente, primeiro Estabelecimento, edições e contatos com CNPJ alfanumérico, formatos de endereço, e-mail/telefone, listas permitidas e erros 422 por campo
- [x] 3.2 Implementar serviço transacional que deriva a raiz, resolve proveniência pelo cache sanitizado e cria Cliente e primeiro Estabelecimento no escritório da sessão ou reverte integralmente
- [x] 3.3 Alterar `POST /api/v1/clients` para o novo contrato e resposta `data.client` + `data.establishment`, incluindo conflito acionável para raiz já visível sem revelar registros de outro escritório
- [x] 3.4 Ampliar consulta e atualização de Cliente e Estabelecimento, mantendo raiz e CNPJ imutáveis e validando matriz única de forma atômica
- [x] 3.5 Implementar CRUD de contatos aninhado no Cliente, com policies por escritório e troca atômica do contato principal
- [x] 3.6 Registrar em auditoria criação, campos alterados, inativação, proveniência e habilitação de captura sem valores desnecessários, contato completo ou conteúdo proibido
- [x] 3.7 Cobrir criação atômica, rollback, duplicidades, matriz única, contato principal, permissões `ADMIN`/`OPERATOR`/`VIEWER`, `office_id` forjado e isolamento entre escritórios com testes de feature

## 4. Elegibilidade e sincronização

- [x] 4.1 Centralizar a regra de elegibilidade que exige Cliente ativo, Estabelecimento ativo, `capture_enabled`, credencial válida e cursor não bloqueado
- [x] 4.2 Aplicar a regra ao Scheduler, ao dispatcher e ao disparo manual sem avançar, reiniciar ou editar NSU quando a entidade estiver inelegível
- [x] 4.3 Criar fluxo autorizado para habilitar captura em situação cadastral externa não ativa somente com motivo obrigatório e auditoria
- [x] 4.4 Ajustar respostas da API para expor estado e motivos sanitizados necessários à interface sem devolver erro remoto bruto
- [x] 4.5 Cobrir agendamento, disparo manual, captura pausada, situação `UNKNOWN`, exceção revisada, credencial vencida e preservação do NSU com testes de regressão

## 5. Frontend no template obrigatório

- [x] 5.1 Abrir e copiar antes da edição os arquétipos fixados `customers/AddModal.vue`, `pages/settings.vue` e `pages/settings/index.vue`, preservando markup, slots, classes e ordem de blocos reconhecíveis
- [x] 5.2 Atualizar tipos e `useApi` para o lookup aninhado, novo contrato transacional, campos cadastrais, contatos e estados de elegibilidade
- [x] 5.3 Adaptar `ClientCreateModal` para CNPJ completo, consulta, prévia compacta, edição de razão social/nome interno, fallback manual, conflito com Cliente existente e limpeza segura ao concluir
- [x] 5.4 Adicionar `Cadastro` à subnavegação reproduzível de `/clients/[id]` e criar cards Settings para identidade da raiz, proveniência, estado, observações e contatos
- [x] 5.5 Ampliar Estabelecimentos com criação curta e edição completa de identidade imutável, situação, atividade, endereço, contato público e habilitação de captura
- [x] 5.6 Implementar lista e formulários de contatos internos distinguindo-os visual e semanticamente dos contatos públicos do CNPJ
- [x] 5.7 Aplicar permissões para `ADMIN`/`OPERATOR` editarem e `VIEWER` consultar em modo somente leitura, sem alterar a proteção existente do A1 por segundo fator
- [x] 5.8 Representar fonte/data, situação externa, estado interno e captura por texto, ícone e cor, explicando cada condição inelegível sem oferecer edição de NSU
- [x] 5.9 Cobrir loading, consulta parcial, indisponibilidade, dados desatualizados, vazio, 403, 422, sucesso e preservação de valores não sensíveis em testes de componentes

## 6. Validação funcional, visual e de segurança

- [x] 6.1 Executar formatador, análise estática e suíte backend completa, corrigindo regressões sem acessar CNPJ.ws ou ADN reais no CI
- [x] 6.2 Executar lint, typecheck e testes de componentes do frontend para modal, Cadastro, Estabelecimentos, Contatos, permissões e estados assíncronos
- [x] 6.3 Executar Playwright em 1440×900, 390×844 e 360 px cobrindo criação assistida, fallback manual, edição, viewer, conflito de raiz e captura desabilitada sem rolagem horizontal
- [x] 6.4 Verificar automaticamente que respostas, logs, fixtures, screenshots, traces e relatórios não contenham QSA, CPF, payload CNPJ.ws bruto, PFX, senha, chave privada, PEM, cookie, token ou `vault_object_id`
- [x] 6.5 Confirmar por comparação com `.reference/nuxt-dashboard-template` que modal e Settings preservam os arquétipos e registrar os arquivos-fonte usados na evidência da mudança
- [x] 6.6 Executar `openspec validate ampliar-cadastro-clientes --json` e corrigir qualquer divergência entre implementação, delta specs e tarefas

## 7. Backup, piloto e liberação

- [x] 7.1 Executar backup e ensaio de restauração antes da migração em ambiente com dados representativos, documentando o ponto de retorno sem usar `down` destrutivo após novos cadastros
- [x] 7.2 Validar em ambiente local com seed sintético o fluxo completo Cliente → primeiro Estabelecimento → Contato → A1 → primeira sincronização
- [x] 7.3 Executar piloto restrito com poucas raízes numéricas, comparar sugestões com fonte cadastral e confirmar rate limit, cache, datas e correções manuais
- [x] 7.4 Executar piloto de CNPJ alfanumérico em preenchimento manual, comprovando normalização, associação ao A1 da raiz e ausência de chamada incompatível ao fornecedor
- [x] 7.5 Liberar gradualmente, monitorar falhas de lookup, conflitos, entidades inelegíveis e sincronizações não enfileiradas e só então ampliar o uso do cadastro completo

## 8. Correção da experiência de criação após validação com o usuário

- [x] 8.1 Corrigir proposta, design e delta specs para manter modal básico com contato, A1, notas e campos adicionais seguros
- [x] 8.2 Ampliar o contrato transacional para aceitar e criar um contato interno responsável opcional, mantendo-o separado do contato público
- [x] 8.3 Modelar campos adicionais `TEXT`/`SECRET`, com tenancy, cofre, compensação, permissões e serialização sem conteúdo secreto
- [x] 8.4 Adaptar `ClientCreateModal` para dados básicos, contato responsável, A1 condicionado a `ADMIN` com 2FA, notas e linhas customizadas
- [x] 8.5 Remover `/clients/new`, restaurar botões/ações rápidas para o modal e exibir os campos adicionais no detalhe Settings
- [x] 8.6 Atualizar testes de contrato, unidade, integração e regressão visual para o modal revisado em desktop e mobile
- [x] 8.7 Executar validações backend/frontend, comparar visualmente com `customers/AddModal.vue` e validar a mudança OpenSpec
