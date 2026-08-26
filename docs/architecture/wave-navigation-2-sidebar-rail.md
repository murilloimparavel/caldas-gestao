# Wave de Navegação 2 — Sidebar hierárquica e rail compacto

**Data:** 26/08/2026
**Status:** implementada e validada em ambiente local
**Escopo:** navegação autenticada desktop; integração com a navegação mobile existente

## Objetivo

Evoluir a navegação lateral do Caldas para que a sidebar continue útil nos dois estados:

- expandida, com categorias e itens filhos claramente hierarquizados;
- recolhida, como rail de ícones com um flyout de links por categoria.

A referência funcional foi observada no Belasis em Chrome. A implementação mantém identidade visual, rotas e permissões próprias do Caldas; não copia marca, paleta, textos ou ativos do produto observado.

## Evidência funcional observada

No Belasis, a sidebar expandida apresenta grupos independentes que podem permanecer abertos simultaneamente. Ao recolher, os nomes e filhos deixam de ocupar a lateral, mas os ícones das categorias continuam disponíveis. Clicar em um ícone abre um menu flutuante à direita com os links daquele grupo. O flyout fecha após a navegação, ao clicar fora ou com `Escape`.

## Contrato implementado

O contrato em `SidebarNavGroup` é a fonte única para o menu:

- `id` estável da categoria;
- `label` e ícone do módulo;
- itens filhos com ícone, rota Wayfinder e permissão opcional;
- estado ativo derivado da URL atual.

O filtro de permissões acontece antes da renderização. Categorias sem itens autorizados não aparecem no accordion nem no rail. A Agenda usa `calendar.view`, alinhada ao catálogo e às policies atuais.

## Comportamento entregue

### Sidebar expandida

- categorias são botões reais com `aria-expanded` e `aria-controls`;
- múltiplas categorias podem ficar abertas;
- preferência dos accordions é persistida por usuário;
- categoria tem ícone, tipografia mais forte, área maior, borda/contraste e chevron;
- filhos têm recuo, menor densidade e estado ativo próprio com `aria-current="page"`.

### Sidebar recolhida

- somente os ícones das categorias visíveis permanecem no rail;
- cada ícone é um trigger acessível com tooltip e nome acessível;
- clique, Enter ou Espaço abre um flyout lateral;
- apenas um flyout fica aberto por vez;
- links do flyout usam Inertia e Wayfinder;
- o flyout fecha ao navegar, clicar fora ou pressionar `Escape`;
- o estado do flyout é efêmero e não é salvo no `localStorage`;
- a categoria da rota atual permanece destacada.

### Mobile

O rail não é exibido no mobile. A barra inferior (`Painel`, `Agenda`, `Clientes` e `Mais`) permanece responsável pelos atalhos, e o Sheet continua abrindo a navegação completa. A permissão da Agenda foi alinhada para `calendar.view` também nessa superfície.

## Arquivos principais

- `resources/js/components/nav-main.tsx`
- `resources/js/components/app-sidebar.tsx`
- `resources/js/components/mobile-bottom-nav.tsx`
- `resources/js/types/navigation.ts`
- `resources/js/components/ui/dropdown-menu.tsx` (primitive Radix reutilizado)

## Validação

Automatizada:

- `npx eslint resources/js/components/nav-main.tsx resources/js/components/app-sidebar.tsx resources/js/components/mobile-bottom-nav.tsx resources/js/types/navigation.ts` — passou;
- `npm run types:check` — passou;
- `npm run build` — passou;
- `git diff --check` — passou.

Chrome local (`http://127.0.0.1:8000/dashboard`):

- accordion expandido e hierarquia visual conferidos;
- sidebar recolhida exibindo o rail de categorias;
- flyout Financeiro aberto com seus links;
- `Escape` fechando o flyout;
- navegação pelo flyout até `/finance/commissions` confirmada;
- logs do navegador sem erros da aplicação.

## Pendências declaradas

- o projeto ainda não possui harness frontend/browser automatizado; a validação de interação desta wave foi manual no Chrome;
- o lint global continua com falhas preexistentes em páginas fora do escopo da wave;
- validar em uma conta com permissões parciais e em viewports mobile físicos continua sendo uma próxima rodada de QA.
