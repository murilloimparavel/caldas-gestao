# Plano de implementação frontend-first

## Objetivo

Construir um produto original de gestão para negócios de beleza a partir dos requisitos do dossiê, sem reproduzir código, ativos, marca ou decisões privadas do produto observado. A entrega será incremental: cada domínio nasce primeiro como experiência navegável e tipada, depois recebe regras, persistência e integrações reais.

**Baseline de evidência:** EV-001 a EV-008. O passe EV-008 tornou mobile e tablet requisitos de arquitetura desde a F0, não uma etapa de adaptação ao final.

## Progresso registrado — Wave de Navegação 2 (26/08/2026)

**Status:** implementada, validada em build e smoke manual no Chrome.

A shell autenticada agora possui accordion hierárquico no modo expandido e rail compacto no modo recolhido. Cada categoria no rail abre um flyout com links autorizados; a navegação fecha o flyout e mantém o destino em Wayfinder. O estado dos accordions é persistido por usuário, enquanto o flyout permanece efêmero.

A hierarquia visual foi refinada para que categorias sejam percebidas como módulos e links filhos como destinos subordinados. A Agenda usa `calendar.view` nas superfícies desktop e mobile. O mobile permanece com barra inferior e Sheet, sem reutilizar o rail desktop.

Validações concluídas: ESLint isolado dos arquivos de navegação, `npm run types:check`, `npm run build`, `git diff --check` e smoke manual no Chrome para accordion, rail, flyout, `Escape` e navegação para `/finance/commissions`. Pendências de QA em permissões parciais e viewports físicos estão registradas no documento da wave.

## Stack aprovada

- Laravel 13 como aplicação principal, autenticação, autorização, validação, regras de negócio e composição das respostas Inertia;
- React 19 e TypeScript estrito para páginas e componentes;
- Inertia 3 como protocolo entre Laravel e React;
- Tailwind CSS 4, tokens próprios e primitives acessíveis para o sistema visual;
- PostgreSQL gerenciado pelo Supabase, acessado somente pelo backend Laravel;
- Redis para cache, sessões, filas, rate limiting e locks distribuídos;
- Vite para build e desenvolvimento.

### Limites arquiteturais

- O navegador nunca acessa o PostgreSQL diretamente e nunca recebe chave de serviço do Supabase.
- Laravel controla usuários, sessões, tenant, unidade e políticas. Supabase Auth não entra no primeiro ciclo.
- Inertia props são o contrato de leitura; Form Requests, Actions e Resources formam o contrato de escrita.
- Estado persistente fica no servidor; filtros compartilháveis ficam na URL; estado efêmero fica no componente.
- Não adicionar React Query ou store global no scaffold. Eles só entram mediante caso concreto que Inertia, URL e estado local não resolvam.
- Jobs que dependam de uma transação devem ser publicados somente após o commit.
- Redis não recebe prontuários, anamnese, documentos ou outros dados pessoais sensíveis em payload aberto.

## Direção de produto e interface

O produto deve parecer uma ferramenta operacional calma, rápida e confiável — não um clone visual. A direção proposta usa alta densidade com hierarquia clara, superfícies neutras quentes, cor de marca própria, tipografia legível e feedback de estado explícito.

O `design-system-proposed.md` define a linguagem visual. Componentes de terceiros podem servir como primitives técnicas, mas serão encapsulados e estilizados pelo nosso sistema. Não importar tema, composição, textos, ícones proprietários ou medidas do produto observado.

### Princípios

1. A próxima ação operacional deve estar evidente sem competir com todo o restante.
2. Tabelas preservam contexto, filtros e posição ao abrir detalhes.
3. Ações destrutivas, financeiras ou externas exigem confirmação proporcional ao risco.
4. Toda tela nasce responsiva e com teclado/leitor de tela, não como correção posterior.
5. Dinheiro, datas, fuso, status e autoria devem ser inequívocos.
6. Estados incompletos nunca podem parecer ausência real de dados.
7. Mobile prioriza a tarefa imediata; não miniaturiza a interface desktop.
8. Tablet aproveita a largura para contexto paralelo, não apenas amplia uma coluna mobile.
9. Navegação, ajuda e ação primária nunca disputam ou cobrem a mesma zona de toque.

## Arquitetura responsiva obrigatória

