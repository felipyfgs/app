## 1. Gates, segurança e evidência oficial

- [x] 1.1 Executar backup e restore drill da instância e registrar sucesso antes de criar o novo schema.
- [x] 1.2 Solicitar/registrar resposta formal da SEFAZ-MA sobre acesso do contador, conteúdo dos pacotes e existência/autorização de contrato máquina-a-máquina; se não existir, registrar `NO_GO_M2M`.
- [x] 1.3 Obter parecer fiscal/jurídico e definir mandato do cliente para consultas, inutilização, transmissão e cancelamento, separando operações somente leitura das mutantes.
- [x] 1.4 Fixar em configuração versionada endpoints e schemas vigentes: SVAN para NF-e 55, SVRS para NFC-e 65, MOC consulta 562/539 e NT de CNPJ/chave alfanuméricos.
- [x] 1.5 Preparar fixtures sanitizadas de `procNFe`, consulta 562, 217, 561, 613, 526, 656, 539, inutilização e cancelamento para os dois modelos, sem certificado real no repositório/CI.
- [x] 1.6 Registrar decisão G0 com flags desligadas, allowlist vazia, kill switch testável e confirmação de que não haverá RPA/automação de Gov.br, SEFAZNET, CAPTCHA ou portal humano.

## 2. Domínio e schema dedicado a nNF

- [x] 2.1 Criar enums tipados para modelo, modo `ASSISTED|AUTOMATIC`, estados de perfil/série/número/recuperação, canal/source MA e finalidade técnica.
- [x] 2.2 Criar migration de `outbound_capture_profiles` com `office_id`, estabelecimento, UF, ambiente, modelo, consentimento, allowlist, estado e referência opcional de CSC no vault.
- [x] 2.3 Criar migration de `outbound_series_cursors` com série, semente, `seed_nnf`, `discovery_position`, maior número confirmado, status, agenda e lock, com unique por estabelecimento+ambiente+modelo+série.
- [x] 2.4 Criar migration de `outbound_number_states` com candidata persistida, chave descoberta, cStat, tentativas, timestamps e máquina de estados única por perfil+série+`nNF`.
- [x] 2.5 Criar `ma_outbound_retrieval_requests` e `outbound_capture_runs` para solicitações assíncronas e histórico com posições `nNF`, sem FK/semântica de cursor NSU.
- [x] 2.6 Criar `document_acquisitions` para proveniências múltiplas e extensão de `nfe_documents` necessária para marcar documento técnico/fonte sem sobrescrever documento canônico.
- [x] 2.7 Implementar models, casts, relações, policies e scopes tenant-aware para todas as novas estruturas.
- [x] 2.8 Adicionar testes de migration, constraints, isolamento por `office_id` e invariantes que impeçam uso de `last_nsu` como posição `nNF`.

## 3. Cofre, credenciais e XML-semente

- [x] 3.1 Implementar serviço tipado para gravar/substituir CSC e ID CSC no `SecureObjectStore` por estabelecimento+ambiente, sem endpoint de leitura do valor.
- [x] 3.2 Implementar endpoints/policies de estado e substituição de CSC restritos a ADMIN com 2FA recente, retornando somente metadados não sensíveis.
- [x] 3.3 Ampliar `CaptureEligibilityService` para saída MA, distinguindo consulta/pacote/M2M/fallback mutante e exigindo CSC somente no fallback modelo 65.
- [x] 3.4 Implementar `OutboundSeedValidator` para assinatura, protocolo, cStat autorizado, `cUF=21`, `tpNF=1`, ambiente, modelo, CNPJ completo, série, `nNF`, `tpEmis` e idade padrão de 60 dias.
- [x] 3.5 Criar fluxo de cadastro/substituição de semente que preserve o XML no vault, não aceite `<NFe>` sem protocolo e não clone itens/tributos para operação fiscal.
- [x] 3.6 Adaptar uso do A1 ativo da raiz para clientes outbound, mantendo PFX/chave somente em memória e impedindo certificado do escritório.
- [x] 3.7 Auditar cadastro de CSC, mandato, semente, ativação, allowlist e reset somente por identificadores/estado, sem segredo ou XML bruto.
- [x] 3.8 Adicionar testes anti-segredo/anti-PEM para API, logs, auditoria, filas e falhas; confirmar que nenhuma dependência runtime materializa PEM ou desliga verificação TLS.

