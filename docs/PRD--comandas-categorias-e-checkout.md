# PRD — Comandas, categorias e fechamento consolidado

- **Status:** proposta de produto para a próxima wave
- **Classificação:** requisitos originais do Caldas Gestão, informados pela decisão de negócio; não reconstrução do banco interno do produto referência
- **Dependências:** F2 fundação/tenancy/RBAC; CRM, catálogo e agenda F3/F4

## Problema

Operações híbridas precisam separar o consumo por área e, ao mesmo tempo, encerrar a visita com poucos toques. Uma pessoa pode usar barbearia e loja; uma mesa pode consumir restaurante e bar; uma venda pode existir sem agendamento e, em alguns contextos, sem cliente identificado.

## Objetivos

- permitir cadastrar categorias de comanda por tenant/unidade;
- abrir uma comanda com fluxo rápido e contexto explícito;
- impedir duplicidade ativa na mesma categoria/contexto;
- permitir várias categorias abertas para a mesma pessoa, mesa ou referência;
- fechar uma ou várias comandas em fechamento consolidado, sem processar pagamento;
- preservar identidade, histórico operacional e auditoria de cada comanda.

## Não objetivos da primeira wave

Gateway, link de cobrança, token/cartão, Pix API, conciliação automática, fiscal, estoque, comissão, pacotes, cashback, recorrência de consumo e entidade completa de mesas ficam fora desta wave/MVP. O desenho deve emitir eventos e manter pontos de extensão sem fingir que esses módulos já existem.

Pagamento ou recebimento, estorno e conciliação são capacidades futuras e não são modelados nesta wave. O fechamento apenas recalcula e confirma o total operacional da comanda, registra o histórico, a auditoria e um recibo interno.

## Personas e cenários

| Persona | Cenário principal | Resultado |
|---|---|---|
| Recepção | abre Barbearia a partir do agendamento | comanda criada/vinculada sem duplicar |
| Caixa | cliente compra roupa sem agendamento | comanda Loja com cliente opcional |
| Garçom | mesa consome restaurante e bar | duas comandas por categoria, mesma referência |
| Caixa mobile | cliente vai embora | seleciona e fecha todas as comandas da unidade/moeda |
| Gestor | muda regra da categoria | categoria inativada/configurada sem apagar histórico |

## Requisitos funcionais

### Categorias

Cada categoria deve possuir nome, chave estável, status (`active`/`inactive`), `tenant_id` e `unit_id` obrigatórios no MVP, tipos permitidos (`service`, `product`, `mixed`), política de cliente, política de agendamento e `uniqueness_scope` (`customer`, `appointment`, `reference`, `none`). Categorias utilizadas não podem ser excluídas; evolução tenant-wide fica para uma wave própria.

### Abertura

O operador escolhe categoria e contexto. O servidor calcula `open_context_key`, valida Policy e tenta criar em transação. Se já existir ativa, a resposta deve oferecer a existente; não deve criar duplicata por corrida ou retry. `customer_id` é opcional em `sales`; o vínculo com agendamento existe somente em `appointment_sale_links`, com no máximo um link ativo por `sale_id` (unique parcial), garantindo `Sale 0..1 Appointment` no MVP.

### Itens

`sale_items` captura tipo, nome, referência de catálogo, profissional quando aplicável, quantidade, moeda, preço unitário, descontos e total. Preço e descrição históricos não mudam quando o catálogo muda. O cálculo usa minor units inteiras e moeda explícita.

### Fechamento consolidado

O operador visualiza comandas abertas agrupadas por cliente, mesa ou referência, seleciona uma ou mais da mesma unidade/moeda e do mesmo `closing_subject` (mesmo cliente ou mesma referência) e inicia o fechamento. `ClosingSession` registra a tentativa, seleção, versões e totais esperados. O serviço trava as comandas em ordem determinística, valida versões e finaliza a seleção, gerando recibo interno e auditoria. Repetir a requisição com a mesma idempotency key devolve o resultado original. O pagamento, se existir, acontece fora do Caldas e não é registrado nesta wave.

## Regras de negócio e invariantes

1. Toda comanda pertence a um tenant e unidade; todo vínculo entre entidades valida o mesmo escopo.
2. Categoria ativa é necessária para novas comandas; histórico mantém snapshot do nome/chave quando necessário.
3. Para categorias com unicidade, há no máximo uma comanda em estados `draft`, `open` ou `ready_to_bill` por categoria/contexto.
4. `open_context_key` é materializada pelo backend, nunca aceita como autorização do cliente.
5. Comanda sem agendamento é válida; cliente é obrigatório somente quando a política determinar.
6. Um agendamento pode ter várias comandas e uma comanda pode ter no máximo um vínculo ao mesmo agendamento por regra de operação; vínculos adicionais precisam ser explícitos.
7. Fechamento consolidado não atravessa unidade, moeda ou tenant.
8. A finalização não depende de pagamento ou recebimento processado pelo Caldas; qualquer registro externo fica para uma wave futura de Caixa.
9. Dados de recebimento externo não alteram automaticamente o estado operacional do agendamento.
10. Cancelamento/finalização libera o slot de unicidade; o histórico operacional e a auditoria permanecem.
11. Desconto, cancelamento e alteração de item exigem permissionamento e motivo conforme política.
12. Eventos são mínimos, sem segredo ou PII desnecessária, e publicados após commit.
13. A cardinalidade do MVP é `Appointment 0..N Sale` e `Sale 0..1 Appointment`.
14. O fechamento nunca mistura clientes ou referências diferentes; `closing_subject` é uniforme na sessão.
15. O fechamento é idempotente e não cria entidades de pagamento nesta wave.

