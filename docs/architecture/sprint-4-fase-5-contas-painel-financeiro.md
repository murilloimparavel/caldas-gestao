# Sprint 4 - Fase 5: Contas a Pagar/Receber e Painel Financeiro Consolidado

**Status**: Concluído  
**Data**: 25/08/2026  
**Stack**: Laravel 13, Inertia.js v3, React 19, Tailwind CSS v4, Wayfinder, Pest PHP  
**Contexto**: Fase 5 - Módulo Financeiro, Estoque e Operações

---

## 1. Visão Geral e Objetivos

A **Sprint 4 da Fase 5** entrega o módulo central de gestão de despesas e receitas do Caldas Gestão, composto pelo controle de obrigações financeiras (`FinancialObligation` - Contas a Pagar e a Receber), fluxo de liquidação e conciliação financeira, controle de concorrência e o **Painel Financeiro Consolidado** (`FinanceDashboard`).

Com a conclusão desta sprint, a **Fase 5 (Módulo Financeiro, Estoque e Operações)** atinge 100% de sua completude operacional e arquitetural, integrando:
1. **Caixa Operacional**: Abertura, suprimentos, sangrias, fechamento e conferência cega/assistida;
2. **Controle de Estoque**: Movimentações (entrada, saída, perda, ajuste) com cálculo de custo e rastreabilidade;
3. **Comissões de Profissionais**: Cadastro flexível de regras, apuração automática por item de comanda e liquidação periódica;
4. **Contas a Pagar / Receber e Painel Financeiro**: Gestão completa de obrigações financeiras e indicadores consolidados de saúde e projeção de caixa.

---

## 2. Invariantes de Domínio e Regras de Negócio

### 2.1 Obrigações Financeiras (`FinancialObligation`)
1. **Tipos de Lançamento**:
   - `payable`: Despesas e contas a pagar para fornecedores, concessionárias, aluguel, infraestrutura, folha, etc.
   - `receivable`: Contas a receber de clientes, contratos corporativos, pacotes e outras receitas diretas.
2. **Status e Transições de Estado**:
   - `pending`: Lançamento cadastrado com vencimento agendado. Pode ser editado, liquidado ou cancelado.
   - `paid`: Lançamento liquidado com data de pagamento (`paid_date`) e forma de pagamento (`payment_method`). Imutável contra edições diretas ou cancelamentos.
   - `cancelled`: Lançamento cancelado para fins de trilha histórica. Não impacta saldos projetados nem pode ser liquidado.
3. **Controle de Concorrência e Multi-Tenancy**:
   - Isolamento obrigatório por `tenant_id` e `unit_id`.
   - Uso de `lock_version` para evitar sobrescritas concorrentes e inconsistências de liquidação simultânea.
   - Trilha de auditoria obrigatória em `audit_events` com chave `lock_version` e metadados sanitizados por `PayloadGovernance`.
4. **Valores Monetários**:
   - Todos os valores são armazenados como inteiros em centavos (`amount_cents > 0`), prevenindo erros de ponto flutuante.

### 2.2 Painel Financeiro (`FinanceDashboard`)
- **Saldo Físico em Caixa**: Total consolidado em centavos dos turnos de caixa atualmente abertos na unidade (`expected_amount_cents`).
- **Saldo Projetado do Mês**: Saldo atual em caixa + Total de receitas a receber pendentes no mês corrente - Total de despesas a pagar pendentes no mês corrente.
- **Hoje**: A Pagar Hoje e A Receber Hoje com filtro estrito na data corrente.
- **Realizado no Mês**: Despesas pagas no mês vs receitas recebidas no mês.
- **Contas em Atraso (Overdue)**: Alerta imediato de inadimplência/pendência com contagem e volume financeiro total vencido (`due_date < hoje` e `status = pending`).
- **Próximos Vencimentos**: Fila prioritária de contas a vencer nos próximos dias com acesso direto à liquidação.
- **Últimas Liquidações**: Histórico recente de pagamentos e recebimentos confirmados.