## 4. Recuperação oficial de XML no Maranhão

- [x] 4.1 Criar `MaOutboundXmlRetrievalClient`, DTOs de solicitação/poll/download e implementação `Disabled/Null` usada enquanto não houver contrato M2M aprovado.
- [x] 4.2 Implementar validador de pacote oficial MA que aceite somente NF-e 55/NFC-e 65 `procNFe` original com assinatura, protocolo, chave, emitente, UF e ambiente coerentes.
- [x] 4.3 Implementar ingestão transacional do pacote no `SecureObjectStore`, `dfe_documents`, `nfe_documents`, `nfe_events` e `document_acquisitions`, reutilizando parser/projetor existentes.
- [x] 4.4 Implementar idempotência por SHA-256/chave e quarentena de mesma chave com bytes divergentes, sem substituir silenciosamente a projeção canônica.
- [x] 4.5 Criar endpoint e job de upload/processamento de pacote oficial para OPERATOR/ADMIN, com resultado por arquivo, limites ZIP e ausência de payload fiscal nos logs.
- [x] 4.6 Implementar estado `ASSISTED`, competências cobertas, solicitações expiradas e pendências de chaves sem XML na API operacional.
- [x] 4.7 Se G4 tiver contrato oficial, implementar adapter M2M de solicitar/poll/baixar com A1 em memória e idempotência; se G4 for no-go, registrar decisão e manter flag/adapter desabilitados.
- [x] 4.8 Adicionar testes unitários/feature com pacotes sanitizados dos dois modelos, cancelados, duplicados, inválidos, divergentes e de outro tenant.
- [x] 4.9 Escrever runbook G1 para solicitar pacote por competência/operação `OUT`, importar, conferir assinatura/protocolo e comparar com XML do emissor.

## 5. Reconciliação somente leitura por consulta 562

- [x] 5.1 Implementar builder versionado de chave candidata de 44 posições para modelos 55/65, incluindo CNPJ/chave alfanuméricos, `cNF` determinístico persistido e DV conforme leiaute vigente.
- [x] 5.2 Criar interface `SefazOutboundProtocolQueryClient` e DTOs que não exponham envelope SOAP bruto.
- [x] 5.3 Implementar transporte SOAP/mTLS próprio com PFX BLOB, TLS ≥1.2 e hostname verify, resolvendo SVAN/55 e SVRS/65 por ambiente.
- [x] 5.4 Implementar parser de consulta para 562 com/sem `chNFe`, chave coincidente, 217, 561, 613, 526, 656, cancelamento/eventos e respostas inesperadas.
- [x] 5.5 Implementar validação integral da chave retornada contra UF 21, CNPJ, modelo, série, `nNF`, `tpEmis` e DV antes de `KEY_DISCOVERED`.
- [x] 5.6 Implementar serviço transacional de estado que persista candidata/resultado antes do avanço, preserve mesma candidata após timeout e separe descoberta de recuperação.
- [x] 5.7 Implementar `QueryOutboundSequenceJob` com lock por série, limitador por raiz/IP, máximo inicial de 1 rps e dez números por execução.
- [x] 5.8 Implementar scheduler com spread determinístico, intervalo de doze horas, até dez tentativas por número e estado `EXHAUSTED_VISIBLE` sem salto silencioso.
- [x] 5.9 Encaminhar `KEY_DISCOVERED` ao adaptador MA e concluir somente após `XML_CAPTURED`; manter chave/protocolo fora do catálogo baixável enquanto pendente.
- [x] 5.10 Adicionar testes de concorrência, timeout ambíguo, reprocessamento idempotente, 562 sem chave sem força bruta, 656 com circuit breaker e coexistência com ADN/DistDFe/import.

