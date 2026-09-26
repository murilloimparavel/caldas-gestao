# Relatório de Teste de Estresse - Sprint 2: Cadastros Fundamentais & Diálogos de Ação Rápida (CRUDs)

- **Data de Execução**: 2026-09-12
- **Ambiente**: Produção (`https://gestao.caldasindica.com`)
- **Tenant**: `teste`
- **Usuário**: `teste@caldasindica.com`
- **Status da Sprint**: Aprovado com 100% de sucesso

## 1. Entidades Criadas e Validadas
1. **Clientes (`/customers`)**:
   - Criado cliente: `[TESTE] Maria Silva`
   - E-mail: `maria.teste@caldasindica.com`, Telefone: `11999998888`
   - Redirecionamento instantâneo para `/customers/{id}` com abas de Histórico & Consumo, Dados Cadastrais e Assinaturas.
2. **Profissionais (`/professionals`)**:
   - Criado profissional: `[TESTE] Carlos Barbeiro`
   - E-mail: `carlos.teste@caldasindica.com`, Telefone: `11988887777`
   - Redirecionamento para `/professionals/{id}`.
   - Configuração de jornada de trabalho semanal: Segunda-feira das 09:00 às 18:00 ativa (9h/semana).
3. **Serviços (`/services`)**:
   - Criado serviço: `[TESTE] Corte Masculino Premium`
   - Duração: 45 minutos | Preço: R$ 65,00
   - Vínculo direto estabelecido com `[TESTE] Carlos Barbeiro`.
4. **Produtos & Estoque (`/products` & `/inventory`)**:
   - Criado produto: `[TESTE] Pomada Modeladora Efeito Matte 100g`
   - Preço de venda: R$ 45,00 | Preço de custo: R$ 20,00
   - Estoque inicial: 25 unidades | Estoque mínimo: 5 unidades
   - Tela de `/inventory` verificada com a nova primitiva `Table` do shadcn refatorada na modernização.
5. **Categorias, Fornecedores e Pacotes**:
   - Telas `/categories`, `/suppliers` e `/packages` acessadas e validadas com zero erros de renderização.

## 2. Auditoria Técnica
- Erros de JavaScript no console: **0**
- Quebras visuais ou overflow: **0**
- Tempo médio de resposta dos modais: < 400ms
- Persistência e integridade relacional entre entidades: **100%**
