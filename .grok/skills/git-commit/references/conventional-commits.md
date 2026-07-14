# Conventional Commits — referência rápida (pt-BR)

## Estrutura

```
<type>[optional scope][optional !]: <descrição>

[corpo]

[rodapé]
```

- **type**: obrigatório, minúsculo
- **scope**: opcional, minúsculo, substantivo da área
- **!**: breaking change no header
- **descrição**: imperativo, sem ponto final, ideal ≤72 chars
- **corpo**: por quê / contexto; wrap ~72
- **rodapé**: `BREAKING CHANGE:`, `Closes #123`, `Refs #456`

## Types e exemplos (subject em pt-BR)

| Type | Exemplo |
|------|---------|
| `feat` | `feat(adn): sincronizar NSU com lock por estabelecimento` |
| `fix` | `fix(vault): impedir exposição de PEM na API de certificados` |
| `docs` | `docs(openspec): alinhar design ao fluxo de backup do vault` |
| `style` | `style(frontend): ajustar espaçamento da tabela de clientes` |
| `refactor` | `refactor(backend): extrair projeção de nfse_notes do parser` |
| `perf` | `perf(adn): limitar concorrência global a 4 rps` |
| `test` | `test(auth): cobrir papéis ADMIN e VIEWER no middleware` |
| `build` | `build(docker): atualizar imagem PHP-FPM para 8.4` |
| `ci` | `ci: adicionar job de lint no pipeline` |
| `chore` | `chore(dx): adicionar skill git-commit para o Grok` |
| `revert` | `revert: reverter feat(adn) que avançava NSU cedo demais` |

## Breaking changes

```
feat(auth)!: exigir TOTP para todos os papéis

BREAKING CHANGE: login sem segundo fator deixa de ser aceito.
```

## Action lines opcionais (body)

Só com contexto real da sessão; não fabricar.

```
feat(nfse): projetar eventos a partir do XML imutável

intent(nfse): painel precisa listar eventos sem reler dfe_documents
decision(nfse): projeção em nfse_events em vez de parse on-read
rejected(nfse): parse sob demanda — custo alto em listagens
constraint(nfse): dfe_documents permanece imutável; só append de interesse
learned(nfse): versão XSD desconhecida gera alerta mas XML bem-formado fica
```

## Scopes do monorepo

`backend` · `frontend` · `docker` · `openspec` · `auth` · `vault` · `adn` · `nfse` · `horizon` · `dx`

Omitir scope se a mudança for transversal sem área clara.

## Checklist de qualidade da mensagem

- [ ] Type correto (feat ≠ fix ≠ refactor)
- [ ] Scope consistente com o histórico do repo
- [ ] Subject em pt-BR, imperativo, sem ponto
- [ ] Body só se agregar “por quê”
- [ ] Sem segredos no diff staged
- [ ] Um tema lógico por commit