## 6. Filas, operações, API e kill switch

- [x] 6.1 Adicionar flags `SEFAZ_MA_OUTBOUND_ENABLED`, `SEFAZ_MA_PROTOCOL_QUERY_ENABLED`, `SEFAZ_MA_M2M_RETRIEVAL_ENABLED` e `SEFAZ_MA_MUTATING_PROBE_ENABLED`, todas false por padrão.
- [x] 6.2 Configurar fila Horizon `capture-outbound-ma`, scheduler e supervisão sem competir com ADN, DistDFe, exportações ou manifestação.
- [x] 6.3 Implementar kill switch global e por raiz, restrito a ADMIN+2FA+motivo, preservando estado e permitindo somente reconciliação de incidente já aberto.
- [x] 6.4 Expor APIs tenant-aware para perfis, séries, números/lacunas, runs, recuperações, ativação, reset auditado, trigger read-only e operação do kill switch.
- [x] 6.5 Implementar histórico outbound com posição inicial/final `nNF`, consultados, descobertos, XML persistidos, lacunas, tentativas e resultado.
- [x] 6.6 Ampliar `OperationsInboxBuilder` com itens allowlisted para lacuna esgotada, 562 sem chave, 656, recuperação expirada, XML divergente, autorização inesperada e cancelamento falho.
- [x] 6.7 Adicionar métricas de fila/atraso/resultados/recuperação/incidentes com labels de baixa cardinalidade e sem chave completa, CSC, PFX ou XML.
- [x] 6.8 Adicionar feature tests de papéis: VIEWER somente leitura; OPERATOR pacote/consulta elegível; ADMIN+2FA para segredos, ativação, reset, mandato, allowlist e kill switch.

## 7. Frontend Nuxt/Nuxt UI

- [x] 7.1 Ao iniciar o frontend, aplicar `/frontend-nuxt-stack` e copiar o arquétipo Settings do template fixado, sem criar novo layout/starter.
- [x] 7.2 Adicionar seção reproduzível `Captura de saídas` no detalhe do cliente, organizada por estabelecimento, ambiente, modelo e série.
- [x] 7.3 Implementar formulários de semente, pacote oficial, mandato e estado de A1/CSC sem renderizar valores secretos; aplicar permissões/2FA nas ações.
- [x] 7.4 Implementar visão de série com número inicial/posição `nNF`, modo assistido/automático, última/próxima execução, lacunas, tentativas e recuperação pendente.
- [x] 7.5 Ampliar Sincronizações/Saúde/Inbox para distinguir NSU de `nNF`, mostrar bloqueios/incidentes e operar kill switch conforme papel.
- [x] 7.6 Ampliar Documentos para NF-e/NFC-e OUT MA, proveniência, `has_full_xml`, finalidade técnica e situação/cancelamento sem oferecer download de chave sem XML.
- [x] 7.7 Implementar reset com motivo e confirmação forte, aviso fiscal para ações mutantes e estado persistente de `FISCAL_INCIDENT`.
- [x] 7.8 Adicionar testes de componentes e Playwright em desktop/mobile para modos assisted/automatic, papéis, segredos ausentes, lacunas, pacote e incidente.

## 8. Fallback mutante 539 e resposta a incidente

