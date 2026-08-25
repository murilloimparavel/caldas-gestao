# Arquitetura — Sprint 2 da Fase 4: Ciclo de Vida da Comanda, Inclusão de Itens e Vínculos com a Agenda

- **Status:** Implementado e validado
- **Data:** 25/08/2026
- **Contexto:** PRD em `docs/PRD--comandas-categorias-e-checkout.md` e ADR-003 em `docs/adr/ADR-003--categorias-de-comanda-e-checkout-consolidado.md`
- **Escopo:** Operações de comanda (`Sale`), itens imutáveis (`SaleItem`), vínculos com a agenda (`AppointmentSaleLink`), histórico de transições (`SaleStatusHistory`), autorização via RBAC e concorrência otimista.

---

## 1. Visão Geral e Princípios

A Sprint 2 da Fase 4 implementa a camada de operações e mutações de comandas (`sales`) no Caldas Gestão, sustentando os seguintes princípios fundamentais:

1. **Unicidade de Contexto Ativo Idempotente:**
   - O backend deriva `open_context_key` com base no `uniqueness_scope` da categoria:
     - `customer`: `customer:{customer_id}`
     - `appointment`: `appointment:{appointment_id}`
     - `reference`: `reference:{slug(reference_label)}`
     - `none`: `null`
   - Se já existir uma comanda ativa (`draft`, `open`, `ready_to_bill`) na mesma unidade e categoria com a mesma chave, a ação `OpenSale` retorna a comanda existente sem gerar duplicatas.
   - O PostgreSQL assegura o bloqueio em nível de banco através do índice único parcial.

2. **Cardinalidade Explícita com Agenda:**
   - `Appointment 0..N Sale`: Um agendamento pode gerar múltiplas comandas (ex: comanda de Barbearia e comanda de Loja).
   - `Sale 0..1 Appointment`: Uma comanda possui no máximo 1 vínculo com agendamento no MVP, isolado na tabela `appointment_sale_links`.

3. **Snapshots Imutáveis:**
   - `Sale`: Preserva `category_key_snapshot` e `category_name_snapshot` no momento da abertura.
   - `SaleItem`: Preserva `name_snapshot`, `unit_price_cents`, `quantity`, `discount_cents` e `total_cents`. Alterações posteriores em cadastros de serviços ou produtos não modificam itens existentes no histórico de comandas.

4. **Cálculo Financeiro Seguro em Centavos (`Minor Units`):**
   - Todos os valores monetários (`unit_price_cents`, `discount_cents`, `total_cents`, `total_amount_cents`, `discount_amount_cents`, `final_amount_cents`) são manipulados exclusivamente como inteiros representando centavos (`BRL`), eliminando imprecisões de ponto flutuante.
   - O cálculo atômico é executado em cada inserção ou remoção de item e aplicação de desconto:
     $$\text{total\_cents} = (\text{unit\_price\_cents} \times \text{quantity}) - \text{discount\_cents}$$
     $$\text{total\_amount\_cents} = \sum \text{items.total\_cents}$$
     $$\text{final\_amount\_cents} = \max(0, \text{total\_amount\_cents} - \text{discount\_amount\_cents})$$

5. **Concorrência e Auditoria:**
   - Concorrência protegida por `lockForUpdate` transacional e checagem de `lock_version` (lançando HTTP 409 Conflict em divergência).
   - Registro append-only em `sale_status_histories` e gravação de eventos estruturados em `audit_events` e outbox.

---

## 2. Ações Implementadas (`app/Actions/Sales/`)

| Action | Responsabilidade | Permissão RBAC |
|---|---|---|
| `OpenSale` | Abertura de comanda avulsa ou vinculada à agenda, resolução de escopo de unicidade, reuso idempotente de comanda ativa, gravação de snapshot e histórico inicial. | `sale.manage` |
| `AddSaleItem` | Inclusão de itens (`service`, `product`, `custom`), validação de tipo compatível com a categoria, snapshot de preço/nome, recálculo atômico e incremento de versão. | `sale.manage` |
| `RemoveSaleItem` | Remoção de item em comanda mutável (`open`/`ready_to_bill`), recálculo atômico de totais e incremento de versão. | `sale.manage` |
| `ApplySaleDiscount` | Aplicação de desconto global na comanda com validação de teto e recálculo do valor final. | `sale.discount` |
| `TransitionSaleStatus` | Transições de status permitidas no ciclo de vida (`open` ↔ `ready_to_bill`, `draft`/`open`/`ready_to_bill` → `cancelled`) com registro em `sale_status_histories`. | `sale.manage` |

---

## 3. Matriz de Transições de Status

```text
       ┌───────────────┐
       │     draft     │
       └───────┬───────┘
               │
               ▼
       ┌───────────────┐        ┌─────────────────┐
       │     open      ├───────►│  ready_to_bill  │
       │               │◄───────┤                 │
       └───────┬───────┘        └────────┬────────┘
               │                         │
               │                         │
               ▼                         ▼
       ┌──────────────────────────────────────────┐
       │                cancelled                 │
       └──────────────────────────────────────────┘
```

- **Transições permitidas na Sprint 2:**
  - `draft` → `open`, `cancelled`
  - `open` → `ready_to_bill`, `cancelled`
  - `ready_to_bill` → `open`, `cancelled`
- **Estados terminais ou futuros:**
  - `finalized` (gerenciado na Sprint de Fechamento Consolidado / `ClosingSession`).

---

## 4. Endpoints e Mutações Operacionais

As rotas são protegidas por autenticação (`auth`, `verified`), contexto de tenant/unidade (`tenant.context`) e RBAC via Policies:

- `GET /sales` (`SaleController@index`) — Listagem paginada com filtros por status, cliente, categoria e busca textual.
- `GET /sales/{sale}` (`SaleController@show`) — Detalhes completos da comanda com itens, agendamento vinculado e histórico de status.
- `POST /sales` (`SaleController@store`) — Abertura de comanda via `OperationalMutation` (suporte a idempotência via `X-Idempotency-Key`).
- `POST /sales/{sale}/discount` (`SaleController@applyDiscount`) — Concessão de desconto global.
- `POST /sales/{sale}/transition` (`SaleController@transitionStatus`) — Transição de estado operacional.
- `POST /sales/{sale}/items` (`SaleItemController@store`) — Adição de itens de serviço, produto ou customizado.
- `DELETE /sales/{sale}/items/{item}` (`SaleItemController@destroy`) — Remoção de item.

---

## 5. Cobertura de Testes

Os testes automatizados em `tests/Feature/SaleOperationsTest.php` cobrem 100% dos fluxos e regras:

1. Abertura de comanda avulsa (sem agendamento) com snapshots e auditoria.
2. Abertura vinculada à agenda respeitando cardinalidade `Appointment 0..N Sale` e `Sale 0..1 Appointment`.
3. Unicidade por escopo (`customer`, `appointment`, `reference`, `none`) e idempotência ao abrir comanda já existente.
4. Adição de itens de serviço, produto e customizados com cálculo exato de centavos.
5. Rejeição de tipos de item incompatíveis com a categoria (ex: produto em categoria de serviço).
6. Remoção de item e recálculo atômico dos totais da comanda.
7. Aplicação de desconto com verificação de permissão `sale.discount` e bloqueio para não autorizados.
8. Concorrência otimista com rejeição HTTP 409 para `lock_version` desatualizado.
9. Transições do ciclo de vida com rastreabilidade em `sale_status_histories`.
10. Repetição de mutação idempotente via header `X-Idempotency-Key`.
