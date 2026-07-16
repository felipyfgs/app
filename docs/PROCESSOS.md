# MVP - Sistema de Tarefas e Processos Operacionais

## 1. Visao Geral

Este documento define um MVP para um sistema de tarefas e processos melhor que a experiencia auditada em `https://app.hubstrom.com/taskhub/processes`.

A auditoria foi feita em modo somente leitura, sem criar, editar, enviar, concluir, excluir ou salvar dados na aplicacao original.

O sistema proposto deve atender equipes que executam rotinas recorrentes por cliente, departamento, competencia e prazo, como fiscal, pessoal, contabil, legalizacao, RH, comercial e administrativo.

## 2. Diagnostico da Referencia Auditada

### 2.1 O que existe hoje na referencia

Na tela auditada foram observados:

- Modulo `TaskHub`, com navegacao principal para `Dashboard`, `Processos`, `Ordem de servico`, `Modelos` e `Configuracoes`.
- Area de `Processos` com duas visualizacoes: `Calendario` e `Tabela`.
- Calendario mensal com contadores por dia.
- Tabela segmentada por tres niveis: `Cliente`, `Processo` e `Tarefa`.
- KPIs por situacao:
  - Total.
  - Em multa.
  - A fazer.
  - Em progresso.
  - Concluido.
  - Dispensada.
- Filtros por periodo, prazo e departamento.
- Busca por cliente, processo ou tarefa.
- Departamentos visiveis:
  - Administrativo.
  - Comercial.
  - Contabil.
  - Fiscal.
  - Legalizacao.
  - Pessoal.
  - RH.
- Acoes:
  - Criar processo.
  - Edicao em lote.
  - Envio em lote.
  - Atribuir documentos.
- Criacao de processo por tres caminhos:
  - Utilizando IA.
  - Utilizando modelo.
  - Manualmente.
- Formulario manual com:
  - Titulo.
  - Cliente.
  - Descricao.
  - Prazo.
  - Prazo-meta.
  - Competencia.
  - Indicador de atraso gerar multa.
  - Departamento.
  - Responsavel.
  - Tarefas.

### 2.2 Dores e oportunidades percebidas

O produto atual resolve a base operacional, mas deixa espaco para um sistema mais forte em cinco pontos:

- Visao executiva mais clara: ha muitos numeros, mas pouca orientacao sobre prioridade real.
- Hierarquia mais explicita: cliente, processo e tarefa existem, mas o fluxo poderia mostrar melhor dependencia, gargalos e proximas acoes.
- Melhor criacao guiada: IA/modelo/manual aparecem como opcoes, mas o MVP deve transformar isso em um fluxo assistido com preview, checklist e validacao antes de criar.
- Priorizacao operacional: filtros por prazo existem, mas falta uma fila inteligente que responda "o que eu devo fazer agora?".
- Automacao e auditoria: tarefas recorrentes precisam de historico, evidencias, documentos, motivo de atraso, responsavel e trilha de decisao.

## 3. Objetivo do MVP

Construir um sistema web para planejar, executar e acompanhar processos recorrentes por cliente, competencia e departamento, com foco em:

- Reduzir tarefas esquecidas ou atrasadas.
- Centralizar responsabilidade e prazo.
- Criar processos a partir de modelos reutilizaveis.
- Dar uma fila diaria objetiva para cada pessoa.
- Mostrar gargalos para gestores.
- Registrar evidencias e historico minimo de execucao.

## 4. Publico-Alvo

### 4.1 Usuario executor

Pessoa que executa tarefas operacionais diariamente.

Necessidades:

- Saber o que fazer hoje.
- Ver prazo, cliente, processo e contexto.
- Atualizar status com poucos cliques.
- Anexar evidencia ou observacao.
- Pedir ajuda ou marcar impedimento.

### 4.2 Coordenador ou gestor

Pessoa responsavel por distribuir trabalho e acompanhar risco.

Necessidades:

