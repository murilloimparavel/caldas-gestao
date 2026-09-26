# Plano de otimização de velocidade e fluidez

Plano técnico para melhorar a experiência mobile e desktop do Caldas Gestão,
definido a partir do baseline Playwright, da build e do grafo de dependências.

## Diagnóstico

- `resources/js/app.tsx` é a entrada central e concentra providers, resolução
  Inertia e layouts.
- `resources/js/pages/calendar/index.tsx` tem mais de 1.600 linhas e 65
  conexões no grafo; é a maior superfície de interação.
- `resources/js/components/operational/index.tsx` tem mais de 700 linhas e é
  transversal a tabelas, filtros, formulários, paginação e estados vazios.
- A build já separa páginas, mas ainda possui um chunk compartilhado grande e
  CSS global de aproximadamente 154 KB.
- O baseline de `/login` mediu cerca de 1,9 s até primeiro paint no mobile e
  1,35 s no desktop, sem erros de console ou rede.
- O domínio Premium é servido pelo Coolify; a validação deve confirmar o
  manifest e os headers no domínio de produção.

## Metas

| Indicador | Meta |
| --- | ---: |
| Resposta inicial mobile | < 800 ms |
| First Contentful Paint mobile | < 1,5 s |
| Largest Contentful Paint mobile | < 2,5 s |
| JavaScript inicial de rota pública | < 150 KB sem gzip |
| Falhas de requests críticos | 0 |
| Erros de console em smoke test | 0 |
| Cumulative Layout Shift | < 0,1 |

## Sprint 1 — Instrumentação e contrato

**Objetivo:** tornar a medição repetível.

- criar smoke tests Playwright para login, dashboard, agenda, clientes e
  agendamento público;
- medir cold load e navegação Inertia em mobile e desktop;
- registrar FCP, LCP, CLS, TBT, TTFB, tamanho transferido e falhas;
- capturar console, exceções e requests não-2xx;
- adicionar orçamento de performance no CI;
- versionar baseline por rota, viewport, rede e data.

**Aceite:** relatório reproduzível em CI para todas as rotas críticas.

## Sprint 2 — Bundle inicial e carregamento percebido

**Objetivo:** reduzir o custo antes da primeira interação.

- investigar o maior chunk compartilhado e remover dependências globais do
  entrypoint;
- revisar imports de ícones e componentes agregadores;
- dividir CSS global quando a análise confirmar ganho real;
- manter o fallback inline e trocar `fallback={null}` por estado acessível;
- adicionar preload somente para recursos críticos;
- configurar cache de assets versionados no proxy Coolify.

**Aceite:** rotas públicas não carregam o shell autenticado, não exibem tela
preta e permanecem dentro do orçamento de JavaScript.

## Sprint 3 — Fluidez das superfícies pesadas

**Objetivo:** reduzir jank durante a interação.

- separar a Agenda em toolbar, filtros, grade e drawers;
- memoizar cálculos de datas, opções e agrupamentos após profiling;
- evitar re-render da grade inteira ao filtrar ou selecionar;
- extrair padrões puros de `operational/index.tsx`;
- aplicar virtualização somente a listas comprovadamente grandes;
- usar skeletons e estados de erro específicos em Dashboard, Financeiro,
  Clientes e Online Booking;
- medir trocar dia, filtrar, abrir modal e salvar formulário.

**Aceite:** interações críticas sem trabalho síncrono acima de 100 ms no
dispositivo de referência e sem regressão funcional.

## Sprint 4 — Rede, backend e produção Coolify

**Objetivo:** reduzir latência e validar o caminho real de produção.

- medir TTFB e revisar shared props, middleware e consultas por rota;
- reduzir page props e paginar coleções grandes;
- confirmar eager loading e ausência de N+1;
- otimizar imagens com dimensões, formatos modernos e lazy loading;
- configurar compressão e cache no proxy do Coolify;
- configurar webhook/integração Coolify no GitHub;
- confirmar o commit implantado pelo manifest servido no domínio Premium;
- repetir smoke test com rede móvel simulada.

**Aceite:** produção serve o build esperado, todas as rotas carregam sem erros
e as metas de Core Web Vitals são atingidas no cenário definido.

## Ordem de execução

1. Sprint 1: medir antes de otimizar novas superfícies.
2. Sprint 2: eliminar custo inicial e percepção de tela preta.
3. Sprint 3: otimizar agenda, dashboard e componentes operacionais.
4. Sprint 4: fechar rede, backend, proxy e validação no Coolify.

Cada sprint deve produzir uma medição antes/depois, testes automatizados e
atualização do baseline.
