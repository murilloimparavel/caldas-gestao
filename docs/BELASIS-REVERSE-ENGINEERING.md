# Engenharia reversa clean-room do Belasis

> **Resumo não canônico.** A fonte de verdade atual é [`reconstruction/README.md`](reconstruction/README.md), com evidências e claims versionados. Em caso de divergência, o dossiê canônico prevalece.

> Documento vivo. Levantamento inicial realizado em 24/08/2026, pela interface autenticada do `belasis.app`, em modo somente leitura. Não contém código proprietário, credenciais nem dados de clientes.

## 1. Objetivo e limites

O objetivo é compreender o produto pelo comportamento observável para projetar uma solução própria. A referência deve orientar requisitos, fluxos e ergonomia; não deve resultar em cópia literal de marca, textos, imagens, código, banco de dados ou elementos protegidos.

Método clean-room adotado:

1. observar telas e estados permitidos pela conta;
2. registrar arquitetura de informação, rotas e contratos funcionais aparentes;
3. anonimizar todo dado operacional;
4. converter observações em requisitos independentes;
5. implementar o novo produto sem reutilizar artefatos proprietários.

Não foram usados bypass de autenticação, inspeção de credenciais, extração de armazenamento local, tentativa de acesso a rotas não expostas ou ações destrutivas.

## 2. Resumo do produto observado

O Belasis é um SaaS operacional para negócios de beleza e atendimento. Seu núcleo conecta agenda, clientes, execução de serviços, comandas/vendas, pacotes, pagamentos, comissões, estoque, marketing e relatórios.

O modelo mental aparente é:

```text
Empresa
├── unidades e configurações
├── profissionais e permissões
├── clientes e anamneses
├── catálogo: serviços, produtos, pacotes, categorias e marcas
├── agenda e agendamentos
├── atendimento/comanda
├── cobrança e financeiro
├── comissões
├── estoque e compras
└── relacionamento: WhatsApp, marketing, avaliações e cashback
```

## 3. Arquitetura de informação

### Principal

| Página | Rota observada | Capacidades aparentes |
|---|---|---|
| Painel | `/wow` | indicadores por período, visão gerencial |
| Agenda | `/calendar` | agenda semanal, profissional, filtros, visualizações, ações e novo agendamento |
| Comandas | `/sales` | busca, filtros, período, listagem e criação |
| Pacotes | `/packages` | listagem, status, validade, cliente, valor e nota fiscal |
| Vendas por assinatura | `/customer/subscriptions` | assinaturas, modelos, configurações, período e contratação do recurso |

### Financeiro e comissões

- Financeiro é um grupo expansível a detalhar no segundo passe.
- Comissões contém pelo menos:
  - Detalhadas — `/finance/commissions`;
  - Resumidas;
  - Pagas;
  - Configurações — `/finance/commissions/settings`.
- A tela de configurações observada trabalha com critérios como valor e nome da empresa.

### Cadastros

- Clientes;
- Anamneses;
- Convidar profissionais;
- Profissionais;
- Fornecedores.

### Controle

- Serviços;
- Produtos;
- Pacotes predefinidos;
- Categorias;
- Marcas;
- Compras;
- Gerador de documento.

### Relatórios

- Painel;
- Metas.

### Marketing e relacionamento

- WhatsApp API Oficial;
- Link de agendamento;
- Agendamento online;
- Automação de marketing;
- Promoções;
- Avaliações;
- Cashback.

### Conta e suporte

- notificações;
- suporte, base de conhecimento, feedback e novidades;
- minha conta;
- assinatura;
- indicação;
- saída.

## 4. Padrões de interface observados

- SPA com navegação lateral hierárquica e grupos expansíveis.
- Barra global com notificações, mensagens, ajuda, perfil e criação rápida.
- Cabeçalhos de página com ações primárias e secundárias.
- Listagens com busca, filtros, intervalo de datas, paginação e ações por item.
- Agenda semanal em grade, colunas por dia, linhas em intervalos de 10 minutos e agrupamento por profissional.
- Ações de agenda: visualização, filtro, ações, configuração e novo.
- Tabelas e cards usam estados vazios, carregamento e modais/drawers.
- CTA primário em azul-violeta, fundo claro e sidebar escura.
- Tipografia observada: Inter com fallbacks de sistema.
- Cor primária observada: aproximadamente `rgb(80, 90, 251)` / `#505afb`.
- Cantos recorrentes entre 6 e 12 px.
- Chat flutuante e centrais de ajuda/feedback integradas.

## 5. Sinais técnicos observáveis

Estas são inferências, não confirmação do código interno:

