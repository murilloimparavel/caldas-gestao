# Modelo de domínio inicial

> Modelo independente para o Caldas Gestão. Não representa tabelas ou agregados internos do produto observado. A fundação relacional detalhada está em [database-architecture.md](../../architecture/database-architecture.md).

## Matriz evidência → modelo

| Elemento                                     | Classificação       | Claims/evidência                      | Racional                                                |
| -------------------------------------------- | ------------------- | ------------------------------------- | ------------------------------------------------------- |
| Cliente como raiz CRM                        | Proposed            | EV-002, FLOW-002                      | identidade deve existir antes de históricos dependentes |
| Profissional separado de Usuário             | Proposed            | SCR-005                               | profissional pode existir sem credencial                |
| Serviço com políticas por profissional       | Proposed            | SCR-006                               | personalização pós-criação observada                    |
| Produto com ledger de estoque                | Proposed            | SCR-006                               | controle automático e estoque inicial exigem histórico  |
| Categoria compartilhada                      | Inferred            | listas/filtros de produtos e serviços | mesma taxonomia aparece nos dois módulos                |
| Regras versionadas de comissão/cashback      | Proposed            | SCR-006                               | alterações não devem reescrever histórico               |
| Catálogo versionado de métricas              | Proposed            | EV-003, SCR-007                       | fórmulas precisam ser reproduzíveis e auditáveis        |
| Execução de relatório separada da exportação | Proposed            | SCR-008                               | exportação tem custo e risco de PII distintos           |
| Progresso de meta derivado                   | Proposed            | SCR-008                               | evita divergência entre KPI e meta                      |
| Tenant e unidade como escopo explícito       | Proposed            | DB-001/002; ADR-001                   | autorização, uniques e jobs precisam do mesmo limite    |
| User/Membership separados de Professional    | Inferred → Proposed | DB-003/004; SCR-005                   | identidade global não é cadastro de execução            |
| Entitlement separado de RBAC                 | Proposed            | DB-005; BEH-010/022                   | recurso não contratado não é o mesmo que ação proibida  |
| Outbox/inbox/idempotência na fundação        | Proposed            | DB-007; api-proposal.md               | retry não pode duplicar efeitos                         |

## Agregado Cliente

- raiz: `Customer`;
- objetos de valor: nome, contato normalizado, documento, endereço;
- comandos: criar, atualizar, inativar, bloquear acesso, alterar preferências;
- invariantes propostas: tenant obrigatório; ao menos nome; contatos/documentos únicos conforme política do tenant;
- histórico operacional é referenciado por `customer_id`, mas pertence aos contextos de origem;
- exclusão proposta: anonimização quando legalmente possível, preservando ledgers obrigatórios.

## Agregado Profissional

- raiz: `Professional`;
- vínculos: conta de usuário opcional, unidades, serviços habilitados e agenda;
- comandos sensíveis separados: conceder acesso, alterar permissões, definir dados bancários e regra de comissão;
- inativação não remove atendimentos/comissões históricos.

## Agregado Item de catálogo

Modelar `Service` e `Product` separadamente, compartilhando value objects e referências de taxonomia.

### Serviço

- preço/duração/visibilidade;
- políticas de agendamento, retorno, cuidado, cashback, comissão e fiscal;
- overrides por profissional como entidade dependente versionada.

### Produto

- preço/custo/unidade/códigos;
- política de controle de estoque;
- estoque inicial como movimento, não atributo mutável isolado;
- vínculos de consumo com serviços.

## Invariantes propostas

