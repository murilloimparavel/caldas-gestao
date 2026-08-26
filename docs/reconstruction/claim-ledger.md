# Ledger de claims

| ID | Classificação | Claim | Contexto | Evidência | Confiança | Implicação / próximo teste |
|---|---|---|---|---|---|---|
| UX-001 | Observed | A navegação autenticada usa sidebar hierárquica com grupos expansíveis. | Desktop, papel desconhecido | DOM acessível do shell em 24/08/2026 | alta | Documentar hierarquia e comportamento expandido/recolhido. |
| UX-047 | Observed | No estado recolhido, a referência mantém ícones de categorias e abre um flyout lateral de links ao acionar uma categoria; o flyout fecha ao navegar ou com Escape. | Belasis, Chrome, shell autenticado, 26/08/2026 | EV-010 | alta | Usar rail acionável e flyout acessível no produto novo. |
| UX-048 | Proposed | No Caldas, o cabeçalho da categoria deve ser visualmente distinto dos filhos: módulo com ícone, chevron, contraste e área maior; filhos recuados, mais densos e com ativo próprio. | Shell autenticado do Caldas | Wave de Navegação 2 | alta | Manter hierarquia compreensível nos modos expandido e recolhido. |
| UX-002 | Observed | O shell oferece ações globais de notificações, mensagens, ajuda, perfil e criação. | Desktop, estado autenticado | DOM acessível do shell | alta | Verificar destinos, badges e comportamento por papel. |
| UX-003 | Observed | A agenda semanal usa colunas por dia e linhas em intervalos de 10 minutos, agrupadas por profissional. | `/calendar`, semana populada | DOM + inspeção visual em 24/08/2026 | alta | Verificar outras visualizações, scroll, sobreposição e conflito. |
| UX-004 | Observed | A interface usa Inter, fundo claro, sidebar escura e CTA azul-violeta próximo de `#505afb`. | Shell e configuração de comissões | estilos computados | média-alta | Clusterizar tokens em mais duas telas antes de normalizar. |
| UX-005 | Inferred | A biblioteca principal de componentes é Ant Design. | Shell autenticado | classes `ant-*` recorrentes | alta | Tratar como detalhe de implementação; propor design system original. |
| DOM-001 | Observed | A aplicação é montada em um elemento `#root`. | Tela autenticada | DOM renderizado | alta | Compatível com SPA; não prova framework isoladamente. |
| DOM-002 | Inferred | O frontend provavelmente usa React com bundle moderno semelhante a Vite. | Tela autenticada | `#root`, sinal de Recharts e assets com hash | média | Não reproduzir stack por paridade; escolher stack do produto novo. |
| BEH-001 | Observed | Itens de menu de domínio podem apenas expandir submenus sem mudar a rota. | Financeiro, Cadastros, Controle, Relatórios, Marketing | cliques de navegação somente leitura | alta | Diferenciar grupo e página-folha no mapa de IA. |
| BEH-002 | Observed | Listagens principais oferecem combinação recorrente de busca, filtros, período e ação Novo. | Comandas, Pacotes, Assinaturas | controles visíveis | alta | Criar padrão original de data table e critérios por tela. |
| BEH-003 | Unknown | Abrir o formulário de novo agendamento pode ou não criar rascunho/telemetria no servidor. | Agenda | ainda não testado | baixa | Abrir somente após observar se a ação é segura; não submeter em conta real. |
| DOM-003 | Observed | Rotas vistas incluem `/wow`, `/calendar`, `/sales`, `/packages`, `/customer/subscriptions`, `/finance/commissions` e `/finance/commissions/settings`. | Navegação autenticada | URL após ações explícitas | alta | Mapear páginas-folha restantes sem adivinhar URLs. |
| DOM-004 | Observed | O produto exibe uma versão visível `v5.8.17`. | Sidebar | texto renderizado | alta | Registrar apenas como contexto temporal do levantamento. |
| UX-006 | Observed | O menu global Novo agrupa atalhos de criação em Principal, Cadastros e Financeiro. | Shell, menu aberto | EV-001 | alta | Considerar command palette original com RBAC. |
| UX-007 | Observed | A agenda oferece visualizações diária, semanal e mensal. | `/calendar`, dropdown Visualização | EV-001 | alta | Mapear cada visão em batches futuros. |
| UX-008 | Observed | O filtro da agenda permite profissionais e seis status visíveis. | `/calendar`, filtro aberto | EV-001 | alta | Não registrar nomes de profissionais; testar combinações em tenant sintético. |
| UX-009 | Observed | As configurações da agenda têm abas Geral, Visualização e Cores e algumas opções afetam todos os usuários. | diálogo Configurações da Agenda | EV-001 | alta | Separar preferências por usuário de políticas globais no produto novo. |
| UX-010 | Observed | Alguns controles somente com ícone não expõem nome acessível útil. | shell e agenda | EV-001 | alta | Exigir accessible name e testes automatizados. |
| BEH-004 | Observed | Abrir Novo agendamento mantém `/calendar` e exibe diálogo; Cancelar fecha sem mudança visual. | agenda semanal | EV-001 | alta | Capturar rede sanitizada apenas em tenant de teste. |
| BEH-005 | Observed | O formulário inicia com defaults contextuais de data, status, profissional, horário, duração, lembrete, encaixe e recorrência. | diálogo Novo agendamento | EV-001 | alta contextual | Verificar origem e precedência dos defaults. |
| BEH-006 | Observed | Ações finais distintas são Salvar e Criar comanda. | diálogo Novo agendamento | EV-001 | alta | Definir atomicidade, idempotência e compensação no produto novo. |
| UX-011 | Observed | Clientes usam listagem paginada com filtros de status, tags, celular, débito, aniversário e avaliação. | `/clients` | EV-002 | alta | Projetar busca e filtros com PII minimizada. |
| UX-012 | Observed | Cliente existente abre em Painel e habilita áreas históricas; cliente novo habilita somente Cadastro. | drawer de cliente | EV-002 | alta | Modelar detalhe 360° com sub-recursos autorizados. |
| UX-013 | Observed | Profissional novo disponibiliza Cadastro, Endereço, Usuário e Assinatura; áreas operacionais ficam desabilitadas. | `/employees` | EV-002 | alta | Separar identidade profissional, acesso e políticas. |
| UX-014 | Observed | Serviço existente habilita seis áreas avançadas que estão desabilitadas no serviço novo. | `/services` | EV-002 | alta | Criar sub-recursos independentes pós-persistência. |
| UX-015 | Observed | Produto possui modos Produtos, Lotes e validades e Solicitações. | `/products` | EV-002 | alta | Mapear lote/validade e solicitações em batch próprio. |
| BEH-007 | Observed | Entidades novas bloqueiam abas que dependem de um registro persistido. | cliente, profissional, serviço e produto | EV-002 | alta | Explicar dependência e carregar sub-recursos após criação. |
| BEH-008 | Observed | Produto pode controlar estoque automaticamente conforme comanda, pacote e compra. | aba Configurações de produto | EV-002 | alta | Usar ledger idempotente de movimentos no produto novo. |
| BEH-009 | Observed | Serviço permite políticas por profissional, consumo de produtos, retorno, cuidados, comissão e fiscalidade após criação. | serviço existente | EV-002 | alta | Versionar políticas e separar contextos. |
| BEH-010 | Observed | Novo pacote predefinido é condicionado a funcionalidade contratada e abre paywall. | `/package-templates` | EV-002 | alta | Tratar entitlements no servidor e UX de upgrade. |
| BEH-011 | Inferred | Painéis de cliente parecem montados simultaneamente no DOM, mesmo quando outra aba está selecionada. | cliente existente | EV-002 | média | Não atribuir tabelas por aba sem evidência visual; considerar performance/privacidade. |
| DOM-005 | Observed | Novas rotas visíveis: `/clients`, `/employees`, `/vendors`, `/services`, `/products`, `/groups`, `/package-templates`. | navegação do Batch 2 | EV-002 | alta | Adicionar ao mapa de IA. |
| UX-016 | Observed | O painel combina KPIs, séries temporais, composição, ranking, funil, ocupação e heatmap sob um filtro temporal. | `/wow` | EV-003 | alta | Definir contratos e drill-down de cada métrica. |
| UX-017 | Observed | Agendamentos e Comandas alternam o bloco analítico primário; os blocos inferiores permanecem. | `/wow` | EV-003 | alta | Projetar tabs com estado na URL e carregamento independente. |
| UX-018 | Observed | Relatórios são organizados em nove famílias e podem ser favoritados. | `/reports/*` | EV-003 | alta | Favorito deve ser preferência, não autorização. |
| UX-019 | Observed | Relatório financeiro detalhado usa filtros tipados, geração explícita e exportação separada. | `/reports/financial/dre` | EV-003 | alta | Modelar execução e export job auditável. |
| UX-020 | Observed | Metas apresenta navegação mensal, filtros e tabela, mas a conta observada está bloqueada por entitlement. | `/goals` | EV-003 | alta contextual | Validar fluxo completo somente em conta licenciada/teste. |
| BEH-012 | Observed | Abrir o menu de exportação revela Excel sem iniciar download. | relatório financeiro | EV-003 | alta | Exigir ação explícita, RBAC e auditoria. |
| BEH-013 | Inferred | Métricas do painel são agregações de agendamentos, comandas, vendas e profissionais no período. | `/wow` | EV-003 | média-alta | Validar fórmulas com dataset sintético conhecido. |
| DOM-006 | Observed | Gráficos do painel usam SVG/Recharts e não expõem `role` ou `aria-label` próprios. | `/wow`, desktop | EV-003 | alta | Implementar nomes, resumos e tabela alternativa. |
| DOM-007 | Observed | Rotas analíticas incluem `/reports/favorites`, famílias em `/reports/*`, detalhe financeiro e `/goals`. | Batch 3 | EV-003 | alta | Incorporar no mapa de IA sem assumir APIs internas. |
| UX-021 | Observed | Agenda oferece vistas diária, semanal e mensal com títulos e estruturas próprias. | `/calendar` | EV-004 | alta | Projetar cada vista para sua decisão operacional. |
| UX-022 | Observed | A vista mensal usa 42 células e inclui dias adjacentes ao mês. | `/calendar`, mensal | EV-004 | alta | Diferenciar visualmente mês corrente sem depender só de cor. |
| UX-023 | Observed | Popover do agendamento reúne contato, conversa, período, serviço, origem, observação, faturamento, cor e ações. | semanal | EV-004 | alta | Separar leitura rápida de edição destrutiva. |
| BEH-014 | Observed | Agendamento faturado revela vínculo para comanda; não faturado não apresenta o vínculo. | quatro exemplos da semana | EV-004 | alta contextual | Validar ordem das transições no Batch 5. |
| BEH-015 | Observed | Alternar vista mantém a rota `/calendar` e altera título/documento e estrutura da grade. | diária/semanal/mensal | EV-004 | alta | Definir persistência e deep-link da preferência no produto novo. |
| DOM-008 | Observed | A agenda semanal usa FullCalendar (`fc-*`) e grid semântico parcial. | `/calendar` | EV-004 | alta | Detalhe de implementação, não requisito de stack. |
| A11Y-009 | Observed | Ações visuais do popover não são consistentemente expostas como links ou botões. | agendamento existente | EV-004 | alta | Exigir semântica, nome e foco para todas as ações. |
| UX-024 | Observed | Comandas usam busca, filtros multidimensionais, seleção, tabela paginada e ações por linha. | `/sales` | EV-005 | alta | Projetar estados independentes de venda e pagamento. |
| UX-025 | Observed | O drawer de comanda existente combina contexto 360° do cliente, itens, benefícios, totais e ações. | comanda finalizada | EV-005 | alta | Limitar PII e separar leitura de edição. |
| UX-026 | Observed | Nova comanda possui comandos distintos Salvar e Faturar; Faturar iniciou desabilitado. | nova comanda | EV-005 | alta contextual | Mapear pré-condições em tenant sintético. |
| BEH-016 | Observed | Comanda finalizada pode coexistir com pagamento Pago ou Bloqueado. | listagem populada | EV-005 | alta contextual | Não colapsar status de venda e cobrança. |
| BEH-017 | Observed | Existem comandas finalizadas/pagas com valor líquido zero. | listagem populada | EV-005 | alta contextual | Investigar pacote, assinatura, crédito ou cortesia. |
| BEH-018 | Observed | Nova comanda inicia com crédito/cashback indisponíveis e Faturar desabilitado. | diálogo novo | EV-005 | alta contextual | Expor motivos e elegibilidade no produto novo. |
| A11Y-010 | Observed | A tabela de comandas contém botões de ação sem nome acessível. | `/sales` | EV-005 | alta | Exigir accessible name e teste de teclado. |
| UX-027 | Observed | Painel financeiro separa vencimentos do dia, saldos por conta, totais, fluxo e vendas. | `/finance/dashboard` | EV-006 | alta | Declarar data e base de cada agregado. |
| UX-028 | Observed | Transações podem ser filtradas por três datas: vencimento/disponibilidade, competência e pagamento. | `/finance/transactions` | EV-006 | alta | Modelar datas separadamente. |
| UX-029 | Observed | Tabela financeira expõe bruto, líquido, conta, origem, categoria, status e marcação de pagamento. | transações | EV-006 | alta | Preservar linhagem e taxas. |
| BEH-019 | Observed | Lançamentos originados de comanda exibem referência à venda. | transações | EV-006 | alta | Usar vínculo auditável entre contextos. |
| BEH-020 | Observed | Histórico de caixa modela abertura, fechamento, saldo inicial e conferido. | histórico vazio | EV-006 | alta para estrutura | Testar divergência em tenant sintético. |
| BEH-021 | Observed | Fiscal e comissões são condicionados a entitlement na conta atual. | fiscal/comissões | EV-006 | alta contextual | Não inferir regras não acessíveis. |
| A11Y-011 | Observed | Ícone de editar transação não é exposto como botão nomeado. | transações | EV-006 | alta | Corrigir semântica e foco. |
| UX-030 | Observed | Configurações cobrem empresa, notificações, personalização, administração e API. | `/settings/*` | EV-007 | alta | Separar escopos tenant/unidade/usuário. |
| UX-031 | Observed | Marketing agrupa agendamento online, automação, promoções, avaliações e cashback. | menu Marketing | EV-007 | alta | Modelar relacionamento como contexto separado. |
| UX-032 | Observed | Ajuda agrupa suporte, conhecimento, feedback e novidades. | menu Ajuda | EV-007 | alta | Preservar contexto ao abrir suporte. |
| BEH-022 | Observed | Recursos não contratados permanecem descobríveis e apresentam aquisição/paywall. | WhatsApp, API e batches anteriores | EV-007 | alta contextual | Separar entitlement de autorização. |
| BEH-023 | Observed | Minha Conta permite alteração de e-mail e senha com senha atual. | diálogo de usuário | EV-007 | alta | Exigir reautenticação e auditoria. |
| A11Y-012 | Observed | Primeiro Tab no diálogo Minha Conta focou `DIV` sem nome/papel. | diálogo de usuário | EV-007 | alta | Corrigir ciclo e foco inicial. |
| DOM-009 | Observed | Superfícies avaliadas não expõem landmarks `main`/`navigation`. | desktop | EV-007 | alta | Estruturar shell semanticamente. |
| UX-033 | Observed | Em 390 e 768 px, a sidebar é substituída por barra inferior flutuante com ações variáveis por rota. | shell autenticado | EV-008 | alta | Propor destinos estáveis e ação contextual separada. |
| UX-049 | Implemented | A navegação do Caldas mantém barra inferior e Sheet no mobile; o rail compacto é exclusivo do desktop. | Caldas local, shell autenticado | Wave de Navegação 2, 26/08/2026 | alta | Validar em dispositivos físicos e com permissões parciais. |
| UX-034 | Observed | Listagens de clientes, serviços e comandas viram cartões em coluna sem overflow horizontal do documento. | mobile 390 px | EV-008 | alta | Validar paginação, detalhe e ações destrutivas em tenant sintético. |
| UX-035 | Observed | A agenda semanal mantém sete dias e intervalos de dez minutos comprimidos em 390 e 768 px. | `/calendar` | EV-008 | alta | Usar dia/lista como padrão mobile e três dias no tablet portrait. |
| UX-036 | Observed | O painel empilha KPIs e análises em uma coluna; em tablet o documento observado ultrapassou quatro mil pixels. | `/wow`, 768 × 1024 | EV-008 | alta contextual | Priorizar resumo progressivo, duas colunas em tablet e drill-down. |
| UX-037 | Observed | Filtros de Clientes ocupam o viewport inteiro e o overlay reapareceu em retorno posterior à rota. | `/clients`, mobile/tablet | EV-008 | média-alta | Determinar política de restauração e testar foco/fechamento. |
| UX-038 | Observed | Em uma visita mobile, o DRE exibiu shell/retorno sem conteúdo analítico visível. | `/reports/financial/dre`, 390 × 844 | EV-008 | média contextual | Repetir sob condições controladas antes de classificar como falha persistente. |
| A11Y-014 | Observed | Itens da barra inferior não aparecem consistentemente como botões/links no snapshot acessível. | mobile/tablet | EV-008 | alta | Exigir semântica, nome, estado ativo e ordem previsível. |
| A11Y-015 | Observed | Barra inferior e chat flutuante ocupam simultaneamente a zona inferior do viewport. | mobile/tablet | EV-008 | alta | Reservar safe area e impedir cobertura/competição de toque. |
| A11Y-016 | Inferred | A grade semanal comprimida produz alvos de toque e texto excessivamente pequenos. | `/calendar`, 390 px | EV-008 | média-alta | Medir cartões em aparelho físico e oferecer modo lista/dia. |
| UX-039 | Observed | O site posiciona o produto como plataforma/CRM com IA para crescimento, combinando operação, relacionamento, dados e pagamentos. | home pública | EV-009 | alta | Criar posicionamento original centrado no ciclo operacional completo. |
| UX-040 | Observed | A jornada oferece conversão self-service, vendas assistidas e demonstração consultiva. | home, preços e conversão | EV-009 | alta | Definir critérios de roteamento por perfil e intenção. |
| UX-041 | Observed | O catálogo público agrupa capacidades em Popular, Marketing e relacionamento, Automações e Gestão. | `/recursos` | EV-009 | alta | Alinhar taxonomia de marketing, produto e entitlements. |
| UX-042 | Observed | Páginas de segmento compartilham quase toda a composição e personalizam principalmente hero e alguns rótulos. | quatro segmentos | EV-009 | alta | Projetar páginas por job, fluxo e prova específica. |
| UX-043 | Observed | Planos publicados são Lite, Pro e Scale, com progressão de operação para automação e escala assistida. | `/precos`, 24/08/2026 | EV-009 | alta temporal | Tratar preços como pesquisa, não decisão do produto novo. |
| UX-044 | Observed | Suporte, migração, treinamento e gerente de contas são argumentos centrais de redução de risco. | home, suporte, preços | EV-009 | alta | Modelar onboarding/customer success como capacidade do produto. |
| UX-045 | Observed | Home e páginas comerciais são extensas, repetitivas e mantêm CTA fixo de teste. | desktop/mobile | EV-009 | alta | Propor narrativa menor, progressiva e sem competição de CTA. |
| UX-046 | Observed | Comparação mobile contém regiões internas muito mais largas que o container e overflow oculto. | `/precos`, 390 × 844 | EV-009 | média-alta | Implementar comparação por accordion ou dois planos selecionados. |
| BEH-024 | Observed | Formulários de criar conta, vendas e demonstração são incorporados de `hub.belasis.ai`. | páginas de conversão | EV-009 | alta | Não inferir campos/contratos; desenhar consentimento e ownership próprios. |
| DOM-010 | Observed | O site usa Inter, primária próxima de `#505afb`, H1 64 px desktop/36 px mobile e raio recorrente de 12 px. | home pública | EV-009 | média-alta | Evidência visual apenas; manter sistema original proposto. |
| A11Y-017 | Observed | Parte do conteúdo animado existe no DOM antes de estar visualmente legível. | home desktop/mobile | EV-009 | média | Garantir conteúdo sem dependência de animação e reduced motion. |
| A11Y-018 | Observed | Marca e alguns controles de navegação pública não expõem nome acessível útil. | header público | EV-009 | alta | Nomear home/menu e comunicar estado expandido. |

## Convenção

- `Observed`: evidência diretamente visível ou resultante da interação escopada.
- `Inferred`: explicação provável, com alternativas possíveis.
- `Proposed`: decisão original para o novo produto.
- `Unknown`: ainda não testado ou inseguro testar no ambiente atual.
