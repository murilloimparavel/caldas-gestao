# Sprint 1 — Fase 4: Fundação de Dados, Modelos e Categorias de Comanda (SaleCategory)

## Visão Geral

A Sprint 1 da Fase 4 estabelece a fundação de dados do domínio de **Comandas & Fechamento Consolidado (Sales & Checkout)** do Caldas Gestão, implementando o agregado de configuração de categorias de comanda (`SaleCategory`), o agregado de comandas (`Sale`), itens (`SaleItem`), vínculos com agendamento (`AppointmentSaleLink`), histórico de transição de status (`SaleStatusHistory`) e sessões de fechamento consolidado (`ClosingSession`).

A implementação segue estritamente o [PRD](../PRD--comandas-categorias-e-checkout.md) e o [ADR-003](../adr/ADR-003--categorias-de-comanda-e-checkout-consolidado.md).

---

## Modelagem e Banco de Dados

### 1. Categorias de Comanda (`SaleCategory`)
- **Tabela**: `sale_categories`
- **Campos**:
  - `id`: UUID (v7)
  - `tenant_id`, `unit_id`: escopo multi-tenant e multi-unidade
  - `name`: string (160)
  - `key`: string (64)
  - `type`: enum (`service`, `product`, `mixed`) com check constraint no PostgreSQL
  - `uniqueness_scope`: enum (`customer`, `appointment`, `reference`, `none`) com check constraint no PostgreSQL
  - `is_active`: boolean (default `true`)
  - `lock_version`: unsignedBigInteger (default `1`)
  - `timestampsTz()`, `softDeletesTz()`
- **Constraints & Índices**:
  - Unicidade por `(tenant_id, id)`, `(tenant_id, unit_id, id)`, `(tenant_id, unit_id, key)`
  - Foreign keys restritivas para `tenants` e `units`

### 2. Comandas (`Sale`)
- **Tabela**: `sales`
- **Campos**:
  - `id`: UUID (v7)
  - `tenant_id`, `unit_id`: escopo multi-tenant
  - `customer_id`: UUID nullable (fk para `customers`)
  - `sale_category_id`: UUID fk para `sale_categories`
  - `category_key_snapshot`, `category_name_snapshot`: snapshots da categoria no momento de criação
  - `reference_label`: string nullable (ex: Mesa 12, Pedido Balcão)
  - `open_context_key`: string nullable calculada pelo backend para unicidade (ex: `customer:{uuid}`, `appointment:{uuid}`, `reference:{normalized}`)
  - `status`: enum (`draft`, `open`, `ready_to_bill`, `finalized`, `cancelled`)
  - `currency`: string (default `'BRL'`)
  - `total_amount_cents`, `discount_amount_cents`, `final_amount_cents`: inteiros em minor units (centavos >= 0)
  - `notes`: text nullable
  - `lock_version`: unsignedBigInteger (default `1`)
  - `timestampsTz()`, `softDeletesTz()`
- **Constraints & Índices**:
  - Unique parcial no PostgreSQL em `(tenant_id, unit_id, sale_category_id, open_context_key)` WHERE `status IN ('draft', 'open', 'ready_to_bill') AND open_context_key IS NOT NULL AND deleted_at IS NULL`

### 3. Itens de Comanda (`SaleItem`)
- **Tabela**: `sale_items`
- **Campos**:
  - `id`: UUID (v7)
  - `tenant_id`, `unit_id`, `sale_id`: integridade referencial com cascade delete na venda
  - `item_type`: enum (`service`, `product`, `custom`)
  - `service_id`, `product_id`, `professional_id`: UUIDs nullable com null on delete
  - `name_snapshot`: string (160)
  - `unit_price_cents`, `quantity`, `discount_cents`, `total_cents`: inteiros em centavos
  - `timestampsTz()`, `softDeletesTz()`

