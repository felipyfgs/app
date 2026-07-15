# Fontes oficiais — `autXML` do escritório e NFeDistribuicaoDFe

Pesquisa consolidada em 14 de julho de 2026 para fundamentar a change `add-office-autxml-and-bulk-xml-import`. Esta nota separa regras publicadas pelo Fisco de decisões e inferências do produto.

## Conclusões confirmadas em fontes oficiais

### 1. Quem informa `autXML` e quando

- O grupo `autXML` integra o XML assinado da NF-e/NFC-e e admite de zero a dez pessoas autorizadas, identificadas por CNPJ ou CPF.
- A documentação que introduziu o grupo afirma expressamente que a empresa emitente pode indicar o contador e outros participantes autorizados.
- Portanto, é o emissor — normalmente por configuração de seu ERP — que inclui o CNPJ completo do escritório antes de transmitir a nota para autorização.
- Uma NF-e autorizada não pode ter seu conteúdo alterado: qualquer modificação invalida a assinatura digital. Assim, não existe inclusão retroativa comum do contador em `autXML`.
- O evento “Ator Interessado na NF-e” é uma exceção específica para transportadores e não deve ser tratado como meio de cadastrar retroativamente o escritório contábil.

### 2. A tag existe nos modelos 55 e 65, mas o canal nacional distribui somente modelo 55

- As regras GA02/GA03 do leiaute identificam `autXML` como aplicável aos modelos 55 e 65.
- A NT 2014.002 v1.40, que rege `NFeDistribuicaoDFe`, valida `consChNFe` com a RV 618: chave de acesso com modelo diferente de 55 é rejeitada.
- Logo, a presença de `autXML` em uma NFC-e 65 não cria um canal nacional de distribuição por esse web service.
- O escopo automático seguro desta change é NF-e 55. NFC-e 65 depende de importação XML/ZIP ou de outro canal estadual expressamente especificado.

### 3. Papel do escritório como terceiro autorizado

- A NT 2014.002 distribui documentos aos papéis de emitente, destinatário, transportador e terceiro informado em `autXML`.
- Para transportadores e terceiros, a NF-e fica disponível integralmente; a exigência de manifestação para trocar `resNFe` por XML integral é regra do destinatário, não do terceiro `autXML`.
- A tabela de distribuição também inclui, conforme o papel, eventos como cancelamento, Carta de Correção, averbação e outros eventos propagados.
- O retorno usa `docZip` individual, Base64 e GZip, com atributo `schema`; um lote pode conter até 50 documentos. Exemplos oficiais de schemas incluem `procNFe`, `resNFe`, `procEventoNFe` e `resEvento`.
- Para guarda fiscal, o próprio Portal define a NF-e como XML assinado agregado à respectiva autorização de uso. Por isso, `procNFe` é o artefato canônico; `NFe` isolada ou `resNFe` não comprovam sozinhos o documento autorizado completo.

### 4. Certificado e CNPJ consultado

- A requisição informa o CNPJ completo do interessado.
- A RV 593 exige que a raiz do CNPJ consultado coincida com a raiz do CNPJ existente no certificado digital de transmissão.
- A validação do certificado também exige cadeia ICP-Brasil, identificação CNPJ/CPF e aptidão para autenticação de cliente.
- A NT não estabelece que somente A1 pode consumir o serviço. Restringir o produto a A1 é uma decisão operacional para permitir automação desassistida e proteção no cofre.
- O produto deve manter um CNPJ completo canônico para `autXML` e para a requisição, mesmo que um certificado de outro estabelecimento da mesma raiz seja tecnicamente aceito pela RV 593.

### 5. Não existe NSU retroativo para novo usuário

O item 3.4 da NT 2014.002 v1.40 mantém as regras introduzidas na versão 1.10:

- somente usuários que utilizaram `distNSU` nos últimos 60 dias continuam gerando NSU normalmente;
- para novo usuário, a geração começa no primeiro acesso e não há geração retroativa;
- após mais de 60 dias sem `distNSU`, a geração é interrompida e retoma na próxima consulta, também sem recompor o período de inatividade;
- nos dois casos, o primeiro acesso retorna `cStat=137`; consultas posteriores somente devem ocorrer depois de aguardar uma hora;
- a continuidade de uso é verificada por CPF ou CNPJ-base.

A menção a documentos dos últimos três meses e o prazo de 90 dias representam a janela máxima de disponibilidade de documentos ou NSUs já existentes. Ela não contradiz nem elimina a regra que impede criação retroativa de NSU.

Consequência: `ultNSU=0` não deve ser apresentado como “backfill dos últimos 90 dias” para um escritório novo. O histórico e os hiatos superiores a 60 dias precisam de importação XML/ZIP.

### 6. Cursor e consumo indevido

- `distNSU` deve sempre continuar a partir do `ultNSU` devolvido e em ordem ascendente.
- Se `ultNSU=maxNSU` ou a resposta for `cStat=137`, não há documento novo e o interessado deve aguardar uma hora.
- Todas as aplicações que consomem o mesmo ator precisam compartilhar a sequência; consultas fora de ordem podem gerar `cStat=656`.
- Como a atividade é controlada por CNPJ-base, duas filiais da mesma raiz não devem ser modeladas como streams independentes. A chave de ownership segura é `office_id + cnpj_base + ambiente + canal`, mantendo um `query_cnpj` completo canônico.
- `consNSU` e `consChNFe` são reparos pontuais. O limite publicado é 20 consultas por hora; excedê-lo bloqueia o CNPJ por uma hora.
- Depois de qualquer `cStat=656`, é necessário aguardar uma hora completa. Nova tentativa antecipada reinicia a contagem.

### 7. Consulta pontual e retenção

