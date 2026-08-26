# Plano de Implementação — Drag-to-Select e Responsividade Mobile da Agenda (Calendar)

- **Status:** Proposto
- **Data:** 26/08/2026
- **Alvo:** Implementar a funcionalidade de clique e arraste (Drag-to-Select) para agendamentos/bloqueios e garantir responsividade e usabilidade mobile de classe mundial na Agenda.

---

## 🎯 Objetivos de UX e Funcionalidade

1. **Drag-to-Select (Clique e Arraste no Time Grid):**
   - Permitir que o usuário clique e arraste o cursor/toque sobre um intervalo na grade temporal da agenda.
   - Exibir feedback visual em tempo real (retângulo semi-transparente destacando a seleção e exibindo o horário inicial, final e duração total, ex: `09:15 – 10:45 • 1h 30m`).
   - Ao soltar o clique/toque, exibir o popover de decisão rápida:
     - 🗓️ **Novo Agendamento:** Abre o formulário de agendamento com data, `starts_at` e `ends_at` pré-configurados.
     - 🔒 **Travar Horário / Novo Bloqueio:** Abre o formulário de bloqueio de agenda pré-preenchendo início, fim e o profissional da coluna.

2. **Responsividade Mobile e Usabilidade Touch (Mobile First & Tablet):**
   - Em telas pequenas (`< 768px`), alternar a visualização para **Dia Único ou Colunas com Scroll Horizontal Suave/Tabs de Profissionais**, evitando que as colunas fiquem comprimidas.
   - Garantir área de toque adequada (mínimo 44px de altura) nos slots e botões de ação.
   - Posicionar botão flutuante e atalhos rápidos ao alcance do polegar.

---

## 🏗️ Fases de Execução

### Fase 1: Mecanismo de Drag-to-Select (`resources/js/components/calendar/index.tsx`)
- Implementar estado de seleção de intervalo (`isDragging`, `dragStart`, `dragCurrent`, `dragProfessionalId`).
- Eventos de mouse e touch (`onMouseDown`, `onMouseMove`, `onMouseUp`, `onTouchStart`, `onTouchMove`, `onTouchEnd`).
- Renderizar a sobreposição visual da seleção e o popover de escolha (**Agendamento** vs **Bloqueio**).

### Fase 2: Integração de Modais e Pré-preenchimento (`resources/js/pages/calendar/index.tsx`)
- Conectar o resultado da seleção ao modal de Novo Agendamento e ao modal de Bloqueio de Horário, preenchendo automaticamente `starts_at`, `ends_at` e `professional_id`.

### Fase 3: Layout Responsivo Mobile
- Ajustes CSS/Tailwind no `WeekCalendar` e `DayAgenda` com breakpoints `sm:`, `md:`, `lg:` e alternador rápido de profissionais para telas móbile.

### Fase 4: Testes e Validação
- Executar `vendor/bin/pint --format agent`, `vendor/bin/phpstan analyse --memory-limit=512M`, `npm run lint:check`, `npm run types:check`, `npm run build` e atualizar o grafo do Graphify.
