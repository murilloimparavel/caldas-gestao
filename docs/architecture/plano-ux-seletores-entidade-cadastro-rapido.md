# Plano de Implementação — Seletores de Entidade e Cadastro Rápido Inline (`+ Novo`)

- **Status:** Proposto
- **Data:** 26/08/2026
- **Alvo:** Eliminar completamente o preenchimento manual de IDs/UUIDs e substituir por seletores amigáveis com busca e botão de cadastro rápido inline (`+ Novo Cliente`, `+ Novo Serviço`, `+ Novo Profissional`) nos formulários da aplicação.

---

## 🎯 Objetivos de UX

1. **Zero IDs Expostos ao Usuário:**
   - Nenhum formulário da aplicação deve exigir que o usuário digite um UUID ou ID numérico (ex: nunca exibir `The customer id field must be a valid UUID.`).

2. **Seletores Amigáveis (`EntitySelect`):**
   - Todos os campos de associação (Cliente, Serviço, Profissional, Produto, Fornecedor) exibirão um dropdown limpo apresentando o **nome amigável** das opções cadastradas.

3. **Atalho de Cadastro Rápido Inline (`+ Novo`):**
   - Junto ao rótulo dos campos, haverá um botão discreto de ação (ex: `+ Novo Cliente`, `+ Novo Serviço`, `+ Novo Profissional`).
   - Clicar no botão abrirá um modal simples com os dados essenciais para o cadastro sem que o usuário perca o contexto do agendamento ou venda em andamento.

4. **Seleção Automática Pós-Cadastro:**
   - Ao salvar o novo registro no modal inline, a lista de opções do formulário é atualizada e a nova entidade criada é **selecionada automaticamente**.

---

## 🏗️ Fases de Execução

### Fase 1: Componente Reutilizável de Cadastro Inline & Seleção
- Criar `EntitySelect` e os modais de cadastro rápido em `resources/js/components/operational/quick-create-dialogs.tsx`:
  - `QuickCreateCustomerModal` (Nome, Telefone, E-mail)
  - `QuickCreateServiceModal` (Nome, Preço, Duração em minutos, Categoria)
  - `QuickCreateProfessionalModal` (Nome, Telefone, Especialidade)

### Fase 2: Reformulação do `AppointmentForm` (`resources/js/pages/calendar/index.tsx`)
- Remover permanentemente a lógica de fallback que exibia `<Input placeholder="ID do cliente" />`.
- Substituir pelos componentes de seleção interativa com botões `+ Novo Cliente`, `+ Novo Serviço` e `+ Novo Profissional`.

### Fase 3: Validação de Outras Telas (Comandas, Vendas, Assinaturas)
- Verificar se outros formulários de venda ou comandas contêm fallbacks de ID e atualizá-los com o novo padrão.

### Fase 4: Testes de Feature e Validação da Suíte
- Executar `vendor/bin/pint --format agent`, `vendor/bin/phpstan analyse --memory-limit=512M`, `npm run lint:check`, `npm run types:check`, `npm run build` e atualizar o grafo do Graphify.
