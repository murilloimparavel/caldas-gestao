# EV-004 — Agenda operacional desktop

- **Data:** 24/08/2026
- **Rota:** `/calendar`
- **Papel:** desconhecido
- **Viewport:** desktop 1710×929
- **Mutação:** nenhuma
- **Redação:** nomes, telefones, IDs e observações reais não foram preservados

## Interações

- alternância entre visualizações diária, semanal e mensal;
- retorno à visão semanal;
- abertura individual do popover de quatro agendamentos existentes;
- leitura estrutural de origem, período, serviço, observação, faturamento, cor, conversa, vínculo com comanda e exclusão;
- nenhum item ou ação do popover foi acionado.

## Resultados

- Diário usa título Hoje e concentração em um único dia/profissional.
- Semanal usa sete dias, agrupamento por profissional e intervalos de dez minutos.
- Mensal usa matriz de seis semanas por sete dias, incluindo dias adjacentes.
- Agendamentos faturados exibem vínculo para uma comanda existente.
- Agendamentos ainda sem faturamento não exibem o vínculo.
- Nos quatro exemplos observados, a origem era agendamento online e havia Conversar, Observação, Cor e Excluir.
- O popover exibe informações e ações como elementos visuais, mas várias não possuem papel semântico de link/botão.

## Segurança

- nenhuma alteração de visualização persistente foi assumida;
- nenhum formulário foi preenchido;
- nenhuma conversa, comanda ou serviço foi aberto;
- nenhuma exclusão, edição, confirmação, reagendamento ou envio ocorreu.

## Limitações

- o conjunto amostral possui somente quatro agendamentos visíveis na semana atual;
- não foram induzidos conflitos, recorrência, encaixe, bloqueio ou mudança de status;
- a diferença entre clicar no cartão e usar seu menu permanece parcialmente desconhecida;
- origem online foi observada, mas outras origens não estavam presentes na amostra.
