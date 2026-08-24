# Acessibilidade — observações iniciais

## Pontos positivos observados

- grupos da sidebar comunicam expansão por `aria-expanded`;
- menus e tabs possuem papéis semânticos;
- diálogo de configurações possui nome;
- controles de status/filtro são checkboxes nomeados;
- ações finais do novo agendamento têm texto visível.

## Riscos observados

| ID | Risco | Impacto | Evidência |
|---|---|---|---|
| A11Y-001 | botões somente com ícone sem nome acessível | leitor de tela não identifica ação | EV-001 |
| A11Y-002 | ausência observada de landmark `main` | navegação estrutural prejudicada | EV-001 |
| A11Y-003 | opções de select expõem valores internos em vez do rótulo visual | anúncio confuso/incorreto | EV-001 |
| A11Y-004 | grade densa agrega múltiplos cartões em células | foco e compreensão podem falhar | EV-001 |
| A11Y-005 | status/cores podem depender excessivamente de cor | baixa visão/daltonismo | EV-001 |
| A11Y-006 | SVGs analíticos não expõem papel ou nome acessível | gráficos ficam opacos ao leitor de tela | EV-003 |
| A11Y-007 | heatmap denso não foi observado como tabela semântica | relação hora × dia pode se perder | EV-003 |
| A11Y-008 | deltas e ocupação usam cor/forma como forte reforço visual | direção e intensidade podem ficar ambíguas | EV-003 |
| A11Y-012 | primeiro Tab em Minha Conta caiu em `DIV` sem nome/papel | sequência de foco imprevisível | EV-007 |
| A11Y-013 | superfícies transversais observadas não possuem landmarks main/navigation | navegação estrutural prejudicada | EV-007 |
| A11Y-014 | itens da barra inferior mobile não são consistentemente controles semânticos | navegação e ativação por tecnologia assistiva ficam ambíguas | EV-008 |
| A11Y-015 | chat e navegação ocupam juntos a região inferior | colisão, cobertura e toque acidental, especialmente com safe area | EV-008 |
| A11Y-016 | agenda semanal é comprimida para sete dias no celular | texto/alvos pequenos e navegação sequencial excessiva | EV-008 |
| A11Y-017 | conteúdo animado pode existir no DOM antes de se tornar visualmente legível | informação inicial ausente para visão/captura e risco com movimento reduzido | EV-009 |
| A11Y-018 | marca e controles públicos nem sempre possuem nome acessível | navegação e estado de menu ficam ambíguos | EV-009 |

## Não testado

- navegação completa por teclado;
- armadilha e retorno de foco em dialogs;
- anúncios de mudanças assíncronas;
- contraste medido;
- zoom/reflow 200–400%;
- leitor de tela;
- prefers-reduced-motion.

## Requisitos propostos

- WCAG 2.2 AA como baseline;
- visão em lista equivalente à grade;
- nomes acessíveis em todos os icon buttons;
- foco previsível em menus, dialogs e date pickers;
- status com texto/ícone além da cor;
- testes axe + teclado + leitor de tela nos fluxos críticos.
- resumo textual e tabela de dados equivalente para todo gráfico;
- tooltips acionáveis por foco, não apenas hover;
- anúncio de carregamento/conclusão/erro de geração de relatório;
- exportação com nome, formato e escopo anunciados antes da execução.
- foco inicial útil, trap, Escape e retorno ao acionador em todo overlay;
- preferências de cor/personalização não reduzem contraste mínimo.
- navegação mobile com links/botões nomeados, estado atual e alvos mínimos de 44 × 44 px;
- conteúdo deve reservar safe area e espaço para teclado virtual, barra inferior e ações flutuantes;
- agenda deve iniciar em modo legível no celular e oferecer alternativa em lista equivalente.