O passe EV-008 observou boas adaptações de listagem, mas também agenda semanal comprimida, dashboard excessivamente longo, tablet subutilizado e competição entre barra inferior e suporte. O produto novo adotará composição adaptativa por tarefa.

### Breakpoints de referência

| Faixa               | Composição padrão                         | Navegação                                                              | Overlays                    |
| ------------------- | ----------------------------------------- | ---------------------------------------------------------------------- | --------------------------- |
| até 639 px          | uma coluna, prioridade operacional        | barra inferior com quatro destinos estáveis + ação contextual separada | bottom sheet ou tela cheia  |
| 640–1023 px         | duas colunas ou master-detail quando útil | rail compacto ou barra inferior conforme orientação                    | drawer lateral preferencial |
| a partir de 1024 px | shell lateral e superfícies densas        | sidebar/rail expandido                                                 | dialog ou drawer contextual |

Esses valores iniciam o desenvolvimento, mas componentes usarão container queries quando sua adaptação depender do espaço disponível, e não do viewport. O breakpoint final será validado por legibilidade e tarefa, não por modelo de aparelho.

### Regras transversais

- reservar espaço para navegação, `env(safe-area-inset-*)`, teclado virtual e ações flutuantes;
- usar `100dvh` nos shells/overlays móveis e tratar mudanças da viewport visual;
- manter destinos primários estáveis entre rotas; ações contextuais não substituem destinos silenciosamente;
- comunicar rota atual, título e contexto de unidade semanticamente;
- persistir filtros na URL quando compartilháveis; overlays fecham ao navegar, salvo restauração deliberada e testada;
- touch target recorrente mínimo de 44 × 44 px;
- ações destrutivas ficam em overflow e confirmação, nunca como toque direto recorrente no cartão;
- suportar portrait e landscape sem exigir recarregamento;
- conteúdo essencial não depende de hover, drag-and-drop ou gesto oculto;
- oferecer alternativa textual/lista para grades e gráficos densos.

### Estratégia por superfície

| Superfície  | Mobile                                                            | Tablet                                          | Desktop                           |
| ----------- | ----------------------------------------------------------------- | ----------------------------------------------- | --------------------------------- |
| shell       | Painel, Agenda, Clientes e Mais; FAB/ação contextual independente | rail compacto em landscape ou barra em portrait | sidebar hierárquica               |
| listas      | cartões, ação primária e overflow                                 | master-detail ou tabela compacta                | tabela completa                   |
| filtros     | sheet com Aplicar/Limpar fixos e contagem                         | drawer lateral preservando contexto             | popover/drawer conforme densidade |
| formulários | uma coluna, etapas apenas quando reduzem carga cognitiva          | duas colunas por seção                          | seções e painel de contexto       |
| agenda      | dia/lista como padrão; alternância explícita                      | três dias em portrait; semana quando legível    | dia/semana/mês                    |
| dashboard   | resumo de 3–5 KPIs, seções progressivas e drill-down              | grade de duas colunas                           | visão analítica densa             |
| relatórios  | filtros primeiro, resultado depois; tabela/cartões                | filtros laterais e resultado principal          | filtros e resultado simultâneos   |
| detalhe     | página ou sheet com retorno previsível                            | master-detail                                   | drawer/página conforme fluxo      |

## Arquitetura do frontend

```text
resources/js/
├── app.tsx
├── types/
│   ├── inertia.d.ts
│   ├── domain/
│   └── page-props/
├── layouts/
│   ├── auth-layout.tsx
│   ├── app-layout.tsx
│   └── settings-layout.tsx
├── components/
│   ├── ui/             # primitives sem regra de negócio
│   ├── patterns/       # tabela, filtros, dialogs, estados e formulários
│   └── shell/          # sidebar, rail, bottom nav, header e unidade ativa
├── features/
│   ├── identity/
│   ├── customers/
│   ├── catalog/
│   ├── calendar/
│   ├── orders/
│   ├── finance/
│   └── analytics/
├── pages/              # entradas Inertia, finas e compostas por features
├── hooks/
├── lib/
│   ├── formatters/
│   ├── permissions/
│   └── telemetry/
└── test/
    ├── fixtures/
    └── factories/
```

### Convenções de implementação

