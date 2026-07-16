## 1. Catálogo documental e domínio

- [x] 1.1 Capturar e normalizar o snapshot oficial das 119 operações com URL/hash, coordenadas, rota, versão, estado e regras por operação.
- [x] 1.2 Remover placeholders e atualizar o manifesto/importador para recusar entrada incompleta, duplicada ou sem fonte oficial.
- [x] 1.3 Versionar metadados de autenticação, schema, assincronismo, módulo e faturamento no catálogo canônico sem apagar versões anteriores.
- [x] 1.4 Cobrir contagens 119/98/19/1/1, coordenadas e bloqueio das 21 não produtivas em testes do catálogo.

## 2. Gateway SERPRO seguro

- [x] 2.1 Criar identidade fiscal tipada e tornar `operation_key` obrigatório no contrato `IntegraRequest`, removendo o fallback legado de coordenadas.
- [x] 2.2 Restringir rotas e headers, serializar `dados` uma vez e gerar `X-Request-Tag` opaca com até 32 caracteres.
- [x] 2.3 Aplicar Termo, token do procurador e poder e-CAC conforme metadado/relação de identidade, sem exigência global incorreta.
- [x] 2.4 Implementar uma renovação OAuth após 401 e normalizar 202/204/304/429/503 sem retry automático de timeout mutante.
- [x] 2.5 Corrigir faturamento por rota/status, rate limit configurável e sanitização de ETag/token/payload fiscal.
- [x] 2.6 Validar o gateway com testes unitários/feature de envelope, CNPJ alfanumérico, headers, retries, assincronismo e segredos.

## 3. Adapters e projeções por família

- [x] 3.1 Registrar drivers explícitos por família e eliminar fallback fake/legado em produção.
- [x] 3.2 Conectar autorização, procurações, eventos e SITFIS por `operation_key`.
- [x] 3.3 Substituir fontes fake de Caixa Postal/DTE por adapters SERPRO tipados.
- [x] 3.4 Conectar DCTFWeb/MIT e Simples/MEI/guias às coordenadas oficiais e codecs tipados.
- [x] 3.5 Substituir a fonte fake dos oito sistemas de parcelamento por adapters oficiais.
- [x] 3.6 Criar projeções e jobs Horizon idempotentes de Cadastro/Vínculos e e-Processo, sempre com `office_id`.
- [x] 3.7 Manter operações mutantes implementadas atrás de todos os gates e provar que scheduler não as executa.
- [x] 3.8 Registrar ledger/proveniência de todas as famílias sem payload ou segredo observável.

## 4. APIs e painel

- [x] 4.1 Criar APIs tenant-scoped de listagem, detalhe por cliente e refresh para Cadastro/Vínculos e Processos fiscais.
- [x] 4.2 Adicionar tipos/composables frontend para as novas APIs sem aceitar `office_id`.
- [x] 4.3 Copiar o arquétipo de lista do template para `/monitoring/registrations` e `/monitoring/tax-processes`, adaptando nav, estados e permissões.
- [x] 4.4 Adicionar seções Cadastro/Vínculos e Processos fiscais no detalhe do cliente pelo arquétipo Settings.
- [x] 4.5 Cobrir rotas, navegação, estados, responsividade e ausência de segredos com testes unitários/E2E/Playwright.

## 5. Validação e liberação fail-closed

- [x] 5.1 Executar Pint e a suíte backend focada em Fiscal/Serpro, corrigindo regressões.
- [x] 5.2 Executar lint, typecheck, generate, testes unitários e E2E do frontend.
- [x] 5.3 Validar isolamento com mesmo CNPJ em dois offices, mutações default OFF e produção sem simulated.
- [x] 5.4 Executar `openspec validate integrar-serpro-monitoramento-completo --json` e registrar que smoke produtivo/evidência legal permanecem pendentes.

### Notas de liberação (pendências operacionais)

- **Smoke mTLS produtivo / evidência comercial-legal:** permanecem **pendentes** (fora do escopo de CI; drivers default `disabled`/`simulated` fora de produção).
- **Frontend:** lint, typecheck, 257 testes unitários, `nuxt generate`, varredura de artefatos e fidelity gate passaram no mesmo estado do worktree.
- **E2E Playwright:** 36 cenários dedicados passaram contra o build isolado em 1440, 390 e 360 px; 19 cenários autenticados de regressão também passaram. A matriz global de 786 casos foi amostrada, não concluída, e não substitui o smoke produtivo restrito.
- **Contratos documentais:** 98 fixtures sintéticas de entrada/saída foram geradas a partir dos schemas oficiais; são marcadas como sintéticas e não substituem o smoke restrito com respostas produtivas.
