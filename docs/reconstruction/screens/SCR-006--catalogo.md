# SCR-006 — Catálogo: serviços, produtos, categorias e pacotes

## Serviços

### Lista

- rota `/services`;
- colunas: Nome, Valor, Comissão, Duração, Categoria e visibilidade no site;
- filtros por status, favorito e categoria;
- ações favoritar, editar e excluir.

### Novo

Abas disponíveis: Cadastro, Configurações e Cashback.

- nome/categoria;
- política de preço, custo adicional e comissão;
- duração e descrição pública;
- antecedência para agendamento;
- ativo, favorito, visível e permite agendamento online;
- cashback com override do padrão geral.

Abas pós-criação: Cuidados, Retorno, Comissões e Auxiliares, Personalizar, Produtos consumidos e Configurar nota fiscal.

### Serviço existente

Todas as abas pós-criação ficaram habilitadas. Capacidades observadas:

- mensagens pré/pós-agendamento;
- retorno em dias e mensagem padrão/personalizada;
- comissão por profissional/auxiliar e base de cálculo;
- preço, duração, custo e disponibilidade online por profissional;
- consumo de produto e unidade extra;
- item fiscal, CNAE e código de serviço municipal.

## Produtos

### Lista

- rota `/products`;
- modos Produtos, Lotes e validades, Solicitações;
- colunas: Nome, Marca, Categoria, Estoque, Preço de venda e Comissão;
- filtros por status, favorito, categoria e marca;
- estoque ajustável por item.

### Novo

- nome, categoria, marca, preço de venda e custo de compra;
- unidade/registro de saída, equivalência, estoque mínimo e inicial;
- disponibilidade para solicitação;
- preço para profissional, custo adicional e comissão;
- código interno, código de barras e observações;
- configuração de ativo, favorito e controle automático de estoque;
- cashback com override.

Abas pós-criação observadas como dependentes: Retorno, Serviços vinculados e Configuração fiscal.

## Categorias

- rota `/groups`;
- lista Nome/Itens;
- estado vazio/populado observado em momentos distintos;
- novo contém Nome e Ativo.

## Pacotes predefinidos

- rota observada `/package-templates`;
- lista Nome/Total;
- Novo abriu paywall de funcionalidade não contratada;
- `Contratar` não foi acionado.

## Critérios de aceitação propostos

- Serviços e produtos compartilham uma taxonomia, mas possuem invariantes distintos.
- Valores monetários usam centavos inteiros ou decimal exato + moeda.
- Duração é valor temporal, não string formatada.
- Comissão e cashback devem referenciar regras versionadas, não sobrescrever histórico.
- Estoque inicial cria movimento de abertura auditável; nunca apenas altera saldo.
- Consumo por serviço gera movimentos idempotentes vinculados ao atendimento.
- Códigos fiscais e regras tributárias devem ser versionados por jurisdição/unidade.
- Capacidades de plano devem ser resolvidas no servidor e comunicadas antes do CTA de criação.

## Desconhecidos

- preço variável e faixas;
- unidades de medida disponíveis;
- lote/validade e rastreabilidade;
- solicitações internas de produto;
- exclusão com itens associados;
- conteúdo e regras de pacote predefinido devido ao paywall.
