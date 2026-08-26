# ADR-006 — Retenção legal, anonimização e legal hold (LGPD)

- **Status:** aceito
- **Data:** 26/08/2026
- **Decisão:** Diretrizes para cumprimento da LGPD, prazos de retenção de dados cadastrais e financeiros, procedimento de anonimização (Soft Delete + Hash/Scramble de PII) e controle por Legal Hold

## Contexto

Como um sistema SaaS para gestão de estabelecimentos de beleza e bem-estar (Caldas Gestão), a plataforma lida diariamente com Dados Pessoais e Pessoais Sensíveis (PII - *Personally Identifiable Information*) de clientes, profissionais e colaboradores dos estabelecimentos contratantes (Tenants).

A Lei Geral de Proteção de Dados (LGPD - Lei nº 13.709/2018) estabelece direitos aos titulares, como a solicitação de eliminação de dados pessoais (Art. 18, VI). No entanto, o mesmo marco legal prevê exceções explícitas no Art. 16, I, permitindo o conservamento de dados para o cumprimento de obrigação legal ou regulatória pelo controlador.

Na legislação brasileira (Código Civil, Código Tributário Nacional e Lei do Salão Parceiro), documentos e registros fiscais, contábeis e transacionais devem ser conservados pelo prazo mínimo de **5 (cinco) anos**.

Além disso, em situações de litígio judicial, auditorias fiscais ou investigações ativas, a eliminação automatizada ou a pedido do usuário pode causar destruição de provas, gerando penalidades legais severas para a empresa.

## Decisão

Estabelecer a arquitetura de retenção legal, anonimização e congelamento de expurgo (*Legal Hold*) conforme os pilares detalhados a seguir.

### 1. Prazos de Retenção Legal

- **Dados Cadastrais Inativos / Contas Encerradas:** Mantidos por até 5 anos após a solicitação de exclusão ou encerramento do contrato, estritamente para fins de defesa judicial e cumprimento tributário.
- **Histórico Transacional e Financeiro:** Lançamentos, comandas pagas, notas fiscais e recebimentos possuem retenção mínima obrigatória de 5 anos.
- **Logs de Acesso e Auditoria:** Mantidos por 6 meses (conforme Marco Civil da Internet - Lei 12.965/2014) a 5 anos para logs de segurança transacional.

### 2. Procedimento de Expurgo e Anonimização (Soft Delete + PII Scramble)

A exclusão direta (*hard delete*) de registros transacionais é **proibida** em tabelas operacionais e financeiras para preservar a integridade referencial dos relatórios consolidados do tenant.

Quando um titular solicitar a exclusão de seus dados ou o prazo de retenção legal expirar:
1. **Fase 1 — Soft Delete:** O registro recebe flag de deleção lógica (`deleted_at`). A interface oculta o registro nas rotinas operacionais.
2. **Fase 2 — Expurgo/Anonimização Irreversível (PII Scramble):**
   - Dados identificáveis (Nome, CPF, E-mail, Telefone, Endereço, Data de Nascimento) são substituídos por hashes unidirecionais não reversíveis ou valores fictícios mascarados (ex: `ANON-482910@deleted.local`, CPF `000.***.***-00`).
   - Registros de comandas e pagamentos permanecem intactos nos aspectos numéricos (valores em centavos, datas, impostos), mas desvinculados de PII identificável do cliente/profissional.

### 3. Mecanismo de Legal Hold (Congelamento de Expurgo)

Para impedir a exclusão ou anonimização automatizada de dados em caso de processos judiciais, investigações ou auditorias em andamento:

- Será implementada a flag/entidade de **Legal Hold** no nível do Tenant ou do Registro (`is_legal_hold = true` ou relação `legal_holds`).
- **Comportamento do sistema:** Enquanto a flag de Legal Hold estiver ativa para um tenant ou titular:
  - Todas as rotinas automatizadas de expurgo e tarefas agendadas (*cron jobs*) de anonimização serão sumariamente bloqueadas para aquele registro/tenant.
  - Solicitações manuais de exclusão via atendimento LGPD retornarão aviso informando a suspensão motivada por obrigação de retenção/ordem judicial.
- Todo acionamento ou remoção de Legal Hold deve ser registrado em log imutável de auditoria com justificativa legal e identificador do operador responsável.

## Alternativas consideradas

### Hard Delete direto (Deleção Física)
Rejeitado. Destruiria a integridade referencial do módulo financeiro e de relatórios contábeis históricos do tenant, além de violar obrigações legais de retenção fiscal de 5 anos.

### Anonimização Imediata ao solicitar exclusão
Rejeitado. Desrespeitaria a prerrogativa do controlador de manter os dados necessários para defesa em eventuais ações trabalhistas, cíveis ou tributárias durante o prazo prescricional.

## Consequências

### Positivas
- Total conformidade com os artigos 16 e 18 da LGPD.
- Proteção jurídica da empresa e dos tenants contra destruição inadequada de provas em litígios (via Legal Hold).
- Preservação do histórico e métricas financeiras sem violar a privacidade do titular (dados anonimizados não são considerados dados pessoais sob a LGPD).

### Custos e riscos
- Complexidade técnica no desenvolvimento dos rotinas de anonimização (PII Scramble) sem quebrar relatórios.
- Necessidade de governança estrita sobre quem pode ativar/desativar a flag de Legal Hold.
