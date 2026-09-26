# Observações de rede sanitizadas

## Estado

Nenhum contrato de rede autenticado foi preservado até o momento. Os batches EV-001 a EV-007 analisaram DOM, comportamento visível e rotas de página, sem registrar bodies, headers, cookies, tokens ou payloads pessoais. A inspeção posterior identificou chaves `data-row-key` em linhas de Clientes, Serviços e Comandas, mas isso é evidência DOM, não contrato de API; ver EV-012.

Isso significa:

- rotas de páginas observadas **não** provam rotas de API;
- ações e estados visíveis **não** provam formato de requests;
- `api-proposal.md` é uma proposta original, não reconstrução da API alvo;
- não existem claims `NET-*` confirmados nesta fase.
- `data-row-key` foi observado como identificador renderizado em algumas listagens, sem inferir a origem backend.

## Interações candidatas para captura futura

Somente em tenant de teste com dados sintéticos:

| Prioridade | Interação | Forma a registrar |
|---|---|---|
| P0 | buscar disponibilidade | método, rota normalizada, filtros e slots |
| P0 | criar/reagendar/cancelar agendamento | comandos, versão, idempotência e erro de conflito |
| P0 | salvar/faturar comanda | itens, totais, benefício, estado e concorrência |
| P0 | criar/liquidar/estornar pagamento | intent, método, webhook e reversão |
| P1 | estoque por consumo/estorno | movimento e chave de origem |
| P1 | gerar relatório/exportação | job, progresso, expiração e autorização |
| P1 | comissão/fiscal | processamento assíncrono e falhas recuperáveis |
| P2 | mensagem/campanha | consentimento, template, fila e delivery |

## Protocolo

- capturar apenas requests causados por ação explicitamente escopada;
- substituir IDs por `opaque-id` e valores por tipos/exemplos sintéticos;
- nunca registrar headers de autorização, cookies, signed URLs ou tokens;
- descrever shape e semântica, não armazenar HAR bruto de produção;
- associar cada observação a um `NET-###`, evidência e efeito visível;
- testar sucesso, validação, conflito, autorização, retry e idempotência no tenant descartável.
