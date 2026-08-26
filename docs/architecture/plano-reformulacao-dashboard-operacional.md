# Plano de Implementação — Reformulação do Dashboard Operacional (Visão de Hoje)

- **Status:** Proposto
- **Data:** 26/08/2026
- **Alvo:** Reformulação completa da tela de Dashboard (`resources/js/pages/dashboard.tsx`) para prover uma central operacional e analítica inspirada nos melhores padrões do setor (Belasis/SaaS Beleza).

---

## 🎯 Visão Geral do Novo Dashboard

O novo Dashboard será um painel analítico dinâmico, responsivo e em tempo real, fornecendo aos gestores e proprietários métricas operacionais, financeiras e de equipe com suporte a filtros por período.

---

## 🏗️ Arquitetura e Fases de Execução

### Fase 1: Agregador de Métricas e Data Provider Backend (Laravel)

1. **Query Parameters & Filtro de Período (`DashboardRequest`):**
   - Aceitar `start_date`, `end_date` e presets: `today`, `7d`, `30d`, `this_month`, `custom`.
   - Calcular automaticamente o **período anterior de igual duração** para comparações percentuais (ex: últimos 14 dias comparados aos 14 dias precedentes).

2. **Action / Service `GetDashboardSnapshot` (`app/Actions/Dashboard/GetDashboardSnapshot.php`):**
   - **Vendas Totais & Vendas do Dia:** Total acumulado em centavos, vendas de hoje e variação % em relação ao período anterior.
   - **Agendamentos Totais & Crescimento:** Total de agendamentos e taxa de crescimento %.
   - **Comandas & Taxa de Conversão:** Total de comandas e percentual de agendamentos convertidos em comandas faturadas.
   - **Série Temporal de Visitas (`visits_trend`):** Array de contagem por dia no intervalo selecionado para gráficos de barras/linha.
   - **Distribuição de Status (`status_breakdown`):** Contagem e percentual por status (Confirmado, Concluído, Cancelado, No-show).
   - **Ticket Médio:** Ticket médio no período atual vs período anterior.
   - **Desempenho por Profissional (`professionals_performance`):** Lista com `professional_id`, `name`, `avatar_url`, `total_appointments`, `variation_percentage`, `average_ticket_cents`.
   - **Vendas por Categoria (`sales_by_category`):** Total em centavos e percentual para Serviços, Produtos e Pacotes.
   - **Funil de Agendamentos (`appointment_funnel`):** Totais por etapa (Agendados → Confirmados → Atendidos → Faturados).
   - **Mapa de Calor de Ocupação (`schedule_heatmap`):** Matriz de intensidade por faixa horária (8h às 19h) e dia da semana (segunda a sábado).

---

### Fase 2: Componentes UI de Gráficos e Métricas Frontend (React)

1. **Cabeçalho com Filtro de Período (`DashboardHeader`):**
   - Saudação personalizada (*"Olá, {nome}"*).
   - Componente de seleção de intervalo de datas (Botoes de preset + Date Range Picker) integrado às rotas do Inertia via `router.get`.

2. **Top KPI Cards com Sparklines (`KpiCard`):**
   - **Card Vendas Totais:** Valor principal em destaque, sub-informação de vendas de hoje, badge de variação % colorida (verde/vermelho) e sparkline SVG.
   - **Card Agendamentos:** Total de agendamentos, taxa de crescimento % e sparkline de linha.
   - **Card Comandas:** Total de comandas e badge de taxa de conversão %.

3. **Gráficos Visuais de Tendência e Status (usando Recharts):**
   - `VisitsTrendChart`: Gráfico de barras responsivo com eixos de data e tooltip.
   - `AppointmentStatusDonut`: Gráfico de rosca/donut mostrando proporção por status dos agendamentos com legenda interativa.

4. **Tabela de Profissionais e Vendas por Categoria:**
   - `ProfessionalPerformanceTable`: Lista com avatar, nome do profissional, quantidade de atendimentos, badge de variação % e ticket médio por atendimento.
   - `SalesCategoryBreakdown`: Visualização em barra de progresso / percentuais (Serviços, Produtos, Pacotes).

5. **Mapa de Calor de Ocupação (`ScheduleHeatmap`):**
   - Grid visual de intensidade de cores (das 8h às 19h, segunda a sábado) facilitando a identificação de horários de pico e ociosidade na agenda.

---

### Fase 3: Montagem do Dashboard Responsivo (`resources/js/pages/dashboard.tsx`)

Layout estruturado em grid responsivo:
- **Linha 1:** Cabeçalho com Saudação e Filtro de Período.
- **Linha 2:** Grid de 3 Top KPI Cards (Vendas Totais, Agendamentos, Comandas).
- **Linha 3:** Bloco Principal de Tendência de Visitas + Donut de Agendamentos por Status.
- **Linha 4:** Bloco de Ticket Médio + Tabela de Atendimentos por Profissional.
- **Linha 5:** Vendas por Categoria + Funil de Agendamentos + Mapa de Calor de Ocupação.

---

### Fase 4: Testes de Feature e Validação

1. **Suíte de Testes Backend (`tests/Feature/DashboardMetricsTest.php`):**
   - Testar o cálculo correto de métricas em `GetDashboardSnapshot` com dados fictícios de vendas e agendamentos.
   - Testar filtro por intervalos de datas e comparação percentual com período anterior.
2. **Gates de Qualidade & Build:**
   - Formatação via `vendor/bin/pint --format agent`.
   - PHPStan 0 erros (`vendor/bin/phpstan analyse --memory-limit=512M`).
   - ESLint & TypeScript 0 erros (`npm run lint:check` e `npm run types:check`).
   - Build do Vite (`npm run build`).
   - Reconstrução do grafo do Graphify (`graphify update .`).
