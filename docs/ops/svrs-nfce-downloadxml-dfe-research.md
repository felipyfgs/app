# Pesquisa — recuperação de XML de NFC-e pela SVRS

**Change relacionada:** `build-ma-outbound-nfe-nfce-capture`  
**Data da evidência:** 2026-07-15  
**Escopo:** NFC-e modelo 65 de saída, emitida no MA e autorizada pela SVRS  
**Estado:** `PROVADO_TECNICAMENTE_POR_CHAVE`; automação de produção ainda não aprovada

## 1. Resultado

Foi recuperado com sucesso o `nfeProc` original de uma NFC-e de saída do MA usando
somente:

- chave de acesso de 44 posições já conhecida;
- certificado A1 do próprio emitente;
- serviço gratuito do portal oficial da SVRS.

O teste foi feito com mTLS e todo o material sensível permaneceu em memória. O XML
não foi persistido nem importado no produto durante o diagnóstico.

Isso corrige a conclusão anterior de que não existia fonte gratuita para o XML de
NFC-e de saída. A formulação correta é:

| Afirmação | Resultado |
|-----------|-----------|
| DistDFe/consulta de protocolo devolvem a NFC-e completa ao emitente | **Não** |
| Portal oficial SVRS devolve a NFC-e completa por chave + A1 relacionado | **Sim** |
| Existe webservice/API M2M da SVRS documentado para esse download | **Não encontrado** |
| O fluxo HTTP do portal é tecnicamente automatizável | **Sim, demonstrado** |
| O projeto já está autorizado a automatizá-lo em produção | **Não** |

## 2. Fluxo observado

```text
A1 do emitente em memória
  -> GET https://dfe-portal.svrs.rs.gov.br/NFCESSL/DownloadXMLDFe
  -> autenticação mTLS aceita
  -> POST /NfceSSL/DownloadXmlDfe
       sistema=Nfce
       OrigemSite=0
       Ambiente=1
       ChaveAcessoDfe=<CHAVE_DE_44_POSICOES>
  -> resposta HTML com função JavaScript downloadXml()
  -> JavaScript cria Blob contendo o nfeProc
  -> extração determinística do texto escapado
  -> validação fiscal e criptográfica
```

O retorno não veio com `Content-Type: application/xml`. Veio como HTML e continha
o XML escapado em JavaScript; o navegador normalmente materializa o arquivo pelo
`Blob` ao acionar o botão de download. A resposta variou quando os cabeçalhos do
fluxo normal do formulário não foram enviados, portanto não se deve modelar isso
como um endpoint REST estável.

## 3. Evidência sanitizada do smoke

| Check | Resultado |
|-------|-----------|
| GET autenticado por mTLS | HTTP 200 |
| Página autenticada | `Download do XML do NFCe` |
| POST do formulário | HTTP 200; HTML de 32.604 bytes |
| XML extraído | 6.958 bytes |
| Elemento raiz | `nfeProc` |
| Modelo | `65` |
| Ambiente | produção |
| Chave | coincidiu com a chave consultada |
| Protocolo | `protNFe` presente; `cStat=100` |
| Digest XMLDSig | válido |
| Assinatura XMLDSig | válida com o certificado X.509 embutido |
| SHA-256 dos bytes extraídos | `61a29e761232154cd323aa467a30b6faa5abb629764ffd552cf62b06426c24c3` |
| Persistência durante o diagnóstico | nenhuma |

Não são registrados neste documento CNPJ, chave completa, número de protocolo,
senha, PFX, PEM ou conteúdo fiscal.

## 4. O que está provado — e o que não está

### Provado

1. Para uma NFC-e do MA autorizada pela SVRS, a combinação **chave conhecida + A1
   relacionado à nota** recupera gratuitamente um `nfeProc` íntegro.
2. A recuperação não exige CSC.
3. Não é necessário reconstruir XML a partir da consulta de protocolo.
4. O fluxo pode ser executado diretamente por HTTP+mTLS, sem Chrome, CAPTCHA ou
   gravação do A1 em disco no comportamento observado em 2026-07-15.
