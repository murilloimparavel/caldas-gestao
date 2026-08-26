# Sprint 1 — Wave de Refinamento: Reativação de Cadastros e Filtros de Status na UI

## Visão Geral

A Sprint 1 da Wave de Refinamento implementa o ciclo de vida completo de reativação de cadastros inativos e filtros rápidos de status na UI para todas as entidades operacionais e de catálogo do Caldas Gestão, além de aprimorar a experiência de abertura de caixa operacional.

Entidades atendidas:
1. **Profissionais (`Professional`)**
2. **Clientes (`Customer`)**
3. **Produtos (`Product`)**
4. **Serviços (`Service`)**
5. **Fornecedores (`Supplier`)**
6. **Categorias de Catálogo (`Category`)**
7. **Categorias de Comanda (`SaleCategory`)**

---

## Arquitetura e Backend

### 1. Actions de Reativação Dedicadas
Cada entidade recebeu uma action correspondente que estende `OperationalAction`, garantindo validação de permissão de workspace/unidade, checagem e bloqueio pessimista via `lockForUpdate()`, controle de concorrência otimista com `lock_version`, persistência de auditoria e incremento atômico de versão:
- `App\Actions\Professionals\ReactivateProfessional` (evento: `professional.reactivated`)
- `App\Actions\Customers\ReactivateCustomer` (evento: `customer.reactivated`)
- `App\Actions\Products\ReactivateProduct` (evento: `product.reactivated`)
- `App\Actions\Services\ReactivateService` (evento: `service.reactivated`)
- `App\Actions\Suppliers\ReactivateSupplier` (evento: `supplier.reactivated`)
- `App\Actions\Categories\ReactivateCategory` (evento: `category.reactivated`)
- `App\Actions\SaleCategories\ReactivateSaleCategory` (evento: `sale_category.reactivated`)

### 2. Rotas & FormRequests
- Adicionadas rotas dedicadas `PATCH /{resource}/{id}/reactivate` sob o grupo de middleware `tenant.context`.
- FormRequests atualizados para aceitar as rotas de reativação exigindo apenas `lock_version` com autorização validada via gate `update`.

### 3. Filtros de Status nos Controllers (`index`)
Os métodos `index()` dos 7 controllers foram atualizados para receber o parâmetro `status` com os seguintes comportamentos:
- `active` (padrão): filtra registros ativos (`status = 'active'` ou `is_active = true`).
- `inactive`: filtra registros inativos (`status = 'inactive'` ou `is_active = false`).
- `all`: retorna todos os registros sem filtro de status.
- O valor aplicado é retornado no array `filters.status` nas props do Inertia.

---

## Frontend (React 19, Inertia v3, TypeScript, Tailwind)

### 1. Telas de Detalhe (`show.tsx`)
Quando um cadastro está inativo (`status === 'inactive'` ou `!is_active`):
- O botão/modal de inativação é substituído por uma seção/card destacado de **"Reativar cadastro"**.
- Confirmação explícita com `Dialog` informando os impactos da reativação.
- Formulário Inertia `<Form {...route.reactivate.form(id)} method="patch">` enviando `lock_version` oculto e `X-Idempotency-Key` no cabeçalho.

### 2. Telas de Listagem (`index.tsx`) & `SearchToolbar`
- Componente `SearchToolbar` (`resources/js/components/operational/index.tsx`) aprimorado com controle segmentado ("Ativos", "Inativos", "Todos") e input oculto para preservar o status no submit da busca.
- Sincronização em tempo real com a URL via `router.get(url, { ...filters, status }, { preserveState: true, preserveScroll: true })`.
- `EmptyState` adaptado para exibir mensagem e ação contextuais caso nenhum registro inativo ou ativo seja encontrado.

### 3. Gatilho de Abertura de Caixa (`finance/cash/index.tsx`)
- O modal `Dialog` de abertura de turno foi desacoplado do `ResourceHeader actions` e movido para o nível do `PageCanvas`.
- O botão central no estado de caixa fechado (*"Abrir Turno de Caixa Agora"*) agora abre o diálogo de abertura de turno diretamente e sem dependências aninhadas.

---

## Qualidade e Testes Automatizados

### 1. Testes de Feature (Pest)
- Arquivo criado: `tests/Feature/ReactivationTest.php` com 7 testes completos (294 asserções) cobrindo:
  - Filtro por status (`active` default, `inactive`, `all`) nas 7 entidades.
  - Reativação com mutação idempotente, validação de `lock_version` e gravação de `AuditEvent` correspondente.
- Suíte completa de testes do projeto: 255 testes executados, 230 aprovados (25 skipped padrão), 1.687 asserções, 0 falhas.

### 2. Estilo e Tipagem
- `vendor/bin/pint --format agent`: 100% de conformidade com os padrões do Laravel Pint.
- `npm run build`: Compilação de frontend e TypeScript sem erros.
