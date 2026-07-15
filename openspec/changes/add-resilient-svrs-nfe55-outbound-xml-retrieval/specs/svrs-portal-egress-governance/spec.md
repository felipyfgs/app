## ADDED Requirements

### Requirement: Governança compartilhada por host e coorte de egress
Todo GET, POST ou redirecionamento manual destinado ao portal `dfe-portal.svrs.rs.gov.br` para recuperar NF-e 55 ou NFC-e 65 SHALL passar pelo mesmo governador associado à coorte de IP/NAT. O sistema MUST falhar fechado quando não puder reservar o orçamento ou garantir exclusão mútua.

#### Scenario: NF-e concorre com NFC-e
- **WHEN** uma transação NFC-e já está em voo na coorte e um job NF-e tenta reservar o canal
- **THEN** o job NF-e é reagendado sem materializar o A1 nem iniciar qualquer exchange HTTP

#### Scenario: Coordenador indisponível
- **WHEN** Redis ou o estado durável do governador não pode confirmar uma reserva atômica
- **THEN** nenhuma chamada remota ocorre e uma falha operacional sanitizada é registrada

### Requirement: Orçamento preventivo e contagem de exchanges
No rollout inicial o sistema SHALL permitir uma transação lógica em voo, intervalo global mínimo de 120 segundos, intervalo por raiz mínimo de 15 minutos, no máximo 10 exchanges por hora, 50 exchanges por dia e 6 chaves por raiz/dia. Cada GET, POST ou redirect manual MUST consumir orçamento antes do envio; cada job MUST tratar somente uma chave e não repetir na mesma execução.

#### Scenario: GET consumiu a última unidade
- **WHEN** o GET usa o último exchange disponível e não há orçamento reservado para o POST
- **THEN** a transação não inicia o GET e é reagendada como uma unidade atômica

#### Scenario: Limite diário atingido
- **WHEN** a coorte já consumiu 50 exchanges na janela diária
- **THEN** NF-e e NFC-e permanecem sem chamadas até a próxima janela e o backlog continua íntegro

### Requirement: Defaults desligados e sem auto-ramp
O canal SHALL manter master e auto-queue desligados por padrão. Limites só poderão ser alterados por configuração de deploy e decisão operacional versionada; a API/UI MUST NOT aumentar taxa e o sistema MUST NOT adaptar limites automaticamente após sucessos.

#### Scenario: ADMIN tenta elevar orçamento pela UI
- **WHEN** um ADMIN solicita intervalo menor ou limite maior pela interface
- **THEN** a ação é recusada sem mudar o governador

### Requirement: Bloqueio textual em HTTP 200
O sistema SHALL reconhecer `IP não autorizado devido múltiplas consultas` e templates equivalentes como `SVRS_EGRESS_BLOCKED_MULTIPLE_QUERIES`, independentemente do status HTTP. O resultado MUST abrir imediatamente o breaker global da coorte para NF-e e NFC-e.

#### Scenario: Página bloqueada retorna sucesso HTTP
- **WHEN** a SVRS responde HTTP 200 com o marcador normalizado de múltiplas consultas
- **THEN** nenhum XML é procurado ou ingerido, o breaker global abre e novas chamadas são impedidas

### Requirement: Cooldown sem bypass e canário único
Após bloqueio por múltiplas consultas, o sistema SHALL aplicar cooldown inicial de 24 horas e permitir somente um canário allowlisted após `next_probe_at`. Reincidências SHALL ampliar o cooldown para 48, 96 e 168 horas; nenhum papel MUST antecipar a prova, e `Retry-After` maior MUST prevalecer.

#### Scenario: Reprocessamento antes da hora
- **WHEN** ADMIN ou OPERATOR tenta reprocessar uma chave antes de `next_probe_at`
- **THEN** o sistema recusa a chamada, preserva o backlog e oferece a contingência assistida

#### Scenario: Canário continua bloqueado
- **WHEN** o único canário permitido retorna o mesmo marcador de bloqueio
- **THEN** o breaker reabre no próximo patamar e nenhum segundo canário é executado

### Requirement: Proibição de evasão
O sistema MUST NOT rotacionar IP, proxy, certificado, raiz, escritório ou worker para contornar orçamento ou breaker e MUST NOT disponibilizar URL, host, cabeçalho ou coorte arbitrários por request.

#### Scenario: Outra raiz está disponível
- **WHEN** o breaker global está aberto e existe A1 de outra raiz
- **THEN** o sistema não usa a outra raiz como prova e mantém todo tráfego da coorte bloqueado

