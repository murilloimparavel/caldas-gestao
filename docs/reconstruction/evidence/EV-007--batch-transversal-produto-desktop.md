# EV-007 — Inteligência transversal do produto

- **Data:** 24/08/2026
- **Viewport:** desktop 1710×929, DPR 2
- **Papel:** desconhecido
- **Mutação:** nenhuma
- **Redação:** dados da empresa, usuário e credenciais não foram preservados

## Superfícies

- Configurações: empresa, notificações, personalização, administração e API;
- WhatsApp API Oficial;
- grupos Marketing e Ajuda;
- menu da conta e Minha Conta.

## Descobertas

- empresa concentra identificação, contatos e endereço;
- notificações possuem tópicos operacionais configuráveis por switches;
- personalização oferece idioma e cor do menu;
- outra superfície oferece idioma padrão, moeda e país;
- API mostra ações Copiar/Gerar token, mas a conta recebeu paywall; nenhum valor foi lido;
- WhatsApp é vendido como módulo adicional com chat e campanhas;
- Marketing agrupa link/agendamento online, automação, promoções, avaliações e cashback;
- Ajuda agrupa suporte, base de conhecimento, feedback e novidades;
- Minha Conta abre diálogo para alterar e-mail ou senha;
- primeiro Tab testado no diálogo resultou em `DIV` sem nome/papel útil;
- não há landmarks `main` ou `navigation` nas superfícies avaliadas;
- shell usa Inter, fundo claro, sidebar escura e CTA azul-violeta.

## Responsividade

Somente desktop foi observado. O Chrome autenticado disponível não expunha redimensionamento seguro de viewport; tablet/mobile permanecem Unknown, não foram simulados por CSS nem inferidos.

## Segurança

- nenhum switch/campo foi alterado;
- nenhum token foi lido, copiado ou gerado;
- nenhum módulo foi adicionado/contratado;
- nenhuma mensagem, campanha, feedback ou suporte foi enviado;
- e-mail e senha não foram visualizados ou preenchidos.