- Páginas Inertia apenas compõem layout, feature e page props; regra reutilizável não vive em `pages/`.
- `components/ui` desconhece clientes, agenda, vendas e financeiro.
- `features` pode conhecer o domínio, mas não importa outro feature diretamente; composição ocorre na página ou em `patterns` deliberados.
- Props públicas não expõem modelos Eloquent inteiros. Cada página possui tipo explícito e payload mínimo.
- Valores monetários trafegam em unidade inteira mínima e são formatados na borda.
- Datas trafegam em ISO 8601; instantes ficam em UTC e horários de negócio carregam o fuso da unidade.
- Permissão no frontend controla affordance, nunca segurança. A autorização final sempre ocorre em Policies/Gates no Laravel.
- Rotas são nomeadas e consumidas por helper tipado; strings de URL não se espalham por componentes.
- Textos de interface ficam centralizados desde o início para consistência e futura internacionalização.
- Responsive variants compartilham contrato e regra de negócio; não serão páginas duplicadas por dispositivo.
- Preferência de visualização pode ser salva por usuário, mas nunca sobrepõe uma composição ilegível no viewport atual.
- Mudança de rota desmonta dialog/sheet local; restauração só ocorre por estado explícito na URL.
- A posição de rolagem é preservada ao abrir e fechar detalhes de listas, inclusive no mobile.

## Catálogo inicial do sistema visual

### Primitives

`Button`, `IconButton`, `Link`, `Input`, `Textarea`, `Select`, `Combobox`, `Checkbox`, `RadioGroup`, `Switch`, `Badge`, `Avatar`, `Tooltip`, `Popover`, `Menu`, `Dialog`, `Drawer`, `Tabs`, `Toast`, `Skeleton`, `Progress` e `Separator`.

### Padrões de produto

- `AppShell`, `Sidebar`, `NavigationRail`, `BottomNavigation`, `PageHeader`, `Breadcrumbs`, `UnitSwitcher` e `QuickCreate`;
- `DataTable`, `EntityCardList`, `MasterDetail`, `FilterBar`, `FilterSheet`, `SavedView`, `Pagination` e `BulkActions`;
- `FormSection`, `Field`, `MoneyField`, `DateTimeField` e `UnsavedChangesGuard`;
- `EmptyState`, `FilteredEmptyState`, `ErrorState`, `PermissionState` e `EntitlementState`;
- `MetricCard`, `Trend`, `ChartFrame`, `Legend` e `InsightCallout`;
- `AuditTimeline`, `StatusHistory` e `ActivityFeed`;
- `CalendarGrid`, `CalendarDayList`, `AppointmentCard` e `ResourceColumn`;
- `SafeArea`, `StickyActionBar`, `ResponsiveDrawer` e `KeyboardAwareViewport`.

Cada componente terá exemplos de estado e acessibilidade verificáveis. Storybook é recomendado para primitives e patterns; páginas completas continuam sob o runtime Inertia.

## Contrato padrão de página

Toda página autenticada recebe apenas o necessário:

```ts
type SharedPageProps = {
    schemaVersion: 1;
    auth: {
        user: UserSummary;
        permissions: string[];
        entitlements: EntitlementSummary[];
    };
    workspace: {
        tenant: TenantSummary;
        activeUnit: UnitSummary;
        availableUnits: UnitSummary[];
    };
    flash: {
        success?: string;
        error?: string;
    };
    requestId: string;
    correlationId: string;
};
```

IDs trafegam como `string`; instantes como ISO 8601 UTC; dinheiro como unidade mínima + moeda; listagens extensas usam cursor opaco com tenant/filtros/ordenação vinculados. Cada tela estende esse contrato com uma estrutura própria. Dados grandes usam paginação; opções raramente alteradas usam lazy/deferred props quando houver benefício medido. Mutação usa formulário Inertia, idempotency key para operações críticas e erros de validação por campo. `permissions` controla affordance, `entitlements` controla capacidade contratada, e nenhum dos dois substitui Policy server-side.

Na Wave F2, `workspace` é nulo durante o bootstrap de uma identidade sem membership ativa. Rotas de workspace usam `tenant.context`: a seleção explícita vem de sessão, `X-Tenant-Id`/`X-Unit-Id` ou parâmetro de rota seguro; sem seleção, somente uma membership ativa permite fallback determinístico. Membership revogada, tenant/unidade de outro tenant e unidade inativa são rejeitados no servidor. `requestId`/`correlationId` aceitam somente UUID/ULID válidos vindos do request; entradas inválidas são substituídas por UUIDv7. `auth.entitlements` permanece uma lista vazia até a fatia de entitlements; auditoria, outbox e inbox não fazem parte deste contrato.

