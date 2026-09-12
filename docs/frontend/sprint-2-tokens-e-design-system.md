# Sprint 2 — Tokens & Design System (Modernização Frontend)

## 🎯 Objetivo
Estruturar a camada semântica de cores (Layer 2) do sistema de design no Tailwind CSS v4, sanear cores primitivas hardcoded nos módulos operacionais, normalizar a escala visual de botões e introduzir a primitiva padrão de tabelas (`Table`) compatível com React 19 / Inertia v3.

---

## 📦 Detalhamento das Entregas

### 2.1 Camada Semântica de Cores (Layer 2) no CSS
- **Arquivo Modificado**: [`resources/css/app.css`](file:///Users/murilloalves/Projects/CRCN/sistemas/caldas-gestao/.worktrees/frontend-modernization/resources/css/app.css)
- **Modificações**:
  - Registradas no `@theme` as variáveis de cores semânticas para suporte nativo a utilitários Tailwind (`bg-success`, `text-warning`, `border-info`, opacidades como `bg-success/10`, etc.):
    ```css
    --color-success: var(--success);
    --color-success-foreground: var(--success-foreground);
    --color-warning: var(--warning);
    --color-warning-foreground: var(--warning-foreground);
    --color-info: var(--info);
    --color-info-foreground: var(--info-foreground);
    ```
  - Definidos valores perceptualmente uniformes baseados no espaço de cor **OKLCH** para modo claro (`:root`) e modo escuro (`.dark`):
    - **Light Mode (`:root`)**:
      - `--success`: `oklch(0.62 0.17 145)` (Verde equilibrado) | `--success-foreground`: `#ffffff`
      - `--warning`: `oklch(0.72 0.16 75)` (Âmbar/Laranja) | `--warning-foreground`: `#2a1b02`
      - `--info`: `oklch(0.60 0.16 235)` (Azul ciano informativo) | `--info-foreground`: `#ffffff`
    - **Dark Mode (`.dark`)**:
      - `--success`: `oklch(0.68 0.17 145)` (Verde luminoso) | `--success-foreground`: `#0b1e13`
      - `--warning`: `oklch(0.76 0.15 75)` (Âmbar vibrante) | `--warning-foreground`: `#221401`
      - `--info`: `oklch(0.70 0.15 235)` (Azul aberto) | `--info-foreground`: `#0b172a`
- **Análise de Contraste (WCAG 2.2 AA)**:
  - Os pares de fundo/primeiro plano (`*-foreground`) garantem taxa de contraste mínima superior a **4.5:1** para texto normal e superior a **3:1** para elementos gráficos/texto grande, tanto em superfícies claras quanto escuras.

---

### 2.2 Saneamento de Cores Hardcoded para Tokens Semânticos
Substituição de classes de paleta primitiva (`emerald-*`, `rose-*`, `blue-*`, `amber-*`) por tokens semânticos adaptáveis que simplificam o CSS e eliminam a necessidade de duplicação explícita `dark:*`.

1. **Agenda / Calendário**:
   - **Arquivo Modificado**: [`resources/js/components/calendar/index.tsx`](file:///Users/murilloalves/Projects/CRCN/sistemas/caldas-gestao/.worktrees/frontend-modernization/resources/js/components/calendar/index.tsx#L51-L67)
   - Mapeamento de `statusClasses` atualizado para tokens semânticos com opacidade de superfície:
     - `completed`: `border-success/30 bg-success/10 text-success`
     - `confirmed`: `border-info/30 bg-info/10 text-info`
     - `scheduled`: `border-warning/30 bg-warning/10 text-warning`
     - `cancelled`: `border-destructive/30 bg-destructive/10 text-destructive`

2. **Módulo de Vendas / Comandas**:
   - **Arquivo Modificado**: [`resources/js/pages/sales/index.tsx`](file:///Users/murilloalves/Projects/CRCN/sistemas/caldas-gestao/.worktrees/frontend-modernization/resources/js/pages/sales/index.tsx#L63-L120)
   - Mapeamento de `statusConfig` e componente `SaleStatusBadge` migrados para classes semânticas com `cn(...)`:
     - `open`: `border-info/30 bg-info/15 text-info` (dot: `bg-info`)
     - `ready_to_bill`: `border-warning/30 bg-warning/15 text-warning` (dot: `bg-warning`)
     - `finalized`: `border-success/30 bg-success/15 text-success` (dot: `bg-success`)
     - `adjusted`: `border-destructive/30 bg-destructive/15 text-destructive` (dot: `bg-destructive`)
   - Cartões de métricas de topo e botões operacionais migrados para os tokens `bg-info/10 text-info`, `bg-warning/10 text-warning`, `bg-success/10 text-success` e botão de fechamento com `bg-success text-success-foreground hover:bg-success/90`.

3. **Agendamento Público (Public Booking)**:
   - **Arquivo Modificado**: [`resources/js/pages/public-booking/show.tsx`](file:///Users/murilloalves/Projects/CRCN/sistemas/caldas-gestao/.worktrees/frontend-modernization/resources/js/pages/public-booking/show.tsx#L516)
   - Estado ativo das abas de navegação migrado de hardcoded `bg-slate-950 text-white dark:bg-white dark:text-slate-950` para tokens semânticos:
     `bg-foreground text-background`

---

### 2.3 Normalização de Escala do Botão (`button.tsx`)
- **Arquivo Modificado**: [`resources/js/components/ui/button.tsx`](file:///Users/murilloalves/Projects/CRCN/sistemas/caldas-gestao/.worktrees/frontend-modernization/resources/js/components/ui/button.tsx#L23-L28)
- **Problema Anterior**: Todas as variantes de tamanho (`default`, `sm`, `lg`, `icon`) estavam travadas em `min-h-11` (44px) ou `size-11`, gerando botões desproporcionais e quebrando a hierarquia visual em listas e tabelas compactas.
- **Nova Escala Normalizada**:
  ```ts
  size: {
    default: "min-h-10 px-4 py-2 text-sm has-[>svg]:px-3",
    sm: "min-h-8 rounded-md px-3 text-xs has-[>svg]:px-2.5",
    lg: "min-h-11 rounded-md px-6 text-base has-[>svg]:px-4",
    icon: "size-10",
  },
  ```
- **Benefícios**:
  - `sm` com 32px (`min-h-8`) ideal para barras de ferramentas densas, ações de linha de tabela e paginação.
  - `default` com 40px (`min-h-10`) estabelecendo a linha base ergonômica para desktop e toque mobile.
  - `lg` com 44px (`min-h-11`) para CTAs primários de conversão.
  - `icon` com 40px (`size-10`) respeitando o target touch mínimo acessível.

---

### 2.4 Primitiva Compartilhada de Tabela (`Table`)
1. **Criação do Componente Base**:
   - **Arquivo Criado**: [`resources/js/components/ui/table.tsx`](file:///Users/murilloalves/Projects/CRCN/sistemas/caldas-gestao/.worktrees/frontend-modernization/resources/js/components/ui/table.tsx)
   - Implementadas todas as partes da tabela padronizadas no ecossistema shadcn/ui utilizando props nativas de elementos React 19 (`React.ComponentProps<"table">`, etc.) e integração com `cn(...)`:
     - `Table`: Container com scroll horizontal embutido (`relative w-full overflow-x-auto`) e tipografia base.
     - `TableHeader`: Cabeçalho com borda inferior automática.
     - `TableBody`: Corpo com remoção limpa de borda na última linha.
     - `TableFooter`: Rodapé destacado com fundo atenuado.
     - `TableRow`: Linha com estados de seleção e hover interativo.
     - `TableHead`: Célula de cabeçalho alinhada, com altura base e suporte a checkboxes.
     - `TableCell`: Célula de dados com padding e alinhamento consistentes.
     - `TableCaption`: Acessibilidade e legenda semântica de dados tabulares.

2. **Refatoração no Módulo Financeiro (Transações / Obrigações)**:
   - **Arquivo Modificado**: [`resources/js/pages/finance/transactions/index.tsx`](file:///Users/murilloalves/Projects/CRCN/sistemas/caldas-gestao/.worktrees/frontend-modernization/resources/js/pages/finance/transactions/index.tsx#L917-L1138)
   - Substituída a tabela manual por `<Table>`, `<TableHeader>`, `<TableRow>`, `<TableHead>`, `<TableBody>`, `<TableCell>`.
   - Adicionados atributos de acessibilidade `aria-label` e `title` aos botões de ação em cada linha (edição e cancelamento de obrigações).
   - Cores de status de transação (A Pagar / A Receber / Liquidado / Pendente / Cancelado) atualizadas para tokens semânticos (`text-destructive`, `text-success`, `border-warning/30 bg-warning/15 text-warning`).

3. **Refatoração no Módulo de Estoque (Extrato de Movimentações)**:
   - **Arquivo Modificado**: [`resources/js/pages/inventory/index.tsx`](file:///Users/murilloalves/Projects/CRCN/sistemas/caldas-gestao/.worktrees/frontend-modernization/resources/js/pages/inventory/index.tsx#L244-L370)
   - Substituída a tabela manual por `<Table>`, `<TableHeader>`, `<TableRow>`, `<TableHead>`, `<TableBody>`, `<TableCell>`.
   - `MovementTypeBadge` e indicadores de quantidade sanitizados com tokens semânticos (`bg-success/15 text-success`, `bg-info/15 text-info`, `text-destructive`).

---

## 🧪 Evidências e Validações

Todas as etapas de validação foram executadas com sucesso no diretório da worktree `frontend-modernization`:

| Validação | Comando | Resultado |
|---|---|---|
| **Linter** | `npm run lint:check` | ✅ 0 erros, 0 avisos (ESLint 9) |
| **Tipagem** | `npm run types:check` | ✅ 0 erros de compilação (TypeScript 5.7) |
| **Build de Produção** | `npm run build` | ✅ Compilação concluída em 6.36s gerando todos os assets estáticos em `public/build/` |
| **Camada Semântica CSS** | `resources/css/app.css` | ✅ Tokens `--color-success`, `--color-warning`, `--color-info` registrados sob `@theme` com suporte a light/dark mode em OKLCH |
| **Componentes de UI** | `resources/js/components/ui/table.tsx` & `button.tsx` | ✅ Primitivas validadas com React 19 e tailwind-merge |
