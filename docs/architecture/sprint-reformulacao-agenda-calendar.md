# Sprint — Reformulação da Agenda Operacional (Calendar)

## 🎯 Objetivo
Executar o plano definido em [`plano-reformulacao-agenda-calendar.md`](file:///Users/murilloalves/Projects/caldas-gestao/docs/architecture/plano-reformulacao-agenda-calendar.md), alinhando a interface e UX da Agenda aos melhores padrões do mercado (referência Belasis).

---

## 📦 Entregas Realizadas

### 1. Backend ([`CalendarController.php`](file:///Users/murilloalves/Projects/caldas-gestao/app/Http/Controllers/CalendarController.php))
- Atualizada a lista `options.professionals` para carregar `avatar_path` e expor `avatar_url` para o frontend Inertia.
- Garantido o retorno dos bloqueios de agenda (`schedule_blocks`) e regras de disponibilidade no payload.

### 2. Frontend React (`resources/js/components/calendar/` e `resources/js/pages/calendar/`)
- **Grade Temporal Constante (Time Grid Always Visible):** A agenda renderiza **sempre a régua lateral de horários** (07:00 às 21:00) e as colunas por dia/profissional, mesmo sem agendamentos (eliminando telas de estado vazio total).
- **Header de Profissionais com Foto/Avatar:** Implementado o componente `ProfessionalAvatarHeader` exibindo a foto (`avatar_url`), iniciais e nome do profissional acima de cada coluna.
- **Blocos Visuais "Ocupado":** Estilização de intervalos de indisponibilidade em tom neutro slate/cinza (`08:00 - 12:00 Ocupado`).
- **Cards de Agendamento:** Formatação legível dos agendamentos sobrepostos na linha do tempo com horário (`09:15 - 10:15`), cliente, lista de serviços e status.
- **Agendamento Rápido por Clique:** Clique em qualquer slot livre da grade abre diretamente o modal de **Novo Agendamento** com a data, o horário e o profissional pré-preenchidos.

### 3. Testes de Feature (`tests/Feature/CalendarOperationsTest.php`)
- Cobertura em Pest testando a presença de `avatar_url` na lista de opções e operabilidade das rotas do calendário (14 testes de calendário passados).

---

## 🧪 Suíte de Validação
- **Pest:** 310 testes executados (**285 aprovados**, 25 skipped, **0 falhas**) com 2.207 asserções.
- **PHPStan:** **0 erros**.
- **ESLint & TypeScript:** **0 erros**.
- **Vite Build:** Compilação de produção executada em 5.21s com sucesso.
- **Graphify:** Grafo atualizado com 4.888 nós e 11.710 arestas.
