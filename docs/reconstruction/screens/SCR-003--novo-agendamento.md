# SCR-003 — Diálogo de novo agendamento

## Contexto

- **Entrada:** Agenda → Novo
- **Saída sem persistência:** Cancelar/Fechar
- **Saídas potencialmente persistentes:** Salvar e Criar comanda — não testadas
- **Evidência:** EV-001; BEH-003

## Campos e controles observados

| Campo | Tipo | Estado inicial observado |
|---|---|---|
| Cliente | busca/combobox | vazio |
| Data | date input | data atual da agenda |
| Status | combobox | Confirmado |
| Cor | combobox | Padrão |
| Serviço | combobox | vazio |
| Profissional | combobox | pré-selecionado pelo contexto |
| Horário | combobox | pré-selecionado pelo contexto/relógio |
| Duração | combobox | 15 min no estado observado |
| Enviar lembrete | switch | ligado |
| Encaixar agendamento | switch | desligado |
| Recorrência | combobox | não se repete |
| Observações | textarea | vazio |

O diálogo também oferece uma busca rápida de cliente e uma linha de item de agendamento removível; o botão de exclusão do único item estava desabilitado.

## Opções observadas

- status visíveis: Confirmado, Não confirmado, Aguardando e Cancelado;
- recorrência: diária, semanal, a cada 14/21/28 dias e mensal no mesmo dia;
- durações observadas no seletor atual: 10, 15 e 20 minutos; a lista completa não foi confirmada;
- cores incluem padrões por status e personalizações do tenant.

## Comportamento

- abrir o diálogo não persistiu registro observável;
- Cancelar fechou sem alterar rota ou agenda;
- nenhum campo foi modificado e nenhum caso de validação foi disparado;
- `Salvar` e `Criar comanda` permanecem Unknown por segurança.

## Critérios de aceitação para reconstrução

- Abrir e cancelar um novo agendamento não deve criar rascunho operacional nem disparar mensagens.
- Salvar deve validar cliente, unidade, profissional, serviço, duração, disponibilidade e timezone no servidor.
- Duas solicitações concorrentes para o mesmo slot não podem gerar dupla reserva sem política explícita de encaixe.
- O envio de lembrete deve exigir consentimento/canal válido e ser processado de forma idempotente.
- Recorrência deve criar uma série auditável com política de exceção por ocorrência.
- Criar comanda deve possuir transação/compensação definida; não deve deixar agendamento e comanda incoerentes.

## Desconhecidos

- obrigatoriedade e validação de cada campo;
- busca/criação inline de cliente;
- múltiplos serviços e profissionais;
- cálculo automático de horários/duração/preço;
- política de conflito e encaixe;
- efeito de cada status;
- efeitos de Salvar/Criar comanda;
- formato de recorrência e edição de série.