---

## 3. Arquitetura Backend

### 3.1 Migrations
- **`2026_08_26_020001_create_financial_obligations_table.php`**:
  - Chaves UUIDv7, soft deletes, índices compostos multi-tenant, foreign keys para `tenants`, `units`, `categories`, `suppliers`, `customers`, e constraints Postgres `CHECK` para tipo, status e `amount_cents > 0`.

### 3.2 Modelos e Relacionamentos
- **`App\Models\FinancialObligation`**:
  - Relacionamentos: `tenant()`, `unit()`, `category()`, `supplier()`, `customer()`.
  - Casts: `due_date => date`, `paid_date => date`, `amount_cents => integer`, `lock_version => integer`.

### 3.3 RBAC e Governança
- **`OwnerPermissionCatalog`**: Adicionadas permissões `'financial.view'`, `'financial.manage'`, `'financial.settle'`.
- **`PayloadGovernance`**: Incluídas as chaves de metadados financeiros nos catálogos de auditoria e outbox (`financial_obligation_id`, `supplier_id`, `due_date`, `paid_date`, `payment_method`).

### 3.4 Actions Operacionais (`app/Actions/Finance/Transactions/`)
- `CreateFinancialObligation`: Criação transacional com validação de escopo de unidade para categorias e clientes, e de tenant para fornecedores.
- `UpdateFinancialObligation`: Atualização com trava otimista (`lock_version`), validação de status pendente e `lockForUpdate()`.
- `SettleFinancialObligation`: Liquidação com registro de `paid_date`, `payment_method` e disparo de evento `financial_obligation.settled`.
- `CancelFinancialObligation`: Cancelamento com trava de concorrência e guarda contra obrigações já quitadas.

### 3.5 Controllers e Rotas
- `FinancialObligationController`: Métodos `index`, `show`, `store`, `update`, `settle`, `cancel`.
- `FinanceDashboardController`: Endpoint do painel consolidado com indicadores de saúde financeira e fluxo mensal.
- Rotas registradas em `routes/web.php` sob o middleware `tenant.context`.

---

## 4. Frontend e Experiência do Usuário (UI/UX)

- **`resources/js/pages/finance/transactions/index.tsx`**:
  - Filtros por Tipo (Todos, A Pagar, A Receber), Status (Todos, Pendentes, Vencidos, Liquidados, Cancelados), Categoria, Período e Busca textual;
  - Cards de resumo no topo com métricas em tempo real;
  - Tabela com badges coloridos, indicativo visual de vencimento/atraso e dropdown/botões de ação direta;
  - Modais modulares para Novo Lançamento (com suporte a fornecedores e clientes), Edição, Liquidação e Cancelamento;
  - Suporte completo a idempotência e feedback de concorrência.
- **`resources/js/pages/finance/dashboard.tsx`**:
  - Visão executiva com cards de KPI, saldo projetado, alertas de contas vencidas, resumo do realizado no mês, lista de próximos vencimentos e histórico das últimas liquidações.
- **`resources/js/components/app-sidebar.tsx`**:
  - Habilitação dos itens "Painel Financeiro" e "Contas a Pagar/Receber" no grupo "Financeiro".

---

## 5. Garantia de Qualidade e Testes

- **`tests/Feature/FinancialObligationTest.php`**:
  - Criação de contas a pagar e contas a receber;
  - Validações de payload e valores positivos;
  - Atualização com incremento de `lock_version`;
  - Rejeição de mutações concorrentes com conflito HTTP 409;
  - Liquidação de obrigação com alteração de status e registro de método de pagamento;
  - Guarda contra liquidação ou cancelamento de obrigações já quitadas;
  - Isolamento estrito entre múltiplos tenants;
  - Cálculo e renderização das métricas do Painel Financeiro.

Resultados da suíte de testes: **100% de aprovação (248 testes executados, 0 falhas)**.
