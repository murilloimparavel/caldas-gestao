# Sprint 3 — Categorias e Produtos Físicos (Fase 2 - Catálogo & Pessoas)

## Visão Geral

A Sprint 3 entrega os agregados de **Categorias** (`Category`) e **Produtos Físicos** (`Product`), complementando a fundação do Catálogo Operacional do Caldas Gestão.

## Arquitetura e Modelagem

### 1. Categorias (`Category`)
- **Tabela**: `categories`
- **Campos**:
  - `id`: UUID (v7)
  - `tenant_id`, `unit_id`: isolamento multi-tenant e multi-unidade
  - `name`: string (160)
  - `type`: enum (`service`, `product`, `general`) com check constraint no PostgreSQL
  - `description`: text (nullable)
  - `is_active`: boolean (default `true`)
  - `lock_version`: unsignedBigInteger (default `1`)
  - `created_at`, `updated_at`, `deleted_at`: timestampsTz e softDeletesTz
- **Relacionamentos**:
  - `tenant()`: BelongsTo
  - `unit()`: BelongsTo
  - `services()`: HasMany
  - `products()`: HasMany
- **Segurança e Auditoria**:
  - RBAC: `category.view` e `category.manage`
  - Idempotência com `OperationalMutation` e `X-Idempotency-Key`
  - Concorrência otimista com `lock_version`
  - Auditoria append-only compatível com LGPD (sem PII no payload do evento)

### 2. Produtos Físicos (`Product`)
- **Tabela**: `products`
- **Campos**:
  - `id`: UUID (v7)
  - `tenant_id`, `unit_id`: escopo multi-tenant
  - `category_id`: UUID nullable (foreign key para `categories` com null on delete)
  - `name`: string (160)
  - `sku`: string (64, nullable)
  - `barcode`: string (64, nullable)
  - `cost_price_cents`: unsignedInteger (minor units em centavos)
  - `sale_price_cents`: unsignedInteger (minor units em centavos)
  - `unit_of_measure`: string (default `'un'`)
  - `min_stock`: integer (default `0`)
  - `current_stock`: integer (default `0`)
  - `is_active`: boolean (default `true`)
  - `lock_version`: unsignedBigInteger (default `1`)
  - `created_at`, `updated_at`, `deleted_at`: timestampsTz e softDeletesTz
- **Relacionamentos**:
  - `tenant()`, `unit()`: BelongsTo
  - `category()`: BelongsTo
- **Segurança e Auditoria**:
  - RBAC: `product.view` e `product.manage`
  - Validação estrita de escopo (garante que `category_id` pertence ao mesmo tenant e unidade)
  - Idempotência com `OperationalMutation`
  - Concorrência otimista com `lock_version`

### 3. Vínculo de Serviços com Categorias
- Adicionada coluna `category_id` na tabela `services` com foreign key nullable e relação `category()` em `Service`.

## Frontend (React 19, Inertia v3, Tailwind v4)

- **Rotas e Páginas**:
  - `/categories`: `resources/js/pages/categories/index.tsx` (listagem, busca, filtro de tipo, criação com dialog)
  - `/categories/{category}`: `resources/js/pages/categories/show.tsx` (edição, inativação, visualização de serviços e produtos associados)
  - `/products`: `resources/js/pages/products/index.tsx` (listagem, busca por nome/SKU/código de barras, indicador de estoque baixo, criação com dialog)
  - `/products/{product}`: `resources/js/pages/products/show.tsx` (edição, cálculo de margem bruta em tempo real, valor total de estoque a custo, status de estoque)
- **Navegação**:
  - Links integrados no `AppSidebar` sob o grupo "Gestão", respeitando as permissões `category.view` e `product.view`.

## Testes Automatizados (Pest)

- `tests/Feature/CategoryTest.php`:
  - Criação, atualização, busca e inativação com escopo tenant/unit
  - Replay idempotente com idempotency key
  - Rejeição de concorrência com versão desatualizada (409 Conflict)
  - Enforce de RBAC (permissões de leitura vs escrita)
  - Isolamento entre workspaces diferentes
- `tests/Feature/ProductTest.php`:
  - Criação, atualização, busca por SKU/nome e inativação
  - Validação de integridade referencial com categorias da mesma unidade
  - Rejeição de categorias pertencentes a outra unidade/tenant
  - Replay idempotente e controle otimista de versão
  - Enforce de RBAC e isolamento multi-tenant
