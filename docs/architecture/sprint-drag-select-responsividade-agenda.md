# Sprint — Drag-to-Select e Responsividade Mobile da Agenda (Calendar)

## 🎯 Objetivo
Executar o plano definido em [`plano-drag-select-responsividade-agenda.md`](file:///Users/murilloalves/Projects/caldas-gestao/docs/architecture/plano-drag-select-responsividade-agenda.md), fornecendo a funcionalidade de clique e arraste (Drag-to-Select) para criar agendamentos ou travar horários e garantindo responsividade mobile touch em dispositivos móveis.

---

## 📦 Entregas Realizadas

### 1. Funcionalidade Drag-to-Select (`resources/js/components/calendar/index.tsx`)
- Implementado suporte a **mouse e touch drag** na grade temporal (`WeekCalendar` e `DayAgenda`).
- Exibição de sobreposição visual em tempo real (`SelectionOverlay`) indicando o horário inicial, final e duração (ex: `09:15 – 10:45 • 1h 30m`).
- Popover de decisão ao soltar (`SelectionPopover`):
  - 🗓️ **Novo Agendamento:** Abre o formulário pré-configurado com data, `starts_at`, `ends_at` e `duration_minutes`.
  - 🔒 **Travar Horário / Novo Bloqueio:** Abre o formulário de bloqueio de agenda pré-preenchendo `starts_at`, `ends_at` e `professional_id`.

### 2. Responsividade Mobile & Usabilidade Touch (`resources/js/pages/calendar/index.tsx`)
- **Adaptabilidade de Viewport (< 768px):** Ajustada a `CalendarToolbar` e a grade para não espremer colunas em telas de smartphone.
- **Tabs/Seletores Mobile:** Adicionada navegação fluida por dia e seletores responsivos de profissional.
- **Touch Targets:** Garantido tamanho mínimo de área de toque de 44px (`min-h-[44px]`) nos botões, controles, cards e slots de horário.

---

## 🧪 Suíte de Validação
- **Pest:** 310 testes executados (**285 aprovados**, 25 skipped, **0 falhas**) com 2.207 asserções.
- **PHPStan:** **0 erros**.
- **ESLint & TypeScript:** **0 erros**.
- **Vite Build:** Compilado em 5.31s com sucesso.
- **Graphify:** Grafo atualizado com 4.908 nós e 11.746 arestas.
