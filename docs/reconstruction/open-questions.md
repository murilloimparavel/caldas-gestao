# Questões abertas

## Ambiente e papéis

- Qual é o papel formal da conta observada?
- Existe tenant de teste descartável?
- Quais itens de menu variam por papel e plano?

## Agenda

- Abrir/cancelar dispara alguma telemetria ou rascunho remoto?
- Quais campos são obrigatórios e quando validam?
- Como funcionam múltiplos serviços/profissionais no mesmo agendamento?
- Como conflito, encaixe, bloqueio e concorrência são resolvidos?
- Recorrência gera série + ocorrências ou cópias independentes?
- Como editar/cancelar uma única ocorrência versus toda a série?
- Quais eventos enviam lembrete e por qual canal?
- O que `Criar comanda` persiste e em que ordem?
- Quais status têm transições e side effects?

## UX e acessibilidade

- Qual é o comportamento responsivo real?
- Existe alternativa em lista para a grade?
- Como o foco se comporta em dialogs/selects/date picker?
- Quais contrastes falham em estados e cartões?

## Próximo experimento seguro

Em conta real: inspecionar visualização diária/mensal, sem selecionar ou alterar filtros. Em tenant de teste: criar um cliente sintético e executar o happy path completo de agendamento com captura de rede sanitizada.

## Analytics, relatórios e metas

- Qual é a fórmula de conversão entre agendamentos e comandas?
- Ocupação usa horas disponíveis, horas configuradas ou slots ofertados?
- Quais status entram no funil e como remarcações são contabilizadas?
- Venda é reconhecida por abertura, fechamento, pagamento ou competência?
- Como estorno, desconto, cashback e pacote afetam ticket e receita?
- O período anterior tem duração idêntica e limites inclusivos?
- Qual timezone governa agrupamento por dia e heatmap?
- Quais relatórios possuem drill-down, totais e exportação?
- Geração de relatório é síncrona, assíncrona ou híbrida?
- Favoritos são por usuário, papel, unidade ou tenant?
- Como metas tratam alteração de alvo, sobreposição e profissional inativo?
- Quais estados e limites aparecem em datasets grandes, falhas e timeouts?

## Cadastros e catálogo

- Qual é a deduplicação de cliente por telefone, e-mail e documento?
- Inativar cliente/profissional bloqueia quais ações e preserva quais históricos?
- Como permissões de profissional são agrupadas e auditadas?
- Como preços, comissões e cashback são versionados?
- Estoque inicial cria movimento ou altera saldo diretamente?
- Como funcionam conversão de unidade, lote e validade?
- Como consumo de produto por serviço é estornado?
- Quais capacidades do catálogo variam por plano?
- Por que painéis não selecionados do cliente parecem permanecer montados no DOM?