- Desde a versão 1.15, `consChNFe` pode devolver uma NF-e conhecida sem exigir que um NSU tenha sido previamente gerado para ela.
- Isso não cria descoberta nem backfill: é necessário possuir a chave de 44 caracteres, ter permissão e consultar dentro do prazo oficial de até 90 dias.
- A RV 632 informa que a NF-e está fora do prazo de download.
- `consChNFe` deve ser reservado para reparo e diagnóstico, nunca para varredura por numeração.

### 8. Escritório também destinatário

- Antes de gerar NSU para transportador ou terceiro `autXML`, o Ambiente Nacional verifica se esse CNPJ também é destinatário da mesma NF-e.
- Se for, o NSU do XML integral não é gerado até a manifestação do destinatário.
- Esta change não autoriza o sistema a manifestar em nome do escritório; o fallback permanece sendo o arquivo XML/ZIP.

### 9. Códigos oficiais relevantes

| Código | Significado no serviço | Consequência de produto |
|---|---|---|
| `137` | Nenhum documento localizado | Estado normal de ativação ou stream alcançado; aguardar uma hora. |
| `138` | Documento localizado | Processar o lote e persistir tudo antes de avançar `ultNSU`. |
| `593` | CNPJ-base consultado difere do CNPJ-base do certificado | Bloquear configuração ou canal; não trocar silenciosamente a identidade. |
| `618` | Chave com modelo diferente de 55 | NFC-e 65 está fora de `NFeDistribuicaoDFe`. |
| `632` | Solicitação fora do prazo de download | Encerrar reparo oficial e usar importação por arquivo. |
| `640` | Interessado sem permissão para consultar a NF-e | Ausência de papel ou `autXML`; não tentar contornar. |
| `641` | NF-e indisponível para o emitente | O A1 do cliente emitente não recupera sua própria saída por esse serviço. |
| `656` | Consumo indevido | Suspender todas as consultas pelo menos uma hora desde a tentativa mais recente. |

As RV 618, 640 e 641 são especialmente relevantes a `consChNFe`. No fluxo `distNSU`, documento sem interesse não deve ser presumido como listável apenas para produzir esses códigos.

## Decisões e inferências desta change

Os itens abaixo não são imposições literais do Fisco; são decisões de arquitetura derivadas das regras oficiais e das restrições de segurança do projeto:

- aceitar somente A1/PFX/P12 para automação, apesar de o serviço não ser limitado oficialmente a A1;
- guardar o A1 e sua senha no `SecureObjectStore`, usar PFX somente em memória e não disponibilizar recuperação de segredo;
- ativar `distNSU` e observar a primeira espera de uma hora antes de concluir o onboarding com os clientes;
- manter um consumidor e lock por `office_id + cnpj_base + ambiente + canal`, com um CNPJ completo canônico para a requisição;
- tratar outro software que consome a mesma raiz como conflito de ownership, em vez de avançar o cursor local cegamente;
- persistir uma página inteira antes do cursor e bloquear após cinco falhas consecutivas de Base64/GZip, sem saltar NSU;
- rotear pela identificação completa do emitente somente dentro do `office_id` dono do stream e colocar documento sem vínculo inequívoco em quarentena criptografada;
- usar importação em massa de XML/ZIP como garantia de histórico, cobertura de NFC-e 65 e recuperação de lacunas de ativação ou de inatividade;
- não contar `NFe` sem protocolo ou `resNFe` como XML autorizado integral.

## Referências oficiais

1. [NT 2014.002 v1.40 — Web Service de Distribuição de DF-e de Interesse dos Atores da NF-e](https://www.nfe.fazenda.gov.br/portal/exibirArquivo.aspx?conteudo=uWO2d%2FgTuWg%3D) — fonte vigente para atores, `docZip`, NSU, ausência de retroatividade, janela de 90 dias, certificados, respostas e consumo indevido.
2. [Portal da NF-e — documentos técnicos vigentes](https://www.nfe.fazenda.gov.br/portal/listaConteudo.aspx?tipoConteudo=6WfrpZYE4Ik%3D) — confirma publicação da NT 2014.002 v1.40 em 03/07/2026.
3. [MOC 7.0, Anexo I — leiaute e regras da NF-e/NFC-e](https://www.confaz.fazenda.gov.br/legislacao/arquivo-manuais/moc7-anexo-i-leiaute-e-rv.pdf) — grupo GA `autXML`, ocorrência de zero a dez e regras GA02/GA03 para modelos 55/65.
4. [NT 2013.005 — criação da autorização de acesso ao XML](https://www.nfe.fazenda.gov.br/portal/exibirArquivo.aspx?conteudo=OeljCPBB1ds%3D) — registra que o emitente pode indicar contador e outras pessoas autorizadas.
5. [FAQ oficial — modelo operacional, alteração e correção da NF-e](https://www.nfe.fazenda.gov.br/portal/perguntasFrequentes.aspx?AspxAutoDetectCookieSupport=1&tipoConteudo=auR4yGlWmRY%3D) — confirma que NF-e autorizada não pode sofrer alteração porque isso invalida a assinatura.
6. [FAQ oficial — obrigações acessórias e guarda da NF-e](https://www.nfe.fazenda.gov.br/portal/perguntasFrequentes.aspx?AspxAutoDetectCookieSupport=1&tipoConteudo=FpTE5yO9A74%3D) — define o documento fiscal guardável como XML assinado agregado à autorização de uso.
7. [NT 2020.007 v1.40 — Evento Ator Interessado](https://www.nfe.fazenda.gov.br/portal/informe.aspx?Informe=LeNQXfyYngg%3D&ehCTG=false) — confirma que o mecanismo posterior à emissão é específico para transportador.

