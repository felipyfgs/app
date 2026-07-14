## 1. Rastreabilidade e contratos de interface

- [x] 1.1 Registrar a matriz de rotas, padrão de template adotado, ação primária, filtros, detalhe, perfis e viewports definida no design
- [x] 1.2 Mapear cada tarefa deste change às tarefas 9.2–9.10 de `build-nfse-adn-capture-system`, impedindo conclusão duplicada ou prematura
- [x] 1.3 Verificar se o cursor de Notas pode ser serializado e retomado com segurança; documentar a decisão de restauração na URL
- [x] 1.4 Inventariar componentes, estilos e testes atuais que serão preservados, substituídos ou removidos em cada módulo

## 2. Shell, tema e feedback compartilhado

- [x] 2.1 Consolidar sidebar recolhível/redimensionável, fechamento mobile, identidade não interativa do escritório ativo e menu do usuário
- [x] 2.2 Derivar sidebar, command palette e atalhos da mesma fonte tipada de permissões e testar `ADMIN`, `OPERATOR` e `VIEWER`
- [x] 2.3 Corrigir o slideover de alertas para distinguir carregamento, lista, vazio e erro parcial ou total, com ação de tentar novamente
- [x] 2.4 Limpar alertas, seleção e estado sensível quando identidade autenticada mudar ou a sessão terminar
- [x] 2.5 Preservar Public Sans, tokens semânticos e modo claro/escuro persistido; remover ou limitar seletores de paleta que não garantam contraste
- [x] 2.6 Criar o preset visual compartilhado de `UTable` com cabeçalho elevado, bordas, cantos e divisores equivalentes ao template

## 3. Dashboard operacional

- [x] 3.1 Migrar indicadores para `UPageGrid` e `UPageCard` com continuidade visual e ordem operacional de severidade
- [x] 3.2 Manter alertas acionáveis abaixo dos indicadores e exibir horário da última atualização válida
- [x] 3.3 Preservar resumo anterior durante falha de atualização manual e oferecer nova tentativa com mensagem sanitizada
- [x] 3.4 Confirmar por teste que não existem gráfico, variação ou filtro temporal com dados artificiais
- [x] 3.5 Cobrir resumo normal, vazio, falho, bloqueado e com certificado próximo do vencimento em desktop e mobile

## 4. Lista e detalhe de Clientes

- [x] 4.1 Aplicar à lista de Clientes o preset de tabela, faixa utilitária do corpo, total, busca server-side e paginação numerada refletida na URL
- [x] 4.2 Migrar criação de cliente para `UForm` com schema Zod, erros 422 por campo, loading e modal no padrão do template
- [x] 4.3 Criar a subnavegação do detalhe para `Resumo`, `Estabelecimentos`, `Certificado A1` e `Sincronização`, com seção reproduzível na URL
- [x] 4.4 Extrair o resumo e onboarding para componente próprio, tratando “gerenciado por ADMIN” como estado informativo para perfis sem permissão
- [x] 4.5 Extrair lista e formulário de Estabelecimentos para componentes próprios com CNPJ alfanumérico preservado
- [x] 4.6 Extrair gestão de A1 para componente próprio que exponha apenas metadados públicos e limpe senha/PFX ao fechar ou concluir
- [x] 4.7 Extrair teste e primeira sincronização para componente próprio com ação condicionada ao perfil e feedback assíncrono
- [x] 4.8 Cobrir abertura direta de cada seção, retorno, permissões e fluxo cliente → estabelecimento → A1 → sincronização

## 5. Catálogo mestre–detalhe de Notas

