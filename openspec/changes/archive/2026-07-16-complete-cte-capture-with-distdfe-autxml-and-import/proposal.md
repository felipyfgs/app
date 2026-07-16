## Por quê

O catálogo já possui uma captura inicial de CT-e, mas ainda não formaliza toda a matriz oficial de participantes, trata incorretamente o emitente como possível origem de saída pelo próprio `CTeDistribuicaoDFe` e não cobre a aquisição do CT-e emitido por `autXML`, XML/ZIP ou integração com o emissor. Precisamos fechar essa lacuna com canais públicos e autorizados, sem prometer recuperação nacional inexistente nem automatizar portais humanos.

## O que muda

- Capturar automaticamente CT-e modelo 57 e eventos distribuíveis quando o cliente for remetente, destinatário, expedidor, recebedor ou tomador, usando `CTeDistribuicaoDFe` com o A1 do cliente e cursor NSU próprio.
- Tratar o CNPJ emitente como exclusão para o XML principal no DistDFe do próprio cliente: um CT-e emitido pelo cliente não pode ser marcado como capturado por esse canal.
- Criar o canal `CTE_AUTXML_DISTDFE` com o A1 e CNPJ do escritório para receber CT-e emitido por clientes que incluíram previamente o escritório em `autXML`, mantendo um stream central por CNPJ-base, ambiente e canal.
- Rotear CT-e recebido pelo escritório ao cliente pelo CNPJ completo de `emit`, exigir presença exata do escritório em `autXML` e identificar o documento como derivado `AUTXML_REDACTED` quando as chaves de DF-e relacionadas vierem substituídas por `999...`.
- Estender o import assíncrono já planejado para aceitar `cteProc`/`procCTe` e eventos CT-e protocolados em XML ou ZIP, inclusive lote multiempresa, como contingência para emitidos sem `autXML` e períodos fora da distribuição.
- Disponibilizar um contrato de ingestão autenticada para o ERP/emissor entregar o mesmo XML autorizado, reutilizando validação, idempotência, quarentena e proveniência do import, sem integração comercial obrigatória.
- Projetar papéis CT-e explícitos (`ISSUER`, `SENDER`, `RECIPIENT`, `EXPEDITOR`, `RECEIVER`, `TAKER`, `AUTXML`) por estabelecimento; somente `ISSUER` gera `OUT`, enquanto os demais papéis representam documento de terceiro relacionado ao cliente e geram `IN`.
- Implementar consumo conservador: página persistida antes do cursor, `ultNSU` da resposta como autoridade, consulta pontual apenas por `consNSU` conhecido, espera mínima após fila vazia, circuito para `656`, reconciliação de lacunas e smoke restrito de produção.
- Exibir cobertura e proveniência por cliente, stream e período, distinguindo `CAPTURED_ORIGINAL`, `CAPTURED_AUTXML_REDACTED`, `PENDING_IMPORT`, lacuna histórica e bloqueio operacional.

## Capacidades

### Novas capacidades

- `cte-autxml-office-distribution`: captura central de CT-e pelo escritório como terceiro em `autXML`, com A1 próprio, NSU único, roteamento por emitente, tratamento da cópia redigida e quarentena.

### Capacidades modificadas

- `cte-document-sync`: explicita os cinco papéis elegíveis, a exclusão do XML emitido pelo próprio CNPJ, o consumo completo por NSU, eventos, reconciliação e proteção contra consumo indevido.
- `cte-mdfe-full-capture`: corrige a direção fiscal e remove a hipótese de obter o CT-e principal do próprio emitente pelo DistDFe; MDF-e continua fora do catálogo escritural.
- `outbound-xml-ingestion`: passa a aceitar CT-e autorizado e eventos CT-e em XML/ZIP e por entrega autenticada do emissor, com a mesma custódia e validação dos demais DF-e.
- `fiscal-document-direction`: passa a conservar todos os papéis CT-e por estabelecimento e a classificar `OUT` exclusivamente quando o cliente é o emitente.
- `fiscal-document-catalog`: passa a expor papéis, proveniência e qualidade do artefato CT-e, inclusive a distinção entre original e cópia `autXML` com referências redigidas.
- `frontend-dashboard-experience`: passa a oferecer onboarding `autXML` para CT-e, saúde dos streams, cobertura, import em massa e resolução de pendências/quarentena.
- `operations-dashboard`: passa a monitorar cursores CT-e do cliente e do escritório, fila, lacunas, `656`, falhas de decode, artefatos redigidos e pendências de emitidos.

## Impacto

- **Backend:** evolução do cliente e dos jobs `CTeDistribuicaoDFe`, cursores de cliente e escritório, parser/projeção de papéis CT-e, aquisições, validação de `cteProc`, import batch, endpoint de entrega do emissor, quarentena, reconciliação e métricas.
- **Frontend:** checklist `autXML` e pendências CT-e no catálogo de Documentos (`/docs/catalog`), visão de cobertura por origem/papel, saúde de sincronização em Sincronizações, upload XML/ZIP e tratamento de pendências — sem superfície CT-e em Configurações.
- **Dados:** interesses fiscais múltiplos por documento, proveniência CT-e, qualidade `ORIGINAL`/`AUTXML_REDACTED`, identidade por chave+modelo+ambiente e histórico de cursor sem sobrescrever XML canônico.
- **Integrações:** Ambiente Nacional do CT-e por SOAP/mTLS; nenhum serviço comercial é requisito. O canal do escritório reutiliza a identidade/A1 e o batch seguro definidos por `add-office-autxml-and-bulk-xml-import`, que deve ser aplicado antes das tarefas dependentes.
- **Compatibilidade:** corrige o contrato atual de direção CT-e; documentos previamente classificados como `OUT` apenas por inferência de parser deverão ser reprocessados.

## Não-objetivos

- Automatizar Portal Nacional, Portal SVRS, Chrome, hCaptcha, gov.br ou qualquer tela declarada para uso humano.
- Inventar consulta por chave: o `CTeDistribuicaoDFe` suporta `distNSU` e `consNSU`, não `consChCTe`.
- Prometer recuperação automática de CT-e emitido sem `autXML`, sem XML do emissor e fora da janela disponível no Ambiente Nacional.
- Inserir ou alterar `autXML` depois da autorização, editar XML fiscal, emitir, autorizar, cancelar, inutilizar ou registrar evento CT-e.
- Tratar uma cópia com referências substituídas por `999...` como byte a byte idêntica ao XML originalmente assinado pelo emitente.
- Incluir MDF-e no catálogo escritural ou prometer projeção completa de CT-e OS/GTV-e nesta change; payloads do mesmo canal serão preservados de forma tolerante até change específica.
