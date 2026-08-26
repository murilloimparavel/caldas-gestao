# Sprint 2 - Wave de Refinamento: Estorno Compensatório de Comandas (Sale Adjustment)

**Status**: Concluído  
**Data**: 25/08/2026  
**Stack**: Laravel 13, Inertia.js v3, React 19, Tailwind CSS v4, Wayfinder, Pest PHP  
**Contexto**: Wave de Refinamento - Integridade Operacional e Compensatória de Comandas

---

## 1. Visão Geral e Objetivos

A **Sprint 2 da Wave de Refinamento** implementa o mecanismo de **Estorno Compensatório de Comandas (`Sale Adjustment`)** no Caldas Gestão.

Em conformidade com os princípios de contabilidade e integridade operacional do sistema:
- O estorno de comanda **NÃO apaga ou destrói registros** no banco de dados.
- O status da comanda transiciona de `finalized` para `adjusted`.
- O estoque baixado durante o fechamento é reposto de maneira transacional via `InventoryMovement` com o tipo `adjustment_gain`.
- Todas as comissões apuradas (`CommissionAccrual`) associadas aos itens da comanda com status `accrued` são canceladas (`cancelled`).
- O histórico de status (`SaleStatusHistory`) e a trilha de auditoria (`AuditEvent`) registram a justificativa (`reason`), operador responsável (`user_id`) e incremento de concorrência (`lock_version`).

---

## 2. Invariantes de Domínio e Regras de Negócio

1. **Elegibilidade para Estorno**:
   - Apenas comandas no status `finalized` podem sofrer estorno compensatório.
   - Comandas em `draft`, `open`, `ready_to_bill`, `cancelled` ou já `adjusted` são rejeitadas.
2. **Reposição Automática de Estoque**:
   - Para cada `SaleItem` com `item_type === 'product'` e `product_id` preenchido, é chamada a action `RecordInventoryMovement` com tipo `adjustment_gain`, quantidade correspondente ao item vendido e referência à comanda estornada.
   - O estoque do produto (`current_stock`) e o `lock_version` do produto são atualizados atomicamente.
3. **Cancelamento de Comissões**:
   - Todos os registros em `commission_accruals` vinculados à comanda que ainda estejam no status `accrued` passam para `cancelled`, incrementando o `lock_version` de cada registro.
4. **Governança e Concorrência**:
   - Exigência de `lock_version` para evitar edições ou estornos simultâneos concorrentes (retorno HTTP 409 Conflict em caso de versão desatualizada).
   - Justificativa obrigatória (`reason`).
   - Registro de histórico em `sale_status_histories`.
   - Evento de auditoria registrado em `audit_events` e outbox sob a ação `sale.adjusted`.
5. **RBAC & Autorização**:
   - Permissão `sale.adjust` registrada no catálogo `OwnerPermissionCatalog`.
   - Operadores com permissão `sale.adjust` ou `sale.manage` têm autorização na política `SalePolicy@adjust`.

---

## 3. Arquitetura Backend

### 3.1 Migrations
- `2026_08_25_230002_create_sales_table.php`: Atualizado check constraint do Postgres para incluir `'adjusted'`.
- `2026_08_26_030001_add_adjusted_status_to_sales_table.php`: Migration segura para atualização da constraint `sales_status_check` em bancos de dados Postgres existentes.

### 3.2 RBAC e Governança
- **`OwnerPermissionCatalog`**: Adicionada chave `'sale.adjust'`.
- **`SalePolicy`**: Método `adjust(User $user, Sale $sale): bool` implementado para checar `sale.adjust` ou `sale.manage`.
- **`PayloadGovernance`**: Garante compatibilidade de chaves auditadas no evento `sale.adjusted`.

### 3.3 Action Operacional (`app/Actions/Sales/AdjustSale.php`)
- Executa em transação de banco de dados (`DB::transaction(..., 5)`).
- Bloqueia comanda com `lockForUpdate()`.
- Dispara reposição de estoque via `RecordInventoryMovement`.
- Cancela comissões vinculadas com status `accrued`.
- Registra transição de status em `sale_status_histories`.
- Incrementa `lock_version` da comanda e grava evento `sale.adjusted`.

### 3.4 Form Request e Controlador
- **`App\Http\Requests\AdjustSaleRequest`**: Valida autorização via `Gate::allows('adjust', $sale)` e campos obrigatórios (`reason`, `lock_version`).
- **`App\Http\Controllers\SaleController@adjust`**: Executa a mutação com rastreamento de idempotência (`OperationalMutation`) e redireciona para `sales.show` com flash message de sucesso.
- **`routes/web.php`**: Rota `POST /sales/{sale}/adjust` nomeada `sales.adjust`.

---

## 4. Frontend React / Inertia

### 4.1 Tipagem (`resources/js/types/sales.ts`)
- Adicionado status `'adjusted'` ao tipo `SaleStatus`.

### 4.2 Badge e Listagem (`resources/js/pages/sales/index.tsx`)
- Status `adjusted` mapeado com estilo e badge "Estornada" (`border-rose-300 bg-rose-50 text-rose-700`).

### 4.3 Tela de Detalhes da Comanda (`resources/js/pages/sales/show.tsx`)
- Se `sale.status === 'finalized'` e o usuário possuir `sale.adjust` ou `sale.manage`:
  - Exibe botão estilizado **"Estornar Comanda"**.
  - Modal de confirmação com aviso detalhado sobre a reversão de estoque e cancelamento de comissões, campo obrigatório para a justificativa e cabeçalho de idempotência.
- Se `sale.status === 'adjusted'`:
  - Exibe badge "Estornada".
  - Exibe banner destacado no topo informando que a comanda foi estornada, o motivo do estorno, a data e o operador responsável.
  - Ações de alteração ou fechamento permanecem bloqueadas de acordo com as regras de ciclo de vida.

---

## 5. Testes e Validação

Criado conjunto de testes automatizados em `tests/Feature/SaleAdjustmentTest.php`:
- **Estorno Completo**: Validação da alteração de status para `adjusted`, reposição exata de estoque (`InventoryMovement` com `adjustment_gain`), cancelamento de comissões vinculadas (`accrued` -> `cancelled`), inserção de histórico em `SaleStatusHistory` e emissão de `AuditEvent` `sale.adjusted`.
- **Guarda de Status**: Rejeição de estorno em comandas com status diferente de `finalized` (`open`, `ready_to_bill`, `cancelled`, `draft`, `adjusted`).
- **Concorrência Otimista**: Rejeição com HTTP 409 Conflict ao fornecer `lock_version` defasado.
- **Validação de Payload**: Rejeição de justificativa em branco ou ausente (422 Unprocessable Entity).
- **RBAC**: Permissão concedida para `Owner` e usuários com `sale.adjust` / `sale.manage`, e bloqueio 403 para usuários não autorizados.
- **Idempotência**: Replay consistente de mutação com cabeçalho `X-Idempotency-Key`.
- **Testes de Regressão**: 42 testes executados com 100% de sucesso cobrindo módulos de comandas, fechamentos, categorias e ajustes.