- dinheiro nunca usa ponto flutuante;
- toda entidade tenant-owned inclui `tenant_id` e, quando aplicável, `unit_id`;
- regras vigentes são versionadas por intervalo de validade;
- movimentos financeiros e de estoque não são apagados, apenas ajustados/revertidos;
- PII não entra em eventos, logs ou fixtures além do mínimo necessário e devidamente protegido;
- autorização é verificada no servidor em cada sub-recurso habilitado na UI.
- ativação de membership ocorre somente por `ActivateMembership` Action transacional, nunca por update direto de status;
- `tenant_id` é obrigatório em todo agregado tenant-owned; `unit_id` deve pertencer ao mesmo tenant;
- uma membership ativa só concede unidades e papéis existentes no mesmo tenant;
- membership revogada pode ser reinvitada na mesma linha, preservando histórico; assignment de papel revogado não volta automaticamente;
- `membership_units.is_primary` é a única fonte de unidade primária; não duplicar `default_unit_id` em membership;
- `membership_roles` persiste scope_kind/assignment_scope e unique parcial só para assignment ativo;
- entitlement vigente usa estados trial/active/grace/suspended/expired/revoked e não pode conceder capacidade fora da Policy;
- vigência de entitlement recebe `as_of` explícito (`starts_at <= as_of` e `ends_at` nulo ou posterior), sem depender de índice que avalie `now()`;
- mudança de acesso gera auditoria append-only e invalida autorização cacheada;
- um comando com a mesma idempotency key e request hash não executa novamente;
- outbox é persistida na transação do agregado e consumidor registra inbox por `(consumer, event_id)`;
- IDs públicos de usuários/agregados são UUIDv7; IDs técnicos não formam contrato de API.

## Validações pendentes

- criação concorrente e deduplicação de cliente;
- mudança de unidade do profissional;
- preço por profissional e vigência;
- conversão de unidade de produto;
- baixa por consumo parcial e estorno;
- anonimização com histórico financeiro e clínico.
- isolamento com dois tenants contendo slugs/unidades/usuários homônimos;
- corrida de dois workers no mesmo evento e duas requisições com a mesma chave;
- concorrência entre `ActivateMembership`, revogação e alteração de unidades/papéis, com lock determinístico;
- comportamento de DST e fuso da unidade em consultas por dia;
- backfill expand-contract e restore do PostgreSQL com schema `app`.

## Contexto analítico proposto

- `MetricDefinition`: fórmula, dimensões, granularidade, timezone e versão;
- `AnalyticFact`: fato imutável ou ajuste referenciado, tenant-scoped;
- `MetricSnapshot`: agregado reproduzível com janela e `calculated_at`;
- `ReportDefinition`: filtros tipados, colunas, totais e permissão;
- `ReportRun`: parâmetros normalizados, versão e estado da execução;
- `ExportJob`: formato, escopo, auditoria, expiração e artefato protegido;
- `Goal`: escopo, métrica versionada, alvo, período e estado;
- `GoalProgressSnapshot`: progresso derivado da mesma camada usada pelo painel.

Invariantes adicionais: toda métrica referencia uma versão de definição; nenhum agregado cruza tenant; reprocessamento é idempotente; ajuste preserva o fato original; exportação nunca amplia o campo autorizado pela consulta interativa.

## Validação transversal e decisões ainda sem ADR

Os cenários mínimos são: acesso cruzado entre tenants, membership revogada durante request/job, entitlement expirado, conflito por `lock_version`, retry pós-commit, falha parcial de consumidor, anonimização com retenção legal e reconciliação de valores. A decisão de RLS, retenção detalhada, particionamento dos ledgers/eventos e geração PostgreSQL de UUIDv7 continua pendente de ADR.

## Agregado Agendamento aprofundado

- `AppointmentSeries`: regra de recorrência e limites;
- `Appointment`: ocorrência, janela temporal, origem e estados operacional/financeiro;
- `AppointmentItem`: serviço, duração, profissional e preço capturado;
- `AvailabilityRule`: jornada recorrente do profissional/unidade;
- `ScheduleBlock`: indisponibilidade explícita;
- `AppointmentStatusTransition`: histórico imutável de transições;
- `AppointmentSaleLink`: vínculo versionado com comanda/venda;
- `CommunicationIntent`: lembrete, confirmação ou conversa como side effect.

Não derivar status operacional de status financeiro. Faturamento, conclusão, comparecimento e cancelamento são conceitos distintos.

## Agregados de venda e fechamento propostos

