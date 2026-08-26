# Sprint 3 - Wave de Refinamento: Histórico de Consumo do Cliente, Sinergia Caixa-Contas e Feedback de Conflitos na Agenda

**Status**: Concluído  
**Data**: 25/08/2026  
**Stack**: Laravel 13, Inertia.js v3, React 19, Tailwind CSS v4, Wayfinder, Pest PHP  
**Contexto**: Wave de Refinamento - Sinergia Operacional, Experiência do Cliente e Prevenção de Conflitos

---

## 1. Visão Geral e Objetivos

A **Sprint 3 da Wave de Refinamento** conecta pontas operacionais essenciais do Caldas Gestão em três frentes:

1. **Histórico de Comandas & Consumo no Perfil do Cliente**:
   - Centraliza a visão de fidelidade, histórico de comandas e consumo de serviços/produtos diretamente na página de detalhes do cliente (`/customers/{customer}`).
   - Calcula indicadores agregados de consumo acumulado (`total_spent_cents` de comandas finalizadas) e contagem total de visitas/atendimentos (`total_visits`).

2. **Sinergia Caixa-Contas (Liquidação em Dinheiro com Turno de Caixa Aberto)**:
   - Integra o módulo financeiro de obrigações (`FinancialObligation`) com o fluxo de caixa físico (`CashShift` e `CashMovement`).
   - Ao liquidar uma obrigação a pagar ou a receber em dinheiro (`payment_method === 'cash'`), o sistema detecta atomicamente se o operador autenticado possui um caixa aberto na mesma unidade e gera o lançamento financeiro correspondente (`expense_outflow` para despesas e `supply` para receitas), atualizando o saldo esperado do turno (`expected_amount_cents`).

3. **Feedback Preventivo de Conflito de Horários na Agenda**:
   - Implementa validação client-side em tempo real no modal/sheet de criação e edição de agendamentos.
   - Identifica sobreposição de horários com outros agendamentos ativos do mesmo profissional ou bloqueios gerais de unidade/profissional, alertando o usuário visualmente com badge e descrição detalhada antes da submissão.

---

## 2. Invariantes de Domínio e Regras de Negócio

1. **Histórico e Métricas do Cliente**:
   - O relacionamento `$customer->sales()` carrega as comandas ordenadas por `created_at desc` com os respectivos itens (`items`) e categoria (`saleCategory:id,name`).
   - O total acumulado considera apenas comandas concluídas/pagas (`finalized`), evitando distorções com orçamentos ou rascunhos em andamento.
   - O total de visitas agrega atendimentos agendados confirmados, em atendimento e concluídos.

2. **Sinergia Transacional Caixa-Contas**:
   - Quando `payment_method === 'cash'`:
     - O sistema busca o turno de caixa aberto do operador (`opened_by_user_id === actor->id`, `status === 'open'`, `unit_id === current_unit_id`) com `lockForUpdate()`.
     - Para obrigações do tipo `payable`: gera `CashMovement` com `type = 'expense_outflow'` e motivo formatado `"Pagamento de despesa: " . $obligation->description`.
     - Para obrigações do tipo `receivable`: gera `CashMovement` com `type = 'supply'` e motivo formatado `"Recebimento de conta: " . $obligation->description`.
     - O saldo esperado do turno (`expected_amount_cents`) é atualizado atomicamente e o `lock_version` do turno é incrementado.
     - Eventos de auditoria emitidos: `financial_obligation.settled` e `cash_shift.moved`.
   - Quando `payment_method !== 'cash'` (ex.: `pix`, `card`, `bank_transfer`) ou se o operador não possuir caixa aberto, a liquidação da obrigação financeira é concluída normalmente sem movimentação física em espécie.

3. **Prevenção de Conflitos na Agenda**:
   - Intervalo avaliado: `[starts_at, starts_at + duration_minutes]`.
   - Considera agendamentos com status ativo (`draft`, `scheduled`, `confirmed`, `checked_in`, `in_service`, `completed`), ignorando cancelados (`cancelled`) e o próprio agendamento em edição.
   - Considera bloqueios de horário específicos do profissional selecionado ou bloqueios gerais da unidade (`professional_id === null`).

---

## 3. Arquitetura Backend

### 3.1 Modelos e Relacionamentos
- **`App\Models\Customer`**: Adicionado relacionamento `sales(): HasMany<Sale>`.
- **`App\Models\Sale`**: Adicionado relacionamento `saleCategory(): BelongsTo<SaleCategory>` como alias e complemento de `category()`.

### 3.2 Controlador de Clientes (`App\Http\Controllers\CustomerController@show`)
- Carrega `appointments` (com profissional) e `sales` (com `items` e `saleCategory:id,name`).
- Calcula métricas agregadas (`total_spent_cents`, `total_visits`).
- Retorna dados estruturados para a view Inertia `customers/show`.

### 3.3 Action de Liquidação Financeira (`App\Actions\Finance\Transactions\SettleFinancialObligation`)
- Executa em transação segura `DB::transaction(..., 5)`.
- Bloqueia obrigação e turno de caixa com `lockForUpdate()`.
- Criação de `CashMovement` com `reference_type = 'financial_obligation'` e `reference_id = $obligation->id`.
- Emissão de evento de auditoria `cash_shift.moved`.

---

## 4. Frontend React / Inertia

### 4.1 Perfil do Cliente (`resources/js/pages/customers/show.tsx`)
- **Cards de Métricas**:
  - Total Gasto Acumulado (formatado em Real brasileiro).
  - Total de Visitas.
  - Total de Comandas Registradas.
  - Total de Agendamentos.
- **Sistema de Abas**:
  1. *Histórico de Comandas & Consumo*: Lista detalhada de comandas com status badge, valor final, desconto aplicado, data/hora, link direto para a comanda (`sales.show`) e discriminação dos itens consumidos (serviços/produtos).
  2. *Agendamentos*: Histórico de horários com data, profissional, status badge e link para a agenda.
  3. *Dados Cadastrais*: Formulário completo de edição com governança de concorrência e ações de inativação/reativação.

### 4.2 Validação de Conflito na Agenda (`resources/js/pages/calendar/index.tsx`)
- Adicionada verificação reativa com `useMemo` no componente `AppointmentForm`.
- Exibição de badge e card de alerta amigável quando selecionados profissional, data e horário conflitantes:
  - `"Atenção: Horário coincide com outro agendamento/bloqueio"` com detalhes do conflito (nome do cliente/motivo do bloqueio e horários).

---

## 5. Testes e Qualidade

- **`tests/Feature/CustomerSalesHistoryTest.php`**:
  - Teste de carregamento do histórico de vendas do cliente com itens, categorias, cálculo de total gasto acumulado e total de visitas.
  - Validação de isolamento multi-tenant e consistência de ordenação decrescente por data de criação.
- **`tests/Feature/CashIntegrationTest.php`**:
  - Teste de liquidação em dinheiro de despesa (`payable`) gerando saída de caixa (`expense_outflow`) e reduzindo saldo esperado.
  - Teste de liquidação em dinheiro de receita (`receivable`) gerando suprimento de caixa (`supply`) e aumentando saldo esperado.
  - Teste de liquidação com método não-dinheiro (`pix`) sem geração indevida de movimentação de caixa.
  - Teste de liquidação em dinheiro na ausência de turno aberto.
- **Pint**: Executado `vendor/bin/pint --dirty --format agent` com 100% de conformidade.
- **Vite & Wayfinder**: `npm run build` executado com sucesso e zero erros de compilação TypeScript/React.