## Modelo de dados proposto

| Tabela | Papel | Campos/constraints essenciais |
|---|---|---|
| `sale_categories` | configuração | `tenant_id`, `unit_id`, nome/chave, status, `uniqueness_scope`, políticas; unique por escopo |
| `sales` | agregado de comanda | `tenant_id`, `unit_id`, `customer_id` nullable, ciclo, moeda, totais, `open_context_key`, `category_key_snapshot`, `category_name_snapshot`, versão |
| `sale_items` | itens capturados | snapshot de nome/preço/moeda, tipo serviço/produto, origem e quantidade |
| `sale_status_histories` | histórico | transição append-only, ator, motivo, correlação |
| `appointment_sale_links` | única fonte do vínculo | appointment/sale, ator, origem, timestamps, unique parcial de link ativo por `sale_id` |
| `closing_sessions` | agregado de fechamento consolidado | seleção, `closing_subject`, unidade/moeda, versões, totais esperados, estado e idempotência |

Unique parcial recomendado: `(tenant_id, unit_id, sale_category_id, open_context_key)` para estados ativos, com `open_context_key IS NOT NULL`; categorias `none` não usam esse índice. Índices de listagem começam por tenant/unidade/status e agrupamento por contexto.

## Estados

```text
Sale: draft → open → ready_to_bill → finalized
                └──────────────→ cancelled
finalized → adjusted (somente ajuste explícito)

ClosingSession: draft → ready → processing → completed
                           └────→ cancelled/failed
```

Pagamento ou recebimento externo não possui estado nem entidade nesta wave. `finalized` significa comanda revisada e encerrada no Caldas; não significa pagamento processado, estorno, emissão fiscal ou conciliação. Essas capacidades ficam para waves futuras.

Estados de agendamento continuam separados. `finalized` significa comanda revisada e encerrada no Caldas; não significa emissão fiscal nem pagamento externo irrevogável.

## RBAC e LGPD

Recepção pode abrir/editar dentro da unidade; operador autorizado pode iniciar fechamento e finalizar comandas; gestor pode configurar categorias, autorizar descontos e reabrir conforme política; profissional vê apenas o necessário para execução; auditor/financeiro acessa relatórios e trilhas segundo escopo. Cada permissão deve ser verificada no backend.

Nome, contato, cliente vinculado, observação e referência podem ser PII. Minimizar page props, mascarar logs, não colocar PII na chave de contexto se uma referência derivada bastar, restringir exportações e definir retenção/anonymização sem apagar auditoria ou histórico legalmente retido.

## Critérios de aceite

- criar categorias e inativá-las sem apagar comandas;
- abrir duas categorias simultâneas para o mesmo cliente;
- bloquear a segunda abertura concorrente da mesma categoria/contexto;
- abrir comanda avulsa sem agendamento;
- listar e selecionar todas/parte das comandas no mobile;
- revisar e fechar uma ou várias comandas sem fundir seus históricos;
- repetir fechamento não duplica finalização nem recibo interno;
- falhas de Policy, conflito e validação preservam seleção e contexto na UI;
- testes PostgreSQL, feature, contrato Inertia/Wayfinder, acessibilidade e E2E nos breakpoints.

## Backlog por fases e dependências

| Fase | Entrega | Depende de |
|---|---|---|
| C0 | ADR, PRD, claims e contratos de estado | F2 |
| C1 | `sale_categories`, policies, seed, CRUD de configuração | F2, shell |
| C2 | `sales`, itens, snapshots, abertura avulsa e a partir da agenda | CRM, catálogo, F4 |
| C3 | histórico, vínculo 0..N/0..1 e ações de ciclo | C2, agenda |
| C4 | fechamento consolidado, recibo interno, auditoria, outbox e read model mobile | C2/C3 |
| C5 | concorrência, E2E, acessibilidade, observabilidade e deploy | C1–C4 |

## Decisões em aberto

Definir a entidade de mesa/referência, regras de cliente anônimo por categoria, futura decisão de Caixa sobre registrar meio/valor de recebimento externo, descontos/valor zero e retenção de PII livre. Essas decisões não devem ser escondidas em migrations ou componentes.

## Fonte canônica

As invariantes normativas deste PRD são as oito invariantes canônicas do [ADR-003](adr/ADR-003--categorias-de-comanda-e-checkout-consolidado.md). Em caso de divergência, o ADR prevalece até nova decisão formal.
