# Sprint 4 - Fase 4: Fechamento Consolidado (ClosingSession), Recibo Interno e Auditoria

**Status**: Concluído  
**Data**: 25/08/2026  
**Stack**: Laravel 13, Inertia.js v3, React 19, Tailwind CSS v4, Wayfinder, Pest PHP  
**Referências**: [PRD - Comandas, Categorias & Checkout](/docs/PRD--comandas-categorias-e-checkout.md) | [ADR-003](/docs/adr/ADR-003--categorias-de-comanda-e-checkout-consolidado.md)

---

## 1. Visão Geral e Objetivos

A **Sprint 4 da Fase 4** implementa a finalização do ciclo de vida das comandas operacionais do Caldas Gestão através do **Fechamento Consolidado (`ClosingSession`)**, emissão do **Recibo Interno**, transição atômica de status para `finalized`, gravação em `sale_status_histories`, rastreabilidade por auditoria e outbox events, e idempotência com proteção contra concorrência via `lock_version`.

> **Nota de Escopo**: Em estrita conformidade com o PRD e o ADR-003, pagamentos externos, gateways de pagamento, maquininhas, Pix e TEF pertencem à **Fase 5 (Financeiro e Caixa)**. Esta sprint consolida o fechamento das comandas operacionais emitindo o recibo interno da sessão.

---

## 2. Invariantes de Domínio e Regras de Negócio

1. **Trava Determinística e Concorrência Atômica**:
   - As comandas selecionadas são bloqueadas em ordem determinística (`orderBy('id')->lockForUpdate()`) dentro de uma transação de banco com isolamento, prevenindo deadlocks em acessos concorrentes.
   - Suporte a `lock_versions` por comanda: se qualquer comanda tiver sido modificada concorrentemente, a operação falha com `409 Conflict`.

2. **Validação Estrita de Sujeito Uniforme (`closing_subject`)**:
   - **Mesmo Cliente**: Se as comandas possuírem cliente vinculado, todas as comandas do lote devem pertencer ao mesmo `customer_id`. `closing_subject` = `customer:{customer_id}`.
   - **Mesma Referência**: Se as comandas não possuírem cliente, mas possuírem identificador/mesa, todas devem ter a mesma `reference_label`. `closing_subject` = `reference:{slug}`.
   - **Comanda Avulsa sem Identificação**: Comandas avulsas anônimas só podem ser fechadas individualmente. `closing_subject` = `sale:{sale_id}`.
   - Lotes divergentes são rejeitados com `422 Unprocessable Entity`.

3. **Validação de Status e Unidade**:
   - Apenas comandas com status `draft`, `open` ou `ready_to_bill` pertencentes ao mesmo `tenant_id` e `unit_id` podem ser fechadas.
   - Comandas já finalizadas (`finalized`) ou canceladas (`cancelled`) são rejeitadas.

4. **Consistência Financeira e Recibo Interno**:
   - Validação de `expected_total_cents` quando informado pelo operador: impede fechamento se o total da comanda mudou durante a conferência.
   - Geração de número único de recibo interno: `REC-YYYYMMDD-XXXXXX`.
   - Snapshot imutável no `receipt_payload`: dados do estabelecimento, unidade, operador, cliente, totais (bruto, descontos, final), listagem detalhada de itens com snapshots de preços e profissionais.

5. **Transição de Status e Auditoria**:
   - Atualização de status da comanda para `finalized` e incremento de `lock_version`.
   - Inserção em `sale_status_histories` com o motivo contendo o ID da sessão de fechamento.
   - Associação na tabela pivô `closing_session_sales`.
   - Disparo de eventos de auditoria e outbox: `sale.finalized` e `closing_session.completed`.
   - Idempotência operacional suportada via cabeçalho `X-Idempotency-Key` e mutation governance.

---

## 3. Arquitetura e Implementação Backend

### 3.1 Ação de Fechamento (`app/Actions/Closing/FinalizeClosingSession.php`)
- Classe que herda de `OperationalAction`.
- Valida permissões `sale.close` ou `sale.manage` no contexto da unidade.
- Executa a transação atômica completa, construindo o recibo interno e atualizando comandas e histórico.

