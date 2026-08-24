# SCR-002 — Agenda semanal

## Contexto

- **Rota:** `/calendar`
- **Papel:** desconhecido
- **Viewport:** 1710 × 929
- **Precondição:** sessão autenticada, pelo menos um profissional visível
- **Evidência:** EV-001; UX-003, BEH-003

## Objetivo

Consultar disponibilidade e compromissos por profissional e período, filtrar a grade e iniciar ações operacionais.

## Hierarquia e controles

- navegação temporal anterior/próximo;
- título contextual do período;
- visualização diária/semanal/mensal;
- filtros por profissional e status;
- ações para bloquear horários e agrupar agendamentos;
- configurações da agenda;
- criação de novo agendamento;
- grade temporal com dias, profissional, intervalos e cartões de agendamento.

## Grade observada

- visual semanal com sete dias;
- intervalos de 10 minutos na configuração/visualização atual;
- coluna/agrupamento por profissional;
- cartões apresentam horário, cliente, serviço e ação contextual;
- períodos não disponíveis são visualmente diferenciados.

## Filtros observados

- seleção/desmarcação de profissionais;
- status: Confirmado, Não confirmado, Aguardando, Cancelado, Faturado e Bloqueado;
- ação de retorno ao padrão.

## Configurações observadas

### Geral

- filtrar profissionais pelo serviço selecionado;
- decidir se cancelamentos mantêm o horário bloqueado;
- aviso explícito de que as mudanças afetam todos os usuários.

### Visualização

- quantidade de colunas/profissionais visíveis;
- intervalo temporal da grade;
- status padrão de novo agendamento;
- exibição de avatares.

### Cores

- cores padrão associadas a status;
- criação de cores personalizadas;
- tabela com nome, cor, status e ações.

## Estados

| Estado | Trigger | Resultado | Evidência |
|---|---|---|---|
| semanal populado | entrada na rota | grade e cartões | EV-001 |
| dropdown de visualização | acionar Visualização | diário/semanal/mensal | EV-001 |
| filtro aberto | acionar Filtrar | profissionais e status | EV-001 |
| ações abertas | acionar Ações | bloquear horários/agrupar | EV-001 |
| configuração aberta | botão de engrenagem | diálogo tabulado | EV-001 |
| novo aberto | botão Novo | diálogo de agendamento | EV-001 |
| empty/loading/error | não testado | Unknown | — |

## Riscos de UX/acessibilidade

- cartões da grade agrupam múltiplos itens em estruturas ARIA difíceis de navegar;
- botões apenas com ícone podem não ter nome acessível;
- status de cor precisa de redundância textual;
- conteúdo da agenda contém PII e requer ocultação em notificações, logs e screenshots;
- uma configuração global aparece no mesmo contexto de operação diária, exigindo RBAC e confirmação clara.

## Tratamento independente proposto

- virtualização acessível da grade para grandes agendas;
- visão alternativa em lista para teclado/leitor de tela;
- cores com rótulos e padrões, nunca como único indicador;
- política explícita de timezone da unidade;
- bloqueio otimista/pessimista contra dupla reserva;
- filtros persistidos por usuário, configurações globais separadas por permissão.
- no celular, visão diária/lista como padrão; semana completa não deve ser miniaturizada para caber;
- no tablet portrait, considerar três dias; semana completa apenas com largura mínima legível.

## Critérios de aceitação

- Dado um profissional e período, a grade apresenta disponibilidade no timezone da unidade.
- Quando o usuário muda de visualização, o período e filtros permanecem coerentes.
- Quando um status é desmarcado, somente cartões desse status são ocultados, sem alterar dados.
- Configurações globais só podem ser modificadas por papel autorizado e devem gerar auditoria.
- A agenda deve possuir alternativa navegável por teclado e não depender exclusivamente de cor.

## Desconhecidos

- conflito simultâneo e política de encaixe;
- carregamento incremental e limites de período;
- drag-and-drop e redimensionamento;
- recorrência, exceções e timezone;
- criação/edição mobile, aparelho físico, landscape e impressão;
- notificações geradas por cada mudança.
