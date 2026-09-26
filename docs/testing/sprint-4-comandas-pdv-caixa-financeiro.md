# Relatório de Teste de Estresse - Sprint 4: Comandas, PDV, Estoque & Caixa Operacional

- **Data de Execução**: 2026-09-12
- **Ambiente**: Produção (`https://gestao.caldasindica.com`)
- **Tenant**: `teste` (`01a096e3-786f-7013-aa93-6fde4d87bf68`)
- **Usuário**: `teste@caldasindica.com`
- **Status da Sprint**: Aprovado com 100% de sucesso

## 1. Módulos e Fluxos Operacionais Validados
1. **Categorias de Comanda (`/sale-categories`)**:
   - Criação da categoria de operação: `[TESTE] Barbearia & Loja`.
   - Listagem e ordenação instantânea na tabela sem travamentos.
2. **Ciclo Completo de Comanda / PDV (`/sales`)**:
   - Abertura de comanda: `Cadeira 01 — [TESTE] Maria Silva` (ID: `01a0970b-b464-7173-a8a6-0d4202bb8336`).
   - Lançamento de Serviço: 1x `[TESTE] Corte Masculino Premium` (R$ 65,00) associado ao profissional `[TESTE] Carlos Barbeiro`.
   - Lançamento de Produto: 1x `[TESTE] Pomada Modeladora Efeito Matte 100g` (R$ 45,00).
   - Subtotal calculado automaticamente: R$ 110,00.
   - Transição de estado de comanda: `open` ➔ `ready_to_bill` (Pronta p/ Fechar).
   - Fechamento com liquidação em dinheiro (R$ 110,00).
   - Emissão de recibo interno de auditoria: `REC-20260912-TMYQPI`.
3. **Baixa e Rastreabilidade de Estoque (`/inventory`)**:
   - Saldo em estoque do item `[TESTE] Pomada Modeladora Efeito Matte 100g` debitado em tempo real de 25 para 24 unidades.
   - Registro imutável de movimentação gerado na trilha de auditoria do produto.
4. **Gestão de Turno de Caixa Operacional (`/finance/cash`)**:
   - Abertura de turno de caixa com fundo de troco inicial de R$ 100,00.
   - Registro de Suprimento (+): +R$ 50,00 com motivo `[TESTE] Troco adicional em cédulas`.
   - Registro de Sangria (-): -R$ 20,00 com motivo `[TESTE] Retirada para cofre`.
   - Saldo computado em gaveta: R$ 130,00.
   - Fechamento com contagem física de dinheiro: informado R$ 130,00, diferença R$ 0,00 (`Caixa Exato (Sem divergência)`).
   - Justificativa registrada: `[TESTE] Fechamento regular de turno sem divergencias`.
5. **Histórico e Detalhes de Turnos (`/finance/cash/history` & `/finance/cash/{id}`)**:
   - Turno arquivado com status `Encerrado`.
   - Visualização de detalhes em `/finance/cash/01a09707-3e46-70be-aeed-33ebf71ab449` com tabela completa de lançamentos e opção de impressão de resumo.

## 2. Auditoria Técnica
- Erros de JavaScript no console: **0**
- Quebras visuais ou overflow: **0**
- Integridade do saldo financeiro e estoque: **100%**
- Latência de transição de status de comanda: < 350ms
