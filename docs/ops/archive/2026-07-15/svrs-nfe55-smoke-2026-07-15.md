# Smoke restrito SVRS NF-e 55 — 2026-07-15

## Escopo e autorização

- Empresa piloto: cliente interno 14, estabelecimento MA, produção.
- Documento canário: NF-e 55 anterior já cadastrada como semente, com XML original e SHA-256 disponíveis offline no cofre.
- A1: ativo e relacionado à raiz; materializado somente após reserva do egress.
- Autorização: solicitação explícita do operador nesta sessão, registrada também na ativação auditada do perfil.
- Janela: 2026-07-15 10:46 BRT.
- Plano de parada: uma única transação lógica GET+POST; interromper diante de bloqueio, contrato divergente, identidade ou validação inválida; auto-queue NF-e 55 permanece desligado.

Nenhuma chave completa, CNPJ, XML, PFX, senha, PEM, cookie ou hash fiscal é publicado neste registro.

## Execução sanitizada

- Governador compartilhado reservou previamente 2 exchanges para o canal `nfe55`.
- Foi executado um único GET autenticado seguido de um único POST allowlisted.
- Resposta HTTP: `200`.
- Parser: versão `2`.
- Resultado tipado: `RESPONSE_CONTRACT_CHANGED`.
- Detalhe sanitizado: literal oficial do Blob não localizado de forma inequívoca.
- XML remoto aceito/persistido: não.
- Nova tentativa na mesma janela: não executada.

## Parada e fallback

- O breaker da coorte foi aberto com causa `SVRS_EGRESS_CONTRACT_CHANGED`.
- Próxima prova não poderá ocorrer antes de 2026-07-16 10:46 BRT.
- Como o breaker é compartilhado pelo host, novas recuperações NF-e 55 e NFC-e 65 ficam bloqueadas até revisão/canário permitido.
- O XML original da semente permanece íntegro no cofre e é o fallback do documento testado.
- O perfil piloto e as posições por nNF foram preservados; nenhum documento foi marcado como capturado pela tentativa remota.

## Decisão

Gate NF-e 55 permanece **não aprovado**. Antes de nova chamada real é obrigatório obter uma fixture sanitizada do wrapper observado por meio autorizado, adaptar o parser fechado sem executar JavaScript, validar localmente e aguardar `next_probe_at`.
