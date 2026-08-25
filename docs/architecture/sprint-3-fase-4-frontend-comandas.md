# Sprint 3 - Fase 4: Frontend de Gestão de Comandas

**Status**: Concluído  
**Data**: 25/08/2026  
**Stack**: Laravel 13, Inertia.js v3, React 19, Tailwind CSS v4, Wayfinder, Pest PHP  

---

## 1. Visão Geral e Objetivos

A **Sprint 3 da Fase 4** entrega a interface operacional completa de **Gestão de Comandas (Sales Frontend)**, conectando o ciclo de vida das comandas com o design system do Caldas Gestão, as rotas digitadas do Wayfinder e a agenda de atendimentos.

---

## 2. Componentes e Telas Implementadas

### 2.1 Painel de Comandas (`resources/js/pages/sales/index.tsx`)
- **Layout Operacional (`PageCanvas`)**:
  - Cabeçalho padronizado com `ResourceHeader` exibindo título, subtítulo e ação para abertura rápida de nova comanda.
  - **Cards de Métricas Operacionais**:
    - Total de comandas abertas (`open`).
    - Comandas prontas para faturamento (`ready_to_bill`).
    - Faturamento em aberto hoje (R$) considerando o fuso horário da unidade ativa.
- **Filtros e Busca (`SearchToolbar`)**:
  - Busca textual por cliente, identificador/mesa e snapshot de categoria.
  - Filtro por status (`open`, `ready_to_bill`, `finalized`, `cancelled`, `draft`).
  - Filtro por Categoria de Comanda e Cliente.
- **Listagem e Grade de Comandas**:
  - Status badges com cores semânticas (`open` azul, `ready_to_bill` âmbar, `finalized` esmeralda, `cancelled` destrutivo).
  - Multi-seleção de comandas por checkbox com barra de ações flutuante (resumo de itens selecionados e total acumulado em R$).
  - Exibição de identificador/mesa, cliente vinculado, categoria, total final em R$, itens e horário formatado.
- **Modal "Nova Comanda"**:
  - Seleção de Categoria de Comanda (obrigatório).
  - Autocomplete/Select de Cliente.
  - Campo de Identificador / Mesa / Referência.
  - Notas adicionais e envio com chave de idempotência (`X-Idempotency-Key`) via Wayfinder `sales.store`.

### 2.2 Detalhe e Edição da Comanda (`resources/js/pages/sales/show.tsx`)
- **Cabeçalho Operacional e Transições de Status**:
  - Link de retorno para a listagem (`/sales`).
  - Identificação de cliente, categoria, total e status badge.
  - Ações contextuais de ciclo de vida:
    - `open` -> "Marcar Pronto para Fechar" (`POST /sales/{sale}/transition` com `status: 'ready_to_bill'`).
    - `ready_to_bill` -> "Reabrir Comanda" (`POST /sales/{sale}/transition` com `status: 'open'`).
    - `open` ou `ready_to_bill` -> "Cancelar Comanda" com diálogo obrigatório para informar o motivo.
- **Tabela de Itens da Comanda**:
  - Exibição de tipo de item com badges (`Serviço`, `Produto`, `Personalizado`), nome, profissional executor, valor unitário, desconto por item e subtotal calculado.
  - Exclusão atômica de itens com diálogo de confirmação (`DELETE /sales/{sale}/items/{item}`).
- **Modal "Adicionar Item"**:
  - Alternância entre Serviço, Produto ou Item Personalizado.
  - Carregamento de catálogo ativo com preenchimento automático de preços e durações.
  - Seleção de profissional responsável, quantidade e desconto individual.
- **Resumo Financeiro Lateral**:
  - Total Bruto, Descontos aplicados e Total a Pagar com destaque tipográfico.
  - Modal "Aplicar Desconto Geral" com valor em reais (R$) e justificativa (`POST /sales/{sale}/discount`).
  - Card de dados do cliente e agendamento vinculado.
  - Linha do tempo com histórico de status (`SaleStatusHistory`).

### 2.3 Integração com a Agenda (`resources/js/pages/calendar/index.tsx`)
- No diálogo de detalhes do agendamento:
  - Se houver comanda vinculada (`saleLink`), exibe o badge do status e o botão "Ver Comanda" direcionando para `/sales/{sale.id}`.
  - Se não houver comanda aberta, exibe o botão "Abrir Comanda", abrindo diálogo rápido com seleção de categoria e criando a comanda via `POST /sales` com redirecionamento imediato.

### 2.4 Navegação (`resources/js/components/app-sidebar.tsx`)
- Habilitado o item "Agenda" apontando para `calendar.index()` com permissão `appointment.view`.
- Habilitado o item "Comandas" apontando para `sales.index()` com permissão `sale.view`.
- Adicionado o item "Categorias de Comanda" sob a seção "Gestão" apontando para `saleCategories.index()` com permissão `sale_category.view`.

---

## 3. Enriquecimento de Dados no Backend

- **`Appointment` Model**:
  - Relacionamentos `saleLinks(): HasMany` e `saleLink(): HasOne` com `AppointmentSaleLink`.
- **`SaleController`**:
  - `index()`: Enriquecido com `categories` ativas, `customers` ativos e `metrics` operacionais calculadas com suporte a timezone.
  - `show()`: Enriquecido com `services`, `products`, `professionals` e `categories` ativas para alimentação dos seletores.
- **`CalendarController`**:
  - Eager loading de `saleLinks.sale:id,status,reference_label` e inclusão de `sale_categories` nas opções do Inertia.

---

## 4. Validação e Testes

- **Wayfinder Route Generation**:
  - Executado `php artisan wayfinder:generate` garantindo sincronização de TypeScript types e form actions.
- **Pest PHP Test Suite**:
  - Executado `php artisan test --compact tests/Feature/Sale* tests/Feature/Calendar*`:
    - 31 testes executados, 31 aprovados (100% assertions ok).
- **Frontend Build**:
  - Executado `npm run build` com sucesso sem erros de lint ou compilação Vite/React/TypeScript.
- **Formatação de Código**:
  - Executado `vendor/bin/pint --dirty --format agent` para conformidade com PSR-12 e Laravel Pint.