- Ver volume por departamento, responsavel e prazo.
- Identificar atrasos, gargalos e sobrecarga.
- Reatribuir tarefas em lote.
- Garantir que processos recorrentes foram gerados corretamente.
- Acompanhar produtividade e SLA.

### 4.3 Administrador

Pessoa que configura modelos, usuarios, departamentos e regras.

Necessidades:

- Criar modelos de processo.
- Definir tarefas padrao.
- Configurar prazos relativos.
- Definir departamentos e permissoes.
- Auditar alteracoes.

## 5. Proposta de Valor

Um painel de operacao que transforma processos recorrentes em uma fila diaria priorizada, com modelos, prazos, responsaveis, status, evidencias e visao gerencial.

O diferencial do MVP nao e apenas listar tarefas. E orientar a execucao:

- O que esta atrasado.
- O que vence hoje.
- O que pode gerar multa.
- Quem esta sobrecarregado.
- Qual cliente ou departamento esta travando.
- Quais processos estao sem responsavel, sem prazo ou sem evidencia.

## 6. Escopo do MVP

### 6.1 Incluido no MVP

- Login simples com perfis.
- Cadastro de clientes.
- Cadastro de usuarios e departamentos.
- Modelos de processo com tarefas padrao.
- Criacao de processos manualmente ou por modelo.
- Geracao de processos por competencia.
- Lista de processos.
- Lista de tarefas.
- Calendario mensal.
- Fila inteligente "Hoje".
- Status de processo e tarefa.
- Responsavel por processo e por tarefa.
- Prazo, prazo-meta e competencia.
- Indicador de multa/risco.
- Comentarios e evidencias simples.
- Filtros por cliente, departamento, responsavel, status, prazo e competencia.
- Edicao em lote para responsavel, prazo e status.
- Dashboard gerencial.
- Log basico de alteracoes.

### 6.2 Fora do MVP

- Chat interno completo.
- IA generativa criando processos sozinha em producao.
- Integracoes fiscais profundas.
- Assinatura digital.
- Automacoes complexas entre sistemas externos.
- Aplicativo mobile nativo.
- Controle financeiro.
- BI avancado.
- Permissoes extremamente granulares.

## 7. Fluxos Principais

### 7.1 Criar modelo de processo

1. Administrador acessa `Modelos`.
2. Clica em `Novo modelo`.
3. Define nome, departamento padrao e descricao.
4. Adiciona tarefas padrao.
5. Para cada tarefa, define:
   - Nome.
   - Descricao.
   - Ordem.
   - Prazo relativo.
   - Responsavel padrao opcional.
   - Departamento.
   - Se exige evidencia.
6. Salva o modelo.

### 7.2 Gerar processo por modelo

1. Usuario acessa `Processos`.
2. Clica em `Criar processo`.
3. Escolhe `Usar modelo`.
4. Seleciona cliente ou grupo de clientes.
5. Seleciona competencia.
6. Seleciona modelo.
7. Sistema mostra preview:
   - Clientes afetados.
   - Processos a criar.
   - Tarefas geradas.
   - Prazos calculados.
   - Responsaveis.
   - Alertas de conflitos.
8. Usuario confirma.
9. Sistema cria os processos.

### 7.3 Executar tarefa

1. Executor abre `Minha fila`.
2. Ve tarefas ordenadas por prioridade:
   - Em multa.
   - Atrasadas.
   - Vencem hoje.
   - A atrasar.
   - Sem responsavel.
3. Abre a tarefa.
4. Consulta cliente, processo, descricao e documentos.
5. Atualiza status:
   - A fazer.
   - Em progresso.
   - Impedida.
   - Concluida.
   - Dispensada.
6. Adiciona comentario ou evidencia.
7. Sistema atualiza progresso do processo.

### 7.4 Gestor acompanha operacao

1. Gestor abre `Dashboard`.
2. Visualiza KPIs por periodo:
   - Total de tarefas.
   - Atrasadas.
   - Em multa.
   - Vencem hoje.
   - Sem responsavel.
   - Concluidas.
3. Filtra por departamento ou responsavel.
4. Entra em uma lista de risco.
5. Reatribui tarefas ou muda prazos em lote.

