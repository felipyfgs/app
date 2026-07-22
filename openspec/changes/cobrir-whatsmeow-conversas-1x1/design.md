## Context

O baseline `adicionar-comunicacao-whatsapp-nativa` criou um gateway Go privado, device store, leases, comandos duráveis e uma ponte inicial de eventos. A versão do whatsmeow está pinada em `v0.0.0-20260721154117-8b4a8ba0d318`; nessa versão `*whatsmeow.Client` possui 135 métodos públicos únicos e `types/events` possui 74 structs públicas. O gateway usa diretamente dez métodos e traduz cinco tipos de evento.

O WuzAPI foi auditado no commit `70642149a0e8a81d49caa640f557217e03e09729`, licença MIT, com whatsmeow `v0.0.0-20260516102357-8d3700152a69`. Ele demonstra 55 chamadas públicas e uma API REST ampla, mas usa uma versão anterior, entrega eventos brutos, aceita mídia base64/URL/S3 e não oferece as mesmas garantias de outbox, tenancy ou replay do hub. Ele será referência de comportamento e testes, não dependência nem runtime incorporado.

O recorte é exclusivamente conversa 1:1. Grupos, communities, newsletters/canais e status/broadcast serão rejeitados pelo boundary mesmo quando uma primitiva genérica do whatsmeow também os aceite. Laravel continua dono de Office, permissões, conversa e histórico; o gateway continua dono de sessão e protocolo.

## Goals / Non-Goals

**Goals:**

- Dar uma disposição explícita a cada método/evento da versão pinada e detectar deriva futura.
- Implementar todas as operações aplicáveis a 1:1 por contratos tipados, sem expor JID/protobuf bruto ao usuário.
- Cobrir mensagens e ações, mídia, sessão, presença, receipts, descoberta/perfil, privacidade, bloqueio, app-state, histórico e recuperação.
- Traduzir eventos 1:1 para envelopes allowlisted e idempotentes, com testes por família.
- Aproveitar do WuzAPI builders e regras comprovadas somente após adaptação à versão pinada e aos controles do hub.

**Non-Goals:**

- Grupos, communities, newsletter/canais, status/broadcast, campanhas, bots/FB/Armadillo e chamadas de áudio/vídeo.
- Expor `DangerousInternals`, protobuf arbitrário, peer messages genéricas ou configuração de transporte ao usuário final.
- Tornar WuzAPI um serviço, copiar seu modelo de usuário/webhook/S3 ou substituir o domínio Laravel.
- Habilitar flags, números, SERPRO live, mutações fiscais, canais SEFAZ ou serviços `mei`/`mei-worker`.

## Decisions

### 1. Catálogo executável é a definição de completude

O repositório terá manifestos versionados para os 135 métodos e 74 eventos com `source`, `scope`, `disposition`, `owner`, `evidence` e referência quando aplicável. As disposições finais serão `baseline`, `implemented`, `internal`, `excluded` e `deprecated`; toda entrada aplicável deverá apontar para código e teste, e toda exclusão deverá ter motivo.

Um teste por reflexão comparará o method set real de `*whatsmeow.Client` ao manifesto e falhará em adição, remoção ou renomeação. Eventos terão referências de compilação para todos os tipos catalogados e um snapshot de fonte/versão; o checklist será revisado quando a dependência mudar. O catálogo humano resumirá a matriz e a referência WuzAPI.

Alternativa rejeitada: considerar apenas os endpoints implementados. Isso não detecta métodos omitidos nem mudanças silenciosas do upstream.

### 2. Operações de produto, não RPC bruto do Client

Métodos de baixo nível serão compostos em operações estáveis do gateway:

- sessão: conectar, desconectar, resetar, logout, QR, pareamento por telefone, estado e modo passivo;
- mensagem: texto/preview/resposta, imagem, áudio/PTT, vídeo, documento, sticker, localização, contato e poll;
- ação: editar, revogar, reagir/remover reação, votar, marcar lida/tocada, temporizador e solicitar mensagem indisponível;
- sinais: presença global, typing/paused/recording e subscription de presença;
- contato/conta: disponibilidade, user info, business profile, avatar, links/QR, blocklist e privacy;
- estado/sync: archive, mute, pin, star/delete-for-me quando compatível, app-state e history sync 1:1.