## Matriz obrigatória de estados

Antes de considerar qualquer tela pronta, verificar:

| Estado              | Resultado esperado                                                         |
| ------------------- | -------------------------------------------------------------------------- |
| inicial/loading     | skeleton coerente, sem salto estrutural severo                             |
| vazio real          | explicação e próxima ação autorizada                                       |
| populado            | conteúdo principal e ações de linha                                        |
| vazio por filtro    | filtros visíveis e ação de limpar                                          |
| validação           | foco no primeiro erro e mensagens associadas aos campos                    |
| erro recuperável    | contexto preservado e tentativa novamente                                  |
| conflito/stale      | não sobrescrever silenciosamente; oferecer recarregar/revisar              |
| sem permissão       | ação ausente ou estado explicativo, conforme contexto                      |
| sem entitlement     | benefício e caminho legítimo de habilitação, sem falso erro                |
| responsivo          | desktop, tablet e mobile com paridade funcional adequada                   |
| teclado virtual     | campo e ação final permanecem visíveis sem layout quebrado                 |
| safe area           | navegação e conteúdo não colidem com recortes/barras do sistema            |
| orientação          | portrait/landscape preservam contexto e tarefa em andamento                |
| overlay + navegação | overlay fecha ou restaura por regra explícita, sem reaparecer por acidente |
| lista extensa       | paginação/carregamento progressivo sem perder posição ou seleção           |

## Estratégia frontend-first

Cada incremento segue o mesmo ciclo:

1. Confirmar claims e lacunas do domínio no dossiê.
2. Definir mapa da tela, tarefas do usuário e critérios de aceite.
3. Criar tipos, fixtures sintéticas e estados no catálogo de componentes.
4. Montar a página Inertia com controller temporário de leitura e dados sintéticos.
5. Validar primeiro em 390 × 844, depois 768 × 1024 e desktop; testar também landscape, teclado, contraste e estados alternativos.
6. Implementar migrations, modelos, Policies, Form Requests e Actions.
7. Substituir fixtures pelo contrato real sem alterar a interface pública da página.
8. Cobrir fluxo crítico com teste de feature e E2E.

Fixtures nunca entram em produção. Identificadores, nomes, telefones e valores observados no alvo não serão reutilizados.

## Incrementos de implementação

### F0 — Decisões e scaffold

**Entrega:** aplicação sobe localmente e em CI com decisões fundamentais registradas.

- iniciar Laravel 13 com starter React/TypeScript/Inertia;
- configurar TypeScript estrito, lint, format, aliases e fronteiras de importação;
- configurar Tailwind e tokens do design system;
- configurar PostgreSQL Supabase, Redis e `.env.example` sem segredos;
- decidir sessão Redis, queue worker e convenção de cache keys;
- criar tratamento global de erro, correlation/request ID e telemetria sem PII;
- criar pipeline de testes e build;
- registrar ADR de breakpoints, container queries, `dvh`, safe areas e política de overlays/URL;
- incluir matriz de projetos Playwright para mobile, tablet e desktop desde o primeiro smoke test.

**Gate:** build reprodutível, smoke test, health check separado para app/DB/Redis e nenhuma credencial no cliente.

### F1 — Fundação visual e shell

**Entrega:** shell autenticado responsivo e catálogo inicial navegável.

- tokens, tipografia, ícones, primitives e focus states;
- sidebar desktop, rail tablet, barra inferior mobile, ação contextual, header e seletor de unidade;
- safe-area, viewport dinâmica, layout com teclado virtual e padding automático do conteúdo;
- cabeçalho de página, breadcrumbs, menu do usuário e criação rápida;
- estados globais, toasts, dialogs e guard de alterações não salvas;
- tabela, cartões de entidade, master-detail, filtros responsivos, paginação e formulários base;
- política de ajuda contextual que não colide com navegação nem ação primária.

**Gate:** testes visuais dos três breakpoints, portrait/landscape, navegação completa por teclado, alvos de 44 × 44 px e zero conteúdo coberto por navegação/ajuda.

### F2 — Identidade, tenant, unidade e autorização

**Entrega:** usuário entra, escolhe contexto e só vê ações permitidas.

F1 pode navegar com fixtures sintéticas, mas o gate de produção desta fase exige que o bootstrap PostgreSQL/schema `app`, baseline Laravel/Fortify UUID e migrations F2 tenham passado antes de substituir fixtures por dados persistentes. Nenhuma tela deve inferir tenant ou entitlement do cliente.

