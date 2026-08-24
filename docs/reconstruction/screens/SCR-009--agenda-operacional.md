# SCR-009 — Agenda operacional aprofundada

## Vistas

| Vista | Unidade visual | Uso proposto |
|---|---|---|
| Diária | um dia × profissionais × horário | execução do dia e encaixes |
| Semanal | sete dias × profissionais × horário | planejamento e capacidade |
| Mensal | 42 células de data | visão de demanda e navegação |

Todas compartilham anterior/próximo, Visualização, Filtrar, Ações, Configurações e Novo. O título muda conforme a granularidade.

## Cartão e popover de agendamento

O cartão semanal sintetiza horário, cliente, serviço e sinal de origem. O menu contextual expande:

- identidade e contato do cliente;
- ação Conversar;
- início/fim e data textual;
- serviço;
- origem;
- observação;
- situação de faturamento;
- cor;
- vínculo com comanda quando existente;
- exclusão.

## Inteligência de UX extraída

- A grade maximiza densidade, enquanto o popover concentra contexto e próximos passos.
- Faturamento é uma transição relevante: altera o conjunto de ações ao revelar a comanda.
- Origem online é sinalizada no cartão e explicada no popover.
- Intervalos de dez minutos permitem precisão, mas elevam densidade cognitiva e custo de acessibilidade.
- Ações destrutivas ficam próximas de ações de navegação; o produto novo deve separar zonas e confirmação.

## Proposta independente

- cartões com nome acessível completo e botão de ações claramente rotulado;
- popover somente informativo; edições em drawer dedicado com foco gerenciado;
- ação primária contextual por estado: confirmar, iniciar atendimento ou abrir comanda;
- exclusão substituída por cancelamento com motivo e trilha de auditoria;
- lista equivalente para teclado/mobile;
- densidade ajustável de 10/15/30 minutos sem mudar a duração real;
- indicadores textuais para origem, status, faturamento e conflito;
- deep-link seguro para o agendamento sem expor PII na URL;
- todas as transições usam versão otimista para detectar concorrência.

## Estados de UI obrigatórios

- carregando, vazio, indisponível e erro parcial por profissional;
- conflito, sobreposição autorizada e encaixe;
- bloqueio integral/parcial;
- agendamento recorrente e exceção da série;
- confirmado, aguardando confirmação, compareceu, não compareceu, cancelado e concluído;
- não faturado, comanda aberta, faturado e estornado.
