# Design system proposto — Caldas Gestão

Direção original: operação calma, legível e densa quando necessário, sem reproduzir marca ou composição do produto observado.

## Tokens-base

| Categoria | Proposta |
|---|---|
| Fonte | Inter ou fonte sans variável equivalente, base 16px |
| Fundo | neutro quente muito claro |
| Superfície | branco + borda neutra, sombra mínima |
| Navegação | grafite profundo, contraste AA |
| Primário | índigo próprio, distinto do observado |
| Sucesso/alerta/erro | verde/âmbar/vermelho com texto e ícone |
| Raios | 8px controles, 12px cards, 16px overlays |
| Espaçamento | escala 4/8/12/16/24/32 |
| Foco | anel de 2–3px com offset e contraste forte |

## Primitivas

- AppShell, PageHeader, CommandMenu e ContextNav;
- Button/IconButton, Field, Select, DateRange e Switch;
- DataTable, FilterPanel, Tabs, Drawer, Dialog e Popover;
- KPI, ChartFrame, DataFallback, StatusBadge e Timeline;
- EmptyState, ErrorState, LoadingSkeleton, Paywall e PermissionDenied.

## Responsividade proposta

- mobile: lista operacional, navegação em drawer, ações primárias fixas e tabelas em cards;
- tablet: sidebar compacta, duas colunas e filtros em sheet;
- desktop: sidebar persistente, alta densidade e painéis multicoluna;
- nenhum fluxo crítico depende de hover ou largura fixa;
- zoom 400% deve preservar operação em uma coluna.

## Acessibilidade

- WCAG 2.2 AA;
- landmarks e um `h1` por página;
- todos os icon buttons nomeados;
- tabela equivalente para gráficos e calendários;
- foco previsível em overlays;
- anúncios `aria-live` para busca, geração e salvamento;
- reduced motion e contraste não dependente de tema.