- login, recuperação e confirmação de credenciais;
- bootstrap de tenant/unidade e seletor de unidade ativa;
- perfis e permissões iniciais: proprietário, gestor, recepção, profissional e financeiro;
- páginas de conta, empresa e preferências essenciais;
- telas 403, sessão expirada e recurso não contratado.

**Gate:** matriz de permissões testada no backend e affordances coerentes no frontend.

### F3 — CRM e catálogo

**Entrega:** base operacional para alimentar agenda e venda.

- clientes: lista, busca, filtros, criação, edição, resumo e histórico vazio;
- profissionais: lista, disponibilidade básica e vínculo com unidade;
- serviços, produtos, categorias e fornecedores;
- componentes reutilizáveis de busca e seleção de entidade;
- cartões mobile com detalhe como alvo primário, overflow para ações e modo de seleção explícito;
- master-detail no tablet para clientes e catálogo quando a largura permitir;
- filtros em sheet/drawer com contagem, Limpar, Aplicar, Escape e retorno de foco;
- importação/exportação ficam fora deste incremento.

**Gate:** CRUD autorizado, conflito e validações cobertos; lista preserva busca, filtros, seleção e posição entre cartão, detalhe e retorno nos três breakpoints.

### F4 — Agenda

**Entrega:** primeira fatia operacional completa.

- desktop com visões diária, semanal e mensal;
- mobile iniciando em dia/lista, com alternância explícita e sem semana miniaturizada;
- tablet portrait com até três dias e landscape/semanal apenas quando cumprir largura mínima legível;
- filtros por unidade, profissional, serviço e status;
- criação/edição/cancelamento em tenant sintético;
- bloqueios, recorrência simples e detecção de conflito;
- detalhes preservando data, filtros e posição de navegação;
- alternativa em lista equivalente para teclado e leitor de tela;
- ações não dependentes de hover, drag ou gesto oculto.

**Gate:** agendamento completo, conflito concorrente, timezone e cancelamento testados E2E em mobile, tablet e desktop; nenhum cartão ou ação crítica abaixo do alvo mínimo.

### F5 — Comanda e fechamento consolidado

**Entrega:** atendimento evolui para venda auditável.

- CRUD de categorias de comanda unit-scoped no MVP, com status ativa/inativa e políticas visíveis;
- abertura rápida: categoria → cliente/agendamento/mesa/referência conforme política; reabrir comanda ativa encontrada em vez de duplicar;
- abrir comanda a partir do cliente/agendamento ou de forma avulsa, sem assumir dependência de cliente/agendamento;
- adicionar serviços, produtos, desconto autorizado e profissional;
- múltiplas comandas por cliente/agendamento, uma ativa por categoria/contexto quando configurado;
- tela mobile `Comandas abertas`, agrupamento por cliente/mesa/referência, seleção parcial e ação `Fechar tudo`;
- fechamento consolidado com `ClosingSession`, resumo por categoria e seleção de comandas sem fundi-las; a seleção exige mesma unidade e mesmo cliente ou referência;
- totais, histórico, auditoria e estados de fechamento;
- recibo interno e timeline de alterações;
- fechamento mobile com resumo de totais e ação final sticky acima da safe area, sem ocultar campos;
- tablet com itens e resumo lado a lado quando houver largura;
- registro de fechamento sem processar ou registrar pagamento/recebimento no MVP; gateway, fiscal e estorno externo permanecem como evolução posterior atrás de interfaces/adapters.

**Gate:** categorias inativadas não abrem novas comandas; unique concorrente por contexto, cálculos invariantes, idempotência, seleção multi-comanda, unidade e dupla submissão testados; teclado virtual não cobre totais ou confirmação.

### F6 — Financeiro e comissões

**Entrega:** leitura financeira confiável antes de automações avançadas.

- visão de caixa, transações, contas e conciliação manual;
- regras e prévia de comissão;
- filtros temporais e exportação assíncrona;
- estados de fechamento, reabertura e permissão reforçada;
- toda alteração material gera auditoria;
- mobile separa filtros do resultado e não comprime todas as colunas financeiras em uma mini-tabela;
- tablet usa detalhe lateral para preservar origem e contexto do lançamento.

