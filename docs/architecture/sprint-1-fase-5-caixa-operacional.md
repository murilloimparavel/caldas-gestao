# Sprint 1 - Fase 5: Caixa Operacional (CashShift & CashMovement)

**Status**: Concluído  
**Data**: 25/08/2026  
**Stack**: Laravel 13, Inertia.js v3, React 19, Tailwind CSS v4, Wayfinder, Pest PHP  
**Contexto**: Fase 5 - Módulo Financeiro e Fluxo de Caixa

---

## 1. Visão Geral e Objetivos

A **Sprint 1 da Fase 5** introduz a fundação financeira e o controle de **Caixa Operacional** no Caldas Gestão. O módulo é responsável pelo controle rigoroso de turnos de operadores de frente de caixa (`CashShift`) e suas movimentações financeiras em dinheiro (`CashMovement`), garantindo isolamento multi-tenant, bloqueio transacional com `lockForUpdate()`, controle de concorrência com `lock_version`, valores monetários estritamente em centavos inteiros (`minor units`), eventos de auditoria e interface moderna e responsiva.

---

## 2. Invariantes de Domínio e Regras de Negócio

1. **Ciclo de Vida do Turno de Caixa (`CashShift`)**:
   - **Abertura (`OpenCashShift`)**: O operador inicia o turno definindo o fundo de troco inicial (`initial_amount_cents`). O saldo esperado inicial (`expected_amount_cents`) assume este valor. Valida-se que o operador não possua outro turno ativo (`status = 'open'`) na mesma unidade operacional.
   - **Movimentações (`RecordCashMovement`)**: Durante a operação, entradas (suprimento `supply`, recebimento de venda `sale_inflow`) e saídas (sangria `bleed`, comissão `commission_outflow`, despesa `expense_outflow`) alteram o saldo esperado (`expected_amount_cents`) de forma transacional e atômica.
   - **Fechamento e Conferência Cega/Física (`CloseCashShift`)**: No encerramento, o operador informa o valor físico contado na gaveta (`final_amount_cents`). O sistema calcula e persiste a diferença (`difference_cents = final_amount_cents - expected_amount_cents`):
     - `0`: Caixa exato (sem divergência).
     - `> 0`: Sobra de caixa.
     - `< 0`: Quebra / falta de caixa.
   - O turno fechado (`status = 'closed'`) recebe carimbo de data/hora (`closed_at`), responsável pelo fechamento (`closed_by_user_id`), e é bloqueado para novas movimentações.

2. **Proteção Transacional e Concorrência**:
   - Toda alteração no turno de caixa utiliza `lockForUpdate()` sobre o registro do turno dentro de transação `DB::transaction()`.
   - Suporte a `lock_version`: requisições com versão desatualizada sofrem rollback e retornam `409 Conflict`.
   - Sangrias e saídas não podem exceder o saldo esperado em gaveta disponível.

3. **Valores Monetários e Minor Units**:
   - Todos os valores são armazenados como inteiros em centavos (`amount_cents`, `initial_amount_cents`, `expected_amount_cents`, `final_amount_cents`, `difference_cents`), prevenindo erros de arredondamento de ponto flutuante.

4. **Auditoria e Governança**:
   - Eventos emitidos via `IdentityEventRecorder`: `cash_shift.opened`, `cash_shift.moved` e `cash_shift.closed`.
   - Permissões RBAC catalogadas em `OwnerPermissionCatalog`: `cash_shift.view`, `cash_shift.open`, `cash_shift.move`, `cash_shift.close`.
   - Chaves auditadas catalogadas em `PayloadGovernance`.

---

## 3. Arquitetura Backend

### 3.1 Modelos e Migrations
- **`database/migrations/2026_08_26_000001_create_cash_shifts_table.php`**: Tabela de turnos com chave primária UUIDv7, tenant/unit scoping, status enum `['open', 'closed']`, timestamps e soft deletes.
- **`database/migrations/2026_08_26_000002_create_cash_movements_table.php`**: Tabela de movimentações avulsas (`supply`, `bleed`, `sale_inflow`, `commission_outflow`, `expense_outflow`) com chave estrangeira para o turno e usuário operador.
- **`app/Models/CashShift.php`** e **`app/Models/CashMovement.php`**: Modelos Eloquent com tipagem rigorosa, relacionamentos e factories correspondentes.

### 3.2 Actions em `app/Actions/Finance/Cash/`
- **`OpenCashShift`**: Executa a abertura garantindo ausência de duplicidade para o operador/unidade, com registro de auditoria.
- **`RecordCashMovement`**: Executa suprimentos e sangrias com `lockForUpdate()`, atualização do `expected_amount_cents` e incremento de `lock_version`.
- **`CloseCashShift`**: Executa a conferência cega/física, persistência dos valores finais e cálculo da quebra/sobra.

### 3.3 Camada HTTP, Policies e Rotas
- **Requests**: `OpenCashShiftRequest`, `CashMovementRequest`, `CloseCashShiftRequest`.
- **Policy**: `CashShiftPolicy` (mapeando `cash_shift.view`, `cash_shift.open`, `cash_shift.move`, `cash_shift.close`).
- **Controller**: `CashShiftController` com endpoints `index`, `show`, `store`, `move`, `close`, `history`.
- **Rotas** em `routes/web.php` sob o middleware `tenant.context`.

---

## 4. Arquitetura Frontend

- **`resources/js/pages/finance/cash/index.tsx`**:
  - Painel do Caixa Operacional ativo: cartões de métricas (Saldo em gaveta, Fundo inicial, Entradas totais, Saídas totais), operador responsável e horário de abertura.
  - Modais interativos com máscaras em R$, validações e idempotência:
    - *Abrir Turno de Caixa*: definição do fundo de troco.
    - *Suprimento (+)*: entrada de dinheiro avulsa na gaveta.
    - *Sangria (-)*: retirada de dinheiro para cofre/banco com verificação de saldo disponível.
    - *Fechar Turno*: conferência física com cálculo em tempo real de sobras ou quebras de caixa.
  - Tabela com histórico detalhado das movimentações do turno ativo.
- **`resources/js/pages/finance/cash/history.tsx`**:
  - Histórico paginado de todos os turnos de caixa passados com filtros por status e data.
- **`resources/js/pages/finance/cash/show.tsx`**:
  - Visão detalhada de turno de caixa com opção de impressão do relatório de fechamento.
- **`resources/js/components/app-sidebar.tsx`**:
  - Adicionado item "Caixa Operacional" no grupo "Operação" com ícone `Banknote` e checagem de permissão `cash_shift.view`.

---

## 5. Qualidade e Testes

- Testes de Feature cobrindo 100% dos cenários em `tests/Feature/CashShiftTest.php` (9 testes, 83 asserções):
  - Abertura de turno e registro de auditoria.
  - Bloqueio de múltiplos caixas abertos concorrentes para o mesmo operador.
  - Registro de suprimento com incremento de saldo esperado e versão de lock.
  - Registro de sangria com decremento de saldo esperado.
  - Rejeição de sangria que exceda o saldo em gaveta.
  - Fechamento com conferência (exato, quebra/falta e sobra) e registro de auditoria.
  - Prevenção de movimentações em caixa fechado.
  - Proteção contra concorrência via `lock_version` (409 Conflict).
  - Renderização das páginas Inertia (`index`, `show`, `history`).
- Suíte geral de testes do projeto: **221 testes executados, 196 aprovados, 0 falhas**.
- Formatação de código validada via Laravel Pint (`vendor/bin/pint --dirty --format agent`).
- Compilação de assets e geração de tipagem Wayfinder verificada com `npm run build`.
