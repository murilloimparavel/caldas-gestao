# Sprint 4 — Fornecedores, Histórico de Atendimentos no Cliente e Governança

## Visão Geral

A Sprint 4 consolida a gestão de cadeia de suprimentos e histórico de relacionamento com clientes no Caldas Gestão, com foco nas seguintes frentes:
1. **Gestão de Fornecedores (`Supplier`)**: Cadastro corporativo e fiscal de fornecedores para suprimentos e revenda, com suporte a escopo multi-tenant e multi-unidade.
2. **Histórico de Atendimentos no Cliente (`Customer`)**: Visualização contextual dos últimos agendamentos diretamente na ficha do cliente.
3. **Governança, Idempotência e Auditoria**: Mutação idempotente com `OperationalMutation`, concorrência otimista via `lock_version` e trilha de auditoria append-only compatível com LGPD.

## Arquitetura e Modelagem

### 1. Fornecedores (`Supplier`)

- **Tabela**: `suppliers`
- **Campos**:
  - `id`: UUID (v7)
  - `tenant_id`: foreign key para `tenants` (cascade on delete)
  - `unit_id`: foreign key nullable para `units` (cascade on delete)
  - `name`: string (160) - Razão social / Nome
  - `trade_name`: string (160, nullable) - Nome fantasia
  - `document_number`: string (32, nullable) - CNPJ ou CPF
  - `email`: string (160, nullable)
  - `phone`: string (32, nullable)
  - `notes`: text (nullable)
  - `is_active`: boolean (default `true`)
  - `lock_version`: unsignedBigInteger (default `1`)
  - `created_at`, `updated_at`, `deleted_at`: timestampsTz e softDeletesTz
- **Relacionamentos**:
  - `tenant()`: BelongsTo
  - `unit()`: BelongsTo (nullable para fornecedores globais do tenant ou específicos de unidade)
- **Segurança e Auditoria**:
  - Permissões RBAC: `supplier.view` e `supplier.manage` cadastradas em `OwnerPermissionCatalog`
  - Idempotência com `OperationalMutation` e `X-Idempotency-Key`
  - Concorrência otimista via `lock_version` (409 Conflict em desatualização)
  - Auditoria append-only com eventos `supplier.created`, `supplier.updated`, `supplier.deactivated`

### 2. Histórico de Atendimentos no Cliente (`Customer`)

- **Relacionamento**: `Customer::appointments(): HasMany`
- **Carregamento Otimizado**: Eager loading dos 20 agendamentos mais recentes ordenados por `starts_at desc` com `professional:id,name` no `CustomerController::show`.
- **Experiência do Usuário**:
  - Aba/seção dedicada em `resources/js/pages/customers/show.tsx`
  - Formatação em padrão brasileiro de datas e horários
  - Badges de status dos agendamentos
  - Link direto com contexto para a agenda (`/calendar?date=YYYY-MM-DD`)

## Frontend (React 19, Inertia v3, Tailwind v4)

- **Menu Lateral**: Adicionado item "Fornecedores" em `app-sidebar.tsx` sob o grupo "Gestão", com ícone `Truck` e controle por permissão `supplier.view`.
- **Páginas**:
  - `/suppliers`: `resources/js/pages/suppliers/index.tsx` (listagem, busca por razão social/nome fantasia/documento, modal Dialog para criação rápida com idempotência, empty state e paginação).
  - `/suppliers/{supplier}`: `resources/js/pages/suppliers/show.tsx` (edição de dados cadastrais/fiscais, status, resumo lateral e desativação segura com confirmação).
  - `/customers/{customer}`: `resources/js/pages/customers/show.tsx` (atualizado com a seção de histórico de agendamentos).

## Testes Automatizados (Pest)

- `tests/Feature/SupplierTest.php`:
  - CRUD e ciclo de vida completo de fornecedores (criação, edição, busca e inativação)
  - Rejeição de concorrência com versão desatualizada (409 Conflict)
  - Enforce de RBAC (permissão `supplier.view` vs `supplier.manage`)
  - Isolamento multi-tenant entre workspaces diferentes
  - Carregamento de histórico recente de agendamentos com profissional vinculado ao visualizar cliente
