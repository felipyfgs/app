## ADDED Requirements

### Requirement: Elegibilidade de NF-e 55 por chave conhecida
O sistema SHALL aceitar para recuperação SVRS somente chave válida de modelo 55 já vinculada a documento de saída, escritório, ambiente, raiz emitente e A1 relacionado ativos. Chave fornecida sem vínculo interno ou de outro modelo MUST ser recusada antes de qualquer rede.

#### Scenario: Chave modelo 65
- **WHEN** uma solicitação NF-e 55 contém chave cujo modelo é 65
- **THEN** a solicitação é recusada localmente sem reservar egress nem materializar certificado

#### Scenario: A1 não relacionado
- **WHEN** nenhuma credencial ativa corresponde à raiz esperada da chave
- **THEN** a pendência é roteada para contingência com motivo sanitizado e nenhuma chamada ocorre

### Requirement: Transporte mTLS allowlisted e segredo efêmero
O cliente SHALL usar TLS 1.2+, validar CA/hostname e acessar somente o host/path configurado. O PFX MUST sair do `SecureObjectStore` apenas em memória por BLOB, e PFX, senha, chave privada, PEM, cookie e conteúdo bruto remoto MUST NOT aparecer em disco, API, log, métrica ou exportação.

#### Scenario: Redirect para outro host
- **WHEN** GET ou POST responde com redirect fora da allowlist
- **THEN** o cliente não segue o redirect, descarta a sessão efêmera e retorna contrato proibido

### Requirement: Contrato fechado do formulário NFESSL
O cliente SHALL implementar no máximo um GET autenticado e um POST para `/NfeSSL/DownloadXmlDfe` com os campos allowlisted esperados. O parser MUST executar somente gramática mínima de literal/escape, sem `eval`, navegador, JavaScript genérico ou heurística permissiva.

#### Scenario: Wrapper contém concatenação executável
- **WHEN** o candidato de download depende de expressão, concatenação ou template string
- **THEN** o parser retorna `RESPONSE_CONTRACT_CHANGED`, não executa conteúdo e aciona o breaker aplicável

### Requirement: Resultados remotos tipados
O cliente SHALL distinguir captura, bloqueio por múltiplas consultas, autenticação proibida, rate limit, indisponibilidade transitória, não encontrado, contrato alterado e payload inválido. Mensagens expostas MUST ser sanitizadas e MUST NOT incluir HTML, JavaScript, XML ou chave completa.

#### Scenario: Bloqueio com HTTP 200
- **WHEN** a resposta contém marcador de IP bloqueado e também scripts genéricos de download
- **THEN** o bloqueio prevalece e o sistema não classifica a existência do script como XML capturado

### Requirement: Validação fiscal e criptográfica integral
Antes da ingestão o sistema SHALL exigir XML bem-formado, `nfeProc` modelo 55, chave/ambiente/emitente esperados, protocolo autorizado vinculado, digests íntegros e assinatura XMLDSig válida. O SHA-256 SHALL corresponder aos bytes originais preservados.

#### Scenario: XML válido de outra chave
- **WHEN** o portal devolve `nfeProc` assinado cuja chave difere da solicitada
- **THEN** o sistema não ingere o XML, bloqueia a tentativa e gera alerta crítico sanitizado

#### Scenario: XSD futuro com identidade íntegra
- **WHEN** o XML é bem-formado e passa identidade, protocolo, digest e assinatura mas usa versão XSD desconhecida
- **THEN** os bytes são preservados com alerta de parse sem inventar ou normalizar o XML original

### Requirement: Gates de smoke e piloto
O sistema MUST manter auto-queue desligado até existirem fixtures, fake server, governador compartilhado, detector do bloqueio HTTP 200, backup/restore, rollback drill e smoke restrito bem-sucedido com NF-e 55 de saída MA e XML original de comparação.

#### Scenario: Bloqueio ainda não expirou
- **WHEN** os testes locais estão completos mas `next_probe_at` ainda não chegou
- **THEN** nenhum smoke real é executado e a liberação permanece pendente

