# ADR-001 — Stack inicial do Caldas Gestão

- **Status:** aceito
- **Data:** 24/08/2026
- **Decisão:** Laravel 13 + React/TypeScript + Inertia + PostgreSQL no Supabase + Redis

## Contexto

O Caldas Gestão será um SaaS transacional para negócios de beleza e atendimento. O escopo previsto inclui multi-tenancy, usuários e permissões, clientes, profissionais, catálogo, agenda concorrente, comandas, pagamentos, financeiro, comissões, estoque, arquivos, notificações, integrações e relatórios.

O produto precisa permitir evolução rápida sem dividir prematuramente o sistema entre vários serviços. As regras críticas de autorização, agenda, financeiro e estoque devem permanecer consistentes, auditáveis e testáveis no servidor.

## Decisão

Adotar um monólito modular com a seguinte stack inicial:

| Camada | Tecnologia | Responsabilidade |
|---|---|---|
| Backend | Laravel 13 | domínio, autenticação, autorização, validação, transações, API, jobs e integrações |
| Frontend | React + TypeScript | interface operacional e componentes interativos |
| Integração web | Inertia | conectar Laravel e React sem exigir uma API SPA separada no início |
| Banco | PostgreSQL gerenciado pelo Supabase | persistência relacional, constraints, índices, backups e recuperação conforme o plano contratado |
| Cache e filas | Redis | cache, filas, locks distribuídos, rate limiting e suporte ao Horizon |
| Arquivos | Supabase Storage, sujeito a validação | documentos e anexos, com autorização mediada pelo backend |
| Testes | Pest, Vitest e Playwright | backend, componentes e fluxos de ponta a ponta |

## Limites de responsabilidade

Laravel será a autoridade do backend:

- autenticação e sessões serão implementadas com os recursos do Laravel, Fortify e/ou Sanctum;
- autorização será aplicada no servidor por Policies, Gates e regras de domínio;
- toda gravação operacional passará pelo Laravel;
- Eloquent e migrations do Laravel serão a fonte de verdade do schema da aplicação;
- o frontend não acessará diretamente tabelas operacionais pela Data API do Supabase;
- Supabase Auth não será usado inicialmente, evitando duas fontes de identidade;
- Supabase Realtime só será adotado após um caso de uso demonstrar vantagem sobre a solução do ecossistema Laravel;
- RLS poderá ser usada como defesa adicional, sem substituir o isolamento por tenant e a autorização no Laravel.

## Arquitetura inicial

```text
Navegador
    |
    v
Laravel + Inertia + React
    |-- autenticação e RBAC
    |-- regras de domínio
    |-- transações e auditoria
    |-- jobs, webhooks e integrações
    |
    +--> PostgreSQL no Supabase
    |
    +--> Redis
    |
    +--> Storage
```

O código será organizado como monólito modular por contextos de negócio. A separação em serviços independentes somente ocorrerá quando métricas operacionais ou limites claros de domínio justificarem o custo.

## Multi-tenancy e integridade

- usar banco e schema compartilhados no início;
- toda entidade pertencente à empresa terá `tenant_id` obrigatório;
- usar `unit_id` quando a informação pertencer a uma unidade;
- garantir escopo de tenant em consultas, comandos e validações de unicidade;
- testar automaticamente tentativas de acesso cruzado entre tenants;
- utilizar transações, constraints e locks para operações concorrentes;
- representar dinheiro em centavos inteiros ou decimal exato, nunca `float`;
- manter histórico financeiro, de estoque, comissão e auditoria por lançamentos e ajustes, evitando alterações destrutivas;
- exigir idempotência para webhooks e mutações críticas repetíveis.

## Conexão com o Supabase

- usar conexão direta para migrations, backup e ferramentas administrativas quando a infraestrutura permitir;
- usar Session Pooler para o tráfego de um backend Laravel persistente quando necessário;
- exigir TLS com `sslmode=require`;
- preferir um schema próprio da aplicação em vez de expor tabelas Laravel diretamente no schema `public`;
- escolher região, plano, backups e política de recuperação antes da produção;
- documentar retenção, subprocessadores, localização e tratamento dos dados para LGPD.

## Laravel Boost

Instalar o Laravel Boost imediatamente após a criação do projeto Laravel:

```bash
composer require laravel/boost --dev
php artisan boost:install
```

O Boost será a referência contextual para boas práticas e documentação compatível com as versões instaladas de Laravel, Inertia, React, Pest e Tailwind. Regras próprias do Caldas Gestão serão adicionadas em `.ai/guidelines/` quando o scaffold existir.

## Estratégia de desenvolvimento

Após um pequeno passe de design do shell, desenvolver em fatias verticais completas:

1. tenant, unidade, autenticação e permissões;
2. shell autenticado e design system;
3. clientes;
4. profissionais e disponibilidade;
5. serviços;
6. agenda e prevenção de conflitos;
7. atendimento, comanda e pagamento;
8. financeiro, comissão e estoque;
9. pacotes, relacionamento e relatórios.

Cada fatia deve incluir schema, regras de domínio, autorização, interface, auditoria e testes proporcionais ao risco.

## Alternativas consideradas

### Frontend completo antes do backend

Rejeitado como estratégia principal porque formulários e estados da interface dependem de regras reais de autorização, concorrência, pagamento, comissão e estoque. Protótipos com dados sintéticos continuam permitidos, mas a implementação seguirá fatias verticais.

### SPA e API separadas desde o início

Adiado. A separação aumenta autenticação, contratos, deploys e observabilidade antes de existir necessidade comprovada. Inertia entrega interatividade mantendo um único fluxo de aplicação.

### Supabase como backend completo

Rejeitado inicialmente. Misturar regras entre Laravel, Data API, Auth, Edge Functions e triggers aumentaria o número de autoridades do sistema e dificultaria auditoria e manutenção.

### Microsserviços

Rejeitado no início. O domínio ainda está sendo validado e exige várias transações entre módulos. Um monólito modular reduz complexidade operacional e preserva a possibilidade de extração posterior.

## Consequências

### Positivas

- stack produtiva para aplicações transacionais;
- uma autoridade clara para regras e segurança;
- interface rica sem manter uma API separada prematuramente;
- PostgreSQL gerenciado com possibilidade de usar Storage e Realtime seletivamente;
- ecossistema maduro para filas, testes, observabilidade e desenvolvimento assistido por agentes.

### Custos e riscos

- dependência operacional do Supabase para banco e possivelmente arquivos;
- necessidade de Redis e worker separados do Supabase;
- disciplina obrigatória no isolamento multi-tenant;
- Inertia acopla o primeiro frontend ao Laravel;
- recursos do Supabase não devem ser adotados apenas por estarem disponíveis;
- a compatibilidade das versões finais deve ser verificada no momento do scaffold.

## Critérios para revisar este ADR

Reavaliar a decisão se ocorrer uma destas condições:

- aplicativo mobile ou API pública passa a ser prioridade;
- volume ou latência exige separar um contexto operacional;
- requisitos de residência, recuperação ou compliance tornam o Supabase inadequado;
- colaboração em tempo real exige infraestrutura diferente;
- Inertia limita comprovadamente uma experiência essencial;
- custos operacionais superam alternativas equivalentes.