### 3.2 Request e Policy
- **`app/Http/Requests/ClosingSessionRequest.php`**: Valida `sale_ids` (array de UUIDs obrigatório), `expected_total_cents`, `notes` e `lock_versions`.
- **`app/Policies/ClosingSessionPolicy.php`**: Valida permissões de visualização e criação no tenant/unit context.

### 3.3 Controller e Rotas
- **`app/Http/Controllers/ClosingSessionController.php`**:
  - `show(ClosingSession $closingSession)`: Renderiza `closing-sessions/show` com eager loading completo de itens, serviços, produtos, profissionais, cliente e operador.
  - `store(ClosingSessionRequest $request)`: Envelopado por `OperationalMutation::execute` para suporte nativo a idempotência.
- **`routes/web.php`**:
  - `Route::resource('closing-sessions', ClosingSessionController::class)->only(['show', 'store']);`

### 3.4 Governança de Payload (`app/Support/PayloadGovernance.php`)
- Ajuste de filtros de chaves proibidas para garantir que chaves de domínio como `closing_session_id`, `closing_session` e `receipt_number` sejam devidamente auditadas sem colisão com fragmentos genéricos de segurança (`session`, `ip`).

---

## 4. Implementação Frontend

### 4.1 Visualização e Impressão do Recibo Interno (`resources/js/pages/closing-sessions/show.tsx`)
- Visualização completa dos dados da sessão de fechamento.
- **Modo de Impressão Integrado**:
  - CSS otimizado para `@media print` e visualização tipo cupom/ticket térmico.
  - Botão "Imprimir Recibo" acionando `window.print()`.
  - Cabeçalho do estabelecimento, dados da comanda/cliente, detalhamento de itens por comanda com profissionais executores, resumo de totais e notas.
  - Botões para "Voltar para Comandas" ou "Nova Comanda".

### 4.2 Fechamento em Lote no Painel (`resources/js/pages/sales/index.tsx`)
- Barra flutuante de multi-seleção de comandas ativas.
- Botão "Fechar selecionadas" com validação de cliente/referência antes de abrir o modal.
- Diálogo de Fechamento Consolidado com resumo das comandas selecionadas, total bruto, descontos e total a pagar.
- Campo de notas adicionais e envio via `closingSessions.store.post()`.

### 4.3 Fechamento Individual no Detalhe da Comanda (`resources/js/pages/sales/show.tsx`)
- Botão "Fechar Comanda" com diálogo de confirmação rápida para comandas ativas (`open`, `ready_to_bill`).
- Botão "Ver Recibo Interno" apontando para `closing-sessions.show` quando a comanda estiver com status `finalized`.

---

## 5. Testes e Validação de Qualidade

### 5.1 Suíte de Testes Feature (`tests/Feature/ClosingSessionTest.php`)
14 testes automatizados cobrindo 100% dos fluxos e regras de negócio:
- `it('finalizes a single sale closing session, generating receipt payload, status histories and audit events')`
- `it('consolidates multiple sales for the same customer into a single closing session')`
- `it('consolidates multiple customer-less sales with the same reference_label')`
- `it('rejects closing session with divergent customers')`
- `it('rejects closing session mixing customer sale and reference sale')`
- `it('rejects closing session if any sale is already finalized or cancelled')`
- `it('rejects closing session if lock_version does not match expected version')`
- `it('supports idempotency via X-Idempotency-Key without re-executing or creating duplicates')`
- `it('renders the internal receipt on closing-sessions.show')`
- `it('closes a single anonymous sale without customer or reference label with sale:{id} subject')`
- `it('rejects multiple anonymous sales without customer or reference label')`
- `it('rejects closing session if expected_total_cents does not match calculated total')`
- `it('rejects closing session if user does not have sale.close or sale.manage permission')`
- `it('rejects closing session for sales belonging to a different unit or workspace')`

### 5.2 Resultados
- **Pest PHP**: 212 testes executados no projeto, 187 aprovados, 25 skipped, 0 falhas (1122 asserções).
- **Vite / TypeScript**: `npm run build` executado com sucesso (zero erros).
- **Laravel Pint**: Código formatado no padrão do projeto.
