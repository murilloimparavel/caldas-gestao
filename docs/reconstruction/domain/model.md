# Modelo de domínio inicial

## Matriz evidência → modelo

| Elemento | Classificação | Claims/evidência | Racional |
|---|---|---|---|
| Cliente como raiz CRM | Proposed | EV-002, FLOW-002 | identidade deve existir antes de históricos dependentes |
| Profissional separado de Usuário | Proposed | SCR-005 | profissional pode existir sem credencial |
| Serviço com políticas por profissional | Proposed | SCR-006 | personalização pós-criação observada |
| Produto com ledger de estoque | Proposed | SCR-006 | controle automático e estoque inicial exigem histórico |
| Categoria compartilhada | Inferred | listas/filtros de produtos e serviços | mesma taxonomia aparece nos dois módulos |
| Regras versionadas de comissão/cashback | Proposed | SCR-006 | alterações não devem reescrever histórico |
| Catálogo versionado de métricas | Proposed | EV-003, SCR-007 | fórmulas precisam ser reproduzíveis e auditáveis |
| Execução de relatório separada da exportação | Proposed | SCR-008 | exportação tem custo e risco de PII distintos |
| Progresso de meta derivado | Proposed | SCR-008 | evita divergência entre KPI e meta |

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

## Validações pendentes

- criação concorrente e deduplicação de cliente;
- mudança de unidade do profissional;
- preço por profissional e vigência;
- conversão de unidade de produto;
- baixa por consumo parcial e estorno;
- anonimização com histórico financeiro e clínico.

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

## Agregados de venda e cobrança propostos

- `Sale`: cliente, unidade, moeda, estado e totais capturados;
- `SaleItem`: tipo, item de catálogo, profissional, quantidade, preço, desconto e origem;
- `SaleBenefitAllocation`: pacote, assinatura, crédito ou cashback consumido;
- `PaymentIntent`: valor a cobrar e política de vencimento;
- `Payment`: método, provedor, estado e identificador externo seguro;
- `PaymentAllocation`: distribuição de um pagamento entre saldos/vendas;
- `Refund`/`Adjustment`: correções sem apagar o original;
- `FiscalDocumentIntent`: emissão fiscal desacoplada;
- `CommissionAccrual`: competência por item/profissional;
- `SaleAuditEvent`: trilha de alterações e transições.

Venda, cobrança, fiscalidade, comissão e estoque são contextos relacionados por IDs e eventos; não devem compartilhar uma única transação longa ou um único status genérico.

## Ledger financeiro proposto

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
