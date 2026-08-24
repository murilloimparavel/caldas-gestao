# EV-002 — Cadastros e catálogo desktop

- **Data:** 24/08/2026
- **Ambiente:** aplicação autenticada com aparência de conta operacional
- **Papel:** desconhecido
- **Viewport:** desktop, mesma sessão do EV-001
- **Mutação:** nenhuma
- **Redação:** nomes, contatos, documentos, identificadores e valores de clientes/profissionais não foram preservados

## Rotas observadas

- Clientes: `/clients`
- Profissionais: `/employees`
- Fornecedores: `/vendors`
- Serviços: `/services`
- Produtos: `/products`
- Categorias: `/groups`
- Pacotes predefinidos: `/package-templates`

## Interações

- listas e filtros de Clientes, Profissionais, Fornecedores, Serviços, Produtos, Categorias e Pacotes predefinidos;
- abertura dos formulários Novo cliente, Novo profissional, Novo fornecedor, Novo serviço, Novo produto e Nova categoria;
- abertura de seções e abas sem preencher campos;
- abertura de um serviço existente e de um cliente existente;
- abertura do paywall de Pacotes predefinidos sem acionar Contratar.

## Resultado de segurança

- nenhum input foi preenchido;
- nenhum switch foi alterado;
- nenhum Salvar, Enviar, Contratar ou upload foi acionado;
- nenhum item foi criado, editado ou excluído;
- batch encerrado em `/clients`, sem diálogo visível.

## Limitação de evidência

No drawer de cliente existente, painéis de abas parecem permanecer montados no DOM. Por isso, cabeçalhos agregados de tabelas não foram atribuídos a uma aba específica sem evidência visual adicional. A habilitação das abas e os controles visíveis após cada clique são evidência válida; a associação individual de tabelas permanece parcial.
