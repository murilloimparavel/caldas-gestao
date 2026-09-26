# Relatório de Teste de Estresse - Sprint 1: Autenticação, Shell & Dashboard Operacional

- **Data de Execução**: 2026-09-12
- **Ambiente**: Produção (`https://gestao.caldasindica.com`)
- **Tenant**: `teste` (`01a096e3-786f-7013-aa93-6fde4d87bf68`)
- **Usuário**: `teste@caldasindica.com` (Owner / Admin Teste)
- **Status da Sprint**: Aprovado com 100% de sucesso

## 1. Testes Executados
1. **Login & Redirecionamento**:
   - Submissão com `Remember me` em `/login`.
   - Redirecionamento suave em < 1.2s para `/dashboard`.
   - Inicialização do `TenantContext` para a unidade `matriz`.
2. **Dashboard Operacional**:
   - Renderização dos cards de KPIs (Vendas Totais, Agendamentos, Comandas) zerados sem qualquer crash ou NaN/null pointers.
   - Teste do seletor de períodos (30 dias, filtros).
   - Acionamento do botão "Atualizar dados do dashboard" com atualização reativa.
   - Renderização de mapas de calor e gráficos de visitas sem erros de render.
3. **Sidebar & Navegação**:
   - Toggle de colapso e expansão da barra lateral.
   - Validação da árvore de menus (Principal, Cadastros, Controle, Configurações, Financeiro).
   - Breadcrumbs reativos.
4. **Alternância de Temas (Appearance)**:
   - Acesso a `/settings/appearance`.
   - Troca dinâmica entre Dark, Light e System sem recarregamento de página.
   - Aplicação imediata das classes no root HTML (`.dark`).
5. **Auditoria de Console & Rede**:
   - Erros de JavaScript: **0**
   - Advertências de Hidratação React: **0**
   - Falhas de Rede HTTP: **0**
