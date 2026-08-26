# Sprint — Seletores de Entidade e Cadastro Rápido Inline (`+ Novo`)

## 🎯 Objetivo
Executar o plano definido em [`plano-ux-seletores-entidade-cadastro-rapido.md`](file:///Users/murilloalves/Projects/caldas-gestao/docs/architecture/plano-ux-seletores-entidade-cadastro-rapido.md), eliminando permanentemente a digitação manual de IDs/UUIDs e introduzindo seletores amigáveis com modais de cadastro rápido inline (`+ Novo Cliente`, `+ Novo Serviço`, `+ Novo Profissional`).

---

## 📦 Entregas Realizadas

### 1. Backend (`CustomerController`, `ServiceController`, `ProfessionalController`)
- Atualizados os métodos `store` das entidades para aceitar requisições assíncronas JSON (`$request->wantsJson()`) e retornar a resposta formatada em JSON com `{ id, name }` (HTTP 201 Created).
- Criado o arquivo de testes em Pest [`QuickCreateTest.php`](file:///Users/murilloalves/Projects/caldas-gestao/tests/Feature/QuickCreateTest.php) validando o cadastro rápido via API JSON.

### 2. Frontend React (`resources/js/components/operational/quick-create-dialogs.tsx`)
- Criado o componente de diálogos inline sobrepostos:
  - `QuickCreateCustomerModal` (Nome, E-mail, Telefone/WhatsApp).
  - `QuickCreateServiceModal` (Nome, Preço em R$, Duração em minutos).
  - `QuickCreateProfessionalModal` (Nome, Telefone/Contato).
- **Agenda / Agendamentos (`resources/js/pages/calendar/index.tsx`):**
  - Removidos permanentemente todos os fallbacks de `<Input placeholder="ID do..." />`.
  - Adicionados botões de atalho `+ Novo Cliente`, `+ Novo Serviço` e `+ Novo Profissional` ao lado do rótulo dos campos.
  - Seleção automática da nova entidade recém-cadastrada no formulário de agendamento.
- **Comandas e Vendas (`resources/js/pages/sales/index.tsx`):**
  - Integrado o cadastro rápido de cliente (`+ Novo Cliente`) no modal de abertura de comanda.

---

## 🧪 Suíte de Validação
- **Pest:** 313 testes executados (**288 aprovados**, 25 skipped, **0 falhas**) com 2.225 asserções.
- **PHPStan:** **0 erros**.
- **ESLint & TypeScript:** **0 erros**.
- **Vite Build:** Compilado em 5.88s com sucesso.
- **Graphify:** Grafo atualizado com 4.931 nós e 11.804 arestas.
