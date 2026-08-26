# Sprint — Padronização Global de Seletores e Cadastros Rápido Inline (`+ Novo`)

## 🎯 Objetivo
Executar o plano definido em [`plano-padronizacao-global-seletores-cadastro-rapido.md`](file:///Users/murilloalves/Projects/caldas-gestao/docs/architecture/plano-padronizacao-global-seletores-cadastro-rapido.md), aplicando o padrão de seletores amigáveis com botão de cadastro rápido inline (`+ Novo`) em **todas as telas operacionais do sistema** (Financeiro, Produtos, Serviços, Comandas e Agenda).

---

## 📦 Entregas Realizadas

### 1. Backend Controllers & API JSON
- **`SupplierController.php`**: Atualizado `store` para responder JSON assíncrono (status HTTP 201).
- **`CategoryController.php`**: Atualizado `store` para responder JSON assíncrono (status HTTP 201).
- **`SaleCategoryController.php`**: Atualizado `store` para responder JSON assíncrono (status HTTP 201).
- **`GlobalQuickCreateTest.php`**: Criada a suíte de testes em Pest validando o cadastro rápido via API de Fornecedores, Categorias de Produtos e Categorias de Comanda (3 testes passados).

### 2. Expansão dos Diálogos Globais (`resources/js/components/operational/quick-create-dialogs.tsx`)
- Adicionados os modais sobrepostos:
  - `QuickCreateSupplierModal` (Empresa/Nome, CNPJ/CPF, Telefone, E-mail).
  - `QuickCreateCategoryModal` (Nome da Categoria).

### 3. Injeção de Cadastro Rápido nas Páginas do Sistema
- **Lançamentos Financeiros (`/finance/transactions`):** Adicionados atalhos `+ Novo Fornecedor`, `+ Novo Cliente` e `+ Nova Categoria` no formulário de contas a pagar e receber.
- **Produtos (`/products`):** Adicionado atalho `+ Nova Categoria` no formulário de cadastro de produto.
- **Serviços (`/services`):** Adicionado atalho `+ Novo Profissional` no formulário de cadastro/edição de serviços.
- **Detalhamento de Comanda (`/sales/show`):** Adicionados atalhos `+ Novo Produto` e `+ Novo Serviço` nos modais de inclusão de itens na comanda.
- **Bloqueio de Agenda (`/calendar`):** Adicionado atalho `+ Novo Profissional` no modal de bloqueio de horário.

---

## 🧪 Suíte de Validação
- **Pest:** 316 testes executados (**291 aprovados**, 25 skipped, **0 falhas**) com 2.243 asserções.
- **PHPStan:** **0 erros**.
- **ESLint & TypeScript:** **0 erros**.
- **Vite Build:** Compilado em 6.67s com sucesso.
- **Graphify:** Grafo atualizado com 4.951 nós e 11.852 arestas.
