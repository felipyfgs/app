## Contexto

O módulo `registrations` já projeta `pnr_contador.consultar_vinculos`, mas o
catálogo oficial também contém as operações de consulta de renúncias,
emissão de comprovante e consulta de situação. Hoje elas passam apenas pelo
executor genérico: não há contrato de resposta validado, persistência de
evidências sanitizadas nem uma tela de negócio para o escritório.

Esta change completa somente o ciclo de leitura. `solicitar_renuncia` é uma
declaração que muda a situação fiscal e permanece sem rota, botão ou flag de
habilitação.

## Objetivos / Não objetivos

### Objetivos

- Modelar operações PNR de leitura com chaves exatas do catálogo oficial.
- Validar a resposta antes de persistir qualquer projeção e rejeitar fonte
  sintética/legada.
- Isolar dados por `CurrentOffice`, guardar somente resumo sanitizado no banco
  e enviar conteúdo documental ao cofre quando aplicável.
- Exibir no painel o histórico, a situação e o descritor do comprovante com
  estados explícitos de vazio, bloqueio e erro.
- Permitir execução manual sob o ambiente configurado (normalmente `TRIAL`),
  sem disparo em lote ou automático.

### Não objetivos

- Criar, enviar ou habilitar `pnr_contador.solicitar_renuncia`.
- Promover qualquer operação a produção, executar canário ou contornar
  flags, contrato, Termo, procuração, 2FA e kill switch.
- Ler, copiar ou expor PFX, tokens, Consumer Secret ou `dados/senhas.txt`.
- Remover dados históricos existentes nesta change.

## Decisões

### Adaptador de domínio único para renúncias PNR

Será criado um serviço de projeção específico, usando
`SerproOperationExecutor`, `SerproContractService`, `CapabilityDriverResolver`
e os mesmos guardrails do serviço de vínculos. Ele recebe `Office` e `Client`
explicitamente e nunca um `office_id` vindo da requisição.

As operações serão identificadas pelas chaves `pnr_contador.consultar_renuncias`,
`pnr_contador.emitir_comprovante` e `pnr_contador.situacao_renuncia`. A
operação de comprovante é tratada como leitura sob execução manual: o sistema
não a agenda e não a chama sem ação do usuário autorizado.

### Codec fail-closed e evidência mínima

Cada resposta terá codec dedicado, baseado somente no envelope oficial
reconciliado. Campos obrigatórios, identificadores e paginação serão validados
antes da projeção. Layout desconhecido, resposta incompleta ou proveniência
`SIMULATED`/legada retornam erro e não criam registros.

O banco armazena campos de consulta e um resumo sanitizado. Bytes de
comprovante, se fornecidos, seguem para `SecureObjectStore`; rotas e logs não
devolvem o conteúdo bruto.

### Superfície no detalhe do cliente

O painel seguirá o shell Nuxt UI já usado em monitoramento: uma aba ou cartão
de renúncias no detalhe do cliente, com ações manuais separadas para consultar
histórico, consultar situação por identificador e obter comprovante por
identificador. A UI não terá ação de solicitar renúncia e não enviará
`office_id`.

### Proveniência e ambiente

Projeções novas só aceitam `SERPRO_TRIAL` ou `SERPRO_REAL`; o rótulo mostra a
origem recebida sem chamar TRIAL de produção. A elegibilidade para produção
continua responsabilidade do fluxo de canário e evidência real por operação.

## Riscos / Trade-offs

- O layout oficial pode variar entre contratos. Rejeitar respostas não
  reconhecidas reduz risco de dado incorreto, mas exige ampliar o codec antes
  de aceitar uma variante confirmada.
- Um cliente piloto pode não ter renúncias. Nesse caso, resposta vazia é um
  resultado válido e não impede validar o fluxo técnico em TRIAL.
- A emissão pode requerer identificador de renúncia existente; a UI deve
  informar isso claramente, sem fabricar valores.
- Documentos têm caráter sensível: persistir apenas o necessário aumenta a
  segurança, ao custo de exigir acesso controlado ao cofre para download.

## Mapa de dependências

| Bloco | Depende de | Relação | Marco necessário |
|---|---|---|---|
| Código PNR de leitura | `eliminar-fake-simulado-runtime-serpro` | bloqueante | tasks 1.1 a 4.2 aplicadas: contrato real-only e rejeição de sintéticos |
| Tela de cliente | `explorador-consultas-manuais-ui` | coordenada | shell e contratos de consulta manual arquivados |
| Validação externa | contrato, Termo, procuração/poder e dado piloto | posterior | execução manual em TRIAL; canário de produção fica fora desta change |
