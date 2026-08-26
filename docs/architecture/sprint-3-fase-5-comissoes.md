# Sprint 3 - Fase 5: Regras e Apuração de Comissões dos Profissionais

**Status**: Concluído  
**Data**: 25/08/2026  
**Stack**: Laravel 13, Inertia.js v3, React 19, Tailwind CSS v4, Wayfinder, Pest PHP  
**Contexto**: Fase 5 - Módulo Financeiro, Estoque e Operações

---

## 1. Visão Geral e Objetivos

A **Sprint 3 da Fase 5** introduz o motor completo de comissionamento de profissionais no Caldas Gestão. O módulo é responsável pelo cadastro flexível de regras de comissionamento (`CommissionRule`), apuração transacional automática por item de comanda finalizada (`CommissionAccrual`), extrato detalhado por profissional com histórico de atendimentos e liquidação periódica consolidada de repasses (`CommissionSettlement`).

Toda a arquitetura segue os princípios estruturantes da plataforma:
- Multi-tenancy estrito (`tenant_id`, `unit_id`);
- Chaves primárias UUIDv7;
- Valores monetários representados em centavos inteiros (`minor units`);
- Controle de concorrência com `lock_version` e bloqueios transacionais `lockForUpdate()`;
- Idempotência em requisições de mutação via `X-Idempotency-Key`;
- Trilha de auditoria em `PayloadGovernance` e eventos de domínio via `IdentityEventRecorder`.

---

## 2. Invariantes de Domínio e Regras de Negócio

### 2.1 Regras de Comissão (`CommissionRule`)
1. **Escopo e Granularidade**:
   - Uma regra pode ser geral para todos os profissionais (`professional_id = null`) ou específica para um profissional.
   - Pode se aplicar a todos os itens (`service_id = null`, `product_id = null`) ou a um serviço específico ou produto específico.
2. **Tipos de Cálculo**:
   - `percentage`: Percentual aplicado sobre o valor bruto do item (`gross_amount_cents`). Fórmula: `round((gross_amount_cents * value_rate) / 100)`.
   - `fixed`: Valor fixo por unidade do item (`value_rate * quantity`), onde `value_rate` é armazenado em centavos.
3. **Hierarquia de Especificidade no Matching de Regras**:
   Quando um item de comanda possui um profissional atribuído (`professional_id`), o motor resolve a regra ativa aplicável com base na maior especificidade:
   - **Prioridade 1 (Score 4)**: Profissional Específico + Serviço/Produto Específico.
   - **Prioridade 2 (Score 3)**: Profissional Específico + Regra Geral de Itens.
   - **Prioridade 3 (Score 2)**: Profissional Geral + Serviço/Produto Específico.
   - **Prioridade 4 (Score 1)**: Regra Global do Workspace (Profissional Geral + Itens Gerais).

### 2.2 Apuração Automática (`CommissionAccrual`)
- Disparada transacionalmente durante o fechamento de comandas em `FinalizeClosingSession`.
- Para cada item da comanda com profissional associado, é gerado um registro de `CommissionAccrual` com status `accrued`, snapshot do nome do item, valor bruto, taxa/tipo aplicado e valor final da comissão em centavos.

### 2.3 Liquidação de Comissões (`CommissionSettlement`)
- Processo de fechamento e pagamento de comissões acumuladas para um profissional.
- Consolida todos os lançamentos com status `accrued` selecionados ou do período.
- Cria o registro de `CommissionSettlement` com `total_amount_cents`, período de apuração (`period_start`, `period_end`), data do pagamento (`paid_at`), usuário responsável e observações.
- Atualiza todos os lançamentos apurados para status `settled`, registrando o `settlement_id` e o timestamp `settled_at`.

---

## 3. Arquitetura Backend

### 3.1 Migrations e Banco de Dados
- **`2026_08_26_010001_create_commission_rules_table.php`**: Tabela de regras de comissionamento com suporte a UUIDv7, tenant/unit scoping, constraints de integridade e foreign keys.
- **`2026_08_26_010002_create_commission_settlements_table.php`**: Tabela de liquidações e repasses pagos.
- **`2026_08_26_010003_create_commission_accruals_table.php`**: Tabela de apuração detalhada de comissões por item de venda.

### 3.2 Modelos e Relacionamentos
- **`App\Models\CommissionRule`**: Relacionamentos com `Tenant`, `Unit`, `Professional`, `Service` e `Product`.
- **`App\Models\CommissionAccrual`**: Relacionamentos com `Tenant`, `Unit`, `Professional`, `Sale`, `SaleItem` e `CommissionSettlement`.
- **`App\Models\CommissionSettlement`**: Relacionamentos com `Tenant`, `Unit`, `Professional`, `User` e `accruals()`.
- **`App\Models\Professional`**: Relações adicionadas: `commissionRules()`, `commissionAccruals()`, `commissionSettlements()`.

