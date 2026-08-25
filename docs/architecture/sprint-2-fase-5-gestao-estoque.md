# Sprint 2 - Fase 5: Gestão e Movimentação de Estoque (InventoryMovement)

**Status**: Concluído  
**Data**: 25/08/2026  
**Stack**: Laravel 13, Inertia.js v3, React 19, Tailwind CSS v4, Wayfinder, Pest PHP  
**Contexto**: Fase 5 - Módulo Financeiro, Estoque e Operações

---

## 1. Visão Geral e Objetivos

A **Sprint 2 da Fase 5** implementa a gestão e movimentação de estoque físico no Caldas Gestão. O módulo é responsável pelo controle transacional de inventário de produtos físicos (`Product`), registrando entradas por compra (`purchase_inflow`), saídas automáticas por fechamento de comanda (`sale_outflow`), ajustes físicos manuais (perda/avaria `adjustment_loss`, sobra `adjustment_gain`) e balanço/contagem física (`manual_count`), suportando multi-tenancy estrito (`tenant_id`, `unit_id`), chaves primárias UUIDv7, controle de concorrência com `lock_version` e bloqueio transacional com `lockForUpdate()`.

---

## 2. Invariantes de Domínio e Regras de Negócio

1. **Movimentações de Estoque (`InventoryMovement`)**:
   - **Tipos de Movimentação**:
     - `purchase_inflow`: Entrada de mercadoria por compra ou nota fiscal. Incrementa `current_stock`.
     - `adjustment_gain`: Ajuste manual positivo por identificação de sobra ou bonificação. Incrementa `current_stock`.
     - `sale_outflow`: Saída automática por venda e consumo em comanda. Decrementa `current_stock`.
     - `adjustment_loss`: Ajuste manual negativo por quebra, perda, avaria ou consumo interno. Decrementa `current_stock`.
     - `manual_count`: Balanço físico e inventário periódico. O `resulting_stock` é definido diretamente com base na quantidade contada.
   - Cada movimentação armazena o saldo anterior (`previous_stock`), a quantidade movimentada (`quantity`), o custo unitário em centavos (`unit_cost_cents`), o saldo resultante (`resulting_stock`), o motivo (`reason`), a referência externa/documento (`reference_type`, `reference_id`) e o usuário operador responsável (`user_id`).

2. **Baixa Automática Transacional na Finalização de Vendas (`FinalizeClosingSession`)**:
   - Na finalização de comandas com itens do tipo `product` (`item_type === 'product'`), o sistema agrega as quantidades por produto e aplica bloqueio pessimista ordenado (`lockForUpdate()`), deduzindo o estoque e gerando os registros de `InventoryMovement` vinculados à sessão de fechamento (`reference_type = 'closing_session'`, `reference_id = $session->id`).

3. **Proteção Transacional e Concorrência Otimista**:
   - Toda alteração em estoque utiliza `DB::transaction(..., 5)` e `lockForUpdate()` no modelo `Product`.
   - Suporte a `lock_version`: requisições com versão desatualizada sofrem rollback automático e retornam `409 Conflict`.

4. **Auditoria e Governança**:
   - Eventos emitidos via `IdentityEventRecorder`: `inventory.moved`.
   - Permissões RBAC catalogadas em `OwnerPermissionCatalog`: `inventory.view`, `inventory.manage`.
   - Chaves auditadas catalogadas em `PayloadGovernance`: `inventory_movement_id`, `unit_cost_cents`, `previous_stock`, `resulting_stock`.

---

## 3. Arquitetura Backend

### 3.1 Modelos e Migrations
- **`database/migrations/2026_08_26_000003_create_inventory_movements_table.php`**: Tabela com chave primária UUIDv7, tenant/unit scoping, constraints e chaves estrangeiras com integridade referencial.
- **`app/Models/InventoryMovement.php`**: Modelo com tipagem estrita, attributes, casts e relacionamentos `product()`, `unit()`, `tenant()`, `user()`.
- **`app/Models/Product.php`**: Relacionamento `inventoryMovements(): HasMany<InventoryMovement, $this>`.
- **`database/factories/InventoryMovementFactory.php`**: Factory para geração de dados sintéticos de teste.

