## Purpose

Capability `cnpj-registration-lookup` — requisitos sincronizados das changes OpenSpec.

## Requirements

### Requirement: Lookup cadastral inclui atividades e IEs essenciais
O sistema SHALL mapear, a partir da consulta pública CNPJ.ws (ou cache sanitizado), o CNAE principal, todos os CNAEs secundários (`code` + `name`) e as inscrições estaduais (`number`, `state`, `active`) no resultado de `GET /api/v1/cnpj/{cnpj}/lookup` e no snapshot persistido do estabelecimento, sem retornar CPF/CNPJ de sócio em claro nem o payload bruto da API externa.

#### Scenario: Fixture Globo com CNAEs secundários
- **WHEN** um operador autorizado consulta o CNPJ `27865757000102` e a fonte retorna o payload de referência da fixture pública
- **THEN** a resposta inclui `establishment.main_cnae_code` igual a `6021700` e `establishment.secondary_cnaes` com pelo menos 26 itens com `code` e `name`

#### Scenario: IEs presentes no snapshot
- **WHEN** a fonte retorna `inscricoes_estaduais` no estabelecimento
- **THEN** `establishment.state_registrations` contém cada inscrição com número, UF e flag `active` correspondente

### Requirement: QSA sem duplicata e com documento mascarado
O sistema SHALL deduplicar sócios do mapeamento CNPJ.ws pela chave lógica nome normalizado + data de entrada + código de qualificação, e SHALL mascarar qualquer documento de sócio antes de cache, resposta JSON ou persistência.

#### Scenario: Sócios duplicados pela fonte
- **WHEN** a fonte envia o mesmo diretor duas vezes (documento mascarado e documento em claro)
- **THEN** o resultado do lookup contém uma única entrada para esse sócio e o campo `document_masked` contém `*`

#### Scenario: CPF em claro nunca aparece no JSON
- **WHEN** o lookup serializa `shareholders`
- **THEN** nenhum valor de documento é uma sequência de exatamente 11 dígitos sem máscara

### Requirement: Dossiê exibe atividades e IEs do snapshot
O painel SHALL exibir, na visão somente-leitura do cadastro do cliente (`ClientRegistration`), o CNAE principal, a lista de CNAEs secundários persistidos e as inscrições estaduais do estabelecimento principal, sem exigir nova consulta à API pública.

#### Scenario: CNAEs secundários no dossiê
- **WHEN** o estabelecimento principal do cliente possui `secondary_cnaes` não vazio
- **THEN** a UI do dossiê lista cada CNAE secundário com código e descrição

#### Scenario: IEs no dossiê
- **WHEN** o estabelecimento principal possui `state_registrations`
- **THEN** a UI lista as IEs (ativas em destaque) com número e UF
