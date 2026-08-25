# ADR-003 — Categorias de comanda e checkout consolidado

- **Status:** proposta aprovada para orientar a próxima wave; implementação ainda pendente
- **Data:** 25/08/2026
- **Decisores:** equipe do Caldas Gestão
- **Escopo:** venda/comanda, abertura operacional e fechamento de múltiplas comandas

## Contexto

O levantamento observou comandas, venda, itens, totais e controles de faturamento, mas não capturou o schema nem os contratos internos do produto referência. Portanto, este ADR é uma decisão original para o Caldas Gestão; não afirma reproduzir a implementação interna observada.

O produto precisa atender operações híbridas: uma barbearia pode ter serviços e loja; um restaurante pode separar restaurante, bar e serviços externos. Uma comanda pode nascer de um agendamento, mas também pode ser aberta sem agendamento e com cliente opcional. O operador precisa abrir rapidamente a comanda correta e fechar, no celular, todas as comandas abertas do mesmo cliente, mesa ou referência sem perder a identidade financeira de cada categoria.

Claims relacionados: `UX-024` a `BEH-018` em [claim-ledger.md](../reconstruction/claim-ledger.md), [FLOW-003](../reconstruction/flows/FLOW-003--agendamento-para-comanda.md) e [FLOW-004](../reconstruction/flows/FLOW-004--comanda-faturamento-pagamento.md).

## Decisões

### 1. Categoria de comanda é configuração tenant-owned

`sale_categories` é um cadastro **unit-scoped no MVP**: `tenant_id` e `unit_id` são obrigatórios. Possível evolução para categorias tenant-wide exige decisão/migration própria. A categoria possui nome, chave estável, status, cor/ícone, tipos de item permitidos, exigência de cliente, aceitação de agendamento e política de unicidade. Atualização de nome/política é auditável e nunca reescreve snapshots de `sales`. Categorias usadas não são apagadas: são inativadas, preservando comandas históricas e snapshots.

As categorias iniciais podem ser Barbearia, Loja, Restaurante, Bar e Serviço externo, mas são exemplos de seed, não enumeração fixa do domínio.

### 2. Várias comandas, no máximo uma ativa por categoria e contexto

`Sale` é independente de `Appointment` e `Customer`. Ambos são referências opcionais, condicionadas à política da categoria. Um cliente pode ter simultaneamente uma comanda de Barbearia, uma de Loja e uma de Serviço externo. Não pode haver uma segunda comanda ativa da mesma categoria para o mesmo contexto quando a categoria exigir unicidade.

O backend materializa `open_context_key` a partir de `uniqueness_scope`:

| Escopo da categoria | Chave de contexto | Exemplo |
|---|---|---|
| `customer` | `customer:{uuid}` | uma Barbearia aberta por cliente |
| `appointment` | `appointment:{uuid}` | uma comanda do agendamento naquela categoria |
| `reference` | `reference:{normalized}` | mesa 12, pedido ou serviço externo |
| `none` | valor nulo | venda sem limite de duplicidade |

O PostgreSQL aplica um unique parcial por `(tenant_id, unit_id, sale_category_id, open_context_key)` somente nos estados ativos. Ao finalizar ou cancelar, o slot é liberado. `sales.category_key_snapshot` e `sales.category_name_snapshot` são obrigatórios e preservam a configuração usada na abertura. `open_context_key` é decisão de design e não evidência do banco do produto referência.

### 3. Vínculo com agenda é 0..N para Appointment e 0..1 para Sale no MVP

Um agendamento pode originar zero ou várias comandas; cada comanda pode ter zero ou um agendamento no MVP. `appointment_sale_links` é a única fonte de verdade do vínculo e possui no máximo um link ativo por `sale_id` (unique parcial), garantindo `Sale 0..1 Appointment` sem duplicidade. O link registra ator, origem, timestamps e correlação. A criação da comanda não altera automaticamente o status operacional do agendamento; faturamento é um eixo separado.

### 4. Checkout consolidado não funde comandas

O operador pode selecionar várias comandas abertas da mesma unidade e moeda para um fechamento consolidado, por cliente, mesa ou referência. O checkout cria `payment_intents`, `payments` e `payment_allocations`; cada comanda mantém itens, histórico, categoria, auditoria e saldo próprios. Uma comanda só é finalizada quando seu saldo fica zerado e a operação atende suas regras de fechamento.

O checkout pode receber pagamentos divididos, múltiplos meios e pagamentos parciais. Uma alocação nunca pode ultrapassar o saldo da comanda nem atravessar unidade/moeda. `CheckoutSession` é o agregado da tentativa consolidada: agrupa a seleção, fixa `checkout_subject`, versões e valor esperado, e possui estados `draft → ready → processing → completed`, com `cancelled`/`failed` como terminais de tentativa. Pagamentos e alocações são efeitos da sessão; saldo parcial é derivado da soma de alocações e não é status de `Sale` ou `PaymentIntent`.

`checkout_subject` deve ser igual para todas as sales selecionadas: mesmo `customer_id` não nulo ou mesma `reference_context` normalizada. Nunca se misturam clientes ou referências diferentes. Uma sessão não atravessa unidade, tenant ou moeda.

