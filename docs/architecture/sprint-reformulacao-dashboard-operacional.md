# Sprint — Reformulação do Dashboard Operacional (Visão de Hoje)

## 🎯 Objetivo
Executar o plano definido em [`plano-reformulacao-dashboard-operacional.md`](file:///Users/murilloalves/Projects/caldas-gestao/docs/architecture/plano-reformulacao-dashboard-operacional.md), transformando o Dashboard (`resources/js/pages/dashboard.tsx`) em um painel analítico dinâmico, responsivo e visualmente rico, inspirado no padrão de referência Belasis.

---

## 📦 Entregas Realizadas

### 1. Backend, Agregador de Métricas e Requests
- **Action [`GetDashboardSnapshot.php`](file:///Users/murilloalves/Projects/caldas-gestao/app/Actions/Dashboard/GetDashboardSnapshot.php):**
  - Cálculo dinâmico de período anterior equivalente para aferição de variações percentuais.
  - Agregação de Vendas Totais, Vendas do Dia, Variação %, Agendamentos Totais, Taxa de Crescimento %, Comandas e Taxa de Conversão %.
  - Série temporal `visits_trend` por dia.
  - Distribuição `status_breakdown` (Confirmados, Concluídos, Cancelados, No-show).
  - Tabela `professionals_performance` agrupada por profissional (com avatar, contagem de atendimentos, variação % e ticket médio).
  - Vendas por Categoria (`sales_by_category`: Serviços, Produtos, Pacotes).
  - Funil de Agendamentos (`appointment_funnel`) e Mapa de Calor da Agenda (`schedule_heatmap`: horários 8h-19h em dias úteis/sábados).
- **Form Request & Controller:**
  - [`DashboardRequest.php`](file:///Users/murilloalves/Projects/caldas-gestao/app/Http/Requests/DashboardRequest.php) e [`DashboardController.php`](file:///Users/murilloalves/Projects/caldas-gestao/app/Http/Controllers/DashboardController.php) aceitando `preset` e datas customizadas.

### 2. Frontend React (`resources/js/features/dashboard/components/`)
- [`dashboard-header.tsx`](file:///Users/murilloalves/Projects/caldas-gestao/resources/js/features/dashboard/components/dashboard-header.tsx): Saudação *"Olá, {nome}"*, seletor de período (*Hoje*, *7 dias*, *30 dias*, *Este mês*, *Customizado*) via Inertia `router.get` e botão de refresh.
- [`top-kpi-cards.tsx`](file:///Users/murilloalves/Projects/caldas-gestao/resources/js/features/dashboard/components/top-kpi-cards.tsx): 3 cards de KPI com sparklines SVG responsivos e badges de tendência.
- [`visits-trend-chart.tsx`](file:///Users/murilloalves/Projects/caldas-gestao/resources/js/features/dashboard/components/visits-trend-chart.tsx): Gráfico de barras de tendência diária.
- [`status-donut-chart.tsx`](file:///Users/murilloalves/Projects/caldas-gestao/resources/js/features/dashboard/components/status-donut-chart.tsx): Donut chart SVG com legenda percentual por status.
- [`professional-performance-table.tsx`](file:///Users/murilloalves/Projects/caldas-gestao/resources/js/features/dashboard/components/professional-performance-table.tsx): Tabela com avatar do profissional, total de atendimentos, variação % e ticket médio.
- [`sales-category-breakdown.tsx`](file:///Users/murilloalves/Projects/caldas-gestao/resources/js/features/dashboard/components/sales-category-breakdown.tsx): Distribuição percentual em barras/cards por Serviços, Produtos e Pacotes.
- [`schedule-heatmap.tsx`](file:///Users/murilloalves/Projects/caldas-gestao/resources/js/features/dashboard/components/schedule-heatmap.tsx): Heatmap de ocupação da agenda por faixa horária.
- Layout de grid responsivo integrado em [`resources/js/pages/dashboard.tsx`](file:///Users/murilloalves/Projects/caldas-gestao/resources/js/pages/dashboard.tsx).

### 3. Testes de Feature (`tests/Feature/DashboardMetricsTest.php`)
- Cobertura em Pest testando cálculo de métricas agregadas, variação entre períodos e filtros por presets de data (2 testes passados, 67 asserções).

---

## 🧪 Suíte de Validação
- **Pest:** 310 testes executados (**285 aprovados**, 25 skipped, **0 falhas**) com 2.197 asserções.
- **PHPStan:** **0 erros**.
- **ESLint & TypeScript:** **0 erros**.
- **Vite Build:** Compilação de produção executada em 6.15s com sucesso.
- **Graphify:** Grafo atualizado com 4.866 nós e 11.652 arestas.
