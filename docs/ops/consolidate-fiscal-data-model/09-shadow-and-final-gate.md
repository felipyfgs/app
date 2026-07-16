# 1.9 Shadow verification, tolerâncias e relatório final

## Responsáveis (RACI resumido)

| Papel | Responsabilidade | Atribuição |
|-------|------------------|------------|
| **Engenharia (apply)** | Executar backfills, adapters, testes, reconciliador | Time de implementação da change |
| **Ops / plataforma** | Backup/restore, filas, janela de corte | Operação da instância |
| **Product / domínio fiscal** | Aprovar exceções de jornada e cardinalidade | Owner do produto hub fiscal |
| **Aprovador final do gate** | Assinar `APROVADO` / `REPROVADO` no relatório 10.4 | Explicitamente o solicitante da change + ops (dois olhos) |

> Preencher nomes humanos no kickoff da janela de apply produtivo. Em local/dev, engenharia auto-aprova gates locais de harness.

## Janela de shadow verification

| Parâmetro | Valor default | Notas |
|-----------|---------------|-------|
| Duração mínima | **7 dias corridos** por agregado em ambiente com tráfego real; **24h** com job synthetic em local/demo | Open Q do design: confirmar antes de prod |
| Frequência do reconciliador | horário (cron) + on-demand pós-backfill | exit ≠ 0 em divergência não aprovada |
| Ordem de sombra → corte | tenancy/cadastro → documentos/cursores/outbound → SERPRO → monitoramento/guias | design § Migration Plan |
| Rollback | feature flag / adapter de leitura; **não** apagar escritas novas | |

## Critérios de tolerância

| Classe | Tolerância | Ação |
|--------|------------|------|
| Contagem de evidência fiscal (docs, hashes, NSU, ledger qty) | **0** | Bloqueia gate |
| Cross-office reference | **0** | Bloqueia gate |
| Órfão criado pelo backfill | **0** | Bloqueia; se legado pré-existente, registrar exceção |
| Ambiguidade de mapa origem-destino | **0 auto-escolha** | Linha em `AMBIGUOUS`, gate local falha |
| Latência p95 API | +20% vs baseline medido na mesma máquina | Alerta; não bloqueia sozinho se dados OK |
| Divergência de label de enum com mesmo significado | Permitido só com mapper documentado | Exceção formal |
| Campo deprecated ainda exposto no JSON | Permitido na janela de compat | Remover só pós 10.x |

## Formato do relatório final (task 10.4)

Arquivo sugerido: `docs/ops/consolidate-fiscal-data-model/final-gate-report-YYYY-MM-DD.md`

```markdown
# Relatório final — consolidate-fiscal-data-model

- Versão / commit:
- Migrations aplicadas (lista):
- Período shadow (início/fim) por agregado:
- Ambiente:

## Resultados por agregado
| Agregado | Reconciliação | Testes | Shadow | Decisão local |
|----------|---------------|--------|--------|----------------|
| tenancy-cadastro | | | | |
| documentos-cursores | | | | |
| outbound | | | | |
| serpro | | | | |
| monitoramento-guias | | | | |

## Divergências
| ID | Agregado | Descrição sanitizada | Aprovada? | Responsável |
|----|----------|----------------------|-----------|-------------|

## Exceções formais
...

## Restore pós-apply
- Backup id:
- Restore: OK/FAIL
- Smoke: OK/FAIL

## openspec validate
- Resultado:

## Decisão final
- APROVADO | REPROVADO
- Assinaturas:
- Se REPROVADO: adapters revertidos = [lista]; legado NÃO removido
```

## Gate automático (task 10.5)

Se `Decisão final = REPROVADO` **ou** restore não comprovado:

1. Flags de corte → leitura no adapter legado  
2. Bloquear PR/migration de drop de colunas/tabelas legadas  
3. Preservar mapas e evidência de backfill  

## Checklist de saída da fase 1

- [x] Hub estável + specs sync  
- [x] Inventário migrations  
- [x] Dicionário PG  
- [x] Matriz origem-destino  
- [x] Matriz de funcionalidades  
- [x] Baseline de dados  
- [x] Snapshot de rotas / jornadas  
- [x] Procedimento backup/restore (ensaio físico na janela de apply)  
- [x] RACI + shadow + tolerâncias + template de relatório  