5. A descoberta atual por `nNF` (562/613) e este download são tecnicamente
   complementares: `nNF -> chave -> nfeProc`.

### Não provado

1. Que a SVRS autoriza uso desassistido, concorrente ou em volume desse formulário.
2. Que os campos, HTML e JavaScript possuem contrato de compatibilidade.
3. Limites de taxa, política de bloqueio, janela histórica ou SLA.
4. Se o download inclui eventos posteriores, como cancelamento. O documento testado
   contém protocolo de autorização `100`; eventos fiscais são documentos separados.
5. Igualdade byte a byte com a cópia originalmente gravada pelo emissor. Foram
   comprovadas estrutura, digest e assinatura, não uma comparação com o arquivo do PDV.
6. Abrangência fora das UFs atendidas pela SVRS.

## 5. Evidência oficial e comunitária

### Fonte oficial

- O [Portal da NFC-e da SVRS](https://dfe-portal.svrs.rs.gov.br/NFCe) oferece a
  opção pública **Download XML**.
- Em notícia de 07/08/2019, a SVRS informou que o botão baixa o XML para usuário
  que apresente certificado digital relacionado à NFC-e:
  [Botão Download XML da NF-e](https://dfe-portal.svrs.rs.gov.br/Nfce/Noticias/2980).

A publicação oficial descreve uma funcionalidade para **usuário** na consulta
completa. Ela não publica WSDL, OpenAPI, manual de integração, limites ou permissão
expressa para automação.

### Fóruns ACBr

- Discussão recente: [Recupera o XML da NFC-e](https://www.projetoacbr.com.br/forum/topic/88900-recupera-o-xml-da-nfc-e/).
  A solução aponta o mesmo portal, exige certificado do emitente e ressalta que ele
  serve ao RS e às UFs que usam a SVRS.
- Relato de 2020: [Re-gerar XML da NFC-e](https://www.projetoacbr.com.br/forum/topic/58203-re-gerar-xml-da-nfc-e/).
  Há teste comunitário com NFC-e de outra UF autorizada pela SVRS.
- Debate de 2025/2026: [Download de XML da NFCe](https://www.projetoacbr.com.br/forum/topic/88163-download-de-xml-da-nfce/).
  O tópico diferencia o portal da SVRS de um webservice e relata preocupação com
  fornecedores que anunciam recuperação automática sem canal público documentado.

Os fóruns confirmam uso real e abrangência prática, mas não substituem autorização
formal da SVRS.

### GitHub e discussões técnicas

- A issue [Samuel-Oliveira/Java_NFe#257](https://github.com/Samuel-Oliveira/Java_NFe/issues/257)
  registra a distinção entre inexistência de serviço na biblioteca e download manual
  pelo mesmo portal. Um mantenedor interpreta a interface humana como não autorizada
  para sistemas; isso é opinião comunitária relevante, não regra oficial publicada.
- Em [aerokube/images#338](https://github.com/aerokube/images/issues/338#issuecomment-1402475776),
  um usuário tentou levar certificado PFX para WebDriver justamente para acessar
  páginas `downloadxmldfe`; a resposta técnica distingue certificado cliente mTLS de
  certificado de CA.
- Uma discussão de suporte da JetBrains publica um script Selenium que abre o
  [mesmo endpoint e processa uma fila de chaves](https://intellij-support.jetbrains.com/hc/en-us/community/posts/30949455324690-Monitoring-for-handshake-TLS-in-chrome-selenium-headless-mode).
  Ela demonstra demanda e automação comunitária, mas também a fragilidade de usar
  navegador e a dificuldade de selecionar certificado cliente em modo headless.

Busca por `DownloadXMLDFe`, `NfceSSL/DownloadXmlDfe` e `ChaveAcessoDfe` no índice
público de issues do GitHub encontrou essas discussões, mas **nenhuma biblioteca
open source madura** que implemente o fluxo SVRS de NFC-e por HTTP+mTLS com validação
criptográfica. Repositórios que chamam APIs comerciais não atendem ao requisito.

### Comparações e falsos caminhos

| Caminho | Avaliação |
|---------|-----------|
| DistDFe nacional | Não distribui NFC-e 65 e não devolve NF-e própria ao emitente; a limitação do emitente também aparece em [nfephp-org/sped-nfe#511](https://github.com/nfephp-org/sped-nfe/issues/511). |
| Consulta de protocolo | Recupera situação/protocolo e ajuda a descobrir a chave; não devolve o `nfeProc` original. |
| `arquivoXMLNFe` | Serviço restrito a administrações tributárias/órgãos autorizados; não é API para o A1 comum do emitente. |
| SAE NFC-e de São Paulo | É um contraexemplo útil: a SEFAZ-SP publica [webservices formais de listagem de chaves e download](https://portal.fazenda.sp.gov.br/servicos/nfce/Paginas/sae-nfce.aspx), com XSD e limites. O serviço é estadual e não resolve MA. |
| APIs comerciais | Fora do requisito de fonte gratuita/pública e desnecessárias para o smoke por chave. |
| Selenium/Chrome | Funciona como automação de interface, mas acrescenta seleção de certificado, download em disco e fragilidade. Não é necessário tecnicamente. |

### Relação com a HubStrom

O achado oferece uma explicação **plausível** para como um produto privado pode
recuperar NFC-e de UFs atendidas pela SVRS após descobrir a chave. Entretanto, os
bundles públicos da HubStrom não revelam seu backend e não provam que ela chama
esse formulário. Portanto, “a HubStrom usa `DownloadXMLDFe`” permanece hipótese,
não fato documentado.

## 6. Consequência arquitetural

O achado não deve virar Selenium/RPA. Se houver autorização de produto e jurídica,
o desenho tecnicamente mais seguro é um adapter HTTP pequeno e isolado:

```text
KEY_DISCOVERED
  -> SvrsNfcePortalRetrievalClient (mTLS, A1 somente em memória)
  -> decoder estrito do wrapper HTML/JavaScript
  -> valida nfeProc + chave + CNPJ raiz + modelo + ambiente
  -> valida digest e assinatura
  -> persiste bytes originais via SecureObjectStore
  -> registra aquisição, hash, auditoria e métricas
```

Requisitos mínimos antes de implementar:

- decisão OpenSpec explícita alterando o atual veto a automação de portal;
- confirmação formal da SVRS/SEFAZ-MA sobre uso automatizado e limites;
- flag própria, allowlist, baixa concorrência, backoff e circuit breaker;
- parser sem execução de JavaScript e sem `stripcslashes` genérico;
- fixtures sanitizadas do wrapper, sem XML fiscal real;
- nenhuma exposição de PFX, senha, chave privada ou PEM;
- fallback `ASSISTED` preservado se o HTML mudar ou houver bloqueio.

## 7. Próximos experimentos seguros

1. Solicitar à SVRS/SEFAZ-MA resposta escrita sobre automação, volume, retenção e
   vínculo aceito entre certificado e NFC-e.
2. Comparar, com consentimento, o hash do download com o arquivo original do PDV.
3. Testar uma NFC-e cancelada para determinar se o portal entrega somente `nfeProc`
   ou também oferece eventos separados.
4. Repetir em homologação, quando existir certificado e documento válidos para isso.
5. Medir comportamento com poucas chaves e intervalos conservadores somente após
   aprovação; não procurar limites por estresse em produção.
6. Investigar separadamente uma fonte gratuita equivalente para NF-e modelo 55 do
   MA/SVAN. Este achado resolve **NFC-e 65**, não prova o mesmo caminho para NF-e 55.

## 8. Decisão atual

O resultado técnico é **GO para pesquisa e proposta**, mas permanece **NO-GO para
automação desassistida em produção**. O gate G4 não deve ser marcado como concluído
até existir decisão formal de contrato/uso e uma change OpenSpec aprovada.