### 5. Mobile é fluxo de operação rápida

O frontend oferece ação `Nova comanda`, busca de cliente/mesa/referência, seleção visual de categoria e reabertura da comanda ativa se a regra de unicidade encontrar uma existente. Uma tela/drawer `Comandas abertas` agrupa por cliente, mesa ou referência, permite selecionar categorias e oferece `Fechar selecionadas`/`Fechar tudo` com resumo e confirmação sticky respeitando safe area e teclado virtual.

### 6. Autorização e proteção de dados

RBAC, unidade ativa, entitlement e estado da comanda são verificados no servidor para abrir, adicionar item, aplicar desconto, vincular agenda, iniciar checkout, pagar, finalizar, cancelar ou estornar. A UI apenas reflete affordances. Cliente, contato, observação e referência livre são PII potencial; page props, logs, outbox e eventos carregam somente o mínimo necessário.

## Invariantes canônicas

Estas são a fonte única para esta decisão; PRD, modelo, banco e fluxos devem referenciá-las e não redefini-las de modo divergente.

1. `Sale` mantém somente o ciclo `draft`, `open`, `ready_to_bill`, `finalized`, `cancelled` e `adjusted`; parcialidade é saldo derivado de pagamentos/alocações.
2. `Appointment 0..N Sale`; `Sale 0..1 Appointment` no MVP.
3. `sale_categories` é unit-scoped: `tenant_id` e `unit_id` obrigatórios; tenant-wide é evolução futura.
4. Categoria ativa, `open_context_key` e unique parcial governam uma comanda ativa por categoria/contexto; `none` não impõe limite.
5. `category_key_snapshot` e `category_name_snapshot` são obrigatórios em `sales`.
6. `CheckoutSession` seleciona sales da mesma unidade/moeda e do mesmo `checkout_subject`: mesmo cliente ou mesma referência, nunca mistura ambos.
7. Saldo é derivado; uma alocação não excede saldo. Finalização libera o slot e não apaga histórico.
8. Idempotência, locks, Policies, auditoria e outbox pós-commit protegem toda mutação.

## Consequências

- O domínio suporta barbearia, loja, restaurante, bar, clínica e serviços externos sem remodelar `sales` por segmento.
- Categorias e regras aumentam a necessidade de uma tela de configuração e de validação de políticas.
- O checkout consolidado exige locks determinísticos, idempotência e uma UX clara para pagamento parcial.
- Relatórios precisam preservar dimensões de categoria, unidade, origem e vínculo com agenda.
- Financeiro, estoque, comissão e fiscal consomem eventos; não entram como uma transação longa do checkout.

## Alternativas rejeitadas

- **Uma comanda por agendamento:** impede vendas de loja e múltiplas áreas no mesmo atendimento.
- **Uma única comanda por cliente:** mistura operações com ciclos de fechamento independentes.
- **Campo livre sem constraint:** permite duplicidade concorrente e torna a regra dependente apenas da UI.
- **Fundir comandas no fechamento:** perde categoria, histórico, reconciliação e estorno granular.
- **Categoria global fixa:** não atende variações de operação por tenant e unidade.

## Gate de aceitação

- migration PostgreSQL com `sale_categories`, `sales`, itens, vínculos e unique parcial concorrente;
- tentativa simultânea de abrir a mesma categoria/contexto resulta em uma comanda e resposta idempotente;
- cliente com várias categorias abertas pode fechar todas ou apenas uma seleção;
- checkout misto rejeita unidade/moeda diferentes e não altera comandas fora da seleção;
- checkout misto rejeita clientes/referências diferentes e exige `checkout_subject` uniforme;
- `CheckoutSession` permite retry/falha sem confundir tentativa com ciclo da venda;
- pagamento parcial mantém saldo e comanda aberta; finalização libera o slot;
- categoria inativada não aparece para novas aberturas, mas permanece em histórico;
- Policies e testes negativos cobrem tenants, unidades, papéis e descontos;
- mobile 390×844, tablet e desktop cobrem abertura, lista agrupada, seleção, erro e confirmação.

## Decisões ainda em aberto

- se `reference` terá entidade tipada de mesa/conta ou apenas referência normalizada na primeira wave;
- se cliente anônimo é permitido para todas as categorias ou somente categorias configuradas;
- política de cancelamento de comanda com pagamentos já liquidados;
- se `checkout_subject` por cliente exigirá cliente obrigatório para a categoria;
- meios de pagamento iniciais e integração externa;
- regras de desconto, crédito, cashback e valor zero;
- retenção/anonymização de observações e referências livres conforme LGPD.

## Referências

- [ADR-002 — Fundação de dados e tenancy](ADR-002--fundacao-de-dados-e-tenancy.md)
- [PRD — Comandas, categorias e checkout](../PRD--comandas-categorias-e-checkout.md)
- [Proposta de banco](../reconstruction/domain/database-proposal.md)
- [Modelo de domínio](../reconstruction/domain/model.md)