Mutação continuará no `POST /internal/v1/commands`, persistida antes do 202. Leituras remotas usarão `POST /internal/v1/sessions/{sessionId}/queries`, HMAC e resposta sanitizada com deadline, sem serem gravadas como comandos. O contrato nunca aceitará `waE2E.Message`, `waBinary.Node`, JID com server arbitrário ou URL de mídia fornecida pelo usuário.

Alternativa rejeitada: endpoint genérico `method + args`. Ele tornaria breaking changes do upstream parte da API e permitiria escapar dos filtros de grupo/canal.

### 3. Allowlist 1:1 central e resolução PN/LID

Todo endereço entrará como telefone E.164 normalizado ou identificador opaco já associado à identidade do Office. O gateway aceitará somente `DefaultUserServer` e `HiddenUserServer`, resolverá LID↔PN pelo store quando necessário e rejeitará `GroupServer`, `NewsletterServer`, broadcast e servers desconhecidos antes de efeito remoto.

A resolução de blocklist observada no WuzAPI será adaptada: o servidor exige PN, portanto LID será convertido pelo store; ausência de mapping falhará fechada. Nenhuma resposta pública devolverá device JIDs ou dados de outros contatos.

### 4. Mensagens usam DTOs allowlisted e IDs estáveis

`MESSAGE_SEND` ganhará `kind` e payload específico. Mídia continuará chegando ao gateway pelo stream interno ligado ao `command_id`; thumbnail será derivada localmente quando útil. Quotes referenciarão `provider_message_id` e metadados mínimos já persistidos pelo Laravel. `BuildEdit`, `BuildRevoke`, `BuildReaction`, `BuildPollCreation` e `BuildPollVote` serão compostos com `SendMessage`; funções lower-level de criptografia serão classificadas como internas.

Padrões WuzAPI aproveitados: campos de áudio/PTT, thumbnail de imagem em memória, metadados de sticker, localização/contato, poll e decriptação de voto/edit. Padrões rejeitados: base64, fetch de URL arbitrária, `context.Background()` sem deadline, retorno de protobuf e geração de ID no gateway quando Laravel já é a fonte do ID.

### 5. Eventos são normalizados por durabilidade e tipo

A ponte filtrará grupo/canal antes de qualquer persistência e produzirá envelopes explícitos:

- duráveis: mensagem recebida/alterada/revogada/reação/poll, receipt, sessão, history batch, undecryptable/media retry, blocklist/privacy/identity/profile;
- efêmeros, ainda entregues com retry curto: presence e chat presence;
- operacionais sanitizados: connect failure, stream error/replaced, keepalive, client outdated e temporary ban.

Inbound reconhecerá texto, extended text/reply, imagem, áudio, vídeo, documento, sticker, localização, contato, poll/voto e respostas interativas. `DecryptPollVote` e `DecryptSecretEncryptedMessage` serão usados antes da projeção. Raw event, media keys, direct paths, token, QR e nodes nunca sairão no payload.

Alternativa rejeitada: `postmap["event"] = rawEvt` do WuzAPI, pois acopla consumidores ao upstream e pode expor material sensível.

### 6. App-state e histórico permanecem controlados

Builders `appstate.BuildArchive`, `BuildMute`, `BuildPin`, `BuildStar`, `BuildDeleteChat` e `BuildMarkChatAsRead` serão allowlisted por ação. `FetchAppState`, `MarkNotDirty`, `ParseWebMessage`, `DownloadHistorySync`, `BuildHistorySyncRequest` e peer send serão usados somente em fluxos internos. History sync imporá destino 1:1, limite, cursor, deduplicação por provider ID e ingestão no Laravel; nunca percorrerá grupos como o fluxo automático do WuzAPI.

### 7. Segurança do baseline prevalece sobre a referência

Todas as chamadas mantêm HMAC com key ID, nonce e janela; queries também passam por replay protection. Comandos e eventos continuam duráveis. Mídia permanece no spool/vault privado. Logs são estruturados e sanitizados; em especial, não repetirão o padrão WuzAPI que registra token no evento de pairing. Operações administrativas exigirão `communication.manage_inboxes`; ações de conversa exigirão `communication.reply`; leitura respeitará membership e `CurrentOffice`.

