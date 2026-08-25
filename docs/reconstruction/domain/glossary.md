# Glossário de domínio

| Termo                        | Classificação       | Definição de trabalho                                                                              | Evidência                    |
| ---------------------------- | ------------------- | -------------------------------------------------------------------------------------------------- | ---------------------------- |
| Cliente                      | Observed            | pessoa/organização atendida, com identidade, contato, preferências e visão 360°                    | EV-002                       |
| Profissional                 | Observed            | pessoa que pode executar serviços e participar de agenda/comissão; pode ou não ter login           | EV-002                       |
| Usuário                      | Inferred            | identidade autenticável ligada opcionalmente a um profissional                                     | SCR-005                      |
| Fornecedor                   | Observed            | parte fornecedora usada em compras e movimentos financeiros                                        | EV-002                       |
| Serviço                      | Observed            | oferta executada com duração, preço, visibilidade, agenda e potenciais consumos/comissões          | EV-002                       |
| Produto                      | Observed            | item vendável/consumível com unidade, estoque, preço, custo e códigos                              | EV-002                       |
| Categoria                    | Observed            | agrupamento compartilhado por itens do catálogo                                                    | EV-002                       |
| Marca                        | Observed            | classificação comercial de produto                                                                 | EV-002                       |
| Pacote predefinido           | Observed parcial    | modelo reutilizável de pacote, condicionado ao plano observado                                     | EV-002                       |
| Regra de comissão            | Proposed            | política versionada que calcula comissão por contexto e data de vigência                           | SCR-006                      |
| Movimento de estoque         | Proposed            | registro imutável de entrada, saída ou ajuste que compõe saldo                                     | SCR-006                      |
| Consentimento de comunicação | Proposed            | autorização por finalidade/canal, distinta do estado ativo do cliente                              | SCR-004                      |
| Tenant                       | Proposed            | organização raiz de isolamento lógico, cobrança e configuração do SaaS                             | ADR-001; DB-001              |
| Unidade                      | Inferred → Proposed | local/contexto operacional pertencente a um tenant                                                 | EV-002, EV-007; DB-002       |
| Membership                   | Proposed            | linha reutilizável de vínculo de um usuário com um tenant; revogada pode receber reinvite auditado | roles-permissions.md; DB-004 |
| Papel                        | Proposed            | conjunto tenant-scoped de permissões atribuído a uma membership                                    | roles-permissions.md; DB-005 |
| Permissão                    | Proposed            | capacidade estável no formato recurso.ação, validada no servidor                                   | roles-permissions.md; DB-005 |
| Entitlement                  | Inferred → Proposed | capacidade/limite contratado, em trial ou revogado, separado de RBAC                               | BEH-010, BEH-022; DB-010     |
| Evento de auditoria          | Proposed            | registro append-only de ação, ator, escopo e correlação                                            | data-governance.md; DB-008   |
| Idempotency key              | Proposed            | chave de deduplicação de um comando repetível no escopo do chamador                                | api-proposal.md; DB-007      |
| Outbox/Inbox                 | Proposed            | registros transacionais de publicação e processamento idempotente de eventos                       | events.md; DB-007            |
| UUIDv7                       | Proposed            | identificador público ordenável por tempo para usuários e agregados                                | api-proposal.md; DB-006      |
| Unidade mínima monetária     | Proposed            | inteiro que representa centavos ou menor unidade, acompanhado de moeda ISO                         | api-proposal.md; DB-008      |
| Categoria de comanda         | Proposed            | configuração tenant-owned que define tipos permitidos, políticas e escopo de unicidade de comandas | ADR-003; PRD de comandas     |
| Comanda/Venda                | Observed → Proposed | agregado transacional de itens e totais; pode ser avulso ou vinculado a agendamento                  | EV-005; ADR-003              |
| `open_context_key`           | Proposed            | chave derivada pelo backend para unique parcial de comandas abertas                               | ADR-003                       |
| Checkout consolidado         | Proposed            | fechamento selecionável de várias comandas da mesma unidade/moeda, com alocações preservadas       | PRD de comandas               |
| Alocação de pagamento        | Proposed            | parcela de um pagamento aplicada a uma comanda sem fundir seus históricos                           | PRD de comandas               |
