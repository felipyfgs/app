## Por que

A descoberta automática de chaves de NF-e 55 de saída já pode deixar documentos em `XML_PENDING`, mas o escritório ainda não possui uma fonte gratuita e automática para recuperar o `nfeProc` original do emitente. O formulário autenticado `NFESSL/DownloadXMLDFe` da SVRS é tecnicamente acessível por mTLS, porém o smoke de 2026-07-15 revelou bloqueio por múltiplas consultas sem que a SVRS publique limiar, escopo ou cooldown; automatizá-lo sem governança compartilhada colocaria no mesmo risco as capturas NF-e e NFC-e.

## O que muda

- Adicionar recuperação automática e idempotente de NF-e modelo 55 de saída cuja chave já seja conhecida, usando A1 relacionado e somente o endpoint oficial allowlisted da SVRS.
- Introduzir um governador único de egress para todo o host da SVRS, compartilhado entre NF-e 55 e NFC-e 65, contando cada GET/POST, serializando chamadas e impedindo que filas ou escritórios concorram pelo mesmo IP público.
- Substituir os limites iniciais ainda não pilotados da recuperação NFC-e por defaults defensivos, desligados por padrão e sem alegação de corresponderem a limites oficiais da SVRS.
- Detectar bloqueio também em respostas HTTP 200 por mensagem/template, abrir circuit breaker global imediatamente e aplicar cooldown longo com apenas uma prova canário antes de reabrir tráfego.
- Priorizar fontes que não consomem o portal: documento já ingerido, `autXML`/DistDFe e importação oficial; reservar a SVRS para recuperação pontual de lacunas conhecidas.
- Encaminhar backlog bloqueado ou inelegível para contingência assistida por XML/ZIP ou pacote oficial, sem perder chave, estado ou auditoria.
- Validar chave, modelo, emitente, ambiente, protocolo, digest, assinatura e hash antes de promover o XML ao catálogo canônico.
- Expor estado do orçamento, cooldown, breaker, fila, proveniência e contingência em API/UI restritas por papel e `office_id`.
- Registrar a base oficial e comunitária pesquisada, separando expressamente os limites publicados do `NFeDistribuicaoDFe` da ausência de limites publicados para o formulário `NFESSL`.

## Capacidades

### Novas capacidades

- `svrs-portal-egress-governance`: orçamento, exclusão mútua, circuit breaker, cooldown, canário e kill switch compartilhados por todo acesso NF-e/NFC-e ao host oficial da SVRS.
- `svrs-nfe55-outbound-xml-retrieval`: transporte mTLS, contrato de resposta, validações fiscais/criptográficas e resultados tipados para recuperar NF-e 55 por chave conhecida.
- `outbound-xml-recovery-routing`: seleção determinística entre vault, `autXML`/DistDFe, recuperação pontual SVRS e contingência assistida.

### Capacidades modificadas

- `outbound-xml-ingestion`: aceitar a proveniência SVRS NF-e 55, reconciliar duplicatas/divergências e concluir pendências somente após validação completa.
- `operations-dashboard`: incorporar saúde compartilhada do portal SVRS, orçamento, cooldown, bloqueios e backlog roteado para contingência.
- `frontend-dashboard-experience`: representar estados de recuperação NF-e 55 e impedir ações que burlem cooldown, breaker, papéis ou 2FA recente.

## Impacto

- **Backend:** novos contratos/adapters Laravel, jobs Horizon, limitador Redis compartilhado, circuit breaker persistente, migrações de tentativas/aquisições e integração com o cofre existente.
- **Frontend:** páginas e componentes Nuxt UI existentes para pendências, saúde do canal, contingência e ações operacionais sanitizadas.
- **Operação:** flags master/auto-queue desligadas por padrão, allowlist de piloto, orçamento conservador, runbook de bloqueio e evidência formal antes de qualquer ampliação.
- **Sistemas externos:** `https://dfe-portal.svrs.rs.gov.br/NFESSL/DownloadXMLDFe`, somente pelo contrato observado e allowlisted; nenhuma API comercial.
- **Changes relacionadas:** a governança desta change passa a ser pré-condição do rollout de `add-svrs-nfce-outbound-xml-retrieval` e substitui seus limites isolados de 5 s/30 s/20 chaves antes do primeiro piloto real.

## Não-objetivos

- Descobrir chaves por varredura no portal ou usar o `NFeDistribuicaoDFe` como fonte de XML do próprio emitente.
- Tratar o limite oficial de 20 consultas/hora do `NFeDistribuicaoDFe` como limite do formulário `NFESSL`.
- Automatizar CAPTCHA, Gov.br, SEFAZNET, navegador, Selenium, JavaScript ou navegação genérica de portal.
- Rotacionar IP, proxy, certificado ou escritório para contornar bloqueio; sondar limite por carga; ou forçar half-open manual antes do cooldown.
- Reconstruir XML, substituir o original do emitente, emitir/cancelar documento ou contratar API comercial.
- Prometer disponibilidade, retenção, SLA ou cobertura nacional não documentados pela SVRS.
