## Por quê

O cadastro atual conserva apenas nome, raiz do CNPJ, observação e estado, descarta o CNPJ completo informado na criação e obriga o operador a redigitá-lo para cadastrar o primeiro estabelecimento. A consulta pública já fornece identidade empresarial, situação cadastral, matriz/filial, endereço e atividade econômica, mas o sistema reduz o retorno a quatro campos e a interface aproveita somente dois, deixando o onboarding incompleto e sujeito a erros manuais.

## O que muda

- Ampliar o modelo de Cliente, no nível da raiz, com razão social, nome interno opcional, natureza jurídica, porte, estado operacional, motivo de inativação, observações e metadados da última consulta cadastral.
- Ampliar o modelo de Estabelecimento com CNPJ completo, matriz/filial, nome fantasia, situação e datas cadastrais, CNAE principal, endereço estruturado, contato público, estado operacional, habilitação independente da captura e metadados da fonte.
- Criar contatos internos estruturados do cliente, separados dos telefones e e-mails públicos retornados pela consulta de CNPJ.
- Expandir a integração com a CNPJ.ws por meio de um DTO sanitizado e explicitamente permitido, sem armazenar nem devolver QSA, CPF, capital social, resposta bruta ou outros dados fora do escopo.
- Tratar os dados externos como sugestões editáveis e datadas, preservando o preenchimento manual quando a fonte estiver indisponível, limitada, desatualizada ou ainda não suportar CNPJ alfanumérico.
- **BREAKING**: substituir o contrato de criação resumida por uma criação transacional de Cliente e primeiro Estabelecimento a partir do CNPJ completo, com campos explícitos de razão social e nome interno e resposta contendo ambos os registros.
- Separar situação do relacionamento (`is_active`) de habilitação da captura (`capture_enabled`), impedindo que um cadastro inativo ou uma situação cadastral incompatível seja sincronizada silenciosamente.
- Manter a criação em modal focado, com somente CNPJ, razão social, nome fantasia, contato responsável, notas, A1 opcional e campos adicionais definidos pelo usuário; a consulta cadastral auxilia os nomes sem transformar a criação em ficha pública extensa.
- Permitir campos adicionais do tipo texto ou segredo. Segredos são gravados no cofre criptografado, nunca entram em notas, logs, exportações ou respostas comuns e aparecem depois somente como configurados, sem recuperação do valor.
- Cobrir validação local e de API, transação atômica, tenancy por `office_id`, auditoria, rate limit/cache da consulta, estados de carregamento/erro/fallback e responsividade.

## Capacidades

### Novas capacidades

Nenhuma. A mudança amplia contratos já existentes de cadastro/credencial e experiência do painel.

### Capacidades modificadas

- `client-credential-management`: amplia o cadastro de clientes e estabelecimentos, o preenchimento assistido por CNPJ, a separação entre estado cadastral e captura, os contatos internos e a criação transacional do primeiro estabelecimento.
- `frontend-dashboard-experience`: adiciona a seção `Cadastro` e amplia o modal de criação com dados básicos, contato, A1 e campos adicionais seguros, com permissões e fallback manual.

## Impacto

- **Backend:** novas migrações PostgreSQL; evolução dos modelos `Client` e `Establishment`; nova entidade de contatos; validações e políticas; transação de criação; DTO sanitizado da consulta; auditoria e testes de isolamento.
- **API:** alteração do `POST /api/v1/clients`; ampliação dos contratos de consulta, detalhe e atualização; contato responsável e campos adicionais iniciais opcionais; endpoints de contatos e manutenção cadastral; manutenção do lookup somente no backend.
- **Frontend:** tipos e `useApi`; `ClientCreateModal`; detalhe `/clients/[id]`; novos componentes de cadastro, contatos e campos adicionais; edição de estabelecimentos; estados de consulta e dados desatualizados.
- **Operação:** permanece o limite público de três consultas por minuto por IP e o cache configurável; nenhuma dependência nova é necessária para a consulta HTTP.
- **Segurança:** `office_id` continua derivado da sessão; respostas e logs não podem conter QSA, CPF, PFX, senha, chave privada, PEM ou payload bruto da fonte externa.

## Não-objetivos

- Transformar o produto em ERP contábil, CRM ou portal para clientes finais.
- Cadastrar ou manter QSA, CPF de sócios, capital social, inscrições estaduais, inscrições municipais, SUFRAMA ou dados completos de Simples/MEI nesta etapa.
- Consultar APIs municipais, emitir ou cancelar NFS-e, selecionar papéis fiscais manualmente ou alterar o fluxo de distribuição por NSU.
- Tornar a CNPJ.ws fonte de verdade ou bloquear o cadastro quando a consulta externa falhar.
- Suportar troca livre de escritório, expor material do certificado A1 ou armazenar a resposta externa bruta.