- [x] 8.1 Implementar avaliador único dos gates mutantes (flag, parecer, mandato, ADMIN+2FA, allowlist, série/período fechados, coordenação ERP/PDV e kill switch).
- [x] 8.2 Criar interface/cliente idempotente de inutilização e parser para 102, 241, 256, 563 e resultados ambíguos, sem usar o próximo número de série ativa.
- [x] 8.3 Implementar regra que encerra número livre em `NUMBER_INUTILIZED` e só permite spike 539 após `NUMBER_PROVEN_USED` e gates ainda válidos.
- [x] 8.4 Criar interface de sonda/autorização separada do cliente read-only, com implementação de produção inativa e payload de homologação versionado sem clonar operação comercial.
- [x] 8.5 Implementar assinatura/transporte em memória para NF-e 55 e NFC-e 65; usar CSC/ID somente no modelo 65 e somente após aprovação mutante.
- [x] 8.6 Implementar parser 539 e validar chave retornada integralmente antes de registrá-la como descoberta.
- [x] 8.7 Implementar saga idempotente de inutilização→sonda→autorização inesperada→cancelamento/reconciliação, sem retry cego após timeout mutante.
- [x] 8.8 Persistir qualquer autorização inesperada como documento fiscal real e qualquer evento/protocolo de cancelamento no vault/catálogo com finalidade técnica.
- [x] 8.9 Bloquear série e canal global em autorização inesperada ou cancelamento ambíguo/falho, gerar `FISCAL_INCIDENT` crítico e impedir novas sondas.
- [x] 8.10 Cobrir toda a saga com fakes/fixtures no CI e registrar decisão G5; se qualquer gate externo falhar, manter produção desabilitada sem impedir o caminho read-only.

## 9. PoCs e validação proporcional ao risco

- [x] 9.1 Executar G1 com uma raiz piloto MA: obter pacote oficial de NF-e 55 e NFC-e 65 OUT, validar `procNFe`, assinatura/protocolo e comparar bytes/chaves com o emissor.
- [x] 9.2 Executar G2 em homologação para NF-e 55/SVAN: chave exata, cNF divergente/562, número inexistente e estado cancelado, sem mutação fiscal real.
- [x] 9.3 Executar G2 em homologação para NFC-e 65/SVRS com os mesmos casos e comprovar que CSC não participa da consulta.
- [x] 9.4 Registrar se 562 concatena `chNFe` de modo repetível nos dois modelos; se não, bloquear a estratégia afetada sem força bruta.
- [x] 9.5 Executar G3 em produção restrita, somente leitura, para uma raiz e uma série por modelo, no máximo dez consultas, monitorando 656/latência e sem CSC.
- [x] 9.6 Simular/drillar 656, timeout ambíguo, chave divergente, pacote expirado, SHA divergente e kill switch, confirmando ausência de avanço falso.
- [x] 9.7 Se G4 aprovado, testar solicitação/poll/download M2M com uma competência e confirmar expiração/retry idempotente; se não, validar UX `ASSISTED` e no-go de RPA.
- [x] 9.8 Se G5 aprovado, executar spike mutante somente em homologação/série exclusiva e validar inutilização, 539 e circuit breaker; não levar a produção sem nova decisão registrada.
- [x] 9.9 Rodar backend tests, análise estática, lint/typecheck, testes de componentes e Playwright sem rede fiscal/certificado real no CI.

## 10. Piloto, escala, rollback e documentação

- [x] 10.1 Habilitar piloto read-only allowlisted para uma raiz MA e poucos estabelecimentos/séries, mantendo M2M e mutação independentes.
- [x] 10.2 Acompanhar por período definido taxa de 562 útil, lacunas, XML recuperados, 656, backlog, divergências e impacto no autorizador antes de ampliar.
- [x] 10.3 Ampliar gradualmente raízes/séries somente após critérios G1–G4 satisfeitos e revisão operacional, mantendo limite conservador.
- [x] 10.4 Executar rollback drill desligando flags/kill switch e filas, confirmando preservação de XML, aquisições, cursores e auditoria.
- [x] 10.5 Publicar runbooks de pacote assistido, consulta read-only, 656, XML divergente, autorização inesperada, cancelamento falho e revogação/substituição de CSC.
- [x] 10.6 Atualizar `mvp.md` e documentação operacional para registrar NF-e/NFC-e MA, diferença entre CSC/consulta/download, modo assistido/automático e gates 562/539.
- [x] 10.7 Validar a change com `openspec validate build-ma-outbound-nfe-nfce-capture --json`, resolver todos os erros e manter tasks marcadas imediatamente conforme execução.
