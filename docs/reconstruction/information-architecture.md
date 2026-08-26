# Arquitetura de informação

## Shell autenticado

| Grupo/área | Itens observados | Tipo |
|---|---|---|
| Global | IA Beta, criação rápida, notificações, mensagens, ajuda, perfil | ações/superfícies globais |
| Principal | Painel, Agenda, Comandas, Pacotes, Vendas por Assinatura | grupo expansível |
| Financeiro | Painel, Transações, Cadastros, Caixas abertos, Histórico de caixa, Belasis Pay, Notas Fiscais, Configurações | grupo expansível |
| Comissões | Detalhadas, Pagas, Configurações e outra visão resumida observada anteriormente | grupo expansível |
| Cadastros | Clientes, Anamneses, convite e profissionais, Fornecedores | grupo expansível |
| Controle | Serviços, Produtos, Pacotes predefinidos, Categorias, Marcas, Compras, Gerador de documento | grupo expansível |
| Relatórios | Painel, Metas | grupo expansível |
| Relacionamento | WhatsApp API Oficial, Marketing | página + grupo expansível |
| Conta/suporte | Configurações, Ajuda, Indique e ganhe, Minha Conta, Assinatura, Sair | páginas/menus |

Classificação: itens e agrupamentos são **Observed** (`UX-001`, `DOM-003`, `EV-001`). A função exata de páginas ainda não abertas permanece **Unknown**.

## Atualização da navegação do Caldas — Wave de Navegação 2

Evidência funcional adicional observada no Chrome em 26/08/2026: no estado expandido, os grupos da referência podem permanecer abertos simultaneamente. No estado recolhido, a lateral mantém um rail de ícones; o clique em uma categoria abre um flyout com os itens filhos, sem expandir novamente toda a sidebar.

Implementação independente no Caldas:

- expandido: `Principal`, `Cadastros`, `Controle`, `Configurações` e `Financeiro` usam accordions multiabertos;
- recolhido: categorias autorizadas permanecem como ícones acionáveis, com tooltip e flyout de links;
- estado de accordion é persistido por usuário; estado do flyout é efêmero;
- filhos continuam dependentes de permissões e rotas Wayfinder;
- mobile preserva barra inferior e Sheet, sem exibir o rail desktop.

Hierarquia visual proposta e implementada: o cabeçalho da categoria é um controle de módulo, com ícone, peso tipográfico, área de toque e estado de abertura próprios. Os links filhos são recuados, mais densos e possuem destaque de rota independente. Ver a especificação de execução em [`wave-navigation-2-sidebar-rail.md`](../architecture/wave-navigation-2-sidebar-rail.md).

## Criação rápida global

O menu global Novo agrupa atalhos por domínio:

- Principal: agendamento, comanda, pacote, pacote predefinido;
- Cadastros: cliente, serviço, produto, categoria, profissional, fornecedor, compra, marca;
- Financeiro: recebimento, despesa, vale, transferência.

Implicação proposta: o produto novo pode oferecer um command palette de criação contextual, com RBAC e telemetria, evitando duplicar toda a árvore de navegação.

## Agenda

- Entrada pelo grupo Principal → Agenda.
- Rota observada: `/calendar`.
- Visualizações oferecidas: diária, semanal e mensal.
- Ações de alto nível: filtrar, configurar, criar, bloquear horários e agrupar agendamentos.
- Navegação temporal por anterior/próximo e período contextual.

## Rotas observadas até o momento

| Rota | Nome neutralizado | Estado de cobertura |
|---|---|---|
| `/wow` | painel principal | happy path analítico somente leitura |
| `/calendar` | agenda | happy path somente leitura |
| `/sales` | comandas | partial |
| `/packages` | pacotes vendidos | partial |
| `/customer/subscriptions` | assinaturas de clientes | partial |
| `/finance/commissions` | comissões | partial |
| `/finance/commissions/settings` | configuração de comissões | partial |
| `/clients` | clientes | happy path somente leitura |
| `/employees` | profissionais | happy path somente leitura |
| `/vendors` | fornecedores | happy path somente leitura |
| `/services` | serviços | happy path novo/existente |
| `/products` | produtos | happy path novo |
| `/groups` | categorias | happy path novo |
| `/package-templates` | pacotes predefinidos | partial/paywall |
| `/reports/favorites` | relatórios favoritos | estado vazio |
| `/reports/financial` | catálogo financeiro | catálogo |
| `/reports/calendars` | catálogo de agendamentos | catálogo |
| `/reports/clients` | catálogo de clientes | catálogo |
| `/reports/sales` | catálogo de vendas | catálogo |
| `/reports/inventory` | catálogo de estoque | catálogo |
| `/reports/nf` | catálogo fiscal | catálogo |
| `/reports/ranking` | início de ranking | partial |
| `/reports/messages` | início de mensagens | partial |
| `/reports/financial/dre` | resultados financeiros | filtros e export menu |
| `/goals` | metas | partial/paywall |

Não serão adivinhadas URLs. Novas rotas serão registradas somente após navegação visível.

## Estado novo versus existente

Clientes, profissionais, serviços e produtos apresentam criação em etapas: o cadastro raiz vem primeiro e áreas dependentes ficam habilitadas após persistência. Ver `FLOW-002`.

## Arquitetura responsiva observada

Em 390 e 768 px, a sidebar deixa de aparecer e uma barra inferior contextual assume a navegação e ações principais. Menu abre uma superfície dedicada; listas tornam-se cartões; filtros podem ocupar a tela inteira. O conjunto da barra varia por rota, combinando Menu com ações como Agenda, Atualizar, Filtros, Selecionar, Ações e Criar. Evidência e proposta independente: EV-008 e SCR-013.

## Analytics e relatórios

- O Painel de Principal é uma visão operacional agregada.
- O Painel de Relatórios é um catálogo por famílias, apesar do mesmo rótulo na sidebar.
- Cada família possui uma página inicial e páginas-folha de relatório.
- Ranking e Mensagens usam página inicial com KPIs e sub-relatórios.
- Metas é uma página irmã do catálogo, condicionada a entitlement.

## Site público de marketing

O Batch EV-009 acrescentou a superfície pública: home, catálogo de recursos, landing pages por segmento/recurso, preços, suporte, institucional, downloads, blog e três caminhos de conversão. A arquitetura observada e a proposta independente estão em `SCR-014--site-marketing-publico.md` e `marketing-strategy-observed.md`.
