# EV-008 — Experiência mobile e tablet

## Escopo e segurança

- data: 24/08/2026;
- superfície: Chrome autenticado autorizado;
- papel: usuário autenticado de papel formal desconhecido;
- viewports simulados: 390 × 844 e 768 × 1024;
- rotas: `/wow`, `/calendar`, `/clients`, `/services`, `/sales`, `/reports/financial/dre` e menu/configurações;
- interações: navegação direta por rotas já observadas, abertura de navegação e inspeção de overlay já presente;
- mutações: nenhuma;
- screenshots: usadas apenas na sessão para inspeção visual; nenhuma foi persistida por conter dados operacionais.

## Evidência observada

### Shell responsivo

- Em 390 e 768 px, a sidebar desktop é substituída por barra inferior flutuante.
- A barra mede aproximadamente 64 px de altura, possui margem lateral de 15 px e permanece fixa próxima à base do viewport.
- O conjunto de ações varia pela rota. Exemplos observados: Menu, Agenda/Calendário, Atualizar, Filtros, Ações, Selecionar e Criar.
- Chat/suporte permanece como botão flutuante de 56 × 56 px imediatamente acima da barra.
- Menu abre uma superfície própria de navegação. Em Configurações foram observadas páginas em lista com chevrons.
- Não foram observados landmarks `main` ou `navigation` no snapshot acessível mobile.

### Painel principal

- KPIs, tabs, gráficos, ranking, funil, ocupação e heatmap são empilhados em uma única coluna.
- O documento mediu cerca de 4.215 px de altura em 768 px de largura.
- O filtro de período permanece no topo e as ações globais migram para a barra inferior.
- Conteúdo gráfico permanece presente no DOM, mas a inspeção acessível continua sem papel/nome útil para os gráficos.
- A composição prioriza paridade de conteúdo, com custo de rolagem longa e baixa visão geral.

### Agenda

- A visão semanal preserva os sete dias, o profissional e intervalos de dez minutos tanto em 390 quanto em 768 px.
- Em 390 px, a grade inteira é comprimida no viewport sem overflow horizontal do documento.
- Cartões e colunas ficam visualmente muito estreitos; a densidade compromete leitura e ação por toque.
- Em 768 px, a mesma estratégia continua ativa; o documento mediu aproximadamente 2.367 px de altura.
- A barra inferior mantém Calendário, Filtros, Ações e Criar, além de Menu.
- Não foi observada troca automática para agenda diária ou lista no mobile.

### Listagens de clientes, serviços e comandas

- Tabelas desktop são convertidas em coleção vertical de cartões/linhas compactas.
- Busca e ordenação permanecem no topo.
- Ações recorrentes migram para a barra inferior: Filtros, Selecionar e Criar.
- Cada item mantém checkbox e affordances de seleção/exclusão; dados secundários aparecem abaixo ou ao lado do título.
- Não houve overflow horizontal do documento em 390 px.
- As páginas carregam muitos registros em uma única coluna, gerando documentos longos; não foi confirmada paginação incremental no mobile.

### Filtros

- O filtro de Clientes aparece como diálogo/drawer de tela inteira em 390 e 768 px.
- Controles incluem status, tags, presença de celular, débito, aniversário e avaliação.
- O overlay observado possuía botão Fechar com nome acessível.
- Ao retornar posteriormente a `/clients`, o filtro continuou montado/aberto. Isso pode ser persistência intencional de UI, restauração de estado da SPA ou efeito da sessão de inspeção; a causa não foi determinada.

### Relatório financeiro detalhado

- Em uma visita mobile, a rota mostrou somente shell inferior e affordance de retorno, sem conteúdo analítico visível.
- O `#root` tinha cerca de 131 px de altura e o documento permaneceu com a altura do viewport.
- Classificação: falha observada contextual, não prova de indisponibilidade permanente. Deve ser repetida em tenant/teste e após carregamento controlado.

## Acessibilidade e ergonomia

- Diversos itens da barra inferior aparecem como elementos genéricos com imagem/texto, não como botões ou links no snapshot acessível.
- Ações de linha continuam semanticamente pouco claras e algumas dependem de ícones.
- Barra inferior e chat ocupam simultaneamente a zona de alcance inferior; precisam considerar safe area e não cobrir conteúdo/CTAs.
- A grade semanal é semanticamente extensa e visualmente comprimida, reforçando a necessidade de visão em lista/dia.
- Alvos de toque da agenda aparentam ser menores que o recomendado, embora não tenham sido medidos individualmente por cartão.

## Limitações

- A emulação alterou o viewport do Chrome, mas não emulou user-agent, touch events, teclado virtual ou safe-area de um aparelho físico.
- Não foram testados orientação landscape, zoom, leitor de tela, gestos, drag-and-drop ou teclado virtual.
- Nenhuma mutação foi executada; criar/editar/cancelar continuam desconhecidos em mobile.
- Dados pessoais foram vistos apenas como conteúdo operacional necessário à navegação e não foram copiados para este artefato.

## Implicações para o produto novo

1. Mobile não deve ser apenas reflow do desktop; agenda e analytics precisam de modos de decisão próprios.
2. Barra inferior deve conter no máximo ações prioritárias e expor semanticamente rota ativa e papel de cada controle.
3. Conteúdo deve reservar espaço para barra, safe area e teclado, sem colisão com ajuda flutuante.
4. Lista mobile deve usar paginação/infinite loading controlado, seleção explícita e ações destrutivas fora do toque casual.
5. Estado de overlay deve ter política definida: fechar na mudança de rota, ou persistir de forma comunicada e previsível.
6. Tablet não deve herdar automaticamente o layout de celular; usar espaço adicional para master-detail, duas colunas ou painel lateral quando útil.
