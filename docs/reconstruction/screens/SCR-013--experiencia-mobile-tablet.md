# SCR-013 — Experiência mobile e tablet transversal

## Contexto

- **Rotas:** `/wow`, `/calendar`, `/clients`, `/services`, `/sales`, `/reports/financial/dre` e menu/configurações
- **Papel:** desconhecido
- **Viewports:** 390 × 844 e 768 × 1024
- **Precondição:** sessão autenticada
- **Evidência:** EV-008; UX-033 a UX-038; A11Y-014 a A11Y-016

## Objetivo

Permitir consulta e operação rápida fora do desktop sem ocultar tarefas essenciais, gerar toques acidentais ou transformar visualizações densas em miniaturas ilegíveis.

## Hierarquia observada

1. conteúdo em uma coluna;
2. ações contextuais da rota;
3. barra inferior flutuante;
4. chat/suporte flutuante acima da barra;
5. menu ou filtro em superfície dedicada.

## Componentes observados

- barra inferior contextual de aproximadamente 64 px;
- item Menu e até quatro ações específicas da rota;
- botão de suporte de 56 × 56 px;
- listas convertidas em cartões compactos;
- busca e ordenação no topo;
- filtros em overlay de tela inteira;
- painel analítico empilhado;
- agenda semanal comprimida para sete colunas.

## Estado por superfície

| Superfície | 390 px | 768 px | Evidência |
|---|---|---|---|
| shell | barra inferior + suporte flutuante | mesmo paradigma ampliado | EV-008 |
| painel | cards/gráficos em coluna longa | coluna longa, ~4.215 px | EV-008 |
| agenda semanal | sete dias comprimidos | sete dias ainda comprimidos | EV-008 |
| listagens | cards em coluna, busca e ações inferiores | cards mais largos, sem master-detail | EV-008 |
| filtros de clientes | overlay de tela inteira | overlay de tela inteira | EV-008 |
| DRE | conteúdo não visível em uma visita | não repetido | EV-008 |

## Riscos de UX

- ações operacionais importantes mudam de posição e conjunto conforme a rota;
- barra inferior e suporte disputam a mesma região de toque;
- seleção e exclusão aparecem com alta frequência nas listagens;
- agenda semanal preserva informação demais para o espaço disponível;
- painel mantém todo o conteúdo, mas perde capacidade de comparação rápida;
- tablet desperdiça largura ao repetir o mesmo layout de coluna única;
- overlay persistido entre visitas pode ser percebido como bloqueio inesperado.

## Acessibilidade observada

- itens visuais da barra inferior não aparecem consistentemente como controles semânticos;
- o estado da rota ativa não foi identificado no snapshot acessível;
- gráficos continuam sem alternativa semântica suficiente;
- cartões da agenda são estreitos e a grade extensa para navegação sequencial;
- botão Fechar do filtro possui nome acessível, mas foco inicial/trap/retorno não foram testados.

## Tratamento independente proposto

### Mobile até 639 px

- navegação inferior com quatro destinos estáveis: Painel, Agenda, Clientes e Mais;
- botão de criação contextual separado, claramente nomeado e sem cobrir conteúdo;
- ações secundárias em bottom sheet acessível;
- agenda abre em visão diária/lista e permite alternar explicitamente para três ou sete dias;
- listas usam cartões com uma ação primária e menu de overflow; exclusão nunca fica como ação de toque direto;
- dashboard começa com resumo de três a cinco KPIs e seções recolhíveis, com comparação e drill-down;
- filtros abrem em sheet com contagem aplicada, limpar e aplicar fixos acima da safe area.

### Tablet de 640 a 1023 px

- rail lateral compacto ou navegação inferior conforme orientação;
- listas podem usar master-detail ou duas colunas;
- agenda usa três dias em portrait e semana completa em landscape quando legível;
- dashboard usa grade de duas colunas e gráficos com largura mínima definida;
- overlays preferem drawer lateral, preservando contexto.

### Desktop a partir de 1024 px

- shell lateral e visualizações densas descritas nas telas desktop;
- breakpoint final será validado por conteúdo, não por aparelho específico.

## Critérios de aceitação para reconstrução

- Nenhuma ação ou conteúdo fica coberto pela navegação, safe area, chat ou teclado virtual.
- Todo item da navegação é link/botão nomeado e comunica rota ativa.
- Alvos recorrentes atendem pelo menos 44 × 44 px, com espaçamento que reduz toque acidental.
- Agenda mobile inicia em modo legível e oferece alternativa em lista equivalente.
- Lista preserva consulta, busca, filtros e acesso ao detalhe sem exigir scroll horizontal.
- Ação destrutiva exige menu/confirmacão e não divide o mesmo alvo do acesso ao detalhe.
- Filtros comunicam quantidade aplicada e possuem Aplicar, Limpar, Fechar, Escape e retorno de foco.
- Dashboard apresenta resumo antes dos detalhes e todo gráfico possui resumo/tabela alternativa.
- Tablet aproveita largura adicional em pelo menos duas colunas ou master-detail quando o conteúdo permitir.
- Overlay não reaparece após navegação sem uma regra explícita e testada de restauração.
- Testes cobrem 390 × 844, 768 × 1024, landscape, zoom/reflow e teclado virtual.

## Desconhecidos

- comportamento em dispositivo físico, Safari/iOS e Chrome/Android;
- safe-area e teclado virtual;
- gestos, drag-and-drop e orientação landscape;
- detalhe de cliente/serviço/comanda no mobile;
- formulários de criação e validação mobile;
- loading, erro, offline e conflito;
- causa do conteúdo ausente no DRE e da persistência do filtro;
- foco e leitor de tela nos overlays.