**Gate:** valores do resumo reconciliam com o detalhe e exportações respeitam tenant/unidade/permissão.

### F7 — Painel, relatórios e análise visual

**Entrega:** decisões gerenciais rastreáveis até a fonte.

- cards de indicadores com definição, período e comparação explícitos;
- gráficos acessíveis com tabela alternativa e estado sem amostra suficiente;
- drill-down do indicador para lista filtrada;
- relatórios favoritos, catálogo, filtros salvos e exportação;
- métricas calculadas por consultas/serviços versionados, nunca no componente React;
- mobile começa por 3–5 KPIs prioritários, usa divulgação progressiva e carrega gráficos sob demanda;
- tablet organiza KPIs e gráficos em duas colunas com largura mínima por visualização;
- heatmap e gráficos densos possuem resumo e modo tabular antes de tentar reproduzir toda a densidade desktop;
- relatório mobile separa configurar, gerar e ler resultado; estado sem conteúdo recebe erro/empty explícito, nunca shell aparentemente vazio.

**Gate:** cada KPI tem fórmula, timezone, política de cancelamento/estorno e reconciliação; dashboard mobile entrega resumo útil no primeiro viewport e relatório nunca termina em shell vazio sem diagnóstico.

### F8 — Expansões controladas

**Entrega:** módulos adicionados conforme evidência e prioridade comercial.

- pacotes e assinaturas;
- metas;
- estoque avançado;
- marketing e WhatsApp;
- anamnese e documentos;
- fiscal e integrações externas.

Cada módulo exige threat/privacy review, estados de entitlement e fluxo sintético completo antes de produção.

## Ordem inicial do backlog frontend

1. Tokens, tipografia, breakpoints por conteúdo, container queries e safe-area.
2. Button, IconButton, Input, Select, Dialog, Drawer/Sheet, Toast e Skeleton.
3. AppShell com Sidebar, NavigationRail, BottomNavigation e ação contextual.
4. PageHeader, unidade ativa, menu Mais e política de ajuda sem colisão.
5. DataTable, EntityCardList, MasterDetail, Pagination e estados vazios/erro.
6. FilterBar/FilterSheet com URL, contagem, aplicar/limpar e ciclo de foco.
7. FormSection, StickyActionBar, teclado virtual, validação e guard de alterações.
8. Login e bootstrap de sessão/contexto nos três breakpoints.
9. Clientes e serviços como fatias piloto responsivas.
10. Agenda com dia/lista mobile, três dias tablet e semana desktop.
11. Categorias de comanda e abertura rápida mobile.
12. Comandas abertas agrupadas e fechamento consolidado.
13. Dashboard mobile progressivo antes do catálogo completo de relatórios.

Clientes e serviços são pilotos adequados porque exercitam listagem, busca, paginação, formulário, detalhe, autorização e relacionamentos antes da complexidade temporal e concorrente da agenda.

## Estratégia de dados e infraestrutura

### PostgreSQL no Supabase

- provisionar explicitamente o schema `app` antes do primeiro migrate remoto; usar `DB_SCHEMA=app`, `search_path=app,public`, TLS e DSN direto para migrations/administrativo;
- produção usa Session Pooler compatível com prepared statements; migrations e operações administrativas usam conexão direta;
- `migrations`, `sessions`, `cache`, `jobs` e `failed_jobs` podem ficar em `app`; Data API do Supabase não expõe `app`;
- baseline Laravel/Fortify deve coordenar users UUIDv7, `email`/`password`, `email_normalized`, `HasUuids`, passkeys/sessions/reset/2FA antes do primeiro migrate;
- exigir TLS, backups, PITR conforme plano contratado e teste periódico de restauração;
- habilitar extensões apenas com ADR e necessidade real;
- índices, constraints, tenant boundaries e auditoria são definidos em `domain/database-proposal.md`.

RLS pode ser defesa adicional, mas não substitui Policies e escopo por tenant no Laravel. Se adotado, precisa funcionar corretamente com pooling e contexto transacional antes de ser obrigatório.

SQLite é fast loop local, não gate de constraints/locks/JSONB/timestamptz. CI PostgreSQL é obrigatório antes de aceitar uma fatia.

### Redis

- prefixos separados por ambiente e aplicação;
- cache sempre com estratégia explícita de invalidação;
- filas separadas por criticidade e workers com retry/backoff controlados;
- locks para fechamento, numeração e operações idempotentes;
- Horizon pode operar filas e métricas, sem payload sensível;
- indisponibilidade do Redis não pode produzir duplicação financeira silenciosa.