### 3.3 Actions em `app/Actions/Finance/Commissions/`
- **`SaveCommissionRule`**: Criação e atualização transacional de regras de comissionamento com controle de versão `lock_version` e auditoria.
- **`DeleteCommissionRule`**: Exclusão segura de regra de comissão.
- **`AccrueCommissionsForSale`**: Apuração automática por algoritmo ponderado de especificidade de regras para cada item vendido.
- **`SettleCommissions`**: Liquidação atômica de comissões pendentes gerando comprovante de liquidação.
- **`FinalizeClosingSession`**: Integrada a apuração automática de comissões na transação de finalização de vendas.

### 3.4 Permissões RBAC e Governança
- Permissões em `OwnerPermissionCatalog`:
  - `commission.view`: Acesso aos extratos e relatórios de comissões.
  - `commission.manage`: Criação, edição e exclusão de regras de comissionamento.
  - `commission.settle`: Autorização e baixa para liquidação de pagamentos aos profissionais.
- `PayloadGovernance`: Adição de chaves permitidas para auditoria e eventos (`commission_rule_id`, `commission_settlement_id`, `commission_accrual_id`, `rate_type`, `rate_value`, `value_rate`, `commission_amount_cents`, `gross_amount_cents`, `settlement_id`, `settled_at`, `paid_at`, `period_start`, `period_end`, `notes`, `accrual_ids`).

### 3.5 Camada HTTP, Policy e Rotas
- **`App\Policies\CommissionPolicy`**: Validação de permissões contextuais de tenant/unit.
- **`App\Http\Requests\CommissionRuleRequest`** e **`CommissionSettlementRequest`**.
- **`App\Http\Controllers\CommissionController`**: Endpoints para `index`, `show`, `storeRule`, `updateRule`, `deleteRule` e `settle`.
- **Rotas em `routes/web.php`**:
  - `GET /finance/commissions` -> `commissions.index`
  - `GET /finance/commissions/professionals/{professional}` -> `commissions.show`
  - `POST /finance/commissions/rules` -> `commissions.rules.store`
  - `PUT /finance/commissions/rules/{rule}` -> `commissions.rules.update`
  - `DELETE /finance/commissions/rules/{rule}` -> `commissions.rules.destroy`
  - `POST /finance/commissions/settle` -> `commissions.settle`

---

## 4. Arquitetura Frontend

- **`resources/js/types/commissions.ts`**: Tipagem TypeScript completa para regras, apurações, liquidações e resumos de profissionais.
- **`resources/js/pages/finance/commissions/index.tsx`**:
  - Painel com cartões de métricas (Total Pendente a Pagar, Liquidado este Mês, Total de Regras Ativas).
  - Tabela consolidada por profissional com saldo pendente, total liquidado, quantidade de atendimentos e atalho para o extrato.
  - Tabela de regras de comissionamento com status e ações.
  - Modal para criação e edição de regras com seleção de escopo (Geral, Serviço ou Produto) e tipo (Percentual ou Valor Fixo).
- **`resources/js/pages/finance/commissions/show.tsx`**:
  - Extrato analítico do profissional com filtros dinâmicos por status e intervalo de datas.
  - Tabela de itens comissionados com valor bruto, taxa/regra, valor apurado e status.
  - Histórico de liquidações passadas do profissional.
  - Modal de liquidação imediata com preenchimento da data de pagamento e observações.
- **`resources/js/components/app-sidebar.tsx`**:
  - Adicionado item "Comissões" na barra lateral de navegação no grupo **Financeiro**, protegido pela permissão `commission.view`.

---

## 5. Qualidade, Testes e Verificação

- **`tests/Feature/CommissionTest.php`** (6 testes, 61 asserções):
  - Apuração automática de comissões percentuais e fixas no fechamento de comanda (`FinalizeClosingSession`).
  - Priorização de regras específicas sobre regras genéricas (matching por score de especificidade).
  - Liquidação de comissões acumuladas (`SettleCommissions`), gerando settlement e atualizando status de lançamentos para `settled`.
  - Ciclo de vida de regras de comissão via controller (criação, edição com `lock_version` e exclusão).
  - Renderização das telas de index e extrato via Inertia.js.
  - Verificação de controle de acesso RBAC (`commission.view`, `commission.manage`, `commission.settle`).
- **Suíte Geral de Testes**: **237 testes executados, 212 aprovados, 0 falhas, 25 skipped**.
- **Pint Formatter**: Código PHP 100% formatado segundo as diretrizes do projeto (`vendor/bin/pint --format agent`).
- **Build Frontend**: Compilação de assets com sucesso via Vite e Laravel Wayfinder (`npm run build`).