## 8. Telas do MVP

### 8.1 Dashboard

Objetivo: mostrar saude operacional.

Componentes:

- Cards de KPI:
  - Tarefas totais.
  - Atrasadas.
  - Em multa.
  - Vencem hoje.
  - Em progresso.
  - Concluidas.
  - Sem responsavel.
- Grafico por departamento.
- Grafico por responsavel.
- Lista de maiores riscos.
- Lista de processos sem dono.
- Filtros por competencia, departamento e cliente.

### 8.2 Minha fila

Objetivo: tela diaria do executor.

Componentes:

- Abas:
  - Hoje.
  - Atrasadas.
  - Esta semana.
  - Impedidas.
  - Concluidas.
- Lista de tarefas com:
  - Prioridade.
  - Cliente.
  - Processo.
  - Tarefa.
  - Prazo.
  - Status.
  - Departamento.
  - Responsavel.
- Acoes rapidas:
  - Iniciar.
  - Concluir.
  - Impedir.
  - Comentar.

### 8.3 Processos

Objetivo: visao consolidada por cliente/processo.

Componentes:

- Alternancia entre `Tabela` e `Calendario`.
- Abas:
  - Cliente.
  - Processo.
  - Tarefa.
- Filtros:
  - Periodo.
  - Competencia.
  - Cliente.
  - Departamento.
  - Responsavel.
  - Status.
  - Risco.
- Acoes:
  - Criar processo.
  - Criar por modelo.
  - Edicao em lote.
  - Exportar CSV.

### 8.4 Calendario

Objetivo: entender distribuicao temporal.

Componentes:

- Visao mensal.
- Visao semanal.
- Contadores por dia:
  - Atrasadas.
  - Hoje.
  - A fazer.
  - Em progresso.
  - Concluidas.
- Clique no dia abre painel lateral com tarefas.

### 8.5 Detalhe do processo

Objetivo: acompanhar um processo do inicio ao fim.

Componentes:

- Cabecalho:
  - Cliente.
  - Nome do processo.
  - Competencia.
  - Status.
  - Prazo.
  - Responsavel.
  - Departamento.
- Progresso das tarefas.
- Checklist de tarefas.
- Comentarios.
- Evidencias.
- Historico.

### 8.6 Modelos

Objetivo: criar processos recorrentes com padrao.

Componentes:

- Lista de modelos.
- Criador de modelo.
- Tarefas padrao ordenaveis.
- Prazos relativos:
  - Dia fixo do mes.
  - Dias antes do prazo do processo.
  - Dias apos inicio da competencia.
- Exigencia de evidencia.
- Responsavel/departamento padrao.

### 8.7 Configuracoes

Objetivo: administrar estrutura basica.

Componentes:

- Usuarios.
- Departamentos.
- Clientes.
- Status.
- Regras de prazo.
- Permissoes simples.

## 9. Modelo de Dados Inicial

### 9.1 Usuario

Campos:

- id.
- nome.
- email.
- perfil: admin, gestor, executor.
- departamento_id.
- ativo.

### 9.2 Departamento

Campos:

- id.
- nome.
- sigla.
- cor.
- ativo.

### 9.3 Cliente

Campos:

- id.
- nome.
- documento.
- grupo.
- responsavel_padrao_id.
- ativo.

### 9.4 ModeloProcesso

Campos:

- id.
- nome.
- descricao.
- departamento_padrao_id.
- ativo.
- criado_por.

### 9.5 ModeloTarefa

Campos:

- id.
- modelo_processo_id.
- nome.
- descricao.
- ordem.
- departamento_id.
- responsavel_padrao_id.
- prazo_relativo_tipo.
- prazo_relativo_valor.
- exige_evidencia.

### 9.6 Processo

Campos:

- id.
- cliente_id.
- modelo_processo_id.
- titulo.
- descricao.
- competencia.
- status.
- prazo.
- prazo_meta.
- gera_multa.
- departamento_id.
- responsavel_id.
- criado_por.
- criado_em.

