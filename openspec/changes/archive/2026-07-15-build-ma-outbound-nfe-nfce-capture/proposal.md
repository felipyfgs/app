## Por quê

O escritório precisa capturar automaticamente as **NF-e modelo 55 e NFC-e modelo 65 de saída** dos clientes do Maranhão, inclusive lacunas de numeração, sem depender da entrega manual de XML pelo cliente. O Maranhão é o primeiro recorte porque a SEFAZ-MA já oferece uma plataforma oficial que prepara downloads de NF-e/NFC-e por competência e tipo de operação, enquanto o comportamento público do XMLHub/HubStrom demonstra a viabilidade de reconciliar séries a partir de XML-semente, A1 e, para NFC-e, CSC/ID CSC.

## O que muda

- Criar um motor de captura por sequência para NF-e 55 e NFC-e 65, configurado por estabelecimento, ambiente, modelo e série a partir de um XML autorizado recente usado como semente.
- Acompanhar `nNF` independentemente de NSU, registrar chaves descobertas, cancelamentos, inutilizações, lacunas e até dez tentativas espaçadas em doze horas, sem salto silencioso.
- Integrar a recuperação dos XML de saída no Maranhão por uma interface própria, usando como fonte prioritária a plataforma oficial da SEFAZ-MA e aceitando como concluído somente o XML fiscal original autorizado/protocolado.
- Consultar primeiro o protocolo pela chave candidata e validar em PoC o uso do `cStat=562`, que pode revelar a chave verdadeira para os modelos 55 e 65 sem transmitir uma nota; testar rejeição 539 somente como fallback mutante, pois sua aplicação é facultativa por autorizador.
- Guardar A1 no cofre já existente e incluir CSC/ID CSC tipados somente para o fallback ativo da NFC-e 65, por estabelecimento e ambiente, sem rota de recuperação de segredo; consulta 562 e download oficial não dependerão de CSC.
- Executar filas, locks, rate limit, retries, trilha de auditoria, métricas, alertas e kill switch próprios para o canal de saída MA.
- Persistir todo XML obtido no vault imutável e projetar NF-e/NFC-e como `direction=OUT`, incluindo qualquer documento técnico eventualmente autorizado e seu evento de cancelamento.
- Disponibilizar no painel a configuração por série, consentimento, estado da captura, posição atual, lacunas, tentativas e bloqueios; reset e ativação exigirão ADMIN com 2FA recente e motivo auditável.
- Manter o import manual de XML/ZIP como canal independente e sem transmissão à SEFAZ.
- **Mudança explícita de escopo:** esta change supera, somente para NF-e/NFC-e de saída no piloto MA, a decisão anterior de “captura sem emissão”. Qualquer transmissão ativa, autorização ou cancelamento técnico permanecerá desligado por padrão e dependerá dos gates fiscal, jurídico e operacional definidos no design.

## Capacidades

### Novas capacidades

- `outbound-sequence-capture`: motor de reconciliação por estabelecimento, ambiente, modelo e série, com XML-semente, posição `nNF`, consulta não mutante 562, fallback 539, lacunas duráveis e saga segura para eventual autorização/cancelamento técnico.
- `ma-outbound-xml-retrieval`: adaptador inicial da SEFAZ-MA para solicitar, acompanhar e obter os XML originais de NF-e/NFC-e de saída, sem tratar HTML, DANFE ou XML remontado como documento de guarda.

### Capacidades modificadas

- `client-credential-management`: permitir uso governado do A1 no canal de saída MA e armazenar CSC/ID CSC de NFC-e no cofre por estabelecimento e ambiente.
- `multi-dfe-catalog-projection`: habilitar projeção real de NFC-e quando o estabelecimento MA estiver elegível e o canal estiver ativado.
- `fiscal-document-direction`: classificar documentos recuperados do emitente MA como papel `ISSUER` e direção `OUT` nos modelos 55 e 65.
- `fiscal-document-catalog`: incluir proveniência do canal MA, estados de recuperação e identificação explícita de documentos técnicos autorizados/cancelados.
- `frontend-dashboard-experience`: adicionar configuração, consentimento, monitoramento por série, lacunas e ações protegidas do canal de saída MA.
- `operations-dashboard`: incluir saúde, histórico, bloqueios, falhas de recuperação/cancelamento e kill switch do motor de sequência.

## Impacto

- **Backend:** novas tabelas de perfis, cursores `nNF`, estados por número e solicitações de recuperação; interfaces de autorização/descoberta e download MA; jobs Horizon e scheduler dedicados; extensão do parser/projetor já usado por `nfe_documents`.
- **Frontend:** nova seção no detalhe do cliente/estabelecimento e ampliação das telas de sincronização, operações e catálogo, seguindo o template Nuxt UI fixado no repositório.
- **Segurança:** A1 permanece único por raiz e somente em memória; CSC/ID CSC ficam criptografados; nenhum PFX, senha, CSC, chave privada, PEM ou XML de sonda entra em log/API comum.
- **Operação:** feature flags desligadas por padrão, allowlist de CNPJ, mandato do cliente, smoke restrito fora do CI, coordenação exclusiva com ERP/PDV da série e bloqueio imediato se houver autorização sem cancelamento confirmado.
- **Sistemas externos:** plataforma SEFAZ-MA para download por competência/operação, SVAN para NF-e 55 e SVRS para NFC-e 65; a automação de download só será liberada após comprovar contrato máquina-a-máquina permitido e estável.
- **Referências oficiais:** [plataforma de download da SEFAZ-MA](https://www.ma.gov.br/noticias/sefaz-ma-disponibiliza-novo-sistema-para-download-de-notas-fiscais-eletronicas), [serviços NFC-e da SVRS](https://dfe-portal.svrs.rs.gov.br/Nfce/Servicos), [MOC 7.0 — consulta/cStat 562](https://www.confaz.fazenda.gov.br/legislacao/arquivo-manuais/moc7-visao-geral.pdf) e [MOC 7.0 — regra 539](https://www.confaz.fazenda.gov.br/legislacao/arquivo-manuais/moc7-anexo-i-leiaute-e-rv.pdf).

## Não-objetivos

- Habilitar outros estados nesta change; o motor poderá ser extensível, mas o único adaptador entregue será MA.
- Automatizar portal por scraping/RPA, contornar Gov.br/SEFAZNET, CAPTCHA, MFA ou restrições de sessão.
- Emitir notas comerciais normais, substituir o ERP/PDV ou oferecer emissor fiscal pelo painel.
- Autorizar/cancelar documentos técnicos em produção sem parecer fiscal/jurídico, mandato do cliente, PoC do autorizador, allowlist e kill switch testado.
- Prometer captura automática caso a SEFAZ-MA não ofereça ou autorize contrato máquina-a-máquina que devolva o XML original.
- Usar DistDFe como fonte da própria NF-e/NFC-e emitida, aceitar DANFE/HTML como XML de guarda ou ocultar lacunas após esgotar tentativas.
- Expor segredo fiscal, material criptográfico ou criar rota de recuperação de certificado/CSC.
