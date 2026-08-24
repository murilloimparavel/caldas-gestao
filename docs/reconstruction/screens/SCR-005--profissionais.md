# SCR-005 — Profissionais: lista e novo

## Contexto

- **Rota:** `/employees`
- **Evidência:** EV-002
- **Estados:** lista ativa/inativa e novo profissional

## Lista

- alternância Ativos/Inativos;
- busca e Novo;
- dados resumidos de nome, celular, e-mail e papel aparente;
- edição por item.

## Novo profissional

Abas inicialmente disponíveis:

- Cadastro;
- Endereço;
- Usuário;
- Assinatura digital.

Abas dependentes de persistência:

- Expediente;
- Personalizar serviços;
- Configurar comissões;
- Comissões e Auxiliares;
- Pagar salário/comissão;
- Vales e Bonificações;
- Permissões;
- Contas de banco.

Campos e políticas iniciais:

- identidade, contato, profissão, documentos e anotações;
- endereço;
- credencial de usuário por e-mail/senha;
- assinatura digital;
- ativo, disponível no agendamento online, gerar agenda, receber comissão, perfil de estoque e contratação pela Lei do Salão Parceiro.

## Implicações

- `Profissional` e `Usuário` são conceitos relacionados, mas não equivalentes;
- agenda, comissões, permissões e conta bancária devem ser contextos/políticas separadas;
- senha não deve fazer parte do formulário de domínio no backend; usar fluxo de convite/ativação seguro;
- assinatura digital e dados bancários exigem controles de acesso e retenção específicos.

## Critérios de aceitação propostos

- Um profissional pode existir sem login.
- Conceder acesso deve usar convite com token curto, não senha escolhida pelo administrador.
- Desativar profissional preserva histórico e bloqueia novas atribuições segundo política explícita.
- Geração de agenda, exposição online, comissão e estoque são capacidades independentes.
- Alterações de permissão, comissão e dados bancários geram auditoria reforçada.

## Desconhecidos

- papéis disponíveis e granularidade de permissões;
- regras da Lei do Salão Parceiro;
- agenda/expediente e exceções;
- assinatura digital e validade jurídica;
- estrutura de comissão/auxiliares.
