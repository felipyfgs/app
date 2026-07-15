## 1. Domínio e fundação

- [x] 1.1 Atualizar o glossário com Escritório cliente, Operação de domínio, Coordenadas SERPRO e Proveniência fiscal
- [x] 1.2 Registrar ADR de drivers por capacidade e proveniência, incluindo não-objetivos e evolução futura de backoffice/suporte
- [x] 1.3 Adicionar e fixar a dependência XMLDSig compatível com PHP 8.4

## 2. Catálogo oficial e migração

- [x] 2.1 Criar manifesto versionado com as 119 entradas e metadados de fonte, estado oficial e suporte da plataforma
- [x] 2.2 Criar validador/importador idempotente do manifesto com contagens e unicidade verificáveis
- [x] 2.3 Adicionar migration aditiva para `operation_key`, coordenadas oficiais e estado de suporte, sem alterar migrations aplicadas
- [x] 2.4 Relacionar o catálogo financeiro à `operation_key` e corrigir as coordenadas/poder `00002` do SITFIS

## 3. Transporte e drivers SERPRO

- [ ] 3.1 Corrigir configuração e autenticador para endpoint oficial, mTLS, `access_token` e `jwt_token`
- [ ] 3.2 Implementar contexto autenticado e envio sanitizado de Bearer, JWT, token do procurador e `X-Request-Tag`
- [ ] 3.3 Refatorar o cliente Integra Contador para resolver `operation_key`, usar rotas funcionais e montar envelope/dados oficiais
- [ ] 3.4 Normalizar respostas HTTP/negócio, `ETag`, `Expires`, mensagens, dados escapados e espera sem vazar payload
- [ ] 3.5 Implementar resolvedor de driver por capacidade com `disabled`, `simulated` e `real`, sem fallback e com bloqueio de simulação em produção
- [ ] 3.6 Implementar simulador determinístico contratualmente equivalente para os cenários SITFIS

## 4. Termo e representação

- [ ] 4.1 Corrigir parser do Termo para atributos/layout/XSD oficiais e datas AAAAMMDD
- [ ] 4.2 Implementar validação criptográfica XMLDSig, certificado, identidade, destinatário, vigência e uso da chave
- [ ] 4.3 Implementar cliente real de Autentica Procurador, cache 304/ETag/Expires e estados LOCAL_VALIDATED/SERPRO_ACCEPTED/SIMULATED/REJECTED

## 5. SITFIS e proveniência

- [ ] 5.1 Migrar runs, evidências e snapshots com proveniência/verificação e classificar legado como UNVERIFIED
- [ ] 5.2 Impedir snapshots simulados/não verificados de representar estado fiscal oficial ou atravessar tenants
- [ ] 5.3 Alinhar solicitação e emissão SITFIS 2.0, protocolo persistido, poder oficial e idempotência
- [ ] 5.4 Interpretar `tempoEspera`, 202, 204 e `ETag`, reencaminhar polling e preservar evidência antes do snapshot
- [ ] 5.5 Implementar atualização diária distribuída e refresh manual que reutiliza snapshot dentro do TTL
- [ ] 5.6 Enriquecer as APIs existentes com proveniência, verificação, observação, próxima atualização, permissão, bloqueio e correlação

## 6. Consumo e experiência

- [ ] 6.1 Aplicar regras oficiais de faturabilidade por rota/status e excluir simulações de reserva, franquia e custo
- [ ] 6.2 Manter preço/custo desconhecido sem contrato e persistir correlação para futura conciliação
- [ ] 6.3 Ajustar tipos e tela Settings existente para estados de autorização acionáveis e sanitizados
- [ ] 6.4 Ajustar carteira/detalhe SITFIS para processamento, idade, próxima atualização, bloqueio e origem conforme o template
- [ ] 6.5 Integrar conclusão/erro assíncrono às notificações internas sem conteúdo fiscal bruto

## 7. Verificação

- [ ] 7.1 Criar testes de catálogo, migrations, proveniência, tenancy e bloqueio do simulador em produção
- [ ] 7.2 Criar contract tests compartilhados para autenticação, envelope, headers, Termo, drivers e cenários SITFIS
- [ ] 7.3 Criar testes do ledger para rotas/status não faturáveis, simulação e custo desconhecido
- [ ] 7.4 Criar testes de API/UI para TTL, processamento, erro acionável, notificação e campos sanitizados
- [ ] 7.5 Executar suíte backend, typecheck/testes/build frontend e corrigir regressões no escopo
- [ ] 7.6 Validar a change com OpenSpec e registrar que contratação/smoke/conciliação real permanecem gates futuros