## Qualidade e testes

- **PHP/feature:** Pest ou PHPUnit, assertions Inertia, Policies, validação, tenancy e invariantes;
- **frontend/unitário:** Vitest e Testing Library para comportamento de components/patterns;
- **E2E:** Playwright para login, cliente, serviço, agenda, comanda e permissões críticas;
- **acessibilidade:** axe automatizado mais revisão manual de teclado e leitor de tela nos fluxos principais;
- **visual:** screenshots estáveis de primitives, patterns e páginas nos três breakpoints;
- **contratos:** page props e respostas de mutação validadas para impedir drift silencioso;
- **performance:** orçamento de bundle, Web Vitals das páginas principais e limite para payload Inertia.

### Matriz mínima de viewport/dispositivo

| Execução                           | Cobertura                                          |
| ---------------------------------- | -------------------------------------------------- |
| Chromium 390 × 844                 | smoke de todas as rotas; fluxos críticos completos |
| Chromium 768 × 1024                | composição tablet portrait e master-detail         |
| Chromium 1024 × 768                | tablet landscape/borda do shell desktop            |
| Chromium 1440 × 900                | densidade completa e regressão principal           |
| Safari/iOS real ou device farm     | safe area, viewport dinâmica, teclado e scroll     |
| Chrome/Android real ou device farm | teclado, back, scroll e performance                |

Testes automatizados de viewport não substituem aparelho real. Antes do beta, login, cliente, agenda, comanda e relatório devem passar em pelo menos um iPhone/Safari e um Android/Chrome representativos.

Nenhum teste E2E deve depender de dados compartilhados ou reais. Cada execução cria seu tenant sintético isolado e realiza cleanup conhecido.

## Definition of Done por fatia

Uma fatia só está pronta quando:

- critérios funcionais e estados da matriz foram cobertos;
- 390 × 844, 768 × 1024, desktop e landscape foram verificados;
- aparelho real foi verificado quando a fatia envolve teclado, safe area, sticky action, scroll complexo ou gesto;
- teclado, foco, nomes acessíveis e contraste passaram;
- autorização e isolamento entre tenants têm testes negativos;
- loading, erro, vazio e conflito não perdem contexto;
- navegação, ajuda, teclado e ação sticky não cobrem conteúdo;
- responsive variants mantêm a mesma regra de negócio sem duplicação de página;
- overlays fecham/restauram conforme política explícita e testada;
- eventos relevantes têm auditoria e telemetria sanitizada;
- page props são mínimos, tipados e documentados;
- feature, component e E2E críticos estão verdes;
- não existem segredos, PII real ou dependência de fixture em produção;
- claim/proposta correspondente foi atualizado no dossiê.

## Decisões pendentes antes da F0

1. Provedor de deploy e topologia dos workers Laravel/Horizon.
2. Serviço Redis gerenciado e política de alta disponibilidade.
3. Região do Supabase e requisitos de residência/retenção de dados.
4. Estratégia de arquivos: Supabase Storage ou provider dedicado.
5. Biblioteca de gráficos após protótipo de acessibilidade e exportação.
6. Uso de SSR apenas em páginas públicas; o painel autenticado não depende dele inicialmente.
7. Ferramenta de catálogo visual e baseline de regressão visual.
8. Dispositivos reais mínimos ou device farm para a matriz mobile.
9. Política final de suporte/chat no mobile: inline, central de ajuda ou botão flutuante sem colisão.

## Relação com os artefatos canônicos

- requisitos observáveis: `information-architecture.md`, `screens/`, `flows/` e `behavior-rules.md`;
- experiência responsiva: `evidence/EV-008--batch-experiencia-mobile-tablet.md` e `screens/SCR-013--experiencia-mobile-tablet.md`;
- linguagem visual: `design-system-observed.md` como evidência e `design-system-proposed.md` como direção original;
- autorização: `roles-permissions.md`;
- contratos: `api-proposal.md`;
- persistência e privacidade: `domain/database-proposal.md` e `domain/data-governance.md`;
- priorização e lacunas: `reconstruction-backlog.md`.

Este plano governa a ordem de construção. Quando uma descoberta futura contradizer uma proposta, atualizar primeiro o claim ledger e o documento de domínio pertinente; depois revisar este plano.