### 8. Compatibilidade com a change upstream

Esta change não editará artifacts de `adicionar-comunicacao-whatsapp-nativa`. Ela amplia de forma aditiva seus enums, OpenAPI, workers e projeções. `MESSAGE_SEND` antigo sem `kind` continuará significando texto/documento conforme o payload atual. Migrações, quando necessárias para novos `MessageKind` ou metadados, serão aditivas; nenhuma tabela do baseline será reescrita.

## Mapa de dependências

```text
C0 adicionar-comunicacao-whatsapp-nativa (apply concluído no worktree)
                         │
                         ▼
C1 cobrir-whatsmeow-conversas-1x1

N0 catálogo + contratos + testes de deriva
 ├── N1 sessão/queries/allowlist
 ├── N1 mensagens/ações/mídia
 └── N1 eventos/sync/app-state
          │
          ▼
   N2 projeções Laravel + UX necessária
          │
          ▼
   N3 gates integrados e auditoria final
```

- Ownership C1: novos manifests, operações e eventos; extensões aditivas em gateway/OpenAPI/Comunicação.
- Arquivos compartilhados: `domain/types.go`, `command/worker.go`, `protocol/*`, OpenAPI, enums/DTOs/ingestor, tipos e componentes de Comunicação.
- Marco upstream: `apply`; gate coordenado: testes Go/API/Web e Compose/OpenSpec do conjunto presente no worktree.
- Rollout: flags permanecem OFF; as operações podem ser ativadas por Office/inbox sem alterar sessões existentes.
- Rollback: desligar as operações novas e manter ledger/eventos; comandos antigos continuam compatíveis e nenhuma credencial é removida automaticamente.

## Risks / Trade-offs

- [A superfície do upstream muda] → teste de method set, versão/commit no manifesto e revisão obrigatória de cada entrada.
- [Método genérico aceita grupo/canal] → allowlist central de JID antes de qualquer chamada e testes negativos por família.
- [Vazamento entre Offices] → Laravel resolve sessão por inbox do Office, gateway nunca aceita `office_id` como autoridade e queries usam sessão já mapeada.
- [Segredos ou PII em log/API] → DTOs allowlisted, redaction e testes que rejeitam raw events, QR, token, media key e direct path.
- [Amostra WuzAPI está em versão anterior] → reimplementar contra assinatura pinada e usar a referência apenas como caso de comportamento.
- [Operações de conta ampliam risco] → permissão administrativa, flags OFF, audit trail e nenhuma UI automática para primitivas internas.
- [Histórico duplica mensagens] → provider ID + inbox únicos, cursor/limites e ingestão idempotente.
- [Presença aumenta carga/privacidade] → subscriptions explícitas, TTL, eventos efêmeros e política de privacidade respeitada.
- [Protocolo não oficial causa banimento] → rate limits, kill switches, nada de campanha/broadcast e rollout por número controlado.
- [Serviço proibido aparece no Compose] → nenhum serviço novo; gates mantêm rejeição de `mei`/`mei-worker`.

## Migration Plan

1. Versionar catálogo, referência WuzAPI e testes de deriva sem alterar runtime.
2. Estender contratos/enums de forma backward-compatible e implementar famílias no gateway com testes focados.
3. Integrar projeções Laravel e somente então expor ações úteis na inbox, sempre atrás das flags existentes.
4. Validar com fakes e sessão controlada; nenhuma chamada live será executada pelos testes.
5. Rodar gates Go, API, Web, Compose e OpenSpec; comparar novamente todos os 135 métodos e 74 eventos.

Rollback desabilita flags/operações e preserva o baseline. Não há downgrade destrutivo de banco nem logout automático.

## Open Questions

- Nenhuma decisão bloqueante. Buttons/list serão catalogados como tipos de mensagem compostos e só permanecerão habilitáveis se a versão pinada e testes de contrato confirmarem o protobuf vigente; caso contrário terão disposição `excluded-unsupported`, não fallback silencioso.
