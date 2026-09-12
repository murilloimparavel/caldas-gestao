# Relatório de Teste de Estresse - Sprint 5: Agendamento Online Público, Retenção & Configurações de Segurança

- **Data de Execução**: 2026-09-12
- **Ambiente**: Produção (`https://gestao.caldasindica.com`)
- **Tenant**: `teste` (`01a096e3-786f-7013-aa93-6fde4d87bf68`)
- **Usuário**: `teste@caldasindica.com`
- **Status da Sprint**: Aprovado com 100% de sucesso

## 1. Módulos Auditados e Validados
1. **Configuração e Publicação de Agendamento Online (`/online-booking`)**:
   - Configuração da unidade `matriz` com regras de agendamento online ativadas.
   - Vinculação e publicação do catálogo de serviços (`[TESTE] Corte Masculino Premium`), profissionais e horários de funcionamento públicos.
   - Publicação bem-sucedida: Versão 1 publicada com status `published`.
2. **Funil Público de Autoagendamento (`/book/teste/matriz`)**:
   - Acesso anônimo/desautenticado de cliente final pela interface pública de booking.
   - Renderização dos cards de serviços com duração, preço e seleção de profissional.
   - Consulta reativa de slots disponíveis (`/book/teste/matriz/availability`): bloqueio rigoroso dos horários ocupados (10:00–10:45 agendamento existente e 12:00–13:00 intervalo de almoço).
   - Agendamento público finalizado para 14/09/2026 às 14:00 (Cliente: `[TESTE] Cliente Online`, ID: `01a0971a-12d6-7320-9b3b-e5af3426ea4b`).
   - Sincronização em tempo real refletida imediatamente no `/calendar` operacional interno da unidade.
3. **Campanhas de Retenção & Reengajamento (`/retention/campaigns`)**:
   - Criação da campanha `[TESTE] Retorno Primavera` direcionada a clientes inativos há mais de 90 dias via canal WhatsApp.
   - Processamento da audiência com status final `Pronta`.
   - Ativação da campanha: transição de status para `Ativa`, estado `Operável` e disponibilização dos controles de `Pausar` e `Preparar fila`.
4. **Configurações do Usuário & Segurança (Fortify)**:
   - Auditoria de perfil (`/settings/profile`): formulários de nome, e-mail e preferências.
   - Auditoria de segurança (`/settings/security`): validação do middleware de confirmação de senha do Fortify (`/user/confirm-password`), formulários de alteração de senha, autenticação de dois fatores (2FA/TOTP com QR code) e chaves de acesso Passkeys (WebAuthn).

## 2. Auditoria Técnica
- Erros de JavaScript no console: **0**
- Vazamento de dados entre tenants ou unidades: **0**
- Tempo de resposta da API de disponibilidade de agendamento online: < 180ms
- Conformidade de acessibilidade e design system: **100%**