### 3.2 Actions em `app/Actions/`
- **`App\Actions\Inventory\RecordInventoryMovement`**: Action operacional que executa entradas, saídas e ajustes manuais com `lockForUpdate()`, atualização de `current_stock`, incremento de `lock_version` e registro de auditoria.
- **`App\Actions\Closing\FinalizeClosingSession`**: Integrada baixa automática de estoque de itens de produto durante a finalização atômica de comandas.

### 3.3 Camada HTTP, Policy e Rotas
- **`App\Http\Requests\InventoryMovementRequest`**: Validação de tipo de movimentação, quantidade, custo unitário e motivo.
- **`App\Policies\InventoryPolicy`**: Autorização por permissões `inventory.view` e `inventory.manage`.
- **`App\Http\Controllers\InventoryController`**: Endpoints para listagem geral de extrato de estoque (`index`) e lançamento de movimentação (`store`).
- **`App\Http\Controllers\ProductController`**: Método `show` estendido para carregar o histórico paginado de movimentações do produto.
- **Rotas em `routes/web.php`**:
  - `GET /inventory` -> `inventory.index`
  - `POST /inventory/movements` -> `inventory.movements.store`

---

## 4. Arquitetura Frontend

- **`resources/js/components/inventory/stock-adjustment-dialog.tsx`**:
  - Componente modal reutilizável para lançamento de movimentações de estoque (compras, ajustes de perda/ganho e balanço físico).
  - Cálculo e pré-visualização em tempo real do estoque resultante com badges de status e idempotência via `X-Idempotency-Key`.
- **`resources/js/pages/products/index.tsx`**:
  - Botão de ação rápida "Ajustar" no cartão de cada produto.
  - Indicador visual e badge de "Estoque baixo" / "Esgotado" (`current_stock <= min_stock`).
  - Acesso direto ao Extrato Geral de Estoque da unidade.
- **`resources/js/pages/products/show.tsx`**:
  - Seção completa "Histórico de Movimentações de Estoque" contendo extrato detalhado do item com data/hora, operador, tipo, quantidade (+/-), custo unitário, saldos e justificativa.
  - Botão de ajuste rápido de estoque no cabeçalho.
- **`resources/js/pages/inventory/index.tsx`**:
  - Página dedicada de Extrato Geral de Estoque da unidade, com filtros dinâmicos por produto, tipo de movimentação e data.
- **`resources/js/components/app-sidebar.tsx`**:
  - Adicionado item "Estoque" no grupo "Gestão" com ícone `Boxes` e verificação de permissão `inventory.view`.

---

## 5. Qualidade e Testes

- **`tests/Feature/InventoryMovementTest.php`** (10 testes, 73 asserções):
  - Entrada de mercadoria por compra com incremento de `current_stock` e registro de auditoria.
  - Ajuste de perda com decremento de `current_stock`.
  - Ajuste de ganho com incremento de `current_stock`.
  - Contagem física de estoque (`manual_count`) definindo diretamente o saldo.
  - Baixa automática transacional de estoque na finalização de vendas (`FinalizeClosingSession`).
  - Prevenção de conflito de concorrência com `lock_version` desatualizado (retornando `409 Conflict`).
  - Isolamento estrito multi-tenant e multi-unidade.
  - Validação de permissões RBAC (`inventory.view`, `inventory.manage`).
  - Listagem do extrato de estoque via Inertia.
  - Replay de mutação idempotente via `X-Idempotency-Key`.
- **Suíte Geral de Testes**: **231 testes executados, 206 aprovados, 0 falhas, 25 skipped**.
- **Formatação de Código**: Validada via Laravel Pint (`vendor/bin/pint --dirty --format agent`).
- **Compilação de Assets**: Validada via Vite e Laravel Wayfinder (`npm run build`).
