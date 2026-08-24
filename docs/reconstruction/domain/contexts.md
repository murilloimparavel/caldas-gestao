# Contextos delimitados propostos

> Este é um modelo original para o novo produto, não descrição do banco interno observado.

## CRM

- dono: Cliente;
- responsabilidades: identidade, contatos, endereços, tags, relacionamentos e preferências;
- não possui: vendas, débitos, mensagens ou anamnese como colunas internas.

## Workforce e acesso

- donos: Profissional, Usuário, Papel e Permissão;
- políticas: capacidade de agenda, exposição online, comissão, estoque e vínculo de parceria;
- identidade autenticável é separada do cadastro profissional.

## Catálogo

- donos: Serviço, Produto, Categoria, Marca e Modelo de Pacote;
- políticas: preço vigente, duração, visibilidade, favoritos, fiscalidade e personalização por profissional.

## Estoque

- donos: ItemEstoque, Movimento, Lote, Validade e Solicitação;
- recebe eventos de compra, venda, consumo de serviço e ajuste;
- saldo é projeção derivada dos movimentos.

## Relacionamento e prontuário

- donos separados: Mensagem, Anotação, Arquivo, Anamnese e Consentimento;
- autorização e retenção reforçadas por LGPD.

## Financeiro/comercial

- donos: Venda/Comanda, Débito, Crédito, Cashback e Comissão;
- cliente e itens do catálogo são referências, não agregados internos.
