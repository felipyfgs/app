# Contexto de domínio

## Visão do produto

O Fiscal Hub é uma plataforma multi-escritório. Cada `Office` é um tenant isolado; configuração contratual SERPRO pertence à instalação, enquanto certificados, termos, procurações, clientes e resultados fiscais pertencem ao Office.

## Linguagem ubíqua

| Termo | Definição | Não significa |
|---|---|---|
| Proprietário | Único usuário com papel global `PLATFORM_ADMIN` na instalação. Configura a plataforma, seleciona Office explicitamente e autoriza mudanças globais. | Não recebe membership fiscal implícita nem pode ver resultado fiscal de qualquer Office. |
| Contratante SERPRO | Pessoa jurídica titular do contrato Integra Contador, da Consumer Key/Secret e do e-CNPJ usado no OAuth mTLS. | Não é o Office atendido nem o contribuinte consultado. |
| Ambiente SERPRO | Partição isolada `TRIAL` ou `PRODUCTION` de contrato, credenciais, tokens, gates, limites e prontidão. | Homologação implícita ou reaproveitamento de token entre ambientes. |
| Versão de credencial | Conjunto imutável e versionado de PFX do Contratante, senha e Consumer Key/Secret, no ciclo PENDING, VERIFIED, ACTIVE, RETIRED ou COMPROMISED. | Um campo editável ou segredo recuperável pela API. |
| Office A1 | Certificado A1 sob custódia e autorização do Office, usado nos fluxos de representação/Termo definidos para esse tenant. | PFX global do Contratante SERPRO. |
| Termo de Autorização | Documento/ciclo de autorização necessário para representar o contribuinte no Integra Contador. | Contrato comercial SERPRO ou procuração e-CAC. |
| Procuração | Poder e-CAC do cliente para o representante; operações podem exigir poderes específicos, como `00050` para DTE. | Consequência automática de possuir certificado ou Termo. |
| Gate externo | Evidência mínima auditável de requisito fora do software, contendo referência, resumo, responsável e data. | Upload do documento, waiver silencioso ou segredo. |
| Kill switch | Bloqueio monotônico que impede novas operações. O estado persistido é operacional; o env é uma trava emergencial prevalente. | Feature flag que habilita operação. |
| Limite quantitativo | Teto positivo por ciclo, instalação e/ou Office, calculado pelo ledger local. | Saldo financeiro informado pelo SERPRO ou zero como ilimitado. |
| Canário DTE | Primeira tentativa faturável, única e idempotente de `dte.consultar` para um Office e cliente piloto, aprovada pelo Proprietário e por Office ADMIN distintos. | Health check, smoke de CI ou liberação do catálogo. |
| Modo LIMITED | Promoção manual de DTE após canário reconciliado, restrita ao mesmo Office e teto inicial de dez consultas. | Produção irrestrita ou liberação de outros Offices/operações. |

## Fronteiras e invariantes

- Rotas tenant usam `CurrentOffice`; `office_id` enviado pelo cliente não define escopo.
- Rotas `/api/v1/platform/*` não resolvem automaticamente contexto fiscal do Office.
- Segredos e resultados canônicos protegidos ficam no `SecureObjectStore`; banco/API usam metadados sanitizados.
- Trial nunca satisfaz gate de Production.
- Configuração ou switch aberto nunca basta para habilitar transporte: todos os gates aplicáveis são recalculados antes do HTTP.
- O resultado fiscal pertence ao Office; o console global vê somente status, correlação e consumo sanitizados.

