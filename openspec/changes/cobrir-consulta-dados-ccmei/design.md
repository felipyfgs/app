## Context

A documentação oficial do SERPRO define `CCMEI/DADOSCCMEI122`, rota de negócio
`/Consultar`, versão `1.0` e objeto `pedidoDados.dados` vazio. A identidade do
contribuinte vem do envelope da Integra Contador; o serviço devolve uma string
JSON escapada com dados cadastrais, enquadramento, atividade e um QR code Base64.
O projeto já contém o catálogo oficial, o alias `ccmei.dadosccmei`, clientes
fake/simulated, mapper de Simples/MEI e um `CcmeiDto` preliminar, mas não tem um
fluxo explícito, histórico e UI que correspondam a este contrato.

O resultado pertence ao cliente do escritório corrente. O CNPJ será obtido do
cliente que já foi autorizado no contexto de `CurrentOffice`, nunca do corpo ou
query string da requisição. Flags SERPRO permanecem desligadas por padrão.

## Goals / Non-Goals

**Goals:**

- Realizar a consulta local do certificado CCMEI de um cliente já pertencente
  ao escritório atual, usando o catálogo oficial e o envelope sem `dados`.
- Normalizar apenas metadados de negócio necessários à interface e persistir
  evidência segura, sem material de QR code, CPF ou documento bruto em resposta
  ou logs.
- Oferecer uma interface explícita com estados de carregamento, vazio, erro e
  sucesso, e histórico limitado ao mesmo escritório e cliente.
- Verificar o comportamento em fake/simulated e deixar a homologação real
  registrada como pendência operacional, nunca como evidência concluída.

**Non-Goals:**

- Emitir CCMEI, alterar dados cadastrais, mudar opção MEI ou acionar produção.
- Armazenar/exibir QR code Base64, CPF completo, tokens, PFX, XML ou payload
  bruto da Integra Contador.
- Aceitar `office_id`, CNPJ ou identificador de contribuinte fornecido pelo
  navegador como autoridade de tenancy.
- Alterar as regras de autorização transversais em
  `padronizar-autorizacao-multitenant`.

## Decisions

### Consulta por cliente tenant-scoped

O endpoint local receberá somente o identificador de cliente resolvido por
binding e verificará seu vínculo com `CurrentOffice`; a chamada SERPRO montará
o contribuinte a partir desse registro e `pedidoDados.dados` será a string vazia
exigida pela documentação. Isso evita que um usuário escolha arbitrariamente
um CPF/CNPJ ou escritório.

Alternativa considerada: receber `cnpj` no formulário. Foi rejeitada porque o
contrato oficial não requer esse campo adicional e ele aumentaria o risco de
consulta fora da carteira autorizada.

### Codec explícito e projeção sanitizada

Será criado ou ajustado um codec específico para decodificar a string `dados`
apenas quando for JSON válido. Ele validará a forma mínima, descartará o QR code
e reduzirá empresário/endereço a campos de apresentação permitidos. O
`CcmeiDto` e a evidência persistida carregarão uma versão de schema para permitir
reprocessamento sem expor o payload original.

Alternativa considerada: reutilizar o DTO genérico atual com `raw`. Foi
rejeitada porque o campo pode reter CPF, endereço e Base64 além do necessário.

### Execução somente por capability fail-closed

O serviço usará o driver `simples_mei` existente e respeitará kill switch,
capability e allowlist. Fake/simulated é a única execução coberta por testes.
O erro externo será convertido em mensagem estável e sanitizada; logs terão
somente chave da operação, IDs internos e código categorizado.

Alternativa considerada: chamar o HTTP do SERPRO diretamente pelo controller.
Foi rejeitada por burlar a política central de flags, autenticação, auditoria e
sanitização.

### UI no contexto de detalhe do cliente

A ação e o histórico ficarão no detalhe do cliente, onde sua pertença ao
escritório já é explícita. A UI reutilizará componentes Nuxt UI existentes e
não criará uma página global de documentos MEI; a ação será claramente de
consulta e não sugerirá emissão ou validade jurídica do certificado.

## Risks / Trade-offs

- [Contrato oficial contém PII e QR code] → codec com allowlist de campos,
  descarte explícito de Base64 e testes que comprovem ausência de material
  sensível na API e nos logs.
- [Sem credenciais/autorização para homologação] → manter capability simulada,
  registrar pendência e exigir aprovação explícita antes de qualquer chamada
  externa.
- [Dados oficiais variam em campos opcionais] → validar estrutura mínima e
  apresentar estado de evidência indisponível em vez de inferir validade.
- [Worktree possui changes concorrentes] → não editar arquivos pertencentes às
  changes de monitoramento ou autorização; serializar qualquer path comum.

## Mapa de dependências

```text
C0 cobrir-consulta-dados-ccmei
 ├─ N0: confirmar contrato oficial, catálogo e limites de dados
 ├─ N1: backend/codec/projeção + testes de isolamento
 ├─ N2: contrato HTTP e UI do detalhe de cliente + testes Nuxt
 └─ N3: gates integrados, revisão de diff e matriz de cobertura
```

Não há upstream ativo obrigatório. A change possui ownership de
`ccmei-certificate-consultation`; `padronizar-autorizacao-multitenant` continua
coordenada por consumir as mesmas garantias estáveis de `CurrentOffice`, sem
editar seus artefatos. Rollback remove a rota e a UI novas, preservando qualquer
evidência existente como inacessível até migração explícita; nenhuma flag live é
ativada no rollout.
