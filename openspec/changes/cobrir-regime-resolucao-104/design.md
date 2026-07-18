## Contrato oficial

`REGIMEAPURACAO` / `CONSULTARRESOLUCAO104`, rota `Consultar`, requer
`anoCalendario` numérico e devolve `dados.textoResolucao` em Base64. Quando há
representação, exige procuração e-CAC `00060`. É consulta não mutante; por ser
bilhetável, a interface separa abertura do histórico da confirmação de coleta.

## Decisões

### Codec e persistência fail-closed

O codec aceita apenas Base64 estrita dentro de limite fixo e texto UTF-8
válido. Campo ausente, Base64 inválido, binário ou tamanho excessivo falham
antes de criar a projeção. O conteúdo é salvo no `FiscalEvidenceStore`; a
projeção pública contém somente ano, tamanho, hash, data e `download_path`.

### Consulta explícita e leitura local

O POST recebe `client_id`, `year` e correlação opcional. O GET resolve o
cliente por `CurrentOffice` e retorna somente descritores locais. Nenhuma
montagem de página, modal ou GET aciona a SERPRO.

### Interface sem dados brutos

Uma ação PGDAS-D abre o modal de resoluções locais. A atualização é um segundo
passo, com aviso de possível cobrança. O download usa somente caminho
same-origin provido pelo backend.
