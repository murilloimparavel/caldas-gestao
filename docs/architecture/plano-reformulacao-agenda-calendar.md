# Plano de Implementação — Reformulação e Alinhamento da Agenda (Calendar)

- **Status:** Proposto
- **Data:** 26/08/2026
- **Alvo:** Reformulação e polimento visual da Agenda (`resources/js/pages/calendar/index.tsx` e `resources/js/components/calendar/index.tsx`) inspirando-se na referência operacional Belasis.

---

## 🎯 Objetivos de UX/UI

1. **Grade de Horários Sempre Visível (Time Grid Constante):**
   - A agenda deve **sempre renderizar a grade temporal** (régua de horários à esquerda de 15/30 min das 07:00 às 21:00) e as colunas dos dias, mesmo quando não houver agendamentos cadastrados.
   - Eliminar a exibição de tela 100% vazia em substituição à grade.

2. **Header de Profissionais com Foto/Avatar:**
   - Exibir a foto de perfil (`avatar_url`) e o nome do profissional no topo de cada coluna de atendimento.

3. **Blocos Visuais de Indisponibilidade ("Ocupado"):**
   - Renderizar faixas em tom cinza suave ("Ocupado") demarcando os horários fora da jornada de trabalho ou bloqueios ativos do profissional.

4. **Cards de Agendamento Estilizados na Linha do Tempo:**
   - Exibição com horário (`09:15 - 10:15`), ícone global/origem, nome do cliente e serviços agrupados.
   - Cores sutis por status (Confirmado, Em atendimento, Concluído, Cancelado, No-show).

5. **Interatividade por Clique na Célula:**
   - Ao clicar em uma célula vazia da grade, abrir diretamente o modal de **Novo Agendamento** com data, horário e profissional pré-preenchidos.

---

## 🏗️ Fases de Execução

### Fase 1: Atualização da Grade Temporal (`resources/js/components/calendar/index.tsx`)
- Garantir que `WeekCalendar` e `DayAgenda` renderizem a régua lateral de horários e as colunas de dias incondicionalmente.
- Adicionar o header de profissional com o componente `Avatar` (foto de perfil + iniciais + nome).
- Renderizar blocos cinzas de "Ocupado" para horários de indisponibilidade ou fora de escala.

### Fase 2: Polimento Visual dos Cards e Navegação
- Atualizar a barra superior (`CalendarToolbar`) com navegação de período (*Semana passada*, *Esta semana*), alternadores de visão (*Dia*, *Semana*, *Mês*), botão de ação `+ Novo Agendamento` e botão flutuante `📅 Calendário`.
- Ajustar os cards de agendamento na grade temporal para exibir nome do cliente, serviços e status de forma legível e sem estouro de layout.

### Fase 3: Ações por Clique e Fluxo de Cadastro
- Implementar o handler `onCellClick(date, time, professionalId)` que abre a dialog de agendamento com as informações do slot clicado.

### Fase 4: Testes e Validação de Suíte
- Executar `php artisan test --compact --filter=Calendar`, `vendor/bin/phpstan analyse --memory-limit=512M`, `npm run lint:check`, `npm run types:check` e `npm run build`.
- Atualizar o grafo do Graphify (`graphify update .`).
