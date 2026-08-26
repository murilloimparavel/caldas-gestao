# Plano de Implementação — Padronização Global de Seletores e Cadastro Rápido Inline (`+ Novo`)

- **Status:** Proposto
- **Data:** 26/08/2026
- **Alvo:** Auditar e aplicar a UX de seleção amigável com botão de cadastro rápido inline (`+ Novo`) em todas as telas da aplicação (Financeiro, Produtos, Serviços, Comandas e Agenda).

---

## 🎯 Objetivos de UX

1. **Padronização Global de Seletores:**
   - Todas as telas de cadastro e lançamento da aplicação (Financeiro, Vendas, Produtos, Serviços, Agenda) devem possuir botões discretos de ação rápida (`+ Novo`) ao lado do rótulo dos campos de associação.

2. **Novos Modais Inline Suportados:**
   - `+ Novo Fornecedor` (Empresa/Nome, Telefone, E-mail, Documento)
   - `+ Nova Categoria` (Nome da Categoria, Tipo)
   - `+ Novo Cliente` (Nome, Telefone, E-mail)
   - `+ Novo Serviço` (Nome, Preço, Duração)
   - `+ Novo Profissional` (Nome, Telefone)
   - `+ Novo Produto` (Nome, Preço de Venda)

3. **Experiência de Uso sem Quebra de Fluxo:**
   - O usuário nunca precisa sair da tela onde está executando um agendamento, venda ou lançamento financeiro para cadastrar uma entidade faltante. Ao cadastrar, a opção é inserida e selecionada automaticamente.

---

## 🏗️ Fases de Execução

### Fase 1: Expansão do Backend para JSON Quick Create
- Atualizar `SupplierController.php`, `CategoryController.php` e `SaleCategoryController.php` para responder requisições JSON assíncronas com HTTP 201 `{ id, name }`.

### Fase 2: Expansão dos Diálogos Globais (`resources/js/components/operational/quick-create-dialogs.tsx`)
- Adicionar os componentes `QuickCreateSupplierModal` e `QuickCreateCategoryModal`.

### Fase 3: Integração nas Páginas
- **Lançamentos Financeiros (`resources/js/pages/finance/transactions/index.tsx`):** Integrar `+ Novo Fornecedor`, `+ Novo Cliente`, `+ Nova Categoria`.
- **Produtos (`resources/js/pages/products/index.tsx`):** Integrar `+ Nova Categoria`.
- **Serviços (`resources/js/pages/services/index.tsx`):** Integrar `+ Novo Profissional`.
- **Detalhamento de Comanda (`resources/js/pages/sales/show.tsx`):** Integrar `+ Novo Produto` e `+ Novo Serviço`.
- **Bloqueio de Agenda (`resources/js/pages/calendar/index.tsx`):** Integrar `+ Novo Profissional`.

### Fase 4: Testes de Integração e Suíte
- Executar `vendor/bin/pint --format agent`, `vendor/bin/phpstan analyse --memory-limit=512M`, `npm run lint:check`, `npm run types:check`, `npm run build` e atualizar o grafo do Graphify.
