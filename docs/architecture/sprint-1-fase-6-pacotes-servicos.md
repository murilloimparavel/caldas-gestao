# Sprint 1 da Fase 6: Pacotes de Serviços (PackageTemplate & CustomerPackage)

## 1. Visão Geral

O módulo de **Pacotes de Serviços** permite a criação de modelos de pacotes (`PackageTemplate`) com preço fechado, quantidade de sessões pré-definidas, validade temporal e catálogo de serviços inclusos. Esses modelos podem ser vendidos aos clientes gerando instâncias de pacotes (`CustomerPackage`), cujo saldo de sessões é consumido de forma atômica (`PackageUsage`) à medida que o cliente realiza seus atendimentos.

---

## 2. Arquitetura e Modelo de Dados

Todas as tabelas seguem o padrão multi-tenant do Caldas Gestão com particionamento lógico por `tenant_id` e `unit_id`, chaves primárias em **UUIDv7**, controle de concorrência via `lock_version`, valores monetários inteiros em centavos (`minor units`) e auditoria completa.

### 2.1 Estrutura das Tabelas

1. **`package_templates`**:
   - `id` (UUIDv7, PK)
   - `tenant_id` (UUID, FK -> tenants)
   - `unit_id` (UUID, FK -> units)
   - `name` (varchar 255)
   - `description` (text, nullable)
   - `price_cents` (int >= 0)
   - `total_sessions` (int > 0)
   - `validity_days` (int > 0, default: 90)
   - `is_active` (boolean, default: true)
   - `lock_version` (bigint, default: 0)
   - `created_at`, `updated_at`, `deleted_at`

2. **`package_template_services`**:
   - `tenant_id` (UUID)
   - `unit_id` (UUID)
   - `package_template_id` (UUID, FK -> package_templates, cascade)
   - `service_id` (UUID, FK -> services, cascade)
   - PK: `['package_template_id', 'service_id']`

3. **`customer_packages`**:
   - `id` (UUIDv7, PK)
   - `tenant_id` (UUID)
   - `unit_id` (UUID)
   - `customer_id` (UUID, FK -> customers, cascade)
   - `package_template_id` (UUID, FK -> package_templates)
   - `sale_id` (UUID, nullable, FK -> sales)
   - `total_sessions` (int > 0)
   - `remaining_sessions` (int >= 0)
   - `expires_at` (date, nullable)
   - `status` (`active`, `exhausted`, `expired`, `cancelled`)
   - `lock_version` (bigint, default: 0)
   - `created_at`, `updated_at`, `deleted_at`

4. **`package_usages`**:
   - `id` (UUIDv7, PK)
   - `tenant_id` (UUID)
   - `unit_id` (UUID)
   - `customer_package_id` (UUID, FK -> customer_packages, cascade)
   - `sale_id` (UUID, nullable)
   - `sale_item_id` (UUID, nullable)
   - `sessions_consumed` (int > 0, default: 1)
   - `user_id` (UUID, FK -> users)
   - `created_at`, `updated_at`

---

## 3. Ações e Regras de Negócio

As regras residem em ações atômicas em `app/Actions/Marketing/Packages/`:

1. **`CreatePackageTemplate`**:
   - Valida serviços da unidade ativa.
   - Grava modelo de pacote com serviços associados na tabela pivô.
   - Emite evento `package_template.created`.

2. **`UpdatePackageTemplate`**:
   - Controle otimista de versão (`lock_version`).
   - Atualiza dados e sincroniza os serviços selecionados.
   - Emite evento `package_template.updated`.

3. **`DeactivatePackageTemplate` / `ReactivatePackageTemplate`**:
   - Inativa ou reativa o modelo para novas vendas.
   - Incrementa `lock_version`.
   - Emite eventos `package_template.deactivated` e `package_template.reactivated`.

4. **`SellCustomerPackage`**:
   - Atribui pacote ativo ao cliente.
   - Calcula a data de expiração `expires_at` com base em `validity_days` ou override manual.
   - Inicia `remaining_sessions` igual a `total_sessions` com status `active`.
   - Emite evento `customer_package.sold`.

5. **`ConsumePackageSession`**:
   - Executa com bloqueio pessimista (`lockForUpdate`).
   - Valida status do pacote (`active`) e validade (`expires_at`).
   - Se expirado, transiciona para `expired` e rejeita a operação com 409 Conflict.
   - Decrementa `remaining_sessions`.
   - Se `remaining_sessions === 0`, transiciona status para `exhausted`.
   - Incrementa `lock_version` e cria registro imutável em `package_usages`.
   - Emite evento `customer_package.consumed`.

---

## 4. RBAC e Governança

- **Permissões (`OwnerPermissionCatalog`)**:
  - `package.view`: Visualizar lista de modelos e pacotes de clientes.
  - `package.manage`: Criar, editar, desativar e reativar modelos de pacotes.
  - `package.sell`: Vender e atribuir pacotes a clientes.
  - `package.consume`: Consumir e dar baixa em sessões de pacotes.
- **Governança de Payloads (`PayloadGovernance`)**:
  - Chaves de auditoria e eventos adicionadas: `package_template_id`, `customer_package_id`, `package_usage_id`, `sessions_consumed`, `remaining_sessions`, `total_sessions`, `validity_days`, `expires_at`.
  - Whitelist segura de fragmentos para contagem de sessões de pacotes.

---

## 5. Frontend & Telas

- **`/packages` (`resources/js/pages/packages/index.tsx`)**:
  - Listagem com filtros por status (`active`/`inactive`), busca por nome/descrição.
  - Modal de criação de pacote com seleção múltipla de serviços inclusos e formatação monetária.
  - Cards com detalhes de preço, valor por sessão, validade e contagem de vendas.
- **`/packages/{id}` (`resources/js/pages/packages/show.tsx`)**:
  - Painel com resumo dos valores, serviços inclusos e validade.
  - Ações para edição, desativação e reativação.
  - Tabela com histórico de clientes que adquiriram o pacote, saldo de sessões e utilizações.
- **Detalhes do Cliente (`resources/js/pages/customers/show.tsx`)**:
  - Nova aba **"Pacotes de Serviços"**.
  - Modal **"Vender / Adicionar Pacote"** selecionando template ativo.
  - Modal **"Consumir Sessão"** para baixa instantânea com bloqueio atômico.
  - Histórico detalhado de consumo por profissional/data.
- **Sidebar (`resources/js/components/app-sidebar.tsx`)**:
  - Link "Pacotes de Serviços" no menu de **Gestão**, condicionado à permissão `package.view`.

---

## 6. Qualidade & Testes

- Testes de Feature em [`tests/Feature/PackageTest.php`](file:///Users/murilloalves/Projects/caldas-gestao/tests/Feature/PackageTest.php):
  - CRUD completo de `PackageTemplate` com serviços.
  - Controle de concorrência com `lock_version` (409 Conflict em stale version).
  - Venda e cálculo de validade para `CustomerPackage`.
  - Consumo atômico de sessões e transição de status para `exhausted` no saldo zero.
  - Validação de expiração e transição para `expired`.
  - Verificação rigorosa de isolamento multi-tenant e RBAC.
- Formatação de código validada com **Laravel Pint** (`vendor/bin/pint --format agent`).
- Frontend compilado com sucesso via **Vite & Wayfinder** (`npm run build`).
