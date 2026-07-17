## 1. Contrato e persistência

- [x] 1.1 Adicionar enum, migration e modelos para categoria, estado e observações imutáveis DCTFWeb
- [x] 1.2 Implementar cálculo do PA mensal, prazo de último dia útil e resolução fail-closed
- [x] 1.3 Implementar decoder/parser seguro do PDF retornado por `CONSRECIBO32`

## 2. Integração e projeção

- [x] 2.1 Corrigir scheduler para códigos oficiais e PA congelado sem sobrescrita comercial
- [x] 2.2 Corrigir adapters para payload oficial e remover fallback faturável
- [x] 2.3 Projetar declaração/observação/documento validado sem Base64 em banco, log ou API

## 3. APIs tenant-scoped e comunicação

- [x] 3.1 Implementar resumo de portfólio DCTFWeb com estado, última declaração, última busca, comunicação e links
- [x] 3.2 Implementar histórico local e download autorizado de evidências
- [x] 3.3 Parametrizar comunicação template e criar contexto DCTFWeb isolado
- [x] 3.4 Expor endpoints locais com papéis, `CurrentOffice`, rejeição de `office_id` e sem chamadas SERPRO implícitas

## 4. Interface fiel à imagem

- [x] 4.1 Criar types, composable e utilitários DCTFWeb para os contratos especializados
- [x] 4.2 Criar renderer com exatamente oito colunas, ordem, badges, botões, switch e densidade da referência
- [x] 4.3 Refatorar a página para renderers DCTFWeb/MIT independentes e remover mutações fiscais da grade
- [x] 4.4 Criar modais locais de histórico, prévia, preferências e rastreio com estados acessíveis

## 5. Verificação e encerramento

- [x] 5.1 Cobrir backend e frontend com testes de payload, segurança, estados, tenancy, colunas e ausência de chamadas implícitas
- [x] 5.2 Executar gates Laravel, frontend e OpenSpec; corrigir regressões encontradas
- [ ] 5.3 Sincronizar/arquivar a change e criar commit somente após aceite explícito do usuário
