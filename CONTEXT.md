# Hub Fiscal

Este contexto define a linguagem canônica do hub fiscal e do atendimento associado. Os termos mantêm claras as fronteiras entre obrigação fiscal, comunicação com o cliente e transporte WhatsApp.

## Organização e acesso

**Escritório**:
Tenant que reúne usuários, clientes, políticas fiscais e canais de atendimento isolados dos demais escritórios.
_Evitar_: Conta, workspace, organização

**Membro do escritório**:
Vínculo de um usuário com um escritório, usado para conceder acesso e atribuir atendimentos.
_Evitar_: Agente, operador global

**Departamento de trabalho**:
Fila operacional do escritório para a qual um atendimento pode ser encaminhado.
_Evitar_: Setor, equipe, queue

## Fiscal

**Cliente fiscal**:
Pessoa ou empresa acompanhada pelo escritório em obrigações e documentos fiscais.
_Evitar_: Contato, destinatário, consumidor

**Competência**:
Período fiscal exato ao qual uma consulta, obrigação, documento ou envio se refere.
_Evitar_: Data de envio, período aproximado

**Artefato fiscal canônico**:
Documento local confirmado que representa uma obrigação em uma competência exata e pode fundamentar um envio.
_Evitar_: Último documento, anexo disponível

**Política de monitoramento**:
Regra do escritório que determina quando consultar uma obrigação fiscal.
_Evitar_: Política de envio, automação de mensagem

**Política de automação de comunicação**:
Regra explícita e inicialmente desativada que determina quando e por qual inbox um artefato fiscal canônico pode ser enviado.
_Evitar_: Política de monitoramento, envio imediato

**Despacho de comunicação**:
Registro auditável de uma tentativa de comunicação fiscal para um único destinatário, competência e documento.
_Evitar_: Conversa, mensagem genérica

## Atendimento

**Inbox**:
Canal WhatsApp pertencente a um escritório, com número, membros, fila padrão e controles de operação próprios.
_Evitar_: Conta externa, sessão, caixa global

**Contato de comunicação**:
Pessoa conhecida ou provisória no contexto de comunicação de um escritório, independente de cliente fiscal.
_Evitar_: Cliente fiscal, usuário

**Identidade de comunicação**:
Endereço normalizado de um contato em um canal, como um número WhatsApp, único dentro do escritório.
_Evitar_: Telefone bruto, sessão

**Vínculo de cliente**:
Associação entre uma identidade de comunicação e um ou mais clientes ou contatos fiscais do mesmo escritório.
_Evitar_: Propriedade do telefone, cliente único

**Conversa**:
Ciclo auditável de atendimento entre uma inbox e uma identidade, com estado, fila e possível responsável.
_Evitar_: Sessão WhatsApp, thread do gateway

**Mensagem**:
Item inbound ou outbound visível na timeline de uma conversa, enviado por pessoa ou automação.
_Evitar_: Evento, comando

**Nota interna**:
Item da timeline visível somente à equipe e que nunca é entregue ao contato.
_Evitar_: Mensagem privada ao cliente

**Destinatário elegível**:
Identidade WhatsApp ativa e autorizada pelas preferências do cliente para receber uma comunicação fiscal específica.
_Evitar_: Primeiro telefone, qualquer contato

## Transporte WhatsApp

**Gateway WhatsApp**:
Componente interno em Go que mantém dispositivos, conexões e entrega de comandos/eventos, sem possuir o domínio de atendimento.
_Evitar_: Backend de atendimento, Chatwoot, provedor de negócio

**Sessão de dispositivo**:
Credencial e estado técnico de uma conexão WhatsApp pertencente a uma inbox.
_Evitar_: Conversa, inbox

**Comando de transporte**:
Instrução idempotente do hub para o gateway executar uma operação técnica, identificada antes de qualquer tentativa.
_Evitar_: Mensagem de domínio, despacho

**Evento de transporte**:
Fato técnico persistido pelo gateway e entregue pelo menos uma vez ao hub, como mensagem recebida, receipt ou mudança de sessão.
_Evitar_: Evento de negócio, webhook descartável

**Lease de sessão**:
Posse temporária e exclusiva de uma sessão por uma réplica do gateway, protegida por token de fencing.
_Evitar_: Shard fixo, lock permanente
