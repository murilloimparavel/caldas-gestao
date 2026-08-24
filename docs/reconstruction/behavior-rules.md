# Regras comportamentais

| ID | Classificação | Precondições | Regra/resultado | Evidência | Confiança | Negativo testado? |
|---|---|---|---|---|---|---|
| RULE-001 | Observed | agenda aberta | Novo abre diálogo sem mudar rota | EV-001 | alta | não |
| RULE-002 | Observed | diálogo aberto e não alterado | Cancelar fecha sem mudança visível | EV-001 | alta | não |
| RULE-003 | Observed | novo agendamento no contexto testado | lembrete ligado; encaixe desligado | EV-001 | alta contextual | não |
| RULE-004 | Inferred | configurações globais definidas | defaults do formulário refletem configuração da agenda | EV-001 | média | não |
| RULE-005 | Unknown | cancelamento do diálogo | nenhum rascunho remoto é criado | — | baixa | não |
| RULE-006 | Observed | filtro aberto | agenda pode ser filtrada por profissional e seis status visíveis | EV-001 | alta | não |
| RULE-007 | Observed | menu Visualização aberto | diário, semanal e mensal são opções | EV-001 | alta | não |
| RULE-008 | Observed | menu Ações aberto | bloquear horários e agrupar agendamentos são ações disponíveis | EV-001 | alta | não |
| RULE-009 | Observed | entidade ainda não persistida | abas dependentes ficam desabilitadas | EV-002 | alta | não |
| RULE-010 | Observed | serviço existente | abas avançadas ficam habilitadas | EV-002 | alta | não |
| RULE-011 | Observed | cliente existente | detalhe abre em Painel com áreas históricas habilitadas | EV-002 | alta | não |
| RULE-012 | Observed | produto com controle de estoque ativo | saldo é ajustado por uso em comandas/pacotes e compras, segundo texto da UI | EV-002 | alta para o texto | não |
| RULE-013 | Observed | capacidade não contratada | Novo pacote predefinido abre oferta em vez de formulário | EV-002 | alta | não |
| RULE-014 | Observed | painel com período carregado | trocar Agendamentos por Comandas substitui o bloco primário e preserva os demais | EV-003 | alta | não |
| RULE-015 | Observed | catálogo de relatórios aberto | selecionar uma família muda rota e lista de relatórios | EV-003 | alta | não |
| RULE-016 | Observed | relatório detalhado aberto | filtros precedem Gerar relatório; exportação fica em ação separada | EV-003 | alta | não |
| RULE-017 | Observed | menu de exportação aberto | Excel aparece como opção sem download automático | EV-003 | alta | não |
| RULE-018 | Observed | conta sem entitlement de metas | entrar em Metas abre oferta; fechar retorna à superfície anterior | EV-003 | alta contextual | não |
| RULE-019 | Proposed | métrica ou relatório consultado | dashboard, detalhe e exportação usam a mesma versão de definição e os mesmos filtros normalizados | SCR-007/008 | — | a testar |
| RULE-020 | Observed | menu Visualização aberto | Diário, Semanal e Mensal alteram a grade sem mudar `/calendar` | EV-004 | alta | não |
| RULE-021 | Observed | agendamento existente | menu abre popover informativo sem navegar | EV-004 | alta | não |
| RULE-022 | Observed | agendamento faturado | popover apresenta vínculo para a comanda | EV-004 | alta contextual | não |
| RULE-023 | Inferred | agendamento sem faturamento | vínculo de comanda ausente indica que ela não existe ou ainda não está disponível | EV-004 | média | não |
| RULE-024 | Observed | busca padrão executada | listagem retorna comandas não excluídas sem exigir texto | EV-005 | alta | não |
| RULE-025 | Observed | comanda existente finalizada | abre em modo leitura; edição exige comando explícito | EV-005 | alta | não |
| RULE-026 | Observed | menu Outros aberto | oferece imprimir, impressão térmica e histórico | EV-005 | alta | não |
| RULE-027 | Observed | nova comanda vazia | Salvar disponível e Faturar desabilitado | EV-005 | alta contextual | não |
| RULE-028 | Observed | transações abertas | filtros padrão incluem pagar/receber, contas, estados, meios e categorias ativos | EV-006 | alta contextual | não |
| RULE-029 | Observed | tipo de data alterável | UI distingue vencimento/disponibilidade, competência e pagamento | EV-006 | alta | não |
| RULE-030 | Observed | lançamento ligado a comanda | origem preserva referência ao ticket | EV-006 | alta | não |
| RULE-031 | Observed | capacidade fiscal/comissão não contratada | abrir superfície apresenta diálogo de contratação | EV-006 | alta contextual | não |