- `Sale`: cliente, unidade, moeda, estado e totais capturados;
- `SaleItem`: tipo, item de catálogo, profissional, quantidade, preço, desconto e origem;
- `SaleBenefitAllocation`: pacote, assinatura, crédito ou cashback consumido (onda posterior, fora do MVP de fechamento de comandas);
- `ClosingSession`: seleção versionada de comandas para fechamento consolidado;
- `Adjustment`: correção auditada sem apagar o original, em wave posterior;
- `FiscalDocumentIntent`: emissão fiscal desacoplada (onda posterior, fora do MVP de fechamento de comandas);
- `CommissionAccrual`: competência por item/profissional (onda posterior, fora do MVP de fechamento de comandas);
- `SaleAuditEvent`: trilha de alterações e transições.

Venda, fechamento, fiscalidade, comissão e estoque são contextos relacionados por IDs e eventos; não devem compartilhar uma única transação longa ou um único status genérico. Recebimento externo não é processado nem registrado nesta wave.

## Ledger financeiro proposto — evolução posterior

Esta seção é uma proposta de arquitetura futura, fora do MVP de fechamento de comandas. O MVP não processa nem registra pagamento/recebimento, estorno ou conciliação; esses conceitos só devem ser implementados em uma wave financeira própria, com ADR e migrações específicas.

- `FinancialObligation`: pagar/receber, competência, vencimento e titular;
- `Settlement`: liquidação parcial/total por pagamento;
- `FinancialAccount`: caixa, banco ou provedor;
- `AccountMovement`: débito/crédito imutável com bruto, taxa e líquido;
- `ChartOfAccount`: categoria versionada e tenant-scoped;
- `CashSession`: abertura, fechamento, responsáveis e saldos;
- `CashCount`: valor conferido por meio e divergência;
- `Reconciliation`: vínculo entre movimento interno e fonte externa;
- `CommissionAccrual`: competência versionada por item/profissional;
- `FiscalDocument`: intenção, estado, referências e artefatos protegidos.

Saldo é projeção dos movimentos, nunca campo livremente editável. Fechamentos e reconciliações preservam histórico por ajustes compensatórios.

## Categoria, comanda e fechamento consolidado (Proposed — ADR-003)

`SaleCategory` é configuração unit-scoped no MVP, ativa ou inativa, com `tenant_id`/`unit_id`, `uniqueness_scope`, tipos de item e políticas de cliente/agendamento. `Sale` é a raiz da comanda: sempre tenant/unit-scoped, com cliente opcional, ciclo `draft/open/ready_to_bill/finalized/cancelled/adjusted`, moeda, totais capturados, `open_context_key`, snapshots obrigatórios `category_key_snapshot`/`category_name_snapshot` e `lock_version`. `SaleItem` captura nome, preço, moeda, profissional e origem. `SaleStatusHistory` é append-only. `Appointment 0..N Sale`; cada `Sale` tem 0..1 `Appointment` no MVP, garantido exclusivamente por `AppointmentSaleLink` com unique parcial de link ativo por `sale_id`.

`ClosingSession` é o agregado de tentativa consolidada, com seleção, versões, `closing_subject` e estados `draft/ready/processing/completed/cancelled/failed`. Não há PaymentIntent, Payment, PaymentAllocation, refund externo ou gateway nesta wave. Estados ativos (`draft`, `open`, `ready_to_bill`) usam unique parcial por `(tenant_id, unit_id, sale_category_id, open_context_key)` quando a chave não é nula; `none` permite duplicidade. Finalização/cancelamento libera o slot.

O comando de fechamento recebe seleção explícita, valida tenant/unidade/moeda/permissão/versão e o mesmo `closing_subject` (mesmo cliente ou mesma referência), bloqueia em ordem determinística, finaliza as vendas selecionadas e gera recibo interno. Repetição é idempotente e vendas não selecionadas não são alteradas. As invariantes canônicas estão em [ADR-003](../../adr/ADR-003--categorias-de-comanda-e-checkout-consolidado.md).