- aplicação montada em `#root`, fortemente compatível com React;
- bundle com hash no formato `/assets/index-*.js`, compatível com build moderno como Vite;
- Ant Design identificado pelas classes `ant-*`;
- componentes estilizados com classes geradas, compatíveis com CSS-in-JS;
- Recharts indicado por `recharts_measurement_span`;
- PWA/mobile-web-app habilitado por metatags;
- integrações observadas: Google Analytics/GTM, Meta Pixel, Bing, Hotjar, Crisp, Tawk, Wootric e ActiveCampaign;
- versão visível no levantamento: `v5.8.17`.

Essas escolhas não precisam ser copiadas. Para o produto novo, tecnologia deve seguir requisitos de manutenção, equipe, privacidade e custo.

## 6. Domínios e entidades candidatas

| Domínio | Entidades mínimas |
|---|---|
| Identidade | tenant, unidade, usuário, papel, permissão, sessão |
| Pessoas | cliente, profissional, fornecedor, contato, consentimento |
| Catálogo | serviço, produto, categoria, marca, pacote, item de pacote, preço |
| Agenda | agenda, disponibilidade, bloqueio, agendamento, participante, recorrência, status |
| Atendimento | comanda, item, execução de serviço, consumo de produto, desconto, observação |
| Comercial | venda, assinatura, cobrança, pagamento, estorno, nota fiscal |
| Financeiro | conta, lançamento, categoria financeira, centro de custo, caixa, conciliação |
| Comissões | regra, base de cálculo, comissão gerada, ajuste, pagamento |
| Estoque | saldo, movimentação, lote, compra, item de compra, inventário |
| Relacionamento | campanha, automação, mensagem, avaliação, cashback, indicação |
| Clínico | anamnese, modelo, resposta, documento, assinatura/aceite |
| Analytics | meta, métrica, evento, relatório, exportação |

## 7. Fluxos críticos a especificar

### Agendamento até recebimento

```text
Cliente → Agendamento → Confirmação/lembrete → Check-in
→ Execução dos serviços → Comanda → Desconto/acréscimo
→ Pagamento → Comissão → Caixa/financeiro → Pós-atendimento
```

### Pacote

```text
Modelo de pacote → Venda ao cliente → Crédito/sessões
→ Consumo no atendimento → Saldo/validade → Renovação ou encerramento
```

### Assinatura

```text
Modelo → Adesão → Cobrança recorrente → Benefícios/créditos
→ Falha de cobrança/retentativa → Pausa/cancelamento
```

### Estoque

```text
Compra → Entrada → Consumo/venda → Ajuste/inventário
→ Alerta de mínimo → Reposição
```

## 8. Matriz de estados que o clone precisa definir

Para cada entidade transacional, definir explicitamente:

- estados possíveis;
- transições permitidas;
- papel autorizado;
- validações;
- efeitos financeiros e de estoque;
- eventos emitidos;
- notificações;
- auditoria;
- reversão/estorno;
- idempotência.

Exemplo inicial de agendamento:

```text
rascunho → agendado → confirmado → em atendimento → concluído
                    ↘ não compareceu
agendado/confirmado → cancelado
```

## 9. Lacunas do levantamento inicial

“Mapear por completo” exige múltiplos passes e contas de teste. Permanecem pendentes:

- formulários de criação e edição, sem submetê-los na conta real;
- campos, máscaras, validações e mensagens de erro;
- permissões por perfil;
- todos os submenus financeiros e de configurações;
- estados vazios, erro, offline, timeout e conflito;
- regras de comissão, caixa, nota fiscal e estoque;
- responsividade em mobile/tablet;
- importações, exportações e impressão;
- integrações e webhooks configuráveis;
- recorrência, cancelamento, reembolso e exclusão;
- acessibilidade e atalhos de teclado;
- limites de plano e paywalls;
- contratos de API, que devem ser definidos para o produto novo sem copiar APIs privadas.

## 10. Protocolo para os próximos passes

1. Criar um tenant de teste sem dados reais.
2. Definir personas: proprietário, recepção, profissional, financeiro e cliente.
3. Percorrer cada página com uma ficha padrão: objetivo, rota, papéis, entradas, ações, estados, validações, saídas e integrações.
4. Criar registros sintéticos identificáveis e descartáveis.
5. Registrar cada transição com antes/depois e efeitos cruzados.
6. Repetir em desktop, tablet e mobile.
7. Montar catálogo de componentes e tokens visuais próprios.
8. Converter cada fluxo em critérios de aceite e testes E2E.
9. Validar requisitos com usuários reais, priorizando problemas e não paridade cega.
