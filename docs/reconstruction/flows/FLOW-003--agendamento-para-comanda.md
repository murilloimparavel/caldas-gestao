# FLOW-003 — Agendamento para comanda

## Evidência observada

Na agenda semanal, dois agendamentos exibiam situação Faturado e ação Ver Comanda; dois não exibiam vínculo com comanda. Nenhuma ação foi executada.

## Fluxo inferido

```text
Agendamento online/interno
        ↓
Agendamento operacional
        ↓ atendimento/faturamento
Comanda vinculada
        ↓ fechamento financeiro
Agendamento marcado como faturado
```

A ordem exata entre criar comanda, fechar comanda e marcar como faturado é **Unknown**.

## Máquina de estados proposta

```text
draft → scheduled → confirmed → checked_in → in_service → completed
                     ↘ no_show
scheduled/confirmed → cancelled
completed → sale_open → billed → adjusted/refunded
```

Status operacional e status financeiro devem ser eixos separados. Um agendamento pode estar concluído com comanda ainda aberta; um ajuste financeiro não deve reabrir automaticamente o atendimento.

## Invariantes propostas

- um agendamento referencia tenant, unidade, cliente, profissional e pelo menos um item de serviço;
- início é anterior ao fim e a duração é derivável;
- conflitos são avaliados contra disponibilidade, bloqueios e política de encaixe;
- transições exigem versão esperada/idempotency key;
- cancelar preserva histórico e motivo;
- vínculo com comanda é explícito e auditável;
- comunicação e lembretes são side effects assíncronos, não parte da transação principal;
- recorrência usa série mais ocorrências/exceções, evitando cópias sem vínculo.

## Decisão proposta — múltiplas comandas (Proposed, ADR-003)

O vínculo proposto não é `Appointment 1:1 Sale`: no MVP, `Appointment 0..N Sale` e `Sale 0..1 Appointment`. Um agendamento pode originar várias comandas, por exemplo serviço de barbearia e venda de loja. `appointment_sale_links` registra cada vínculo permitido. A categoria define se o contexto de unicidade é `appointment`; nesse caso há no máximo uma comanda ativa daquela categoria/contexto. Categorias de loja ou venda avulsa podem usar `customer`, `reference` ou `none` e não dependem de agendamento.

Ao abrir a partir do agendamento, a UI oferece a categoria e reabre a comanda ativa encontrada. O servidor calcula `open_context_key`, aplica Policy, unique parcial e idempotência. Finalizar/cancelar libera o slot sem alterar automaticamente o status operacional do agendamento. O detalhe lista todas as comandas vinculadas.

## Próximo experimento

No Batch 5, abrir a comanda já vinculada pelo caminho da listagem de Comandas, não pelo popover, e comparar o vínculo reverso com o agendamento sem alterar dados.

Implementação futura: testar duas categorias para o mesmo agendamento, corrida de duas aberturas da mesma categoria e checkout selecionando apenas parte das comandas.

As invariantes normativas desta proposta são as [invariantes canônicas do ADR-003](../../adr/ADR-003--categorias-de-comanda-e-checkout-consolidado.md).