### 9.7 Tarefa

Campos:

- id.
- processo_id.
- titulo.
- descricao.
- ordem.
- status.
- prazo.
- prazo_meta.
- departamento_id.
- responsavel_id.
- exige_evidencia.
- concluida_em.
- concluida_por.

### 9.8 Comentario

Campos:

- id.
- entidade_tipo: processo ou tarefa.
- entidade_id.
- autor_id.
- texto.
- criado_em.

### 9.9 Evidencia

Campos:

- id.
- tarefa_id.
- nome_arquivo.
- url_arquivo.
- enviado_por.
- enviado_em.

### 9.10 LogAuditoria

Campos:

- id.
- usuario_id.
- acao.
- entidade_tipo.
- entidade_id.
- valor_anterior.
- valor_novo.
- criado_em.

## 10. Status e Regras

### 10.1 Status de tarefa

- A fazer.
- Em progresso.
- Impedida.
- Concluida.
- Dispensada.

### 10.2 Status de processo

- A fazer: nenhuma tarefa iniciada.
- Em progresso: pelo menos uma tarefa iniciada.
- Impedido: existe tarefa impedida critica.
- Concluido: todas as tarefas obrigatorias concluidas ou dispensadas.
- Atrasado: prazo vencido e nao concluido.
- Em multa: prazo vencido em processo marcado como `gera_multa`.

### 10.3 Prioridade da fila

Ordem sugerida:

1. Em multa.
2. Atrasada.
3. Vence hoje.
4. Vence em ate 3 dias.
5. Impedida aguardando usuario.
6. Sem responsavel.
7. Demais tarefas abertas.

## 11. Melhorias-Chave Sobre a Referencia

### 11.1 Fila inteligente

Em vez de depender apenas de calendario e tabela, o usuario executor recebe uma tela de trabalho diario com priorizacao automatica.

### 11.2 Preview antes de gerar processos

Criacao por modelo deve mostrar exatamente o que sera criado antes de confirmar.

### 11.3 Tarefas com evidencia e impedimento

Cada tarefa pode exigir evidencia e pode ser marcada como impedida com motivo.

### 11.4 Risco operacional

O sistema deve destacar:

- Sem responsavel.
- Sem prazo.
- Atrasada.
- Em multa.
- Cliente com muitas pendencias.
- Responsavel sobrecarregado.

### 11.5 Historico confiavel

Toda alteracao relevante deve entrar no log:

- Status.
- Prazo.
- Responsavel.
- Departamento.
- Conclusao.
- Dispensa.
- Comentario.
- Evidencia.

## 12. Backlog Priorizado

### P0 - Fundacao do MVP

- Autenticacao.
- Usuarios.
- Departamentos.
- Clientes.
- CRUD de modelos.
- CRUD de processos.
- CRUD de tarefas.
- Status basicos.
- Lista de tarefas.
- Lista de processos.
- Filtros essenciais.

### P1 - Operacao diaria

- Minha fila.
- Dashboard operacional.
- Calendario mensal.
- Criacao por modelo.
- Preview de geracao.
- Comentarios.
- Evidencias.
- Edicao em lote de responsavel e prazo.

### P2 - Gestao e controle

- Log de auditoria.
- Indicador de risco.
- Tarefas impedidas.
- Relatorio CSV.
- Metricas por responsavel.
- Metricas por departamento.

### P3 - Diferenciais

- Criacao assistida por IA.
- Sugestao automatica de responsavel.
- Balanceamento de carga.
- Integracoes com documentos.
- Notificacoes por email ou WhatsApp.
- Automacoes externas.

## 13. Criterios de Aceite do MVP

O MVP sera considerado pronto quando:

- Um administrador conseguir cadastrar usuarios, departamentos e clientes.
- Um administrador conseguir criar um modelo de processo com tarefas.
- Um usuario conseguir gerar processos por modelo para uma competencia.
- Um executor conseguir ver sua fila diaria.
- Um executor conseguir iniciar, concluir, impedir e comentar tarefas.
- Um gestor conseguir ver tarefas atrasadas, em multa e sem responsavel.
- O calendario mensal mostrar distribuicao por prazo.
- A tabela permitir filtrar por cliente, departamento, responsavel, status e competencia.
- Alteracoes relevantes forem registradas em historico.

