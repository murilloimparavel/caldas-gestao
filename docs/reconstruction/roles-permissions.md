# Papéis e permissões — proposta independente

O papel real observado é Unknown. A UI ampla sugere acesso administrativo, mas isso não prova um papel formal.

## Papéis iniciais propostos

| Papel | Escopo típico |
|---|---|
| Proprietário | tenant, assinatura e segurança crítica |
| Administrador | unidades, usuários, configurações e operação |
| Gestor | relatórios, agenda, vendas, estoque e equipe |
| Recepção | clientes, agenda, comandas e cobrança limitada |
| Profissional | própria agenda, atendimentos e comissão própria |
| Financeiro | contas, caixa, conciliação e fiscal |
| Marketing | campanhas, avaliações e segmentos autorizados |
| Auditor | leitura histórica e exportações aprovadas |

## Modelo

- RBAC concede capacidades; ABAC restringe por tenant, unidade, propriedade, horário e estado;
- entitlement de plano é verificado separadamente;
- permissões seguem `recurso.ação` e escopo, por exemplo `sale.refund:unit`;
- ações sensíveis exigem step-up, motivo e auditoria;
- exportação, token, pagamento, estorno, fechamento e fiscal têm permissões próprias;
- mudança de papel invalida sessões/cache de autorização rapidamente;
- UI pode esconder ação, mas servidor sempre revalida.

## Matriz mínima a validar

- visualizar/editar PII;
- criar/reagendar/cancelar agenda;
- editar/faturar/estornar venda;
- liquidar/reconciliar/fechar caixa;
- pagar comissão/emitir nota;
- enviar mensagem/campanha;
- exportar relatório;
- gerenciar usuário, token, integração e assinatura.