- [x] 5.1 Extrair catálogo, filtros, linha de nota e detalhe em componentes de domínio independentes
- [x] 5.2 Aplicar ao catálogo o preset de tabela, filtros server-side e paginação por cursor sem conversão para offset local
- [x] 5.3 Sincronizar busca, filtros e seleção com a rota, removendo parâmetros vazios e reiniciando resultados ao mudar a consulta
- [x] 5.4 Implementar painel mestre redimensionável entre 30% e 40% e painel de detalhe adjacente em viewport `lg` ou maior
- [x] 5.5 Implementar estado neutro sem seleção e realce semântico da linha selecionada no desktop
- [x] 5.6 Implementar detalhe em `USlideover` abaixo de `lg`, com fechamento que retorna ao catálogo preservando filtros
- [x] 5.7 Implementar seleção anterior/próxima por teclado e rolagem do item selecionado para a área visível
- [x] 5.8 Preservar abertura direta de `/notes/:accessKey`, isolamento entre escritórios e download autorizado sem renderizar XML bruto
- [x] 5.9 Cobrir desktop, mobile, URL direta, retorno, resultado vazio, falha de atualização e nota não encontrada

## 6. Exportações e Sincronizações

- [x] 6.1 Aplicar à lista de Exportações o preset de tabela e hierarquia de ação primária, estado, escopo, tamanho, expiração e download
- [x] 6.2 Migrar solicitação de exportação para `UForm` tipado, impedir submissão duplicada e manter filtros funcionais do domínio
- [x] 6.3 Manter polling somente enquanto houver exportação pendente e preservar os dados atuais em falha transitória
- [x] 6.4 Remover ação de download quando o pacote expirar e orientar solicitação de novo pacote
- [x] 6.5 Aplicar à lista de Sincronizações o preset de tabela e paginação por cursor
- [x] 6.6 Refatorar detalhe de sincronização em slideover com origem, horários, NSUs, páginas, documentos e falha sanitizada
- [x] 6.7 Destacar cursor bloqueado sem oferecer avanço ou salto manual de NSU
- [x] 6.8 Cobrir Exportações e Sincronizações nos estados inicial, processando, pronto, falho, expirado e bloqueado

## 7. Administração e formulários

- [x] 7.1 Reorganizar Administração em conteúdo central no padrão Settings e adicionar toolbar somente se existirem múltiplas seções reais
- [x] 7.2 Impedir renderização de conteúdo administrativo antes da confirmação de papel e segundo fator
- [x] 7.3 Padronizar formulários restantes com `UForm`, `UFormField name`, erros associados, loading e hierarquia cancelar/confirmar
- [x] 7.4 Padronizar modais destrutivos com alvo, consequência, cancelar neutro e confirmar em `error`

## 8. Acessibilidade, segurança e responsividade

- [x] 8.1 Adicionar nomes acessíveis a todos os botões icônicos e manter tooltip apenas como ajuda complementar
- [x] 8.2 Validar foco visível, ordem de tabulação, contenção e retorno de foco em menus, modais, popovers e slideovers
- [x] 8.3 Garantir que seleção e estados operacionais usem texto ou ícone além de cor
- [x] 8.4 Validar que identidade, estado e ação principal permanecem disponíveis nas tabelas em viewport móvel
- [x] 8.5 Verificar ausência de rolagem horizontal do documento a 360 px e conclusão dos fluxos a 390×844
- [x] 8.6 Adicionar testes que rejeitem PFX, senha, chave privada, PEM, XML bruto e resposta ADN não sanitizada na interface e artefatos de teste

## 9. Validação e limpeza

- [x] 9.1 Executar lint e typecheck do frontend e corrigir somente regressões relacionadas ao change
- [x] 9.2 Executar testes de componentes para shell, permissões, tabelas, formulários, mestre–detalhe e estados assíncronos
- [x] 9.3 Executar Playwright em 1440×900 e 390×844 para navegação, Clientes, Notas, Exportações, Sincronizações e Administração
- [x] 9.4 Fazer inspeção adicional dos fluxos principais a 360 px e somente por teclado
- [x] 9.5 Remover componentes e estilos obsoletos após confirmar ausência de consumidores
- [x] 9.6 Atualizar README do frontend com padrões adotados, rotas e comandos de validação
- [x] 9.7 Reconciliar evidências com as tarefas 9.2–9.10 do change ativo e marcar apenas critérios efetivamente comprovados
