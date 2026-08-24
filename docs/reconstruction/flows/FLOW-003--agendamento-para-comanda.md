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

## Próximo experimento

No Batch 5, abrir a comanda já vinculada pelo caminho da listagem de Comandas, não pelo popover, e comparar o vínculo reverso com o agendamento sem alterar dados.
