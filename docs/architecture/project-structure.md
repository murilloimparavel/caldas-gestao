# Estrutura do projeto

O projeto é um monólito modular Laravel. O framework permanece organizado pelas
fronteiras convencionais (`app/Http`, `app/Models`, `app/Providers`), enquanto
regras e casos de uso de negócio evoluem em `app/Domain` por contexto.

## Backend

- `app/Domain/Identity`: identidade, tenant, unidade e autorização.
- `app/Domain/Customers`: clientes, busca, perfil e histórico.
- `app/Domain/Catalog`: serviços, produtos, categorias e fornecedores.
- `app/Domain/Calendar`: disponibilidade, agenda, recorrência e conflitos.
- `app/Domain/Orders`: comandas, checkout e pagamentos.
- `app/Domain/Finance`: caixa, transações, conciliação e comissões.
- `app/Domain/Analytics`: consultas, indicadores e relatórios.
- `app/Http`: entrada HTTP, requests, middleware e respostas Inertia.
- `app/Models`: modelos Eloquent compartilhados.
- `database`: migrations, factories e seeders; fonte de verdade do schema.
- `routes`: rotas nomeadas, incluindo os health checks operacionais.

Pastas de domínio contêm apenas código com responsabilidade real. Enquanto um
contexto ainda não tiver implementação, seu README registra a fronteira sem
criar classes vazias.

## Frontend

O frontend segue o mapa aprovado no plano: `resources/js/pages` contém entradas
Inertia; `features` concentra comportamento por domínio; `components/ui` contém
primitives sem regra de negócio; `components/patterns` e `components/shell`
compõem padrões compartilhados; `types` contém contratos explícitos; `lib`
contém formatadores, permissões e telemetria.

## Operação

- `/health/app` verifica que a aplicação responde.
- `/health/database` verifica conectividade com a conexão configurada.
- `/health/redis` verifica Redis separadamente e retorna `503` quando indisponível.

Falha do Redis não impede o processo web de subir, mas nunca é ocultada: o
endpoint retorna estado `unhealthy` e status HTTP `503`.
