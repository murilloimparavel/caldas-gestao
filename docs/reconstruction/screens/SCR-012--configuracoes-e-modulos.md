# SCR-012 — Configurações, conta e módulos

## Configurações organizacionais

- dados jurídicos e nome da empresa;
- e-mail, telefone e WhatsApp;
- endereço completo;
- idioma, moeda e país;
- idioma do sistema e cor da navegação;
- tópicos de notificação operacional;
- API condicionada a entitlement.

## Conta pessoal

Menu: Minha Conta, Assinatura e Sair. Minha Conta usa diálogo com abas Alterar e-mail e Alterar senha, senha atual e comandos Cancelar/Salvar.

## Módulos e relacionamento

- WhatsApp oficial aparece como página de aquisição de módulo;
- Marketing organiza aquisição, retenção, avaliações e benefícios;
- Ajuda combina atendimento, autoatendimento, feedback e changelog;
- recursos não contratados continuam descobríveis na navegação.

## Proposta independente

- separar configurações de tenant, unidade e usuário;
- idioma/timezone/moeda versionados e com impacto explicado;
- credenciais de API nunca exibidas novamente após criação; scopes, expiração, rotação e auditoria;
- central de integrações com estados não instalado, configurando, ativo, degradado, suspenso e removido;
- entitlement separado de autorização: plano permite capacidade, RBAC permite ação;
- preferências de notificação por evento × canal × destinatário;
- conta pessoal separada da administração de usuários;
- toda mudança sensível exige reautenticação e notificação de segurança.

## Critérios de aceitação

- usuário sem permissão não lê nem altera configuração sensível;
- alteração de moeda/timezone não reescreve histórico;
- token só é mostrado uma vez e armazenado por hash;
- desligar canal não cancela mensagens já enviadas, mas impede novas intenções;
- diálogos têm foco inicial, armadilha, Escape e retorno ao acionador;
- módulos bloqueados explicam plano e permissão separadamente.
