# SCR-004 — Clientes: lista, novo e existente

## Contexto

- **Rota:** `/clients`
- **Evidência:** EV-002
- **Papel:** desconhecido
- **Estados:** lista populada, novo cliente e cliente existente

## Lista observada

- colunas: Nome, E-mail, Celular, Nascimento, Créditos e Observações;
- paginação, tamanho de página e salto para página;
- busca e filtros;
- ações por linha de edição e exclusão;
- seleção em lote.

## Filtros observados

- ativos/inativos;
- hashtags;
- com/sem celular;
- com/sem débito;
- intervalo de aniversário;
- última avaliação por estrelas.

## Novo cliente

Somente Cadastro fica habilitado. Campos/grupos observados:

- identidade: nome obrigatório, apelido, aniversário;
- contato: celular, telefone, e-mail;
- documentos: CNPJ, CPF, RG;
- relacionamento: indicado por, hashtags, observações, dependentes após criação;
- endereço: CEP, logradouro, número, complemento, bairro, estado e cidade;
- redes: Instagram e Facebook;
- política comercial: desconto padrão e aplicação na comanda;
- estado e comunicação: ativo, notificações e bloqueio de acesso.

Abas desabilitadas até existir cliente: Painel, Débitos, Créditos, Cashback, Agendamentos, Produtos, Vendas, Pacotes, Mensagens, Anotações, Imagens e Arquivos, Anamneses e Vendas por Assinatura.

## Cliente existente

- abre inicialmente em Painel, não em Cadastro;
- todas as abas acima ficam habilitadas;
- Cadastro passa a permitir dependentes;
- Agendamentos e Vendas expõem filtros de período;
- Mensagens expõe canal/destino e composição;
- Anotações expõe composição de anotação;
- arquivos/anamneses/assinaturas ficam navegáveis.

## Implicações

- cliente é um agregado central com projeção 360°;
- históricos financeiros e operacionais não devem ser gravados dentro da linha de cliente;
- mensagens, arquivos e anamnese possuem risco LGPD elevado;
- exclusão deve provavelmente ser anonimização/inativação, não remoção física de histórico.

## Critérios de aceitação propostos

- Criar cliente exige apenas o mínimo definido pelo negócio; CPF/CNPJ não devem ser obrigatórios por padrão.
- Duplicidade deve considerar telefone/e-mail/documento normalizados dentro do tenant.
- Histórico só fica acessível após identidade persistida e autorização server-side.
- Bloquear acesso e inativar são comandos distintos e auditáveis.
- Preferência de notificações deve registrar finalidade, canal, base legal e data.
- Toda visualização de anamnese, arquivo ou mensagem deve respeitar escopo e trilha de acesso.

## Desconhecidos

- validações, deduplicação e normalização;
- diferença entre notificações e consentimento de marketing;
- política real de exclusão;
- permissões por aba;
- ações e tabelas exatas de cada painel histórico.
