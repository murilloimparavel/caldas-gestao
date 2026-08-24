# Inventário de componentes

## Componentes globais

| ID | Componente | Estados observados | Reutilização proposta |
|---|---|---|---|
| CMP-001 | Sidebar hierárquica | recolhida/expandida/selecionada | shell autenticado |
| CMP-002 | Botão/menu de criação rápida | fechado/aberto | criação transversal |
| CMP-003 | Header de página | título, navegação, ações | todas as rotas |
| CMP-004 | Badge de atividade | contagem/dot | notificações/mensagens |
| CMP-005 | Dropdown de ações | fechado/aberto | ações contextuais |
| CMP-006 | Diálogo tabulado | aberto/fechado/aba selecionada | configurações complexas |
| CMP-007 | Botão ícone | normal/ativo/desabilitado | navegação e ações compactas |

## Componentes da agenda

| ID | Componente | Estados observados | Observações |
|---|---|---|---|
| CMP-101 | Grade semanal | populada | dias × intervalos × profissionais |
| CMP-102 | Cabeçalho temporal | período + anterior/próximo | título contextual |
| CMP-103 | Cartão de agendamento | status distintos + menu | contém PII; exige redação |
| CMP-104 | Painel de filtro | aberto/fechado, checks | profissional + status |
| CMP-105 | Seletor de visualização | diário/semanal/mensal | dropdown |
| CMP-106 | Linha de item de agendamento | preenchida, exclusão desabilitada | serviço/profissional/horário/duração |
| CMP-107 | Seletor pesquisável | vazio/preenchido/aberto | cliente, serviço e profissional |
| CMP-108 | Switch com descrição | ligado/desligado | lembrete, encaixe, configuração |
| CMP-109 | Configuração de cor/status | padrão/personalizada | não depender só de cor |
| CMP-110 | Grade diária | dia × profissional × intervalo | execução operacional |
| CMP-111 | Grade mensal | seis semanas × sete dias | navegação e densidade mensal |
| CMP-112 | Popover de agendamento | não faturado/faturado + vínculo | leitura rápida e ações contextuais |
| CMP-113 | Indicador de origem | online/outros desconhecidos | ícone + texto obrigatório |
| CMP-114 | Vínculo agenda–comanda | ausente/presente | deep-link autorizado |
| CMP-401 | Tabela de comandas | vazia/populada, seleção, paginação | venda + cobrança + fiscal |
| CMP-402 | Drawer de comanda | nova/visualização/edição | checkout operacional |
| CMP-403 | Linha de item de venda | vazia/preenchida/desabilitada | serviço/produto + profissional |
| CMP-404 | Resumo de totais | desconto/crédito/cashback/total | cálculo no servidor |
| CMP-405 | Painel 360° no checkout | saldos, débitos, pacotes, assinatura, notas | PII e autorização fina |
| CMP-406 | Ações de impressão/histórico | menu fechado/aberto | não misturar com exclusão |
| CMP-501 | Painel de saldos | contas + resumo temporal | diferenciar contábil/disponível |
| CMP-502 | Filtro contábil | três datas, conta, status, meio, categoria | alta densidade |
| CMP-503 | Tabela de lançamentos | bruto/líquido/origem/status | ledger operacional |
| CMP-504 | Ciclo de caixa | aberto/histórico/conferência | divergência auditável |
| CMP-505 | Hub fiscal | NFS-e/NF-e/NFC-e/XML | entitlement observado |
| CMP-506 | Hub de comissões | detalhada/resumida/paga/configuração | entitlement observado |
| CMP-601 | Settings hub | empresa/notificações/personalização/admin/API | escopos distintos |
| CMP-602 | Module acquisition | disponível/bloqueado/contratar | entitlement, não permissão |
| CMP-603 | User security dialog | e-mail/senha | step-up e foco seguro |
| CMP-604 | Support menu | suporte/base/feedback/novidades | contexto preservado |
| CMP-605 | Relationship menu | online/automação/promoção/avaliação/cashback | canais e consentimento |

## Inconsistências observadas

- alguns botões somente com ícone não expõem nome acessível;
- opções de certos selects expõem valores internos como nome acessível;
- o filtro e dropdowns usam estruturas semelhantes, mas sem landmark/label consistente;
- a grade expõe agrupamentos densos que podem dificultar leitura por tecnologia assistiva.

## Cadastros e catálogo

| ID | Componente | Estados observados | Observações |
|---|---|---|---|
| CMP-201 | Data table paginada | populada/vazia, filtros, seleção | clientes, fornecedores, catálogo |
| CMP-202 | Drawer de entidade 360° | novo/detalhe, abas bloqueadas/habilitadas | cliente/profissional |
| CMP-203 | Formulário seccionado | seções recolhidas/expandidas | cliente |
| CMP-204 | Tabs dependentes de persistência | disabled/enabled/selected | cliente, profissional, serviço, produto |
| CMP-205 | Money/percent input | zero/preenchido | preço, custo, comissão, cashback |
| CMP-206 | Controle de unidade/estoque | unidade, mínimo, inicial, equivalência | produto |
| CMP-207 | Entitlement/paywall dialog | capacidade não contratada | pacote predefinido |
| CMP-208 | Histórico 360° | painel e áreas operacionais | cliente existente |

## Analytics e relatórios

| ID | Componente | Estados observados | Observações |
|---|---|---|---|
| CMP-301 | KPI comparativo | valor, submétrica, alta/queda | declarar fórmula e período |
| CMP-302 | Sparkline | série temporal compacta | precisa equivalente textual |
| CMP-303 | Gráfico analítico | barras, área, rosca, pizza, funil | Recharts observado; stack não é requisito |
| CMP-304 | Tabela de ranking | ordenada, delta por coluna | profissional + métricas |
| CMP-305 | Indicador de ocupação | percentual + faixa qualitativa | não depender de cor/medalha |
| CMP-306 | Heatmap temporal | hora × dia + intensidade | requer legenda e modo tabular |
| CMP-307 | Catálogo de relatórios | família, favorito, página ativa | favorito é preferência |
| CMP-308 | Construtor de filtros | período, conta, switches, checks | esquema varia por relatório |
| CMP-309 | Execução/exportação | gerar, loading, menu de exportação | separar consulta de export job |
| CMP-310 | Progresso de meta | período, profissional, progresso | bloqueado por entitlement observado |
# Complemento responsivo — EV-008

## Barra inferior contextual observada

- fixa, flutuante e com aproximadamente 64 px de altura;
- margem lateral aproximada de 15 px;
- item Menu recorrente e até quatro ações dependentes da rota;
- chat flutuante de 56 × 56 px acima da barra;
- semântica de link/botão e estado ativo inconsistentes no snapshot acessível.

### Proposta original

- quatro destinos estáveis e semanticamente nomeados;
- ação contextual primária separada;
- ações secundárias em bottom sheet;
- padding calculado com `env(safe-area-inset-bottom)`;
- conteúdo sempre recebe espaço inferior equivalente à navegação;
- suporte não compete com a ação primária nem cobre conteúdo.

## Cartão de entidade mobile observado

- substitui linha de tabela em Clientes, Serviços e Comandas;
- mantém seleção, título e atributos secundários;
- controles de excluir/selecionar são recorrentes por item.

### Proposta original

- cartão inteiro abre detalhe, com alvo claramente separado do menu de ações;
- no máximo uma ação inline não destrutiva;
- seleção múltipla entra por modo explícito;
- exclusão fica em overflow e exige confirmação;
- skeleton conserva altura e hierarquia do cartão real.
