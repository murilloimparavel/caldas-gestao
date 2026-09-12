# Sprint 1 — DX & Quick Wins (Modernização Frontend)

## 🎯 Objetivo
Executar as fundações de Developer Experience (DX) e melhorias rápidas de acessibilidade, tipografia e compatibilidade no Caldas Gestão, alinhando a base de código aos padrões do Tailwind CSS v4, React 19 / Inertia v3 e WCAG 2.2 AA.

---

## 📦 Detalhamento das Entregas

### 1.1 Ajuste de Ignores do ESLint
- **Arquivo Modificado**: [`eslint.config.js`](file:///Users/murilloalves/Projects/CRCN/sistemas/caldas-gestao/.worktrees/frontend-modernization/eslint.config.js)
- **Modificações**:
  - Adicionados padrões globais de exclusão no array `ignores`: `.worktrees/**`, `**/.worktrees/**`, `vendor/**`, `**/vendor/**`, `node_modules/**`, `public/**` e `bootstrap/ssr/**`.
  - Removida referência ao arquivo obsoleto `tailwind.config.js` (Tailwind v4 utiliza abordagem CSS-first).
- **Validação**: `npm run lint:check` rodando com 0 erros e 0 avisos em toda a árvore.

### 1.2 Template de IA (.cursorrules)
- **Arquivo Criado**: [`.cursorrules`](file:///Users/murilloalves/Projects/CRCN/sistemas/caldas-gestao/.worktrees/frontend-modernization/.cursorrules)
- **Diretrizes Consolidadas**:
  - **Stack**: Laravel 12 + Inertia.js v3 + React 19 + TypeScript 5.7 + Tailwind CSS v4.0 + Wayfinder.
  - **React Compiler**: Diretiva explícita para não utilizar `useMemo`/`useCallback` prematuramente.
  - **Padronização de Classes**: Obrigatoriedade de `cn(...)` de `@/lib/utils` (sem concatenação manual de strings de classe).
  - **Tailwind v4**: Configuração estritamente CSS-first em `resources/css/app.css` sob `@theme`. Proibição de `focus:outline-none` sem anéis de foco acessíveis correspondentes.
  - **Acessibilidade (WCAG 2.2)**: Botões com apenas ícone obrigatoriamente semantizados com `<span className="sr-only">` ou `aria-label`; ícones decorativos marcados com `aria-hidden="true"`.
  - **Inertia v3**: Uso nativo de `<Form>`, `useHttp` ou `router` em vez de chamadas diretas a `fetch`/`axios` com leitura de token CSRF no DOM.

### 1.3 Tokens Tipográficos de Microcopy
- **Arquivo Modificado**: [`resources/css/app.css`](file:///Users/murilloalves/Projects/CRCN/sistemas/caldas-gestao/.worktrees/frontend-modernization/resources/css/app.css)
  - Adicionados ao bloco `@theme`:
    ```css
    --text-2xs: 0.625rem;
    --text-2xs--line-height: 0.875rem;
    --text-3xs: 0.6875rem;
    --text-3xs--line-height: 1rem;
    ```
- **Refatoração Global em `resources/js/`**:
  - 39 ocorrências de `text-[10px]` substituídas pela utility class de design token `text-2xs`.
  - 72 ocorrências de `text-[11px]` substituídas pela utility class de design token `text-3xs`.
  - Componentes e páginas beneficiados: `app-logo.tsx`, `calendar/index.tsx`, `mobile-bottom-nav.tsx`, `operational/index.tsx`, `passkey-item.tsx`, `image-uploader.tsx`, `dashboard-panel.tsx`, `next-appointments.tsx`, `professional-performance-table.tsx`, `sales-category-breakdown.tsx`, `schedule-heatmap.tsx`, `status-donut-chart.tsx`, `visits-trend-chart.tsx`, `closing-sessions/show.tsx`, `customers/show.tsx`, `finance/dashboard.tsx`, `inventory/index.tsx`, `marketing/home.tsx`, `online-booking/*`, `packages/*`, `products/show.tsx`, `professionals/show.tsx`, `sales/index.tsx`, `subscriptions/index.tsx`.

### 1.4 Correção dos 17 Focos Invisíveis (WCAG 2.4.7)
- **Problema**: O uso de `focus:outline-none` suprimia a percepção de foco por navegação via teclado, violando o critério WCAG 2.4.7 (Focus Visible).
- **Substituições Aplicadas**:
  - **Links e Botões de Ação Inline** (`hover:underline focus:outline-none`):
    Substituídos por: `hover:underline focus:outline-hidden focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-1 rounded-xs`
    - [`resources/js/pages/services/index.tsx`](file:///Users/murilloalves/Projects/CRCN/sistemas/caldas-gestao/.worktrees/frontend-modernization/resources/js/pages/services/index.tsx#L276): Ação "+ Novo Profissional"
    - [`resources/js/pages/sales/show.tsx`](file:///Users/murilloalves/Projects/CRCN/sistemas/caldas-gestao/.worktrees/frontend-modernization/resources/js/pages/sales/show.tsx#L908): Ação "+ Novo Serviço"
    - [`resources/js/pages/sales/show.tsx`](file:///Users/murilloalves/Projects/CRCN/sistemas/caldas-gestao/.worktrees/frontend-modernization/resources/js/pages/sales/show.tsx#L1027): Ação "+ Novo Produto"
    - [`resources/js/pages/finance/transactions/index.tsx`](file:///Users/murilloalves/Projects/CRCN/sistemas/caldas-gestao/.worktrees/frontend-modernization/resources/js/pages/finance/transactions/index.tsx#L417): Ação "+ Nova Categoria"
    - [`resources/js/pages/finance/transactions/index.tsx`](file:///Users/murilloalves/Projects/CRCN/sistemas/caldas-gestao/.worktrees/frontend-modernization/resources/js/pages/finance/transactions/index.tsx#L481): Ação "+ Novo Fornecedor"
    - [`resources/js/pages/finance/transactions/index.tsx`](file:///Users/murilloalves/Projects/CRCN/sistemas/caldas-gestao/.worktrees/frontend-modernization/resources/js/pages/finance/transactions/index.tsx#L548): Ação "+ Novo Cliente"
    - [`resources/js/pages/products/index.tsx`](file:///Users/murilloalves/Projects/CRCN/sistemas/caldas-gestao/.worktrees/frontend-modernization/resources/js/pages/products/index.tsx#L286): Ação "+ Nova Categoria"
    - [`resources/js/pages/calendar/index.tsx`](file:///Users/murilloalves/Projects/CRCN/sistemas/caldas-gestao/.worktrees/frontend-modernization/resources/js/pages/calendar/index.tsx#L1495): Ação "+ Novo Profissional"
  - **Campos de Seleção (`select`)**:
    Substituídos por: `focus:outline-hidden focus-visible:ring-1 focus-visible:ring-ring`
    - [`resources/js/pages/finance/transactions/index.tsx`](file:///Users/murilloalves/Projects/CRCN/sistemas/caldas-gestao/.worktrees/frontend-modernization/resources/js/pages/finance/transactions/index.tsx#L436): Select de Categoria no formulário de despesa/receita
    - [`resources/js/pages/finance/transactions/index.tsx`](file:///Users/murilloalves/Projects/CRCN/sistemas/caldas-gestao/.worktrees/frontend-modernization/resources/js/pages/finance/transactions/index.tsx#L503): Select de Fornecedor
    - [`resources/js/pages/finance/transactions/index.tsx`](file:///Users/murilloalves/Projects/CRCN/sistemas/caldas-gestao/.worktrees/frontend-modernization/resources/js/pages/finance/transactions/index.tsx#L570): Select de Cliente
    - [`resources/js/pages/finance/transactions/index.tsx`](file:///Users/murilloalves/Projects/CRCN/sistemas/caldas-gestao/.worktrees/frontend-modernization/resources/js/pages/finance/transactions/index.tsx#L860): Select de Filtro de Categoria
    - [`resources/js/pages/finance/transactions/index.tsx`](file:///Users/murilloalves/Projects/CRCN/sistemas/caldas-gestao/.worktrees/frontend-modernization/resources/js/pages/finance/transactions/index.tsx#L1268): Select de Categoria em Edição de Obrigação
    - [`resources/js/pages/finance/transactions/index.tsx`](file:///Users/murilloalves/Projects/CRCN/sistemas/caldas-gestao/.worktrees/frontend-modernization/resources/js/pages/finance/transactions/index.tsx#L1307): Select de Fornecedor em Edição de Obrigação
    - [`resources/js/pages/finance/transactions/index.tsx`](file:///Users/murilloalves/Projects/CRCN/sistemas/caldas-gestao/.worktrees/frontend-modernization/resources/js/pages/finance/transactions/index.tsx#L1349): Select de Cliente em Edição de Obrigação
    - [`resources/js/pages/finance/transactions/index.tsx`](file:///Users/murilloalves/Projects/CRCN/sistemas/caldas-gestao/.worktrees/frontend-modernization/resources/js/pages/finance/transactions/index.tsx#L1473): Select de Forma de Pagamento ao liquidar
  - **Menu de Navegação Base**:
    - [`resources/js/components/ui/navigation-menu.tsx`](file:///Users/murilloalves/Projects/CRCN/sistemas/caldas-gestao/.worktrees/frontend-modernization/resources/js/components/ui/navigation-menu.tsx#L94): Substituído seletor de supressão por anel de foco visível acessível (`focus:outline-hidden focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-1`).
- **Resultado**: 0 ocorrências de `focus:outline-none` remanescentes no código.

### 1.5 Correção da Fonte no Blade
- **Arquivo Modificado**: [`resources/views/app.blade.php`](file:///Users/murilloalves/Projects/CRCN/sistemas/caldas-gestao/.worktrees/frontend-modernization/resources/views/app.blade.php#L69-L70)
- **Modificações**:
  - Remoção da diretiva morta `@fonts`.
  - Inclusão dos links de pré-conexão e folha de estilo de alta performance via Bunny Fonts para a família tipográfica `Inter`:
    ```html
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />
    ```

---

## 🧪 Evidências e Validações

Todas as etapas de verificação e gates de qualidade estática foram executadas na worktree `frontend-modernization`:

| Validação | Comando | Resultado |
|---|---|---|
| **Linter** | `npm run lint:check` | ✅ 0 erros, 0 avisos |
| **Tipagem** | `npm run types:check` | ✅ 0 erros de TypeScript |
| **Build** | `npm run build` | ✅ Compilação bem-sucedida em 7.17s com bundle gerado em `public/build/` |
| **Acessibilidade de Foco** | `grep -rn "focus:outline-none" resources/js/` | ✅ 0 ocorrências (17/17 corrigidas) |
| **Tokens Tipográficos** | `grep -rn "text-\[10px\]" resources/js/` & `text-\[11px\]` | ✅ 0 ocorrências (todas migradas para `text-2xs` e `text-3xs`) |
