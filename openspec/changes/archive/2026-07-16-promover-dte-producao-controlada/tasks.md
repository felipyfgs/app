## 1. Domínio do canário DTE

- [x] 1.1 Criar migration/modelos do pedido, alvo server-side, aprovações, tentativa idempotente, reconciliação e controle `DISABLED|CANARY|LIMITED`, com operação/coordenadas DTE imutáveis; testar migrate/rollback.
- [x] 1.2 Implementar seleção global de Office Production e cliente pertencente, rejeitando `office_id`/operação/coordenadas livres e cross-tenant; executar testes de tenancy.
- [x] 1.3 Implementar aprovação do Proprietário e confirmação separada do Office ADMIN pelo `CurrentOffice`, rejeitando a mesma conta nos dois papéis e senha acima de 15 minutos.
- [x] 1.4 Implementar gate pré-transporte para credencial/OAuth/gates/A1/Termo/procuração `00050`/limites/switches/Office Production; testar regressão de cada dependência.
- [x] 1.5 Implementar reserva/dispatch único de `dte.consultar`, chave idempotente e `UNCERTAIN` sem retry, usando executor/ledger central; provar uma chamada máxima com HTTP fake.
- [x] 1.6 Persistir resultado fiscal somente no Office/vault e criar DTO global sanitizado; testar que Proprietário sem membership não recebe payload fiscal.
- [x] 1.7 Implementar reconciliação manual e promoção `LIMITED` ao mesmo Office/teto dez, além de desativação imediata e alerta 80/100%; testar outro Office/operação bloqueados.

## 2. APIs e superfícies por autoridade

- [x] 2.1 Criar APIs globais para alvo, aprovação do Proprietário, execução unitária, resumo, reconciliação, promoção e desativação sob Sanctum/Proprietário/senha recente.
- [x] 2.2 Criar API tenant para Office ADMIN confirmar participação no Office corrente, sem importar serviço global no controller tenant e sem aceitar `office_id`.
- [x] 2.3 Criar API tenant read-only do resultado DTE e autorização por membership, com testes VIEWER/ADMIN e cross-tenant.
- [x] 2.4 Cobrir auditoria/correlação/redaction, consumo unitário, replay, limites 80/100%, external kill e ausência de outros idSistema/idServico/rotas.

## 3. UI e verificação offline

- [x] 3.1 Estender a Configuração global com alvo, estado do canário, aprovação, tentativa, reconciliação, promoção limitada e desativação, sem mostrar resultado fiscal.
- [x] 3.2 Estender `/settings` do Office com confirmação de participação para Office ADMIN e resultado tenant conforme autorização, reutilizando arquétipo Settings.
- [x] 3.3 Criar testes frontend unit/E2E para conta dual, senha expirada, cross-tenant, replay, kill switch, links e ausência de payload global.
- [x] 3.4 Executar backend Pint/PHPUnit/SERPRO/arquitetura, frontend lint/typecheck/test/generate/E2E e `openspec validate promover-dte-producao-controlada --type change --strict` sem rede real.

## 4. Live ops-gated e encerramento

- [ ] 4.1 Live ops-gated: preencher credencial Production, OAuth recente, seis gates, Office/cliente piloto, A1, Termo, procuração `00050`, limites e duas aprovações reais.
- [ ] 4.2 Live ops-gated: executar exatamente uma tentativa DTE, registrar evidência sanitizada e reconciliar manualmente com histórico da Área do Cliente; manter desmarcado até prova real.
- [ ] 4.3 Live ops-gated: promover `LIMITED` somente se o canário reconciliado passou e comprovar teto dez/desativação; nenhuma outra capability ou Office.
- [ ] 4.4 Após aceite de software e live ops, sincronizar `serpro-go-live-controlado`, arquivar a change e commitar no mesmo dia sem apontar CI para archive.

