## Contexto

As consultas CCMEI já possuem projeções locais para dados e situação cadastral,
mas a operação produtiva `ccmei.emitirccmei` ainda não transforma a resposta
documental oficial em um certificado protegido e disponível ao escritório
corrente. O fluxo precisa usar o cliente pertencente ao `CurrentOffice`, evitar
egress implícito e nunca expor PDF, Base64, CPF/CNPJ completo ou parâmetros
técnicos SERPRO ao navegador.

## Objetivos / Não objetivos

**Goals:**

- Criar um contrato específico, fail-closed e sanitizado para
  `EMITIRCCMEI121`.
- Persistir somente projeção e descritor auditável; guardar bytes documentais
  exclusivamente no `SecureObjectStore`.
- Oferecer consulta manual confirmada, histórico local e download autorizado
  no detalhe do cliente, com todos os estados de negócio.
- Preservar flags OFF, kill switch, limites e a classificação `BLOCKED` até
  Trial/canário aprovados.

**Non-Goals:**

- Não realizar canário, não habilitar runtime real/Trial e não alterar
  credenciais, Termo, procurações ou allowlists.
- Não reutilizar JSON cru do executor como resposta pública e não criar mutação
  fiscal, envio externo ou acesso de plataforma sem contexto privilegiado.

## Decisões

1. **Serviço de domínio específico, não controller genérico.** Um
   `CcmeiCertificateIssuanceService` receberá `Office` e `Client` resolvidos
   no servidor, chamará o executor e delegará a decodificação/projeção. Isso
   preserva tenancy, idempotência e sanitização; montar o envelope no
   controller duplicaria regras e arriscaria expor parâmetros SERPRO.

2. **Documento no cofre, metadados na projeção.** O codec aceitará apenas os
   formatos oficiais confirmados e rejeitará conteúdo inválido, grande demais
   ou ambíguo. O PDF será escrito no `SecureObjectStore`; banco/API receberão
   hash, MIME, tamanho, referência segura e estado. Persistir Base64 no banco
   simplificaria a consulta, mas viola o limite de segredo e artefato.

3. **POST confirmado para egress; GET somente local.** A interface primeiro
   lê histórico local. A emissão ocorre somente após ação consciente do usuário
   autorizado, com orçamento/rate limit e `x-request-tag` sanitizado. Polling,
   montagem de componente e refresh visual não chamam SERPRO.

4. **Download same-origin por descritor.** Um endpoint de download valida
   `CurrentOffice`, permissão e vínculo da projeção antes de abrir o cofre. O
   navegador nunca recebe referência interna de vault nem URL externa.

## Mapa de dependências

`catálogo reconciliado` → `codec/DTO` → `serviço + cofre + projeção` → `API`
→ `painel UI` → `testes/gates/ledger` → `Trial/canário autorizado`.

Ownership desta change: classes e testes específicos de emissão CCMEI e o
subpainel correspondente. Arquivos compartilhados (catálogo canônico,
`OperationKeyMap`, ledger, `createFiscalApi` e rotas) serão alterados somente
depois de leitura do estado atual e de forma serializada. As changes CCMEI de
consulta são bases coordenadas já aplicadas; não há upstream ativo bloqueante.

## Riscos / Trade-offs

- **Layout oficial documental divergente** → usar fonte oficial/fixture
  sanitizada e rejeitar qualquer variante desconhecida; atualizar snapshot em
  change própria se houver divergência.
- **Consulta potencialmente bilhetável** → POST explícito, rate limit,
  orçamento e nenhuma repetição automática.
- **Vazamento documental** → bytes só no cofre, API/DOM/testes com descritor
  sanitizado e scan de artefatos.
- **Reexecução concorrente** → chave idempotente derivada de office, cliente,
  operação e versão de contrato; timeout fica pendente de conciliação, sem
  retry cego.
- **Produção sem contexto fiscal válido** → runtime permanece fail-closed e
  ledger registra `BLOCKED_EXTERNAL`, não um PASS artificial.

## Plano de migração

Adicionar migrações forward-only para projeção/observação e referência segura,
rodar testes em SQLite e PostgreSQL, publicar com flag OFF e executar somente
homologação Trial quando autorizada. Rollback é lógico: desabilitar a
capability, impedir novos POSTs e preservar evidências já registradas; não há
remoção de documento ou migração destrutiva.

## Questões em aberto

- Confirmar na página oficial específica os nomes e a forma exata dos campos
  documentais antes do codec final.
- Antes de Trial/canário, confirmar existência de cliente piloto elegível,
  Termo, procuração/poder, teto de custo e autorização operacional.