## 14. Metricas de Sucesso

### Produto

- Tempo medio para criar processos recorrentes.
- Percentual de tarefas com responsavel.
- Percentual de tarefas com prazo.
- Percentual de tarefas concluidas no prazo.
- Quantidade de tarefas atrasadas.
- Quantidade de tarefas em multa.

### Operacao

- Tarefas concluidas por dia.
- Tarefas por responsavel.
- Tarefas por departamento.
- Processos gerados por competencia.
- Tempo medio entre inicio e conclusao.

### Qualidade

- Percentual de tarefas concluidas com evidencia quando obrigatoria.
- Quantidade de tarefas impedidas.
- Motivos mais comuns de impedimento.
- Alteracoes manuais de prazo.

## 15. Arquitetura Sugerida

### 15.1 Stack simples para MVP

- Frontend: Next.js ou Nuxt.
- Backend: API REST ou server actions.
- Banco: PostgreSQL.
- ORM: Prisma ou Drizzle.
- Auth: email/senha no MVP, com possibilidade de SSO depois.
- Storage: S3 compativel para evidencias.
- Deploy: Vercel, Render, Railway ou VPS.

### 15.2 Entregas tecnicas

- Banco relacional com migracoes.
- API para processos, tarefas, modelos e usuarios.
- UI responsiva desktop-first.
- Controle de permissao por perfil.
- Testes basicos de criacao e transicao de status.
- Logs de auditoria.

## 16. Roadmap de 6 Semanas

### Semana 1

- Setup do projeto.
- Auth.
- Layout base.
- Usuarios, departamentos e clientes.

### Semana 2

- Modelos de processo.
- Tarefas padrao.
- Criacao manual de processo.

### Semana 3

- Criacao por modelo.
- Geracao por competencia.
- Preview antes de criar.

### Semana 4

- Minha fila.
- Status.
- Comentarios.
- Evidencias simples.

### Semana 5

- Dashboard.
- Calendario.
- Filtros.
- Edicao em lote.

### Semana 6

- Log de auditoria.
- Ajustes de UX.
- Testes.
- Seed de dados.
- Piloto com usuarios reais.

## 17. Primeiro Prototipo Navegavel

O primeiro prototipo deve conter estas telas:

- Login.
- Dashboard.
- Minha fila.
- Processos em tabela.
- Processos em calendario.
- Detalhe do processo.
- Modelos.
- Criar processo por modelo.
- Configuracoes basicas.

## 18. Decisoes de Produto

### Decisao 1: A tela principal deve ser "Minha fila"

Para executor, a primeira tela nao deve ser calendario nem tabela. Deve ser uma fila priorizada.

### Decisao 2: Calendario e tabela continuam importantes

Calendario serve para planejamento. Tabela serve para gestao e busca. A fila serve para execucao.

### Decisao 3: Modelo e competencia sao o coracao do sistema

Sem modelos bons, o sistema vira cadastro manual de tarefas. O MVP deve investir em modelos desde o inicio.

### Decisao 4: IA entra primeiro como assistente, nao como automacao autonoma

No MVP, IA pode sugerir modelo ou tarefas, mas o usuario deve revisar antes de criar qualquer processo.

## 19. Resumo Executivo

O MVP recomendado e um sistema operacional de processos recorrentes com tres pilares:

- Planejamento: modelos, competencias, prazos e responsaveis.
- Execucao: fila diaria, status, comentarios e evidencias.
- Gestao: dashboard, calendario, filtros, riscos e auditoria.

A referencia auditada mostra uma boa base de calendario, tabela, status e criacao de processos. O produto proposto deve ir alem ao priorizar a experiencia diaria do executor, deixar o gestor enxergar risco rapidamente e transformar modelos em uma maquina confiavel de geracao de processos.