### 4. Vínculo Agendamento-Comanda (`AppointmentSaleLink`)
- **Tabela**: `appointment_sale_links`
- **Campos**:
  - `id`: UUID (v7)
  - `tenant_id`, `unit_id`, `appointment_id`, `sale_id`, `created_by`
  - Unique parcial / total em `sale_id` garantindo cardinalidade `Sale 0..1 Appointment` e `Appointment 0..N Sale` no MVP

### 5. Histórico de Transição de Status (`SaleStatusHistory`)
- **Tabela**: `sale_status_histories`
- **Campos**:
  - `id`: UUID (v7)
  - `sale_id`, `from_status`, `to_status`, `user_id`, `reason`, `timestampsTz()`

### 6. Sessões de Fechamento Consolidado (`ClosingSession` e Pivot `closing_session_sales`)
- **Tabela**: `closing_sessions`
  - `id`: UUID (v7)
  - `tenant_id`, `unit_id`
  - `closing_subject`: string (uniforme para a sessão: mesmo cliente ou mesma referência)
  - `currency`: string (default `'BRL'`)
  - `expected_total_cents`, `final_total_cents`
  - `status`: enum (`draft`, `ready`, `processing`, `completed`, `cancelled`, `failed`)
  - `receipt_number`, `receipt_payload` (jsonb), `idempotency_key`, `closed_by_user_id`, `lock_version`
- **Tabela Pivot**: `closing_session_sales`
  - `closing_session_id`, `sale_id` (PK composta)

---

## RBAC & Governança de Payloads

- Adicionadas permissões no `OwnerPermissionCatalog`:
  - `sale_category.view`
  - `sale_category.manage`
  - `sale.view`
  - `sale.manage`
  - `sale.discount`
  - `sale.close`
- `PayloadGovernance`:
  - Adicionadas chaves de auditoria e eventos sanitizadas sem PII para `SaleCategory`, `Sale`, `SaleItem` e `ClosingSession`.

---

## Ações e Controladores (SaleCategory)

- **Ações**:
  - `App\Actions\SaleCategories\CreateSaleCategory`: gera UUIDv7, chave estável, lock_version 1 e emite `sale_category.created`.
  - `App\Actions\SaleCategories\UpdateSaleCategory`: controle otimista de versão (`lock_version`), transação segura e emite `sale_category.updated`.
  - `App\Actions\SaleCategories\DeactivateSaleCategory`: inativação lógica sem apagar histórico e emite `sale_category.deactivated`.
- **Policy**: `SaleCategoryPolicy` (autorização tenant/unit-scoped com `sale_category.view` e `sale_category.manage`).
- **Request**: `SaleCategoryRequest` (validações estritas de enum e lock_version).
- **Controller**: `SaleCategoryController` (utilizando `OperationalMutation` para idempotência com cabeçalho `X-Idempotency-Key`).

---

## Frontend (React 19, Inertia v3, Tailwind v4, Wayfinder)

- **Rotas**:
  - `/sale-categories`: `resources/js/pages/sale-categories/index.tsx`
  - `/sale-categories/{sale_category}`: `resources/js/pages/sale-categories/show.tsx`
- **Recursos da UI**:
  - Modais de criação e inativação com proteção de idempotência.
  - Indicadores visuais de tipo de itens e escopo de unicidade.
  - Integração com Wayfinder (`@/routes/sale-categories`).

---

## Testes & Validação

- Testes de Feature Pest em `tests/Feature/SaleCategoryTest.php`:
  1. Criação, atualização, busca e inativação com escopo tenant/unit e auditoria.
  2. Replay idempotente de criação com idempotency key.
  3. Rejeição de concorrência com `lock_version` obsoleto (409 Conflict).
  4. Enforce de RBAC (permissões de leitura vs escrita).
  5. Isolamento estrito entre tenants e unidades.
- Suíte completa de testes: 185 testes executados e aprovados.
- Formatação de código validada via Laravel Pint (`vendor/bin/pint --format agent`).
- Build frontend compilado com sucesso (`npm run build`).
